<?php

declare(strict_types=1);

namespace App\Modules\Chat\Http\Controllers\Api;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMember;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Models\ChatPushSubscription;
use App\Modules\Chat\Models\ChatReaction;
use App\Modules\Chat\Support\ChatDepartmentGroups;
use App\Modules\Chat\Support\ChatPeople;
use App\Modules\Chat\Support\ChatPresenter;
use App\Modules\Chat\Support\ChatPush;
use App\Modules\Chat\Support\ChatRecords;
use App\Modules\Chat\Support\ChatWebPush;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Chat (user 2026-11-22: Norban's chat module, WhatsApp style): a company's
 * people one to one and in groups — messages with files, replies, forwards,
 * edits and deletes, reactions, pins, read ticks, typing, online / last seen,
 * and a group's name, photo, members and admins.
 *
 * Open to everyone of the company (no permission of its own, like one's own
 * notifications): what a person reaches is decided by being in the chat.
 * Every change is pushed live to the chat's people on their own channel.
 */
class ChatApiController extends ApiController
{
    public const PAGE = 50;

    /** Files a chat carries beyond the shared evidence types. */
    private const EXTRA_TYPES = [
        'image/gif', 'text/plain', 'text/csv', 'application/zip', 'application/x-zip-compressed',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'video/mp4', 'audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/webm',
    ];

    private const MAX_FILE = 25 * 1024 * 1024;

    /* ── Lists ──────────────────────────────────────────────────────── */

    /** My chats (newest first, pinned on top), who I am, and my unread total. */
    public function index(): JsonResponse
    {
        [$company, $me] = $this->who();
        ChatPeople::touch($company, $me);
        ChatDepartmentGroups::syncSoon($company);
        $this->deliverAll($company, $me);
        $chats = ChatPresenter::chats($company, $me);

        return ApiResponse::ok([
            'me' => ChatPeople::card($company, $me), 'chats' => $chats,
            'unread' => array_sum(array_map(fn ($c) => $c['muted'] ? 0 : $c['unread'], $chats)),
        ]);
    }

    /** One chat row (after a live event names a chat this screen has not drawn yet). */
    public function row(string $chat): JsonResponse
    {
        [$company, $me] = $this->who();
        $this->member($chat, true);

        return ApiResponse::ok(ChatPresenter::chats($company, $me, [$chat])[0] ?? null);
    }

    /** The company's people to start a chat or a group with (not me), with online / last seen. */
    public function contacts(Request $request): JsonResponse
    {
        [$company, $me] = $this->who();
        $q = mb_strtolower(trim((string) $request->query('q', '')));
        $people = ChatPeople::all($company)->except($me)->filter(fn ($p) => $q === '' || str_contains(mb_strtolower($p['name'].' '.($p['email'] ?? '').' '.($p['about'] ?? '')), $q));

        return ApiResponse::ok($people->values()->all());
    }

    /** The app's minute heartbeat: I am here; how many unread I have. */
    public function ping(): JsonResponse
    {
        [$company, $me] = $this->who();
        ChatPeople::touch($company, $me);
        $this->deliverAll($company, $me);

        return ApiResponse::ok(['me' => $me, 'unread' => $this->unreadTotal($company, $me)]);
    }

    /* ── Start ──────────────────────────────────────────────────────── */

    /** A one-to-one chat with a colleague — the same one every time. */
    public function direct(Request $request): JsonResponse
    {
        [$company, $me] = $this->who();
        $data = $request->validate(['user_id' => ['required', 'string']]);
        $other = (string) $data['user_id'];
        if ($other === $me || ! ChatPeople::all($company)->has($other)) {
            throw ValidationException::withMessages(['user_id' => [__('chat.v.person')]]);
        }
        $key = Chat::directKey($me, $other);
        $chat = DB::transaction(function () use ($key, $me, $other): Chat {
            $chat = Chat::query()->where('direct_key', $key)->lockForUpdate()->first();
            if ($chat) {
                return $chat;
            }
            $chat = Chat::query()->create(['is_group' => false, 'direct_key' => $key, 'created_by' => $me]);
            foreach ([$me, $other] as $u) {
                ChatMember::query()->create(['chat_id' => $chat->id, 'user_id' => $u, 'role' => ChatMember::MEMBER, 'joined_at' => now()]);
            }

            return $chat;
        });

        return ApiResponse::ok(ChatPresenter::chats($company, $me, [$chat->id])[0]);
    }

    /** A new group: its name and the people in it (I am its owner). */
    public function group(Request $request): JsonResponse
    {
        [$company, $me] = $this->who();
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500'], 'user_ids' => ['required', 'array', 'min:1', 'max:256'], 'user_ids.*' => ['string']]);
        $people = ChatPeople::all($company);
        $ids = array_values(array_unique(array_filter($data['user_ids'], fn ($u) => $u !== $me && $people->has($u))));
        if ($ids === []) {
            throw ValidationException::withMessages(['user_ids' => [__('chat.v.members')]]);
        }
        $chat = DB::transaction(function () use ($data, $me, $ids): Chat {
            $chat = Chat::query()->create(['is_group' => true, 'name' => trim($data['name']), 'description' => $data['description'] ?? null, 'created_by' => $me]);
            ChatMember::query()->create(['chat_id' => $chat->id, 'user_id' => $me, 'role' => ChatMember::OWNER, 'joined_at' => now()]);
            foreach ($ids as $u) {
                ChatMember::query()->create(['chat_id' => $chat->id, 'user_id' => $u, 'role' => ChatMember::MEMBER, 'joined_at' => now()]);
            }
            $this->system($chat, $me, 'created', ['name' => $chat->name]);

            return $chat;
        });
        $this->pushChat($chat);

        return ApiResponse::created(ChatPresenter::chats($company, $me, [$chat->id])[0]);
    }

    /* ── One chat ───────────────────────────────────────────────────── */

    /** A chat's details: its people (with roles, online), pinned messages, photos and files shared. */
    public function show(string $chat): JsonResponse
    {
        [$company, $me] = $this->who();
        $mine = $this->member($chat, true);
        $c = $mine->chat;
        $members = $c->members()->get();
        $pinned = ChatMessage::query()->with(['file', 'replyTo.file', 'reactions'])->where('chat_id', $c->id)->whereNotNull('pinned_at')->whereNull('deleted_at')->orderByDesc('pinned_at')->limit(3)->get();
        $media = ChatMessage::query()->with('file')->where('chat_id', $c->id)->whereNotNull('file_id')->whereNull('deleted_at')
            ->when($mine->cleared_message_id, fn ($q) => $q->where('id', '>', $mine->cleared_message_id))->orderByDesc('id')->limit(30)->get();

        return ApiResponse::ok(ChatPresenter::chat($company, $c, $mine, $members, $me) + [
            'members' => $members->whereNull('left_at')->map(fn (ChatMember $m) => ['role' => $m->role] + ChatPeople::card($company, $m->user_id))->sortBy(fn ($m) => [$m['id'] === $me ? 0 : 1, $m['role'] === ChatMember::MEMBER ? 1 : 0, $m['name']])->values()->all(),
            'pinned_messages' => $pinned->map(fn ($m) => ChatPresenter::message($m, $me, $members))->all(),
            'media' => $media->map(fn ($m) => ['id' => $m->id, 'name' => $m->file?->original_name, 'image' => (bool) $m->file?->isImage(), 'size' => (int) $m->file?->size_bytes, 'created_at' => $m->created_at?->toIso8601String()])->all(),
            'created_at' => $c->created_at?->toIso8601String(),
        ]);
    }

    /**
     * A page of messages, oldest first: the newest page, or the page `before`
     * a message (scrolling up), or `after` one (catching up), or `around` one
     * (jumping to a pinned or quoted message). Opening the newest page reads the chat.
     */
    public function messages(Request $request, string $chat): JsonResponse
    {
        [, $me] = $this->who();
        $mine = $this->member($chat, true);
        $members = ChatMember::query()->where('chat_id', $chat)->get();
        $base = fn () => ChatMessage::query()->with(['file', 'replyTo.file', 'reactions'])->where('chat_id', $chat)
            ->when($mine->cleared_message_id, fn ($q) => $q->where('id', '>', $mine->cleared_message_id))
            ->when($mine->joined_at, fn ($q) => $q->where('created_at', '>=', $mine->joined_at->copy()->subSecond()))
            ->when($mine->left_at, fn ($q) => $q->where('created_at', '<=', $mine->left_at))
            ->whereNotIn('id', DB::table('chat_message_hides')->where('user_id', $me)->select('message_id'));
        $before = (string) $request->query('before', '');
        $after = (string) $request->query('after', '');
        $around = (string) $request->query('around', '');
        if ($around !== '') {
            $older = $base()->where('id', '<=', $around)->orderByDesc('id')->limit(25)->get();
            $newer = $base()->where('id', '>', $around)->orderBy('id')->limit(25)->get();
            $rows = $older->reverse()->values()->concat($newer);
            $hasOlder = $older->count() === 25;
            $hasNewer = $newer->count() === 25;
        } elseif ($after !== '') {
            $rows = $base()->where('id', '>', $after)->orderBy('id')->limit(self::PAGE + 1)->get();
            $hasNewer = $rows->count() > self::PAGE;
            $rows = $rows->take(self::PAGE);
            $hasOlder = true;
        } else {
            $rows = $base()->when($before !== '', fn ($q) => $q->where('id', '<', $before))->orderByDesc('id')->limit(self::PAGE + 1)->get();
            $hasOlder = $rows->count() > self::PAGE;
            $rows = $rows->take(self::PAGE)->reverse()->values();
            $hasNewer = false;
        }
        // Where I had read to before this opening: the screen's "N unread messages" line goes after it.
        $readBefore = $mine->read_message_id;
        if ($before === '' && $after === '' && $around === '' && ! $mine->left_at) {
            $this->markRead($mine, $members);
            $members = ChatMember::query()->where('chat_id', $chat)->get();
        }

        return ApiResponse::ok([
            'messages' => $rows->map(fn ($m) => ChatPresenter::message($m, $me, $members))->values()->all(),
            'has_older' => $hasOlder, 'has_newer' => $hasNewer,
            'read_upto' => $readBefore,
        ]);
    }

    /**
     * Search messages (text, captions, file names) in my chats — all of them,
     * or one (`chat`) — newest first. Only what I may still see: not before I
     * joined or after I left, not cleared, not hidden, not deleted.
     */
    public function search(Request $request): JsonResponse
    {
        [$company, $me] = $this->who();
        $q = trim((string) $request->query('q', ''));
        $chat = (string) $request->query('chat', '');
        if (mb_strlen($q) < 2) {
            return ApiResponse::ok(['results' => []]);
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
        $rows = ChatMessage::query()->withoutGlobalScope(TenantScope::class)->from('chat_messages as msg')
            ->join('chat_members as cm', fn ($j) => $j->on('cm.chat_id', '=', 'msg.chat_id')->where('cm.user_id', '=', $me))
            ->leftJoin('file_attachments as f', 'f.id', '=', 'msg.file_id')
            ->where('msg.company_id', $company)
            ->when($chat !== '', fn ($w) => $w->where('msg.chat_id', $chat))
            ->whereIn('msg.type', ['TEXT', 'IMAGE', 'FILE'])->whereNull('msg.deleted_at')
            ->where(fn ($w) => $w->where('msg.body', 'ilike', $like)->orWhere('f.original_name', 'ilike', $like))
            ->where(fn ($w) => $w->whereNull('cm.cleared_message_id')->orWhereColumn('msg.id', '>', 'cm.cleared_message_id'))
            ->where(fn ($w) => $w->whereNull('cm.joined_at')->orWhereRaw("msg.created_at >= cm.joined_at - interval '1 second'"))
            ->where(fn ($w) => $w->whereNull('cm.left_at')->orWhereColumn('msg.created_at', '<=', 'cm.left_at'))
            ->whereNotIn('msg.id', DB::table('chat_message_hides')->where('user_id', $me)->select('message_id'))
            ->orderByDesc('msg.id')->limit(60)
            ->get(['msg.id', 'msg.chat_id', 'msg.user_id', 'msg.type', 'msg.body', 'msg.created_at', 'f.original_name as file_name']);

        return ApiResponse::ok(['results' => $rows->map(fn ($m) => [
            'id' => $m->id, 'chat_id' => $m->chat_id, 'user_id' => $m->user_id,
            'user_name' => $m->user_id ? (ChatPeople::card($company, $m->user_id)['name'] ?? null) : null,
            'type' => $m->type, 'body' => $m->body, 'file_name' => $m->file_name,
            'created_at' => $m->created_at?->toIso8601String(),
        ])->values()->all()]);
    }

    /** Records the caller may mention with `#`: the kinds they may read, and the matches of one kind. */
    public function records(Request $request): JsonResponse
    {
        $this->who();
        $types = ChatRecords::allowed($this->caller());
        $type = (string) $request->query('type', 'all');

        return ApiResponse::ok(['types' => $types, 'type' => $type, 'results' => ChatRecords::search($this->caller(), $type, (string) $request->query('q', ''))]);
    }

    /** The cards of mentioned records ("type:ref,…"): null where the caller may not read one. */
    public function recordCards(Request $request): JsonResponse
    {
        $this->who();
        $keys = array_values(array_filter(array_map('trim', explode(',', (string) $request->query('keys', '')))));

        return ApiResponse::ok(['cards' => (object) ChatRecords::cards($this->caller(), $keys)]);
    }

    /**
     * The mentions a message carries, checked: people still in the chat (not
     * me) whose `@name` is in the text, and real records of this company
     * whose token is. Sent as an array or as JSON (a multipart upload).
     *
     * @return array{users?: list<array{id: string, name: string}>, refs?: list<array{type: string, ref: string, token: string}>}|null
     */
    private function mentions(Request $request, string $chat, string $company, string $me, string $body): ?array
    {
        $raw = $request->input('mentions');
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (! is_array($raw) || $body === '') {
            return null;
        }
        $members = ChatMember::query()->where('chat_id', $chat)->whereNull('left_at')->where('user_id', '!=', $me)->pluck('user_id')->flip();
        $users = [];
        foreach (array_slice((array) ($raw['users'] ?? []), 0, 50) as $u) {
            $id = is_array($u) ? (string) ($u['id'] ?? '') : (string) $u;
            $name = ChatPeople::card($company, $id)['name'] ?? null;
            if ($id !== '' && isset($members[$id]) && $name && str_contains($body, '@'.$name)) {
                $users[$id] = ['id' => $id, 'name' => $name];
            }
        }
        $refs = [];
        foreach (array_slice((array) ($raw['refs'] ?? []), 0, 20) as $r) {
            $type = (string) ($r['type'] ?? '');
            $ref = mb_substr(trim((string) ($r['ref'] ?? '')), 0, 60);
            $token = mb_substr(trim((string) ($r['token'] ?? '')), 0, 64);
            if (isset(ChatRecords::TYPES[$type]) && $ref !== '' && str_starts_with($token, '#') && str_contains($body, $token) && ChatRecords::exists($company, $type, $ref)) {
                $refs["{$type}:{$ref}"] = ['type' => $type, 'ref' => $ref, 'token' => $token];
            }
        }
        $out = array_filter(['users' => array_values($users), 'refs' => array_values($refs)]);

        return $out ?: null;
    }

    /** Send: text, or a file (an image shows in the chat) with a caption; a reply to another message. */
    public function send(Request $request, string $chat, StoreFileAttachment $files): JsonResponse
    {
        [$company, $me] = $this->who();
        $mine = $this->member($chat);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:8000'], 'reply_to_id' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:'.(int) (self::MAX_FILE / 1024)],
        ]);
        $body = trim((string) ($data['body'] ?? ''));
        $mentions = $this->mentions($request, $mine->chat_id, $company, $me, $body);
        if ($body === '' && ! $request->hasFile('file')) {
            throw ValidationException::withMessages(['body' => [__('chat.v.empty')]]);
        }
        $reply = ! empty($data['reply_to_id']) ? ChatMessage::query()->where('chat_id', $chat)->find($data['reply_to_id'])?->id : null;
        $message = DB::transaction(function () use ($request, $files, $mine, $me, $body, $reply, $mentions): ChatMessage {
            $file = null;
            if ($request->hasFile('file')) {
                $file = $files->handle($request->file('file'), 'chat_message', $mine->chat_id, $me, self::EXTRA_TYPES, self::MAX_FILE);
            }
            $m = ChatMessage::query()->create([
                'chat_id' => $mine->chat_id, 'user_id' => $me,
                'type' => $file ? ($file->isImage() ? ChatMessage::IMAGE : ChatMessage::FILE) : ChatMessage::TEXT,
                'body' => $body !== '' ? $body : null, 'file_id' => $file?->id, 'reply_to_id' => $reply,
                'meta' => $mentions ? ['mentions' => $mentions] : null,
            ]);
            if ($file) {
                FileAttachment::withoutGlobalScope(TenantScope::class)->whereKey($file->id)->update(['attachable_id' => $m->id]);
            }
            $this->touchChat($mine->chat_id, $m);
            $mine->forceFill(['read_message_id' => $m->id, 'delivered_message_id' => $m->id, 'archived' => false])->save();

            return $m;
        });
        $this->pushMessage($company, $message);

        return ApiResponse::created(ChatPresenter::message($message->load(['file', 'replyTo.file', 'reactions']), $me, ChatMember::query()->where('chat_id', $chat)->get()));
    }

    /** Edit my own text (or a file's caption). */
    public function edit(Request $request, string $message): JsonResponse
    {
        [$company, $me] = $this->who();
        $m = $this->own($message);
        $data = $request->validate(['body' => ['required', 'string', 'max:8000']]);
        if ($m->type === ChatMessage::SYSTEM) {
            throw ValidationException::withMessages(['body' => [__('chat.v.not_yours')]]);
        }
        $mentions = $this->mentions($request, $m->chat_id, $company, $me, trim($data['body']));
        $meta = $m->meta ?? [];
        unset($meta['mentions']);
        $m->forceFill(['body' => trim($data['body']), 'edited_at' => now(), 'meta' => ($mentions ? ['mentions' => $mentions] : []) + $meta ?: null])->save();
        $this->pushUpdate($company, $m);

        return ApiResponse::ok(ChatPresenter::message($m->load(['file', 'replyTo.file', 'reactions']), $me, ChatMember::query()->where('chat_id', $m->chat_id)->get()));
    }

    /** Delete for everyone (my own message) or for me only (any message I see). */
    public function destroy(Request $request, string $message): JsonResponse
    {
        [$company, $me] = $this->who();
        $scope = $request->query('scope') === 'everyone' ? 'everyone' : 'me';
        $m = ChatMessage::query()->findOrFail($message);
        $this->member($m->chat_id, true);
        if ($scope === 'everyone') {
            $m = $this->own($message);
            $m->forceFill(['deleted_at' => now(), 'pinned_at' => null, 'pinned_by' => null])->save();
            $this->pushUpdate($company, $m);
        } else {
            DB::table('chat_message_hides')->insertOrIgnore(['id' => strtolower((string) \Illuminate\Support\Str::ulid()), 'message_id' => $m->id, 'user_id' => $me, 'created_at' => now(), 'updated_at' => now()]);
        }

        return ApiResponse::ok(['id' => $m->id, 'scope' => $scope]);
    }

    /** My reaction: an emoji (a different one replaces mine), or none. */
    public function react(Request $request, string $message): JsonResponse
    {
        [$company, $me] = $this->who();
        $m = ChatMessage::query()->findOrFail($message);
        $this->member($m->chat_id);
        $data = $request->validate(['emoji' => ['nullable', 'string', 'max:16']]);
        $emoji = trim((string) ($data['emoji'] ?? ''));
        $current = ChatReaction::query()->where('message_id', $m->id)->where('user_id', $me)->first();
        if ($emoji === '' || ($current && $current->emoji === $emoji)) {
            $current?->delete();
        } elseif ($current) {
            $current->forceFill(['emoji' => $emoji])->save();
        } else {
            ChatReaction::query()->create(['message_id' => $m->id, 'user_id' => $me, 'emoji' => $emoji]);
        }
        $this->pushUpdate($company, $m);

        return ApiResponse::ok(ChatPresenter::message($m->fresh(['file', 'replyTo.file', 'reactions']), $me, ChatMember::query()->where('chat_id', $m->chat_id)->get()));
    }

    /** Pin a message for the chat (at most three; the oldest pin gives way), or unpin it. */
    public function pin(string $message): JsonResponse
    {
        [$company, $me] = $this->who();
        $m = ChatMessage::query()->findOrFail($message);
        $this->member($m->chat_id);
        if ($m->deleted_at || $m->type === ChatMessage::SYSTEM) {
            throw ValidationException::withMessages(['message' => [__('chat.v.cannot_pin')]]);
        }
        if ($m->pinned_at) {
            $m->forceFill(['pinned_at' => null, 'pinned_by' => null])->save();
        } else {
            $m->forceFill(['pinned_at' => now(), 'pinned_by' => $me])->save();
            ChatMessage::query()->where('chat_id', $m->chat_id)->whereNotNull('pinned_at')->orderByDesc('pinned_at')->skip(3)->take(100)->get()
                ->each(fn (ChatMessage $old) => $old->forceFill(['pinned_at' => null, 'pinned_by' => null])->save());
        }
        $this->pushUpdate($company, $m);
        ChatPush::send($this->peopleOf($m->chat_id), 'pins', $m->chat_id);

        return ApiResponse::ok(['id' => $m->id, 'pinned' => $m->pinned_at !== null]);
    }

    /** Forward a message to other chats (each gets a copy marked forwarded; a file is shared, not copied). */
    public function forward(Request $request): JsonResponse
    {
        [$company, $me] = $this->who();
        $data = $request->validate(['message_id' => ['required', 'string'], 'chat_ids' => ['required', 'array', 'min:1', 'max:20'], 'chat_ids.*' => ['string']]);
        $src = ChatMessage::query()->findOrFail($data['message_id']);
        $this->member($src->chat_id, true);
        if ($src->deleted_at || $src->type === ChatMessage::SYSTEM) {
            throw ValidationException::withMessages(['message_id' => [__('chat.v.cannot_forward')]]);
        }
        $sent = [];
        foreach (array_unique($data['chat_ids']) as $chatId) {
            $mine = $this->member((string) $chatId);
            $copy = DB::transaction(function () use ($src, $mine, $me): ChatMessage {
                $m = ChatMessage::query()->create(['chat_id' => $mine->chat_id, 'user_id' => $me, 'type' => $src->type, 'body' => $src->body, 'file_id' => $src->file_id, 'forwarded' => true,
                    // Records stay mentioned on a forward; people do not (they were of the other chat).
                    'meta' => ! empty($src->meta['mentions']['refs']) ? ['mentions' => ['refs' => $src->meta['mentions']['refs']]] : null]);
                $this->touchChat($mine->chat_id, $m);
                $mine->forceFill(['read_message_id' => $m->id, 'delivered_message_id' => $m->id, 'archived' => false])->save();

                return $m;
            });
            $this->pushMessage($company, $copy);
            $sent[] = $mine->chat_id;
        }

        return ApiResponse::ok(['chat_ids' => $sent]);
    }

    /** Who has read and received my message (a group's "Message info"). */
    public function info(string $message): JsonResponse
    {
        [$company, $me] = $this->who();
        $m = ChatMessage::query()->findOrFail($message);
        $this->member($m->chat_id, true);
        $others = ChatMember::query()->where('chat_id', $m->chat_id)->where('user_id', '!=', $m->user_id)->whereNull('left_at')->get();
        $read = $others->filter(fn ($x) => $x->read_message_id !== null && $x->read_message_id >= $m->id);
        $delivered = $others->filter(fn ($x) => ! $read->contains($x) && $x->delivered_message_id !== null && $x->delivered_message_id >= $m->id);

        return ApiResponse::ok([
            'read' => $read->map(fn ($x) => ChatPeople::card($company, $x->user_id))->values()->all(),
            'delivered' => $delivered->map(fn ($x) => ChatPeople::card($company, $x->user_id))->values()->all(),
            'waiting' => $others->reject(fn ($x) => $read->contains($x) || $delivered->contains($x))->map(fn ($x) => ChatPeople::card($company, $x->user_id))->values()->all(),
        ]);
    }

    /** I have read the chat up to its newest message (the screen calls it as messages arrive while open). */
    public function read(string $chat): JsonResponse
    {
        [, $me] = $this->who();
        $mine = $this->member($chat, true);
        $this->markRead($mine, ChatMember::query()->where('chat_id', $chat)->get());

        return ApiResponse::ok(['read_upto' => $mine->read_message_id]);
    }

    /** I am typing (the others see "typing…" for a few seconds). */
    public function typing(string $chat): JsonResponse
    {
        [$company, $me] = $this->who();
        $this->member($chat);
        ChatPush::send(array_diff($this->peopleOf($chat), [$me]), 'typing', $chat, ['user_id' => $me, 'name' => ChatPeople::card($company, $me)['name'] ?? '']);

        return ApiResponse::ok(['ok' => true]);
    }

    /** My own settings of a chat: pinned to the top, muted, archived. */
    public function settings(Request $request, string $chat): JsonResponse
    {
        [$company, $me] = $this->who();
        $mine = $this->member($chat, true);
        $data = $request->validate(['pinned' => ['sometimes', 'boolean'], 'muted' => ['sometimes', 'boolean'], 'archived' => ['sometimes', 'boolean']]);
        if (array_key_exists('pinned', $data)) {
            $mine->pinned_at = $data['pinned'] ? now() : null;
        }
        foreach (['muted', 'archived'] as $k) {
            if (array_key_exists($k, $data)) {
                $mine->{$k} = (bool) $data[$k];
            }
        }
        $mine->save();

        return ApiResponse::ok(ChatPresenter::chats($company, $me, [$chat])[0] ?? null);
    }

    /** Clear the chat for me: its messages so far no longer show to me. */
    public function clear(string $chat): JsonResponse
    {
        [$company, $me] = $this->who();
        $mine = $this->member($chat, true);
        $lastId = ChatMessage::query()->where('chat_id', $chat)->max('id');
        $mine->forceFill(['cleared_message_id' => $lastId, 'read_message_id' => $lastId ?? $mine->read_message_id])->save();

        return ApiResponse::ok(ChatPresenter::chats($company, $me, [$chat])[0] ?? null);
    }

    /* ── A group ────────────────────────────────────────────────────── */

    /** Rename a group or change its description (its admins). */
    public function update(Request $request, string $chat): JsonResponse
    {
        [$company, $me] = $this->who();
        $mine = $this->admin($chat);
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:120'], 'description' => ['sometimes', 'nullable', 'string', 'max:500']]);
        $c = $mine->chat;
        if (array_key_exists('name', $data) && trim($data['name']) !== $c->name) {
            $old = $c->name;
            $c->name = trim($data['name']);
            $this->system($c, $me, 'renamed', ['from' => $old, 'to' => $c->name]);
        }
        if (array_key_exists('description', $data)) {
            $c->description = $data['description'];
        }
        $c->save();
        $this->pushChat($c);

        return ApiResponse::ok(ChatPresenter::chats($company, $me, [$chat])[0] ?? null);
    }

    /** A group's photo (its admins). */
    public function image(Request $request, string $chat, StoreFileAttachment $files): JsonResponse
    {
        [$company, $me] = $this->who();
        $mine = $this->admin($chat);
        $request->validate(['file' => ['required', 'file', 'image', 'max:5120']]);
        $file = $files->handle($request->file('file'), 'chat_group', $chat, $me);
        if (! $file->isImage()) {
            throw ValidationException::withMessages(['file' => [__('chat.v.image')]]);
        }
        $mine->chat->forceFill(['image_file_id' => $file->id])->save();
        $this->system($mine->chat, $me, 'image');
        $this->pushChat($mine->chat);

        return ApiResponse::ok(ChatPresenter::chats($company, $me, [$chat])[0] ?? null);
    }

    /** Add people to a group (its admins). */
    public function addMembers(Request $request, string $chat): JsonResponse
    {
        [$company, $me] = $this->who();
        $mine = $this->admin($chat);
        $data = $request->validate(['user_ids' => ['required', 'array', 'min:1', 'max:256'], 'user_ids.*' => ['string']]);
        $people = ChatPeople::all($company);
        $added = [];
        DB::transaction(function () use ($data, $people, $mine, $me, &$added): void {
            foreach (array_unique($data['user_ids']) as $u) {
                if (! $people->has($u)) {
                    continue;
                }
                $row = ChatMember::query()->where('chat_id', $mine->chat_id)->where('user_id', $u)->first();
                if ($row && ! $row->left_at) {
                    continue;
                }
                $row ??= new ChatMember(['chat_id' => $mine->chat_id, 'user_id' => $u]);
                $row->forceFill(['role' => ChatMember::MEMBER, 'joined_at' => now(), 'left_at' => null, 'cleared_message_id' => ChatMessage::query()->where('chat_id', $mine->chat_id)->max('id')])->save();
                $added[] = $u;
            }
            if ($added !== []) {
                $this->system($mine->chat, $me, 'added', ['users' => $added]);
            }
        });
        $this->pushChat($mine->chat);

        return ApiResponse::ok(['added' => $added]);
    }

    /** Remove someone from a group (its admins), or leave it myself. */
    public function removeMember(string $chat, string $user): JsonResponse
    {
        [$company, $me] = $this->who();
        $mine = $this->member($chat);
        if (! $mine->chat->is_group) {
            throw ValidationException::withMessages(['chat' => [__('chat.v.not_group')]]);
        }
        if ($user !== $me && ! $mine->isAdmin()) {
            throw ValidationException::withMessages(['user' => [__('chat.v.admins_only')]]);
        }
        $row = ChatMember::query()->where('chat_id', $chat)->where('user_id', $user)->whereNull('left_at')->firstOrFail();
        $people = $this->peopleOf($chat);
        DB::transaction(function () use ($row, $mine, $me, $user): void {
            $row->forceFill(['left_at' => now(), 'pinned_at' => null])->save();
            // An owner who leaves hands the group to its longest-standing admin (else member).
            if ($row->role === ChatMember::OWNER) {
                $next = ChatMember::query()->where('chat_id', $row->chat_id)->whereNull('left_at')->orderByRaw("case role when 'ADMIN' then 0 else 1 end")->orderBy('joined_at')->first();
                $next?->forceFill(['role' => ChatMember::OWNER])->save();
            }
            $this->system($mine->chat, $me, $user === $me ? 'left' : 'removed', ['users' => [$user]]);
        });
        $this->pushChat($mine->chat, $people);

        return ApiResponse::ok(['removed' => $user]);
    }

    /** Make someone a group admin, or a member again (its admins). */
    public function role(Request $request, string $chat, string $user): JsonResponse
    {
        [$company, $me] = $this->who();
        $mine = $this->admin($chat);
        $data = $request->validate(['role' => ['required', 'in:ADMIN,MEMBER']]);
        $row = ChatMember::query()->where('chat_id', $chat)->where('user_id', $user)->whereNull('left_at')->firstOrFail();
        if ($row->role === ChatMember::OWNER) {
            throw ValidationException::withMessages(['user' => [__('chat.v.owner')]]);
        }
        $row->forceFill(['role' => $data['role']])->save();
        $this->system($mine->chat, $me, $data['role'] === ChatMember::ADMIN ? 'admin' : 'unadmin', ['users' => [$user]]);
        $this->pushChat($mine->chat);

        return ApiResponse::ok(['user_id' => $user, 'role' => $data['role']]);
    }

    /* ── Files ──────────────────────────────────────────────────────── */

    /** A message's file (inline for an image, `?download=1` to save it). */
    public function file(Request $request, string $message): StreamedResponse
    {
        $m = ChatMessage::query()->findOrFail($message);
        $this->member($m->chat_id, true);
        abort_if($m->deleted_at || ! $m->file_id, 404);

        return $this->stream(FileAttachment::withoutGlobalScope(TenantScope::class)->findOrFail($m->file_id), $request->boolean('download'));
    }

    /** A colleague's photo (their HR employee photo). */
    public function avatar(string $user): StreamedResponse
    {
        [$company] = $this->who();
        abort_unless(ChatPeople::all($company)->has($user), 404);
        $fileId = DB::table('users as u')->join('hr_employees as e', fn ($j) => $j->on('e.employee_code', '=', 'u.employee_code')->where('e.company_id', '=', $company))
            ->where('u.id', $user)->value('e.photo_file_id');
        abort_unless($fileId, 404);

        return $this->stream(FileAttachment::withoutGlobalScope(TenantScope::class)->where('company_id', $company)->findOrFail($fileId), false, 'private, max-age=600');
    }

    /** A group's photo. */
    public function chatImage(string $chat): StreamedResponse
    {
        $mine = $this->member($chat, true);
        abort_unless($mine->chat->image_file_id, 404);

        return $this->stream(FileAttachment::withoutGlobalScope(TenantScope::class)->findOrFail($mine->chat->image_file_id), false, 'private, max-age=600');
    }

    /* ── Inside ─────────────────────────────────────────────────────── */

    /** @return array{0: string, 1: string} company id, my user id */
    private function who(): array
    {
        $caller = $this->caller();
        abort_unless($caller->user !== null, 403);

        return [$caller->companyId, $caller->user->id];
    }

    /** My membership of a chat; a past member may still read (`$read`) but not write. */
    private function member(string $chat, bool $read = false): ChatMember
    {
        [, $me] = $this->who();
        $row = ChatMember::query()->with('chat')->where('chat_id', $chat)->where('user_id', $me)->first();
        abort_unless($row && $row->chat, 404);
        if (! $read && $row->left_at) {
            throw ValidationException::withMessages(['chat' => [__('chat.v.left')]]);
        }

        return $row;
    }

    private function admin(string $chat): ChatMember
    {
        $mine = $this->member($chat);
        if (! $mine->chat->is_group) {
            throw ValidationException::withMessages(['chat' => [__('chat.v.not_group')]]);
        }
        if (! $mine->isAdmin()) {
            throw ValidationException::withMessages(['chat' => [__('chat.v.admins_only')]]);
        }

        return $mine;
    }

    private function own(string $message): ChatMessage
    {
        [, $me] = $this->who();
        $m = ChatMessage::query()->findOrFail($message);
        $this->member($m->chat_id);
        if ($m->user_id !== $me || $m->deleted_at) {
            throw ValidationException::withMessages(['message' => [__('chat.v.not_yours')]]);
        }

        return $m;
    }

    /** @return list<string> the chat's present members */
    private function peopleOf(string $chat): array
    {
        return ChatMember::query()->where('chat_id', $chat)->whereNull('left_at')->pluck('user_id')->all();
    }

    private function touchChat(string $chat, ChatMessage $m): void
    {
        Chat::query()->whereKey($chat)->update(['last_message_id' => $m->id, 'last_message_at' => $m->created_at, 'updated_at' => now()]);
        // A new message brings an archived chat back for everyone in it, as WhatsApp does.
        ChatMember::query()->where('chat_id', $chat)->where('archived', true)->where('muted', false)->update(['archived' => false]);
    }

    /** @param  array<string, mixed>  $meta */
    private function system(Chat $chat, string $by, string $event, array $meta = []): ChatMessage
    {
        $m = ChatMessage::query()->create(['chat_id' => $chat->id, 'user_id' => $by, 'type' => ChatMessage::SYSTEM, 'meta' => ['event' => $event] + $meta]);
        $this->touchChat($chat->id, $m);
        ChatMember::query()->where('chat_id', $chat->id)->where('user_id', $by)->update(['read_message_id' => $m->id, 'delivered_message_id' => $m->id]);

        return $m;
    }

    /** Read up to the chat's newest message, and tell the senders (their ticks turn blue). */
    private function markRead(ChatMember $mine, Collection $members): void
    {
        $lastId = ChatMessage::query()->where('chat_id', $mine->chat_id)->max('id');
        if (! $lastId || ($mine->read_message_id !== null && $mine->read_message_id >= $lastId)) {
            return;
        }
        $mine->forceFill(['read_message_id' => $lastId, 'delivered_message_id' => max((string) $mine->delivered_message_id, $lastId)])->save();
        ChatPush::send(array_diff($members->whereNull('left_at')->pluck('user_id')->all(), [$mine->user_id]), 'read', $mine->chat_id, ['user_id' => $mine->user_id, 'upto' => $lastId]);
    }

    /** Everything sent to me so far has reached me (grey ✓✓ for its senders). */
    private function deliverAll(string $company, string $me): void
    {
        $rows = ChatMember::query()->toBase()->join('chats as c', 'c.id', '=', 'chat_members.chat_id')
            ->where('chat_members.user_id', $me)->whereNull('chat_members.left_at')->whereNotNull('c.last_message_id')
            ->whereRaw("c.last_message_id > coalesce(chat_members.delivered_message_id, '')")
            ->get(['chat_members.id', 'chat_members.chat_id', 'c.last_message_id']);
        foreach ($rows as $r) {
            ChatMember::query()->whereKey($r->id)->update(['delivered_message_id' => $r->last_message_id]);
            ChatPush::send(array_diff($this->peopleOf($r->chat_id), [$me]), 'delivered', $r->chat_id, ['user_id' => $me, 'upto' => $r->last_message_id]);
        }
    }

    private function unreadTotal(string $company, string $me): int
    {
        return (int) array_sum(array_map(fn ($c) => $c['muted'] ? 0 : $c['unread'], ChatPresenter::chats($company, $me)));
    }

    /** A new message to everyone in the chat: each gets it drawn for them (their own ticks, their own unread). */
    private function pushMessage(string $company, ChatMessage $m): void
    {
        $m->load(['file', 'replyTo.file', 'reactions']);
        $members = ChatMember::query()->where('chat_id', $m->chat_id)->get();
        $people = $members->whereNull('left_at')->pluck('user_id')->all();
        $chat = Chat::query()->find($m->chat_id);
        ChatPush::send($people, 'message', $m->chat_id, fn (string $u) => ['message' => ChatPresenter::message($m, $u, $members), 'chat' => ['is_group' => (bool) $chat?->is_group, 'name' => $chat?->name]]);
        ChatWebPush::message($company, $m, $chat, $members);
    }

    /** The key a browser subscribes to chat notifications with (Web Push); null when the install has none. */
    public function pushKey(): JsonResponse
    {
        $this->who();

        return ApiResponse::ok(['public_key' => ChatWebPush::enabled() ? (string) config('services.webpush.public_key') : null]);
    }

    /** This browser takes chat notifications for me in this company (moved here if it was someone else's). */
    public function pushSubscribe(Request $request): JsonResponse
    {
        [$company, $me] = $this->who();
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'starts_with:https://', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:100'],
        ]);
        ChatPushSubscription::query()->updateOrCreate(['endpoint_hash' => hash('sha256', $data['endpoint'])], [
            'company_id' => $company, 'user_id' => $me, 'endpoint' => $data['endpoint'],
            'p256dh' => $data['keys']['p256dh'], 'auth' => $data['keys']['auth'],
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 300) ?: null,
        ]);

        return ApiResponse::ok(['subscribed' => true]);
    }

    /** This browser stops taking chat notifications (turned off, or signing out). */
    public function pushUnsubscribe(Request $request): JsonResponse
    {
        [, $me] = $this->who();
        $endpoint = (string) $request->input('endpoint', '');
        if ($endpoint !== '') {
            ChatPushSubscription::query()->where('endpoint_hash', hash('sha256', $endpoint))->where('user_id', $me)->delete();
        }

        return ApiResponse::ok(['subscribed' => false]);
    }

    private function pushUpdate(string $company, ChatMessage $m): void
    {
        $m->load(['file', 'replyTo.file', 'reactions']);
        $members = ChatMember::query()->where('chat_id', $m->chat_id)->get();
        ChatPush::send($members->whereNull('left_at')->pluck('user_id')->all(), 'update', $m->chat_id, fn (string $u) => ['message' => ChatPresenter::message($m, $u, $members)]);
    }

    /** The chat itself changed (made, renamed, people in / out): its people reload its row. @param  list<string>|null  $people */
    private function pushChat(Chat $chat, ?array $people = null): void
    {
        ChatPush::send($people ?? $this->peopleOf($chat->id), 'chat', $chat->id);
    }

    private function stream(FileAttachment $f, bool $download, string $cache = 'private, max-age=0, no-store'): StreamedResponse
    {
        abort_unless(Storage::disk($f->disk)->exists($f->path), 404);

        return Storage::disk($f->disk)->response($f->path, $f->original_name, [
            'Content-Type' => $f->mime_type,
            'Content-Disposition' => sprintf('%s; filename="%s"', $f->isImage() && ! $download ? 'inline' : 'attachment', str_replace('"', '', $f->original_name)),
            'Cache-Control' => $cache, 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

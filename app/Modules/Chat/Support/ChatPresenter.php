<?php

declare(strict_types=1);

namespace App\Modules\Chat\Support;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMember;
use App\Modules\Chat\Models\ChatMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Chats and messages as the WhatsApp-style screen draws them: a chat row
 * (name, photo, last message, its time, unread count, pinned / muted), a
 * message bubble (text or file, reply quote, forwarded, edited, reactions,
 * and — on one's own — the ticks: sent ✓, delivered ✓✓, read ✓✓ blue).
 */
final class ChatPresenter
{
    /**
     * Ticks of one's own message: read when every other member has read up
     * to it, delivered when every other member has received it.
     *
     * @param  Collection<int, ChatMember>  $members
     */
    public static function status(ChatMessage $m, Collection $members): string
    {
        $others = $members->filter(fn (ChatMember $x) => $x->user_id !== $m->user_id && $x->left_at === null && $x->joined_at !== null);
        if ($others->isEmpty()) {
            return 'sent';
        }
        if ($others->every(fn (ChatMember $x) => $x->read_message_id !== null && $x->read_message_id >= $m->id)) {
            return 'read';
        }
        if ($others->every(fn (ChatMember $x) => ($x->delivered_message_id !== null && $x->delivered_message_id >= $m->id) || ($x->read_message_id !== null && $x->read_message_id >= $m->id))) {
            return 'delivered';
        }

        return 'sent';
    }

    /**
     * @param  Collection<int, ChatMember>  $members
     * @return array<string, mixed>
     */
    public static function message(ChatMessage $m, string $viewerId, Collection $members): array
    {
        $deleted = $m->deleted_at !== null;
        $file = ! $deleted && $m->file ? [
            'name' => $m->file->original_name, 'size' => (int) $m->file->size_bytes, 'mime' => $m->file->mime_type, 'image' => $m->file->isImage(),
        ] : null;
        $reply = null;
        if ($m->reply_to_id && ($r = $m->replyTo)) {
            $reply = [
                'id' => $r->id, 'user_id' => $r->user_id, 'type' => $r->type, 'deleted' => $r->deleted_at !== null,
                'body' => $r->deleted_at ? null : mb_substr((string) $r->body, 0, 160),
                'file_name' => $r->deleted_at ? null : $r->file?->original_name, 'image' => ! $r->deleted_at && $r->file?->isImage(),
            ];
        }
        $reactions = [];
        foreach ($m->reactions as $x) {
            $reactions[$x->emoji] ??= ['emoji' => $x->emoji, 'count' => 0, 'mine' => false, 'users' => []];
            $reactions[$x->emoji]['count']++;
            $reactions[$x->emoji]['users'][] = $x->user_id;
            $reactions[$x->emoji]['mine'] = $reactions[$x->emoji]['mine'] || $x->user_id === $viewerId;
        }

        return [
            'id' => $m->id, 'chat_id' => $m->chat_id, 'user_id' => $m->user_id, 'type' => $m->type,
            'user_name' => $m->user_id ? (ChatPeople::card($m->company_id, $m->user_id)['name'] ?? null) : null,
            'body' => $deleted ? null : $m->body, 'file' => $file, 'reply' => $reply, 'forwarded' => (bool) $m->forwarded,
            'meta' => $m->meta, 'edited' => $m->edited_at !== null, 'deleted' => $deleted,
            'pinned' => $m->pinned_at !== null, 'reactions' => array_values($reactions),
            'status' => $m->user_id === $viewerId && $m->type !== ChatMessage::SYSTEM ? self::status($m, $members) : null,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    /** The preview of a chat's last message in the list. @return array<string, mixed>|null */
    public static function preview(?ChatMessage $m, string $viewerId, Collection $members): ?array
    {
        if (! $m) {
            return null;
        }

        return [
            'id' => $m->id, 'user_id' => $m->user_id, 'type' => $m->type, 'deleted' => $m->deleted_at !== null,
            'body' => $m->deleted_at ? null : mb_substr((string) $m->body, 0, 120), 'meta' => $m->meta,
            'file_name' => $m->deleted_at ? null : $m->file?->original_name,
            'status' => $m->user_id === $viewerId && $m->type !== ChatMessage::SYSTEM ? self::status($m, $members) : null,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    /**
     * Every chat of one person, newest first (pinned on top), each with its
     * unread count — one query each for the members, last messages and counts.
     *
     * @return list<array<string, mixed>>
     */
    public static function chats(string $companyId, string $userId, ?array $onlyIds = null): array
    {
        $mine = ChatMember::query()->where('user_id', $userId)->when($onlyIds !== null, fn ($q) => $q->whereIn('chat_id', $onlyIds))->get()->keyBy('chat_id');
        if ($mine->isEmpty()) {
            return [];
        }
        $chats = Chat::query()->whereIn('id', $mine->keys())->get()->keyBy('id');
        $members = ChatMember::query()->whereIn('chat_id', $mine->keys())->get()->groupBy('chat_id');
        $last = ChatMessage::query()->with('file')->whereIn('id', $chats->pluck('last_message_id')->filter())->get()->keyBy('id');
        $hidden = DB::table('chat_message_hides')->where('user_id', $userId)->whereIn('message_id', $last->keys())->pluck('message_id')->flip();
        $unread = ChatMessage::query()->toBase()
            ->join('chat_members as cm', fn ($j) => $j->on('cm.chat_id', '=', 'chat_messages.chat_id')->where('cm.user_id', '=', $userId))
            ->whereIn('chat_messages.chat_id', $mine->keys())
            ->where(fn ($q) => $q->whereNull('chat_messages.user_id')->orWhere('chat_messages.user_id', '!=', $userId))
            ->where('chat_messages.type', '!=', ChatMessage::SYSTEM)
            ->whereRaw("chat_messages.id > coalesce(cm.read_message_id, '')")
            ->whereRaw("chat_messages.id > coalesce(cm.cleared_message_id, '')")
            ->whereRaw('chat_messages.created_at >= coalesce(cm.joined_at, chat_messages.created_at)')
            ->groupBy('chat_messages.chat_id')
            ->selectRaw("chat_messages.chat_id, count(*) as n, count(*) filter (where coalesce(chat_messages.meta::jsonb->'mentions'->'users', '[]'::jsonb) @> jsonb_build_array(jsonb_build_object('id', ?::text))) as at", [$userId])
            ->get()->keyBy('chat_id');

        $out = [];
        foreach ($chats as $chat) {
            /** @var ChatMember $me */
            $me = $mine[$chat->id];
            $list = $members[$chat->id] ?? collect();
            $lastMsg = $chat->last_message_id ? $last->get($chat->last_message_id) : null;
            if ($lastMsg && ($hidden->has($lastMsg->id) || ($me->cleared_message_id && $lastMsg->id <= $me->cleared_message_id))) {
                $lastMsg = null;
            }
            $out[] = self::chat($companyId, $chat, $me, $list, $userId) + [
                'last' => self::preview($lastMsg, $userId, $list),
                'unread' => $me->left_at ? 0 : (int) ($unread[$chat->id]->n ?? 0),
                // An unread message names me: the list shows "@".
                'mentioned' => ! $me->left_at && (int) ($unread[$chat->id]->at ?? 0) > 0,
            ];
        }
        usort($out, fn ($a, $b) => [$b['pinned'] ? 1 : 0, $b['sort_at'] ?? ''] <=> [$a['pinned'] ? 1 : 0, $a['sort_at'] ?? '']);

        return $out;
    }

    /**
     * A chat's own face: a group's name and photo, or the other person's.
     *
     * @param  Collection<int, ChatMember>  $members
     * @return array<string, mixed>
     */
    public static function chat(string $companyId, Chat $chat, ChatMember $me, Collection $members, string $userId): array
    {
        $active = $members->whereNull('left_at');
        $other = $chat->is_group ? null : ChatPeople::card($companyId, $members->firstWhere('user_id', '!=', $userId)?->user_id);

        return [
            'id' => $chat->id, 'is_group' => $chat->is_group,
            // A department group: its people follow HR & Payroll (nobody adds or removes them here).
            'department' => str_starts_with((string) $chat->system_key, ChatDepartmentGroups::PREFIX),
            'name' => $chat->is_group ? $chat->name : ($other['name'] ?? '—'),
            'description' => $chat->description, 'image' => $chat->is_group ? $chat->image_file_id !== null : (bool) ($other['photo'] ?? false),
            'tone' => $chat->is_group ? ChatPeople::tone($chat->id) : ($other['tone'] ?? 0),
            'initials' => $chat->is_group ? ChatPeople::initials((string) $chat->name) : ($other['initials'] ?? '?'),
            'other' => $other, 'member_count' => $active->count(),
            'role' => $me->role, 'left' => $me->left_at !== null,
            'pinned' => $me->pinned_at !== null, 'muted' => (bool) $me->muted, 'archived' => (bool) $me->archived,
            'sort_at' => ($chat->last_message_at ?? $chat->created_at)?->toIso8601String(),
        ];
    }
}

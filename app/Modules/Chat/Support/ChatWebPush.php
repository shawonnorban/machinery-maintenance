<?php

declare(strict_types=1);

namespace App\Modules\Chat\Support;

use App\Modules\Chat\Models\Chat;
use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Models\ChatPushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Chat notifications that reach a browser that is closed (Web Push, the
 * user's request of 2026-11-23). Sent after the response, so a slow push
 * service never slows the sender; a browser that unsubscribed (404/410) is
 * forgotten. Muted chats, the sender and people who left get none. The
 * browser itself skips the notification while a chat tab is in front
 * (public/chat-push-sw.js), where the page's own toast already shows it.
 */
final class ChatWebPush
{
    public static function enabled(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    /** @param  Collection<int, \App\Modules\Chat\Models\ChatMember>  $members */
    public static function message(string $companyId, ChatMessage $message, ?Chat $chat, Collection $members): void
    {
        if (! self::enabled() || $message->type === 'SYSTEM' || $message->deleted_at) {
            return;
        }
        // A muted chat still tells whoever the message mentions (as WhatsApp does).
        $named = collect($message->meta['mentions']['users'] ?? [])->pluck('id')->flip();
        $to = $members->whereNull('left_at')->where('user_id', '!=', $message->user_id)
            ->filter(fn ($m) => ! $m->muted || $named->has($m->user_id))->pluck('user_id')->values()->all();
        if ($to === [] || ! ChatPushSubscription::query()->where('company_id', $companyId)->whereIn('user_id', $to)->exists()) {
            return;
        }
        app()->terminating(fn () => self::deliver($companyId, $message, $chat, $to));
    }

    /** @param  list<string>  $userIds */
    public static function deliver(string $companyId, ChatMessage $message, ?Chat $chat, array $userIds): void
    {
        try {
            $subs = ChatPushSubscription::query()->where('company_id', $companyId)->whereIn('user_id', $userIds)->get();
            if ($subs->isEmpty()) {
                return;
            }
            $locales = DB::table('users')->whereIn('id', $subs->pluck('user_id')->unique())->pluck('locale', 'id');
            $sender = ChatPeople::card($companyId, $message->user_id)['name'] ?? '';
            $message->loadMissing('file');
            $push = new WebPush(['VAPID' => [
                'subject' => (string) config('services.webpush.subject'),
                'publicKey' => (string) config('services.webpush.public_key'),
                'privateKey' => (string) config('services.webpush.private_key'),
            ]], ['TTL' => 86400, 'urgency' => 'high']);

            foreach ($subs as $sub) {
                $locale = in_array($locales[$sub->user_id] ?? 'en', ['en', 'bn'], true) ? $locales[$sub->user_id] : 'en';
                $push->queueNotification(Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys' => ['p256dh' => $sub->p256dh, 'auth' => $sub->auth],
                ]), json_encode(self::payload($message, $chat, $sender, $locale), JSON_UNESCAPED_UNICODE));
            }

            $gone = [];
            foreach ($push->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    $gone[] = hash('sha256', $report->getEndpoint());
                } elseif (! $report->isSuccess()) {
                    Log::info('chat web push not delivered', ['reason' => Str::limit($report->getReason(), 300)]);
                }
            }
            if ($gone !== []) {
                ChatPushSubscription::query()->whereIn('endpoint_hash', $gone)->delete();
            }
            ChatPushSubscription::query()->whereIn('id', $subs->pluck('id'))->whereNotIn('endpoint_hash', $gone)->update(['last_sent_at' => now()]);
        } catch (Throwable $e) {
            Log::warning('chat web push failed', ['chat' => $message->chat_id, 'error' => $e->getMessage()]);
        }
    }

    /** What the notification says: who (and in which group), and the message or what was sent. */
    public static function payload(ChatMessage $message, ?Chat $chat, string $sender, string $locale): array
    {
        $text = trim((string) $message->body);
        if ($text === '' && $message->file) {
            $text = $message->file->isImage() ? '📷 '.__('chat.photo_msg', [], $locale) : '📄 '.$message->file->original_name;
        } elseif ($message->file) {
            $text = ($message->file->isImage() ? '📷 ' : '📄 ').$text;
        }
        $group = (bool) $chat?->is_group;

        return [
            'title' => $group ? (string) $chat->name : $sender,
            'body' => Str::limit($group ? "{$sender}: {$text}" : $text, 180),
            'tag' => 'chat-'.$message->chat_id,
            'chat_id' => $message->chat_id,
            'message_id' => $message->id,
            'url' => '/chat',
            'icon' => '/chat-icon-192.png',
            'badge' => '/chat-badge-96.png',
        ];
    }
}

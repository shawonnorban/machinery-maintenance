<?php

declare(strict_types=1);

namespace App\Modules\Chat\Support;

use App\Modules\Chat\Events\ChatEvent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the people of a chat, live. A socket that is down never fails the
 * message itself: it is saved, and the others see it when they next load.
 */
final class ChatPush
{
    /**
     * @param  iterable<string>  $userIds
     * @param  array<string, mixed>|callable(string): array<string, mixed>  $data  the payload, or one per person
     */
    public static function send(iterable $userIds, string $kind, ?string $chatId, array|callable $data = []): void
    {
        foreach (array_unique([...$userIds]) as $userId) {
            try {
                broadcast(new ChatEvent((string) $userId, $kind, $chatId, is_callable($data) ? $data((string) $userId) : $data));
            } catch (Throwable $e) {
                Log::warning('chat push failed', ['kind' => $kind, 'chat' => $chatId, 'error' => $e->getMessage()]);
            }
        }
    }
}

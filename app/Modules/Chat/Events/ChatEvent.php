<?php

declare(strict_types=1);

namespace App\Modules\Chat\Events;

use App\Shared\Broadcasting\AdvisoryBroadcast;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Something happened in a chat, for one person (their own `user.{id}`
 * channel — the one every tab already may listen on): a message came,
 * changed or went, a reaction, read up to, typing, the chat itself changed.
 * Sent at once (not queued): a chat is only a chat if it arrives now.
 */
class ChatEvent implements ShouldBroadcastNow
{
    // Sent at once; the advisory settings (one try, its own queue) apply should it ever be queued.
    use AdvisoryBroadcast;

    /** @param  array<string, mixed>  $data */
    public function __construct(public readonly string $userId, public readonly string $kind, public readonly ?string $chatId, public readonly array $data = []) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'chat.event';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['kind' => $this->kind, 'chat_id' => $this->chatId, 'data' => $this->data];
    }
}

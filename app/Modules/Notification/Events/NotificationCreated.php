<?php

declare(strict_types=1);

namespace App\Modules\Notification\Events;

use App\Modules\Notification\Models\Notification;
use App\Shared\Broadcasting\AdvisoryBroadcast;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A notification arrived for one person (SRS 27, 29).
 *
 * The user channel only. A notification is addressed to somebody: putting it on
 * the company channel would show one manager's approval requests to everyone
 * with a browser tab open.
 *
 * The title and body were rendered in the recipient's language when the
 * notification was written, so the socket carries them as they are rather than
 * translating at delivery — the person's language is a property of the message,
 * not of whoever happens to be connected.
 */
class NotificationCreated implements ShouldBroadcast
{
    use AdvisoryBroadcast;
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly Notification $notification) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->notification->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'event_type' => $this->notification->event_type,
            'severity' => $this->notification->severity,
            'title' => $this->notification->title,
            'body' => $this->notification->body,
            'action_url' => $this->notification->action_url,
            'created_at' => $this->notification->created_at?->toIso8601String(),
        ];
    }
}

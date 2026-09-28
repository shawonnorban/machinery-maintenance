<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Events;

use App\Modules\Breakdown\Models\Breakdown;
use App\Shared\Broadcasting\AdvisoryBroadcast;
use App\Shared\Realtime\BroadcastsToFactory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * A machine has stopped (SRS 29).
 *
 * The event that most justifies having websockets at all: a line is down now,
 * and the difference between a maintenance manager hearing about it in four
 * seconds and in four minutes is four minutes of production.
 *
 * The payload carries what a list row needs and nothing else. A websocket
 * message is delivered to every subscriber on the channel, so anything included
 * here is visible to everyone with factory reach — the full breakdown, with its
 * costs and its notes, is fetched over REST by whoever opens it (SRS 29: the
 * API remains the source of truth).
 */
class BreakdownReported implements ShouldBroadcast
{
    use AdvisoryBroadcast;
    use BroadcastsToFactory;
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly Breakdown $breakdown) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->factoryChannels($this->breakdown->company_id, $this->breakdown->factory_id);
    }

    public function broadcastAs(): string
    {
        return 'breakdown.reported';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->breakdown->id,
            'number' => $this->breakdown->breakdown_number,
            'asset_id' => $this->breakdown->asset_id,
            'asset_code' => $this->breakdown->asset?->asset_code,
            'severity' => $this->breakdown->severity,
            'priority' => $this->breakdown->priority,
            'status' => $this->breakdown->status,
            // Trimmed: a problem description can be a paragraph, and this goes
            // to every connected client on the channel.
            'problem' => Str::limit((string) $this->breakdown->problem_description, 120),
            'reported_at' => $this->breakdown->reported_at?->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Events;

use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Broadcasting\AdvisoryBroadcast;
use App\Shared\Realtime\BroadcastsToFactory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Work handed to somebody (SRS 29).
 *
 * Goes to the assigned technicians personally as well as to the factory. A
 * technician's phone is the one screen where this matters most, and it is
 * usually not showing the work order list when the job arrives.
 */
class WorkOrderAssigned implements ShouldBroadcast
{
    use AdvisoryBroadcast;
    use BroadcastsToFactory;
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  list<string>  $userIds  Technicians who have a login account.
     */
    public function __construct(
        public readonly WorkOrder $workOrder,
        public readonly array $userIds = [],
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = $this->factoryChannels($this->workOrder->company_id, $this->workOrder->factory_id);

        foreach ($this->userIds as $userId) {
            $channels[] = new PrivateChannel('user.'.$userId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'work-order.assigned';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->workOrder->id,
            'number' => $this->workOrder->work_order_number,
            'title' => $this->workOrder->title,
            'asset_code' => $this->workOrder->asset?->asset_code,
            'priority' => $this->workOrder->priority,
            'status' => $this->workOrder->status,
            'scheduled_start' => $this->workOrder->scheduled_start?->toIso8601String(),
        ];
    }
}

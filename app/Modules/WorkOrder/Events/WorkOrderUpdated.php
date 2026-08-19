<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Events;

use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Realtime\BroadcastsToFactory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A work order moved (SRS 29).
 *
 * Status transitions only, not every field edit. A live list wants to know that
 * a job started, finished or went on hold; it does not want a message every
 * time somebody corrects a spelling in the description, and neither does the
 * socket.
 */
class WorkOrderUpdated implements ShouldBroadcast
{
    use BroadcastsToFactory;
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly WorkOrder $workOrder,
        public readonly ?string $fromStatus = null,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->factoryChannels($this->workOrder->company_id, $this->workOrder->factory_id);
    }

    public function broadcastAs(): string
    {
        return 'work-order.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->workOrder->id,
            'number' => $this->workOrder->work_order_number,
            'asset_code' => $this->workOrder->asset?->asset_code,
            'from_status' => $this->fromStatus,
            'status' => $this->workOrder->status,
            'priority' => $this->workOrder->priority,
        ];
    }
}

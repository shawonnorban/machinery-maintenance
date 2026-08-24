<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Events;

use App\Modules\Inventory\Models\SparePart;
use App\Shared\Broadcasting\AdvisoryBroadcast;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Stock moved (SRS 29).
 *
 * Company-wide rather than per factory: a store serves whichever factories draw
 * from it, and a part's balance is not a floor-level fact.
 *
 * Carries whether the part is now at or below its reorder level, because that
 * is the only part of this event most screens act on. Working it out on the
 * client would mean shipping the reorder rules to the browser and keeping two
 * copies of them in step.
 */
class StockChanged implements ShouldBroadcast
{
    use AdvisoryBroadcast;
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly SparePart $part,
        public readonly string $onHand,
        public readonly bool $belowReorderLevel,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.'.$this->part->company_id)];
    }

    public function broadcastAs(): string
    {
        return 'stock.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->part->id,
            'part_number' => $this->part->part_number,
            'name' => $this->part->name,
            'on_hand' => $this->onHand,
            'reorder_level' => $this->part->reorder_level,
            'below_reorder_level' => $this->belowReorderLevel,
            'is_critical_spare' => (bool) $this->part->is_critical_spare,
        ];
    }
}

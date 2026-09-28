<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A part line on a work order (ERD Section 13).
 *
 * The v1.0 API exposed GET /work-orders/{id}/parts with no table behind it.
 *
 * Five quantities rather than one, because "we took four out of the store" and
 * "four went into the machine" are different facts, and the gap between them is
 * stock sitting in a toolbox that the system believes is fitted.
 */
class WorkOrderPart extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = [
        'REQUESTED', 'RESERVED', 'ISSUED', 'PARTIALLY_CONSUMED',
        'CONSUMED', 'RETURNED', 'CANCELLED',
    ];

    protected $table = 'work_order_parts';

    protected $fillable = [
        'company_id', 'work_order_id', 'spare_part_id', 'substitute_for_spare_part_id',
        'bin_id', 'quantity_requested', 'quantity_reserved', 'quantity_issued',
        'quantity_consumed', 'quantity_returned', 'unit_cost', 'currency',
        'total_cost', 'base_total_cost', 'reservation_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity_requested' => MoneyCast::class,
            'quantity_reserved' => MoneyCast::class,
            'quantity_issued' => MoneyCast::class,
            'quantity_consumed' => MoneyCast::class,
            'quantity_returned' => MoneyCast::class,
            'unit_cost' => MoneyCast::class,
            'total_cost' => MoneyCast::class,
            'base_total_cost' => MoneyCast::class,
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function substituteFor(): BelongsTo
    {
        return $this->belongsTo(SparePart::class, 'substitute_for_spare_part_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(SparePartReservation::class, 'reservation_id');
    }

    /**
     * Issued but neither fitted nor given back. A work order cannot close while
     * this is above zero: that stock is unaccounted for, and writing it off
     * quietly is how a store's figures drift away from its shelves
     * (ERD Section 13 rule 2).
     */
    public function outstandingQuantity(): string
    {
        return bcsub(
            (string) $this->quantity_issued,
            bcadd((string) $this->quantity_consumed, (string) $this->quantity_returned, 4),
            4,
        );
    }

    public function isSettled(): bool
    {
        return bccomp($this->outstandingQuantity(), '0', 4) <= 0;
    }
}

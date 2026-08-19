<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock promised to a work order but not yet moved (ERD Section 13).
 *
 * A reservation writes nothing to the ledger. v1.0 listed RESERVATION and
 * RELEASE as ledger transaction types, which was wrong: nothing physically
 * moves, and putting them in the ledger would mean replaying it no longer
 * reproduces the quantity on the shelf. A reservation only encumbers, by
 * raising quantity_reserved on the balance.
 */
class SparePartReservation extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['ACTIVE', 'PARTIALLY_ISSUED', 'ISSUED', 'RELEASED', 'EXPIRED', 'CANCELLED'];

    /** Still holding stock away from everyone else. */
    public const HOLDING_STATUSES = ['ACTIVE', 'PARTIALLY_ISSUED'];

    protected $table = 'spare_part_reservations';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'company_id', 'spare_part_id', 'work_order_id', 'bin_id', 'work_order_part_id',
        'quantity', 'quantity_released', 'quantity_issued', 'status',
        'reserved_by', 'reserved_at', 'expires_at', 'released_by', 'released_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => MoneyCast::class,
            'quantity_released' => MoneyCast::class,
            'quantity_issued' => MoneyCast::class,
            'reserved_at' => 'datetime',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** What this reservation still holds: the original less issued and released. */
    public function outstanding(): string
    {
        return bcsub(
            (string) $this->quantity,
            bcadd((string) $this->quantity_issued, (string) $this->quantity_released, 4),
            4,
        );
    }

    public function isHolding(): bool
    {
        return in_array($this->status, self::HOLDING_STATUSES, true);
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast() && $this->isHolding();
    }
}

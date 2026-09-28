<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock held for somebody without moving it (Inventory spec §7).
 *
 * A reservation writes nothing to the stock ledger — nothing has physically
 * moved, and a ledger row here would mean replaying the ledger no longer
 * reproduces what is on the shelf. It only raises `quantity_reserved` on the
 * balance (or, for a roll, flips that roll to RESERVED), which makes the
 * stock unavailable to everyone else while leaving it exactly where it is.
 *
 * The item-library twin of SparePartReservation. Separate rather than shared
 * because the keys differ: this one reserves against (item, bin, lot) or one
 * named roll, and can exist before the stock does.
 */
class ItemReservation extends BaseModel
{
    use BelongsToTenant;

    /**
     * PENDING_ARRIVAL is the one this model adds over the spare-part side: a
     * claim staked on stock that is still on a purchase order. It holds
     * nothing until the goods receipt fills it.
     */
    public const STATUSES = [
        'PENDING_ARRIVAL', 'ACTIVE', 'PARTIALLY_ISSUED', 'ISSUED',
        'RELEASED', 'EXPIRED', 'CANCELLED',
    ];

    /** Still holding stock away from everyone else. */
    public const HOLDING_STATUSES = ['ACTIVE', 'PARTIALLY_ISSUED'];

    /** Open in the sense that the claim still expects to be satisfied. */
    public const OPEN_STATUSES = ['PENDING_ARRIVAL', 'ACTIVE', 'PARTIALLY_ISSUED'];

    /**
     * BOOKING is a confirmed fabric booking's claim on stock the supplier
     * has not shipped yet (Procurement spec §3); ORDER would be a hard
     * allocation to a buyer's order, which nothing raises yet.
     */
    public const TYPES = ['FLOOR_REQUISITION', 'BOOKING', 'MANUAL'];

    protected $table = 'item_reservations';

    protected $fillable = [
        'company_id', 'item_id', 'bin_id', 'lot_no', 'fabric_roll_id',
        'reservation_type', 'reference_type', 'reference_id',
        'quantity', 'quantity_issued', 'quantity_released', 'status',
        'reserved_by', 'reserved_at', 'expires_at', 'released_by', 'released_at',
        'override_reason',
    ];

    protected $attributes = [
        'lot_no' => ItemStockBalance::NO_LOT,
    ];

    protected function casts(): array
    {
        return [
            'quantity' => MoneyCast::class,
            'quantity_issued' => MoneyCast::class,
            'quantity_released' => MoneyCast::class,
            'reserved_at' => 'datetime',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function fabricRoll(): BelongsTo
    {
        return $this->belongsTo(FabricRoll::class);
    }

    public function reserver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reserved_by');
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

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /** A claim on stock that has not arrived yet — it encumbers nothing. */
    public function isEarmark(): bool
    {
        return $this->status === 'PENDING_ARRIVAL';
    }

    /** One named roll locked to this claim, rather than a quantity block. */
    public function isHardAllocation(): bool
    {
        return $this->fabric_roll_id !== null;
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast() && $this->isOpen();
    }
}

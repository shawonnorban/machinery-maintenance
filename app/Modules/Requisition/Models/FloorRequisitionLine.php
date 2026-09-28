<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Models;

use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemReservation;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One item on a floor requisition (Floor/Store Requisition spec §3).
 *
 * The quantities read left to right: requested by the floor, approved by
 * the store, issued from the shelf, returned if the item was borrowed
 * rather than consumed, and short if the store could not find the rest.
 */
class FloorRequisitionLine extends BaseModel
{
    use BelongsToTenant;

    /**
     * BORROWED is where a returnable item lands instead of ISSUED (spec §7):
     * the stock left the store but is expected back, so the line is not
     * finished until it returns.
     */
    public const STATUSES = [
        'PENDING', 'PARTIALLY_ISSUED', 'ISSUED', 'SHORT', 'BORROWED', 'RETURNED', 'REJECTED',
    ];

    protected $table = 'floor_requisition_lines';

    protected $fillable = [
        'company_id', 'floor_requisition_id', 'item_id', 'item_code', 'item_name', 'uom_id',
        'requested_qty', 'approved_qty', 'issued_qty', 'returned_qty', 'shortage_qty',
        'status', 'item_reservation_id', 'purpose', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'requested_qty' => MoneyCast::class,
            'approved_qty' => MoneyCast::class,
            'issued_qty' => MoneyCast::class,
            'returned_qty' => MoneyCast::class,
            'shortage_qty' => MoneyCast::class,
        ];
    }

    public function issues(): HasMany
    {
        return $this->hasMany(FloorRequisitionIssue::class)->orderBy('issued_at')->orderBy('id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(ItemReservation::class, 'item_reservation_id');
    }

    /**
     * What the store has undertaken to give: the approved quantity once the
     * store has reviewed it, the requested one until then.
     */
    public function targetQty(): string
    {
        return (string) ($this->approved_qty ?? $this->requested_qty);
    }

    /** Still to hand over. */
    public function outstandingQty(): string
    {
        $outstanding = bcsub($this->targetQty(), (string) $this->issued_qty, 4);

        return bccomp($outstanding, '0', 4) === 1 ? $outstanding : '0.0000';
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['REJECTED', 'RETURNED'], true)
            && bccomp($this->outstandingQty(), '0', 4) === 1;
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(FloorRequisition::class, 'floor_requisition_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
}

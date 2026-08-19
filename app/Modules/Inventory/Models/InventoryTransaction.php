<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One posted stock movement. Append-only (ERD Section 13 rule 1).
 *
 * Never updated, never deleted. A correction is a REVERSAL row pointing at the
 * one it undoes, so the history shows both what was recorded and what was done
 * about it. Deleting the original would leave the balance right and the story
 * wrong, and a missing row is harder to find than a wrong number.
 */
class InventoryTransaction extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = [
        'OPENING_BALANCE', 'RECEIPT', 'ISSUE', 'CONSUME', 'RETURN',
        'ADJUSTMENT_IN', 'ADJUSTMENT_OUT', 'TRANSFER_OUT', 'TRANSFER_IN',
        'SCRAP',
    ];

    /**
     * Direction is carried by the type, never by the sign of the quantity. A
     * sign error would silently reverse a movement; an unknown type cannot.
     */
    public const INBOUND = ['OPENING_BALANCE', 'RECEIPT', 'RETURN', 'ADJUSTMENT_IN', 'TRANSFER_IN'];

    public const OUTBOUND = ['ISSUE', 'CONSUME', 'ADJUSTMENT_OUT', 'TRANSFER_OUT', 'SCRAP'];

    /**
     * Only these move the weighted average. An issue draws at the average in
     * force and leaves it alone, so the cost of a repair does not depend on how
     * much happened to be in the bin that day (ERD Section 13 rule 4).
     */
    public const AFFECTS_WAC = ['OPENING_BALANCE', 'RECEIPT', 'TRANSFER_IN', 'RETURN', 'ADJUSTMENT_IN'];

    public $timestamps = true;

    protected $table = 'inventory_transactions';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'company_id', 'spare_part_id', 'bin_id', 'transaction_type', 'quantity',
        'unit_cost', 'total_cost', 'currency', 'exchange_rate', 'base_total_cost',
        'balance_after', 'wac_after', 'reference_type', 'reference_id',
        'reservation_id', 'inventory_transfer_id', 'work_order_id',
        'reverses_transaction_id', 'performed_by', 'transaction_at', 'notes',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => MoneyCast::class,
            'unit_cost' => MoneyCast::class,
            'total_cost' => MoneyCast::class,
            'base_total_cost' => MoneyCast::class,
            'balance_after' => MoneyCast::class,
            'wac_after' => MoneyCast::class,
            'transaction_at' => 'datetime',
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

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_transaction_id');
    }

    public function isInbound(): bool
    {
        return in_array($this->transaction_type, self::INBOUND, true);
    }

    /** For display: a signed quantity, derived rather than stored. */
    public function signedQuantity(): string
    {
        return $this->isInbound()
            ? (string) $this->quantity
            : '-'.$this->quantity;
    }
}

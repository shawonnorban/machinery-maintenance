<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Models;

use App\Modules\Inventory\Models\Bin;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One posted movement of a library item. Append-only.
 *
 * Never updated, never deleted. A correction is an opposing row pointing at
 * the one it undoes, so the history shows both what was recorded and what
 * was done about it. The type vocabulary is the spare-parts ledger's, so a
 * stock report can read both ledgers with one legend.
 */
class ItemStockTransaction extends BaseModel
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
     * force and leaves it alone.
     */
    public const AFFECTS_WAC = ['OPENING_BALANCE', 'RECEIPT', 'TRANSFER_IN', 'RETURN', 'ADJUSTMENT_IN'];

    /**
     * A value-only movement: landed cost arriving weeks after the goods did
     * (Procurement spec §11). It carries a zero quantity on purpose —
     * nothing physically moved, only what the stock is worth changed — so
     * replaying the ledger still reproduces the shelf exactly.
     */
    public const REVALUATION = 'LANDED_COST';

    public $timestamps = true;

    protected $table = 'item_stock_transactions';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'company_id', 'item_id', 'bin_id', 'lot_no', 'transaction_type', 'quantity',
        'unit_cost', 'total_cost', 'currency', 'exchange_rate', 'base_total_cost',
        'balance_after', 'wac_after', 'reference_type', 'reference_id',
        'reverses_transaction_id', 'performed_by', 'transaction_at', 'notes',
        'idempotency_key',
    ];

    protected $attributes = [
        'lot_no' => ItemStockBalance::NO_LOT,
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

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
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

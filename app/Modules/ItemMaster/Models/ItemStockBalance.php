<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Models;

use App\Modules\Inventory\Models\Bin;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many of an item are in one bin (and, for lot-tracked items, one lot),
 * and what they are worth.
 *
 * Never written directly. Every change goes through ItemLedger inside the
 * same database transaction as the ledger row that caused it, under a row
 * lock — otherwise two concurrent issues of the last unit both read "1
 * available".
 */
class ItemStockBalance extends BaseModel
{
    use BelongsToTenant;

    /** The lot key a pooled (QUANTITY-tracked) balance is stored under. */
    public const NO_LOT = '';

    protected $table = 'item_stock_balances';

    protected $fillable = [
        'company_id', 'item_id', 'bin_id', 'lot_no', 'quantity_on_hand',
        'quantity_reserved', 'weighted_average_cost', 'currency', 'version',
    ];

    protected $attributes = [
        'lot_no' => self::NO_LOT,
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => MoneyCast::class,
            'quantity_reserved' => MoneyCast::class,
            'weighted_average_cost' => MoneyCast::class,
            'version' => 'integer',
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

    /** On hand minus reserved. Reserved stock is already promised elsewhere. */
    public function available(): string
    {
        return bcsub((string) $this->quantity_on_hand, (string) $this->quantity_reserved, 4);
    }

    public function totalValue(): string
    {
        return bcmul((string) $this->quantity_on_hand, (string) $this->weighted_average_cost, 4);
    }
}

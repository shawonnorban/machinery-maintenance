<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemStockTransaction;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item going back to the supplier (Procurement spec §12).
 *
 * A quantity line names the bin it comes out of; a fabric line names the
 * roll, because for a roll-tracked item the roll is the stock (Inventory
 * spec §11) and "20 metres of navy" is not something that can be handed to
 * a driver.
 */
class PurchaseReturnLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'purchase_return_lines';

    protected $fillable = [
        'company_id', 'purchase_return_id', 'goods_receipt_line_id', 'item_id',
        'item_code', 'item_name', 'uom_id', 'bin_id', 'lot_no', 'fabric_roll_id',
        'quantity', 'unit_cost', 'line_total', 'item_stock_transaction_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => MoneyCast::class,
            'unit_cost' => MoneyCast::class,
            'line_total' => MoneyCast::class,
        ];
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function goodsReceiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function fabricRoll(): BelongsTo
    {
        return $this->belongsTo(FabricRoll::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(ItemStockTransaction::class, 'item_stock_transaction_id');
    }
}

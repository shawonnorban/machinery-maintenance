<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A stock lot a budget line uses: purchase / stock contract, yarn mill, origin (Local / Import), LC, lot, qty and price. Stock Yarn Use, Pre-Budget (AnnotechERP 33/34).
 */
class PbStockLot extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'pb_stock_lots';

    protected $fillable = ['company_id', 'budget_id', 'section', 'line_key', 'sort_order', 'purchase_contract_no', 'stock_contract_no', 'mill', 'origin', 'lc_no', 'lot_no', 'qty', 'price'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'qty' => 'float', 'price' => 'float'];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One yarn line of a purchase order: quantity, rate and what the store has received against it.
 */
class YarnPurchaseOrderLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_po_lines';

    protected $fillable = ['company_id', 'po_id', 'sort_order', 'composition_id', 'yarn_count_id', 'colour', 'brand', 'lot_no', 'uom', 'qty', 'rate', 'received_qty', 'knit_start_date', 'ship_date', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rate' => 'float', 'received_qty' => 'float', 'knit_start_date' => 'date', 'ship_date' => 'date'];
    }
}

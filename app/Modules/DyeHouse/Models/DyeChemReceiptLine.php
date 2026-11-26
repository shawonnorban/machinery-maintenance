<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One lot a receipt brings in: the item, lot number and expiry, the quantity in its unit and the rate. Dye House → Chemical Store (AnnotechERP 17/06).
 */
class DyeChemReceiptLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_chem_receipt_lines';

    protected $fillable = ['company_id', 'receipt_id', 'item_id', 'lot_no', 'exp_date', 'qty', 'uom', 'rate_bdt', 'remarks', 'lot_id', 'sort_order'];

    protected function casts(): array
    {
        return ['exp_date' => 'date', 'qty' => 'float', 'rate_bdt' => 'float'];
    }
}

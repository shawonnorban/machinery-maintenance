<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One roll or lot on an out-house receive challan.
 */
class KnitScGreyReceiptLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_sc_grey_receipt_lines';

    protected $fillable = ['company_id', 'receipt_id', 'sort_order', 'type', 'fabric_type_id', 'description', 'colour_id', 'colour_name', 'shade', 'roll_no', 'finish_dia', 'gsm', 'r_qty', 'qty', 'uom', 'remarks', 'lot_id'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'gsm' => 'float', 'r_qty' => 'integer', 'sort_order' => 'integer'];
    }
}

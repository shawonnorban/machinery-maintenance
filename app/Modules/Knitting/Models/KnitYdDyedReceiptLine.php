<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One shade received on a dyed yarn receive challan.
 */
class KnitYdDyedReceiptLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yd_dyed_receipt_lines';

    protected $fillable = ['company_id', 'receipt_id', 'sort_order', 'knit_prog_id', 'collar_prog_id', 'composition_id', 'yarn_count_id', 'colour_id', 'colour_name', 'lot_no', 'pack_qty', 'qty', 'uom', 'remarks', 'lot_id'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'pack_qty' => 'integer', 'sort_order' => 'integer'];
    }
}

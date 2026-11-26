<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One lot going back on a return, against the receipt line it came in on. Dye House → Chemical Store (AnnotechERP 17/06).
 */
class DyeChemReturnLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_chem_return_lines';

    protected $fillable = ['company_id', 'return_id', 'receipt_line_id', 'item_id', 'lot_no', 'qty', 'uom', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'float'];
    }
}

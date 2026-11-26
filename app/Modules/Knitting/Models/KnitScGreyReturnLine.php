<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One roll or lot on a grey return challan.
 */
class KnitScGreyReturnLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_sc_grey_return_lines';

    protected $fillable = ['company_id', 'return_id', 'sort_order', 'type', 'lot_id', 'description', 'colour_id', 'colour_name', 'shade', 'roll_no', 'r_qty', 'qty', 'uom', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'r_qty' => 'integer', 'sort_order' => 'integer'];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One stock line corrected on a grey adjustment.
 */
class KnitGreyAdjustmentLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_grey_adjustment_lines';

    protected $fillable = ['company_id', 'adjustment_id', 'sort_order', 'order_id', 'colour_id', 'colour_name', 'fabric', 'roll_no', 'part', 'qty', 'uom', 'rate', 'reason', 'lot_id'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rate' => 'float', 'sort_order' => 'integer'];
    }
}

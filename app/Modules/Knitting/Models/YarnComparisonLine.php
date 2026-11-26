<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One yarn line of a comparative statement, and the spinner it is finalized with.
 */
class YarnComparisonLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_comparison_lines';

    protected $fillable = ['company_id', 'comparison_id', 'sl_no', 'composition_id', 'yarn_count_id', 'colour', 'brand', 'uom', 'qty_local', 'qty_import', 'present_stock', 'pipeline', 'knit_start_date', 'ship_date', 'booking_price', 'buyer_id', 'supplier_id', 'remarks'];

    protected function casts(): array
    {
        return ['qty_local' => 'float', 'qty_import' => 'float', 'present_stock' => 'float', 'pipeline' => 'float', 'booking_price' => 'float', 'knit_start_date' => 'date', 'ship_date' => 'date'];
    }
}

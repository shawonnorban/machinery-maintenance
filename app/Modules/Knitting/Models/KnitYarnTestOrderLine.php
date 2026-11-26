<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One lot going to the lab.
 */
class KnitYarnTestOrderLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_test_order_lines';

    protected $fillable = ['company_id', 'order_id', 'sort_order', 'composition_id', 'yarn_count_id', 'colour_group', 'brand', 'supplier_id', 'lc_no', 'lot_no', 'qty', 'uom', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float'];
    }
}

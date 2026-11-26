<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One yarn lot on a sub-contract yarn receive challan.
 */
class KnitScYarnChallanLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_sc_yarn_challan_lines';

    protected $fillable = ['company_id', 'challan_id', 'sort_order', 'composition_id', 'yarn_count_id', 'colour_group', 'brand', 'lot_no', 'qty', 'uom', 'cones', 'lot_id', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'cones' => 'integer', 'sort_order' => 'integer'];
    }
}

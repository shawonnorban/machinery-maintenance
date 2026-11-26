<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One programme line brought back on a receive challan.
 */
class KnitTwistReceiveLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_twist_receive_lines';

    protected $fillable = ['company_id', 'receive_id', 'program_line_id', 'sort_order', 'colour_group', 'brand', 'lot_no', 'grey_qty', 'receive_qty', 'cones', 'uom', 'remarks'];

    protected function casts(): array
    {
        return ['grey_qty' => 'float', 'receive_qty' => 'float', 'cones' => 'integer'];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One yarn going out single and coming back twisted.
 */
class KnitTwistProgramLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_twist_program_lines';

    protected $fillable = ['company_id', 'program_id', 'sort_order', 'composition_id', 'yarn_count_id', 'colour_group', 'brand', 'lot_no', 'grey_qty', 'tpi', 'ply', 'rate', 'uom', 'received_qty', 'remarks'];

    protected function casts(): array
    {
        return ['grey_qty' => 'float', 'tpi' => 'float', 'ply' => 'integer', 'rate' => 'float', 'received_qty' => 'float'];
    }
}

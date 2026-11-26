<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One shade a Y/D programme expects back.
 */
class KnitYdProgramLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yd_program_lines';

    protected $fillable = ['company_id', 'program_id', 'sort_order', 'colour_id', 'colour_name', 'colour_group', 'composition_id', 'yarn_count_id', 'lot_no', 'qty', 'uom', 'rate', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rate' => 'float', 'sort_order' => 'integer'];
    }
}

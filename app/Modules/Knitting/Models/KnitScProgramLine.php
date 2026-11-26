<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One fabric in one shade the party is asked to knit.
 */
class KnitScProgramLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_sc_program_lines';

    protected $fillable = ['company_id', 'program_id', 'sort_order', 'fabric', 'colour_id', 'colour_group', 'machine_dia', 'gauge', 'finish_dia', 'gsm', 'qty', 'rate', 'uom', 'received_qty', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rate' => 'float', 'received_qty' => 'float'];
    }
}

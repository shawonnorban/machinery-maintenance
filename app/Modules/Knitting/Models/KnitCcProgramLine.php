<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One part a programme places: pieces, and what the party is paid for each.
 */
class KnitCcProgramLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_cc_program_lines';

    protected $fillable = ['company_id', 'program_id', 'sort_order', 'item', 'description', 'colour_id', 'size_desc', 'gauge', 'qty', 'rate', 'amount', 'uom', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rate' => 'float', 'amount' => 'float'];
    }
}

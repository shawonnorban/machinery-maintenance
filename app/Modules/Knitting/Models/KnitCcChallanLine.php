<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One part on a collar and cuff challan.
 */
class KnitCcChallanLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_cc_challan_lines';

    protected $fillable = ['company_id', 'challan_id', 'sort_order', 'requirement_id', 'item', 'description', 'colour_id', 'size_desc', 'order_id', 'qty', 'uom', 'remarks', 'program_line_id'];

    protected function casts(): array
    {
        return ['qty' => 'float'];
    }
}

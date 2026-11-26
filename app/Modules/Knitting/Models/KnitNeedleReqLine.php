<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One machine and needle on a machine-wise indent.
 */
class KnitNeedleReqLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_needle_req_lines';

    protected $fillable = ['company_id', 'req_id', 'sort_order', 'machine_id', 'item_id', 'uom', 'req_qty', 'rate', 'remarks'];

    protected function casts(): array
    {
        return ['req_qty' => 'float', 'rate' => 'float'];
    }
}

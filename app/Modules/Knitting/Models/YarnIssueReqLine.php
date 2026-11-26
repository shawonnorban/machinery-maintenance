<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One count asked for on a requisition, and what has gone out against it.
 */
class YarnIssueReqLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_issue_req_lines';

    protected $fillable = ['company_id', 'req_id', 'sort_order', 'composition_id', 'yarn_count_id', 'colour', 'brand', 'lot_no', 'uom', 'req_qty', 'kp_alloc', 'issued_qty', 'remarks'];

    protected function casts(): array
    {
        return ['req_qty' => 'float', 'kp_alloc' => 'float', 'issued_qty' => 'float'];
    }
}

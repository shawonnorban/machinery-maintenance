<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One needle on a loan indent, and what the store has released against it.
 */
class KnitNeedleLoanReqLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_needle_loan_req_lines';

    protected $fillable = ['company_id', 'req_id', 'sort_order', 'item_id', 'uom', 'required_qty', 'issue_qty', 'rate', 'remarks'];

    protected function casts(): array
    {
        return ['required_qty' => 'float', 'issue_qty' => 'float', 'rate' => 'float'];
    }
}

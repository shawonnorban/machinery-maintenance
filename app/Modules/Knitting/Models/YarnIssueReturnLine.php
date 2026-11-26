<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One line coming back: what was sent and what the store counted in.
 */
class YarnIssueReturnLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_issue_return_lines';

    protected $fillable = ['company_id', 'return_id', 'sort_order', 'issue_line_id', 'composition_id', 'yarn_count_id', 'colour', 'brand', 'lot_no', 'uom', 'ret_qty', 'recv_qty', 'lot_id', 'remarks'];

    protected function casts(): array
    {
        return ['ret_qty' => 'float', 'recv_qty' => 'float'];
    }
}

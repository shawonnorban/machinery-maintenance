<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Yarn the store released against an indent line, off one stock line.
 */
class KnitCcYarnIssue extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_cc_yarn_issues';

    protected $fillable = ['company_id', 'req_id', 'req_line_id', 'issue_no', 'issue_date', 'lot_id', 'qty', 'created_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'qty' => 'float'];
    }
}

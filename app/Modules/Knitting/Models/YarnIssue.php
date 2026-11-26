<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Yarn issued off the store against one requisition.
 */
class YarnIssue extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_issues';

    protected $fillable = ['company_id', 'issue_no', 'issue_date', 'req_id', 'store_id', 'gate_pass_no', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'date'];
    }
}

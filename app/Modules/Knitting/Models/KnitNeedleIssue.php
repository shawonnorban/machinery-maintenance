<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Needles released against a machine-wise indent.
 */
class KnitNeedleIssue extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['ISSUED', 'WITHDRAWN'];

    protected $table = 'knit_needle_issues';

    protected $fillable = ['company_id', 'issue_no', 'issue_date', 'req_id', 'from_store_id', 'to_store_id', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'date'];
    }
}

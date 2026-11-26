<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Unused yarn coming back off the floor against the issue it left on.
 */
class YarnIssueReturn extends BaseModel
{
    use BelongsToTenant;

    /** Draft → Issued → Accepted (the counted weight back in stock) or Rejected; Cancelled before acceptance. */
    public const STATUSES = ['DRAFT', 'ISSUED', 'ACCEPTED', 'REJECTED', 'CANCELLED'];

    protected $table = 'knit_yarn_issue_returns';

    protected $fillable = ['company_id', 'return_no', 'return_date', 'req_type', 'store_id', 'req_id', 'return_by', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['return_date' => 'date'];
    }
}

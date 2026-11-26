<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A store requisition for yarn: what the floor asks for, one count against one programme per line.
 */
class YarnIssueReq extends BaseModel
{
    use BelongsToTenant;

    /** Open → Partly Issued → Fully Issued as yarn goes out; Closed is a decision, Cancelled only before anything left. */
    public const STATUSES = ['OPEN', 'PARTLY_ISSUED', 'FULLY_ISSUED', 'CLOSED', 'CANCELLED'];

    /** The ones an issue can still be raised against. */
    public const ISSUABLE = ['OPEN', 'PARTLY_ISSUED'];

    protected $table = 'knit_yarn_issue_reqs';

    protected $fillable = ['company_id', 'req_no', 'req_date', 'req_type', 'store_id', 'buyer_id', 'order_id', 'party_id', 'program_no', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['req_date' => 'date'];
    }
}

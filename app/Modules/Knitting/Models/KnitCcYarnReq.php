<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A collar and cuff yarn indent, solid or yarn-dyed.
 */
class KnitCcYarnReq extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['SOLID', 'YD'];

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'PARTLY_ISSUED', 'ISSUED', 'CLOSED', 'CANCELLED'];

    /** The ones the store can still release yarn against. */
    public const ISSUABLE = ['APPROVED', 'PARTLY_ISSUED'];

    protected $table = 'knit_cc_yarn_reqs';

    protected $fillable = ['company_id', 'req_no', 'kind', 'req_date', 'knit_plan_no', 'program_id', 'buyer_id', 'order_id', 'work_style_no', 'store_id', 'req_type', 'remarks', 'status', 'approved_at', 'approved_by', 'created_by'];

    protected function casts(): array
    {
        return ['req_date' => 'date', 'approved_at' => 'datetime'];
    }
}

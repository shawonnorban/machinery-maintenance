<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The sample room's knit plan: the yarn a sample needs, raised on the store as a requisition.
 */
class KnitSamplePlan extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['SOLID', 'YD'];

    /** Draft → Requisition Submitted (Issued and Closed are read off the requisition); Cancelled. */
    public const STATUSES = ['DRAFT', 'REQ_SUBMITTED', 'ISSUED', 'CLOSED', 'CANCELLED'];

    protected $table = 'knit_sample_plans';

    protected $fillable = ['company_id', 'plan_no', 'kind', 'plan_date', 'buyer_id', 'store_id', 'req_type', 'remarks', 'status', 'issue_req_id', 'created_by'];

    protected function casts(): array
    {
        return ['plan_date' => 'date'];
    }
}

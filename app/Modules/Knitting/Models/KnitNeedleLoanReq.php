<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A request to lend needles to a party or send borrowed ones back.
 */
class KnitNeedleLoanReq extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['LOAN_ISSUE', 'LOAN_RETURN'];

    public const STATUSES = ['DRAFT', 'WAITING', 'VERIFIED', 'PARTLY_ISSUED', 'ISSUED', 'CLOSED', 'CANCELLED'];

    protected $table = 'knit_needle_loan_reqs';

    protected $fillable = ['company_id', 'book', 'req_no', 'req_date', 'kind', 'req_type', 'office_id', 'department_id', 'party_id', 'store_id', 'reason', 'remarks', 'status', 'verified_at', 'verified_by', 'created_by'];

    protected function casts(): array
    {
        return ['req_date' => 'date', 'verified_at' => 'datetime'];
    }
}

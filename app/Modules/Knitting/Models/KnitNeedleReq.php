<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Needles asked for by machines, one store of another.
 */
class KnitNeedleReq extends BaseModel
{
    use BelongsToTenant;

    public const REASONS = ['NEEDLE_BROKEN', 'SERVICING', 'SET_CHANGE', 'NEW_SET', 'DAMAGED', 'OTHERS'];

    public const STATUSES = ['DRAFT', 'WAITING', 'VERIFIED', 'PARTLY_ISSUED', 'ISSUED', 'CLOSED', 'CANCELLED'];

    /** The ones an issue can still answer. */
    public const ISSUABLE = ['VERIFIED', 'PARTLY_ISSUED'];

    protected $table = 'knit_needle_reqs';

    protected $fillable = ['company_id', 'req_no', 'req_date', 'reason', 'broken_id', 'office_id', 'from_store_id', 'to_store_id', 'remarks', 'status', 'verified_at', 'verified_by', 'created_by'];

    protected function casts(): array
    {
        return ['req_date' => 'date', 'verified_at' => 'datetime'];
    }
}

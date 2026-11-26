<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Knit Machine Oil Indent: one store asking another for oil.
 */
class KnitOilReq extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'WAITING', 'VERIFIED', 'PARTLY_ISSUED', 'ISSUED', 'CLOSED', 'CANCELLED'];

    protected $table = 'knit_oil_reqs';

    protected $fillable = ['company_id', 'req_no', 'req_date', 'req_type', 'office_id', 'department_id', 'from_store_id', 'to_store_id', 'reason', 'remarks', 'status', 'verified_at', 'verified_by', 'created_by'];

    protected function casts(): array
    {
        return ['req_date' => 'date', 'verified_at' => 'datetime'];
    }
}

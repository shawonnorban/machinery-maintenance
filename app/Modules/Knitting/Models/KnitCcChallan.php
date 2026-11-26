<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Collar and cuff pieces moved between the floor and the store.
 */
class KnitCcChallan extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['BULK', 'SAMPLE'];

    public const TYPES = ['INTERNAL_TRANSFER', 'DELIVERY', 'RETURN', 'SC_RECEIVE'];

    public const STATUSES = ['DRAFT', 'ISSUED', 'RECEIVED', 'CANCELLED'];

    protected $table = 'knit_cc_challans';

    protected $fillable = ['company_id', 'challan_no', 'kind', 'challan_type', 'challan_date', 'gate_pass_no', 'vehicle_no', 'from_floor_id', 'store_id', 'remarks', 'status', 'rcv_date', 'created_by'];

    protected function casts(): array
    {
        return ['challan_date' => 'date', 'rcv_date' => 'date'];
    }
}

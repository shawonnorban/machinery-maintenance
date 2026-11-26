<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What a party hands back against a loan issued to it, on its own challan.
 */
class KnitNeedleLoanReturn extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'RECEIVED', 'FINALIZED', 'CANCELLED'];

    protected $table = 'knit_needle_loan_returns';

    protected $fillable = ['company_id', 'book', 'return_no', 'ret_challan_no', 'ret_challan_date', 'req_id', 'party_id', 'store_id', 'received_by', 'gate_pass_no', 'vehicle_no', 'remarks', 'status', 'rcv_date', 'created_by'];

    protected function casts(): array
    {
        return ['ret_challan_date' => 'date', 'rcv_date' => 'date'];
    }
}

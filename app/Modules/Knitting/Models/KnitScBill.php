<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A sub-contract (in-house, out-house) or yarn dyeing bill.
 */
class KnitScBill extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['IN_HOUSE', 'OUT_HOUSE', 'YD'];

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'REVISED', 'CANCELLED'];

    protected $table = 'knit_sc_bills';

    protected $fillable = ['company_id', 'bill_no', 'kind', 'bill_date', 'party_id', 'party_bill_no', 'ref_bill_id', 'rev_descr', 'currency_id', 'gross', 'deduction', 'vat', 'net', 'remarks', 'status', 'submitted_at', 'approved_at', 'approved_by', 'created_by'];

    protected function casts(): array
    {
        return ['bill_date' => 'date', 'gross' => 'float', 'deduction' => 'float', 'vat' => 'float', 'net' => 'float', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Needles or oil borrowed in from a party, received on the store MRR.
 */
class KnitNeedleLoanReceipt extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'WAITING', 'VERIFIED', 'WAIT_ACCOUNTS', 'APPROVED', 'CANCELLED'];

    /** The ones that have posted to the book. */
    public const POSTED = ['VERIFIED', 'WAIT_ACCOUNTS', 'APPROVED'];

    protected $table = 'knit_needle_loan_receipts';

    protected $fillable = ['company_id', 'book', 'mrr_no', 'mrr_date', 'req_type', 'party_id', 'store_id', 'pay_method', 'source', 'challan_no', 'challan_date', 'lc_no', 'remarks', 'status', 'verified_at', 'approved_at', 'approved_by', 'created_by'];

    protected function casts(): array
    {
        return ['mrr_date' => 'date', 'challan_date' => 'date', 'verified_at' => 'datetime', 'approved_at' => 'datetime'];
    }
}

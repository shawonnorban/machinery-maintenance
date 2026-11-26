<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Needles taken into a store against a purchase or a return.
 */
class KnitNeedleReceipt extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'WAITING', 'VERIFIED', 'WAIT_ACCOUNTS', 'APPROVED', 'CANCELLED'];

    public const PAY_METHODS = ['ADJUSTMENT', 'CASH', 'CREDIT', 'LC'];

    /** The ones that have posted to the store. */
    public const POSTED = ['VERIFIED', 'WAIT_ACCOUNTS', 'APPROVED'];

    protected $table = 'knit_needle_receipts';

    protected $fillable = ['company_id', 'mrr_no', 'mrr_date', 'store_id', 'req_type', 'supplier_id', 'challan_no', 'challan_date', 'source', 'pay_method', 'lc_no', 'remarks', 'status', 'verified_at', 'approved_at', 'approved_by', 'created_by'];

    protected function casts(): array
    {
        return ['mrr_date' => 'date', 'challan_date' => 'date', 'verified_at' => 'datetime', 'approved_at' => 'datetime'];
    }
}

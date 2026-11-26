<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Oil taken into a store against a purchase, signed desk by desk.
 */
class KnitOilReceipt extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'WAIT_QC', 'WAITING', 'WAIT_ACCOUNTS', 'APPROVED', 'COMPLETE', 'CANCELLED'];

    public const PAY_METHODS = ['ADJUSTMENT', 'CASH', 'CREDIT', 'LC'];

    /** Each desk in turn: Send to Next Desk moves it one along. */
    public const DESKS = ['DRAFT' => 'WAIT_QC', 'WAIT_QC' => 'WAITING', 'WAITING' => 'WAIT_ACCOUNTS', 'WAIT_ACCOUNTS' => 'APPROVED', 'APPROVED' => 'COMPLETE'];

    protected $table = 'knit_oil_receipts';

    protected $fillable = ['company_id', 'mrr_no', 'mrr_date', 'store_id', 'req_type', 'supplier_id', 'challan_no', 'challan_date', 'pay_method', 'source', 'lc_no', 'remarks', 'status', 'finalized_at', 'finalized_by', 'accounted_at', 'accounted_by', 'created_by'];

    protected function casts(): array
    {
        return ['mrr_date' => 'date', 'challan_date' => 'date', 'finalized_at' => 'datetime', 'accounted_at' => 'datetime'];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A daily yarn receipt (MRR): yarn taken into the store against a supplier's challan.
 */
class YarnReceipt extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['STORE_RECEIVE', 'STORE_VERIFY', 'AUDIT_VERIFY', 'ACCOUNTS_APPROVE', 'CANCELLED'];

    public const OPTIONS = ['DIRECT', 'PO', 'PI'];

    protected $table = 'knit_yarn_receipts';

    protected $fillable = ['company_id', 'mrr_no', 'mrr_date', 'store_id', 'recv_opt', 'po_id', 'recv_ref', 'challan_no', 'supplier_id', 'buyer_id', 'order_id', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['mrr_date' => 'date'];
    }
}

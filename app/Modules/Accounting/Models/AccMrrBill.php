<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A supplier bill drawn from one or more material receive reports of one kind. Finance & Ledger → Voucher Entry (AnnotechERP 23/02).
 */
class AccMrrBill extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'acc_mrr_bills';

    protected $fillable = ['company_id', 'bill_no', 'bill_date', 'supplier_id', 'mrr_type', 'prepared_by', 'maker_id', 'credit_account_id', 'remarks', 'fixed_asset', 'final', 'finalized_at', 'created_by'];

    protected function casts(): array
    {
        return ['bill_date' => 'date', 'fixed_asset' => 'boolean', 'final' => 'boolean', 'finalized_at' => 'datetime'];
    }
}

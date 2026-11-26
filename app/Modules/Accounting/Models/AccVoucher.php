<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A voucher: a draft carrying only its reference, or committed with its number and posting state. Finance & Ledger → Voucher Entry (AnnotechERP 23/02).
 */
class AccVoucher extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'acc_vouchers';

    protected $fillable = ['company_id', 'voucher_no', 'voucher_ref_no', 'voucher_date', 'voucher_type', 'trx_source', 'bill_no', 'voucher_for_type', 'voucher_for', 'narration', 'action_status', 'posting_status', 'total', 'approved_by', 'approved_at', 'posted_by', 'posted_at', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['voucher_date' => 'date', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'total' => 'float'];
    }
}

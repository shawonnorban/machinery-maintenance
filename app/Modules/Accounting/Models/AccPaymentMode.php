<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A mode a voucher may be settled by — cash, cheque, transfer — and the voucher types it is offered on (Payment Method Master). Finance & Ledger → Master Data (AnnotechERP 23/01).
 */
class AccPaymentMode extends BaseModel
{
    use BelongsToTenant;

    /** Voucher types: bank payment, bank receive, cash payment, cash receive, contra, bill, journal. */
    public const VOUCHER_TYPES = ['BPV', 'BRV', 'CPV', 'CRV', 'CV', 'BV', 'JV'];

    protected $table = 'acc_payment_modes';

    protected $fillable = ['company_id', 'name', 'short_name', 'voucher_types', 'remarks', 'active'];

    protected function casts(): array
    {
        return ['voucher_types' => 'array', 'active' => 'boolean'];
    }
}

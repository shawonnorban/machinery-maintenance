<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Money received against a final bill of exchange. Trade & Export → CM Payment Receive (AnnotechERP 22/04).
 */
class TxCmReceipt extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tx_cm_receipts';

    protected $fillable = ['company_id', 'receipt_no', 'boe_id', 'bank_bill_type', 'amount', 'receive_date', 'exchange_rate', 'dfc_margin', 'final', 'created_by'];

    protected function casts(): array
    {
        return ['receive_date' => 'date', 'final' => 'boolean', 'amount' => 'float', 'exchange_rate' => 'float', 'dfc_margin' => 'float'];
    }
}

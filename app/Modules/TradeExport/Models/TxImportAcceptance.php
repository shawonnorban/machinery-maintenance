<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An accepted import bill (IFDBC). Trade & Export → Import (AnnotechERP 22/03).
 */
class TxImportAcceptance extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tx_import_acceptances';

    protected $fillable = ['company_id', 'boe_no', 'lc_id', 'invoiced_amount', 'currency', 'acceptance_date', 'ifdbc_no', 'ifdbc_amount', 'is_pending', 'open_date', 'maturity_date', 'ext_maturity_date', 'b2b_qty', 'attached_document', 'payment_type', 'exchange_rate', 'local_currency', 'remarks', 'paid_amount', 'paid_date', 'accounting_done', 'final', 'created_by'];

    protected function casts(): array
    {
        return ['acceptance_date' => 'date', 'open_date' => 'date', 'maturity_date' => 'date', 'ext_maturity_date' => 'date', 'paid_date' => 'date', 'is_pending' => 'boolean', 'accounting_done' => 'boolean', 'final' => 'boolean', 'invoiced_amount' => 'float', 'ifdbc_amount' => 'float', 'b2b_qty' => 'float', 'exchange_rate' => 'float', 'paid_amount' => 'float'];
    }
}

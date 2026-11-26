<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The early or final realisation of a bank bill. Trade & Export → Operations (AnnotechERP 22/02).
 */
class TxBillRealisation extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tx_bill_realisations';

    protected $fillable = ['company_id', 'type', 'realized_no', 'boe_id', 'realized_date', 'bank_bill_type', 'bank_name', 'export_currency', 'local_currency', 'rate_ods', 'rate_ttc', 'invoice_amount', 'invoice_amount_bdt', 'collection_amount', 'fbc_amount', 'penalty_amount', 'remarks', 'final', 'created_by'];

    protected function casts(): array
    {
        return ['realized_date' => 'date', 'final' => 'boolean', 'rate_ods' => 'float', 'rate_ttc' => 'float', 'invoice_amount' => 'float', 'invoice_amount_bdt' => 'float', 'collection_amount' => 'float', 'fbc_amount' => 'float', 'penalty_amount' => 'float'];
    }
}

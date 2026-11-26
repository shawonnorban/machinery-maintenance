<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A cash incentive claim on an export L/C or sales contract. Trade & Export → Cash Incentive (AnnotechERP 22/05).
 */
class TxCashIncentive extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tx_cash_incentives';

    protected $fillable = ['company_id', 'lc_id', 'cia_type', 'claim_part_no', 'claim_sl_no', 'application_date', 'doc_sub_date', 'claim_amount', 'claim_currency', 'certificate_no', 'audit_currency', 'audit_cert_date', 'audit_amount', 'collection_date', 'remarks', 'final', 'created_by'];

    protected function casts(): array
    {
        return ['application_date' => 'date', 'doc_sub_date' => 'date', 'audit_cert_date' => 'date', 'collection_date' => 'date', 'final' => 'boolean', 'claim_amount' => 'float', 'audit_amount' => 'float', 'claim_part_no' => 'integer'];
    }
}

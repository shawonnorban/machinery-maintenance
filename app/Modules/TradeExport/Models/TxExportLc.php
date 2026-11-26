<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An export L/C or sales contract. Trade & Export → Operations (AnnotechERP 22/02).
 */
class TxExportLc extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tx_export_lcs';

    protected $fillable = ['company_id', 'kind', 'lc_no', 'buyer_id', 'issue_date', 'expiry_date', 'place_of_expiry_id', 'currency', 'sender_branch_id', 'receiving_branch_id', 'advising_branch_id', 'same_receiver_bank', 'inco_term', 'delivery_place', 'payment_term_description', 'credit_tolerance_pct', 'port_of_loading', 'port_of_discharge', 'partial_shipment', 'trans_shipment', 'last_shipment_date', 'notify_party', 'other_info', 'is_advance', 'applicant_id', 'lc_amount', 'payment_term', 'payment_days', 'bb_limit_pct', 'shipment_mode', 'lc_value', 'po_value', 'ignore_difference', 'lc_charge', 'prc_no', 'prc_date', 'prc_entry_date', 'prc_file_id', 'prc_file_name', 'merge_state', 'amendment_no', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'expiry_date' => 'date', 'last_shipment_date' => 'date', 'prc_date' => 'date', 'prc_entry_date' => 'date', 'same_receiver_bank' => 'boolean', 'partial_shipment' => 'boolean', 'trans_shipment' => 'boolean', 'other_info' => 'boolean', 'is_advance' => 'boolean', 'ignore_difference' => 'boolean', 'lc_amount' => 'float', 'lc_value' => 'float', 'po_value' => 'float', 'lc_charge' => 'float', 'credit_tolerance_pct' => 'float', 'bb_limit_pct' => 'float', 'payment_days' => 'integer', 'amendment_no' => 'integer'];
    }
}

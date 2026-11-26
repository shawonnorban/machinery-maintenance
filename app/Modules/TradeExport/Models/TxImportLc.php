<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A back-to-back L/C opened on an export L/C. Trade & Export → Import (AnnotechERP 22/03).
 */
class TxImportLc extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tx_import_lcs';

    protected $fillable = ['company_id', 'ref_no', 'lc_type', 'is_cash', 'commodity_type', 'supplier_id', 'buyer_id', 'export_lc_id', 'sender_branch_id', 'receiving_branch_id', 'lean_branch_id', 'same_receiver_bank', 'issue_date', 'expire_date', 'ref_date', 'currency', 'inco_term', 'delivery_place', 'payment_term_description', 'lcaf_no', 'insurance', 'credit_tolerance_pct', 'partial_shipment', 'trans_shipment', 'btma_cert_no', 'btma_cert_date', 'port_of_loading', 'port_of_discharge', 'last_shipment_date', 'time_of_shipment', 'country_of_origin_id', 'bank_ref_no', 'lc_bank_code', 'lc_year', 'lc_code', 'lc_sl_no', 'place_of_expiry_id', 'place_of_issue_id', 'lc_value', 'payment_term', 'tenor_days', 'irc_no', 'shipment_mode', 'freight_cost', 'lc_status', 'bin_no', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'expire_date' => 'date', 'ref_date' => 'date', 'btma_cert_date' => 'date', 'last_shipment_date' => 'date', 'is_cash' => 'boolean', 'same_receiver_bank' => 'boolean', 'partial_shipment' => 'boolean', 'trans_shipment' => 'boolean', 'credit_tolerance_pct' => 'float', 'lc_value' => 'float', 'freight_cost' => 'float', 'tenor_days' => 'integer'];
    }
}

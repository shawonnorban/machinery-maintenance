<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An advance proforma invoice on a buyer. Trade & Export → Operations (AnnotechERP 22/02).
 */
class TxExportPi extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tx_export_pis';

    protected $fillable = ['company_id', 'pi_no', 'buyer_id', 'buyer_attention', 'pi_date', 'pi_amount', 'currency', 'commodity_type', 'inco_term', 'delivery_place', 'payment_term', 'expected_delivery_date', 'bank_branch_id', 'origin_country_id', 'packing', 'shipment_mode', 'transport_mode', 'partial_shipment', 'transhipment', 'last_shipment_date', 'shipment_schedule', 'validity_days', 'place_of_signature', 'tolerance_pct', 'tolerance_description', 'payment_way', 'delivery_terms', 'port_of_loading', 'port_of_discharge', 'insurance', 'shipment_terms', 'remarks', 'total_amount', 'advance_pct', 'advance_amount', 'discount_type', 'discount_value', 'discount_amount', 'net_amount', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['pi_date' => 'date', 'expected_delivery_date' => 'date', 'last_shipment_date' => 'date', 'partial_shipment' => 'boolean', 'transhipment' => 'boolean', 'pi_amount' => 'float', 'total_amount' => 'float', 'advance_pct' => 'float', 'advance_amount' => 'float', 'discount_value' => 'float', 'discount_amount' => 'float', 'net_amount' => 'float', 'tolerance_pct' => 'float'];
    }
}

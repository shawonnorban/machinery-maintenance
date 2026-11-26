<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The shipping invoice on an export L/C. Trade & Export → Operations (AnnotechERP 22/02).
 */
class TxCommercialInvoice extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tx_commercial_invoices';

    protected $fillable = ['company_id', 'lc_id', 'invoice_prefix', 'auto_no', 'invoice_no', 'invoice_date', 'currency', 'loc_exchange_rate', 'exp_no', 'exp_part_no', 'exp_date', 'forwarder', 'ex_factory_date', 'on_board_date', 'bl_no', 'bl_date', 'handover_date', 'payment_term', 'payment_days', 'port_of_loading', 'port_of_discharge', 'delivery_country_id', 'target_collection_date', 'shipment_mode', 'applicant_id', 'invoiced_amount', 'penalty_amount', 'agency_commission', 'sales_discount', 'net_amount', 'freight_type', 'freight_charges', 'adjust_against_advance', 'is_negotiated', 'export_percentage', 'vessel_no', 'exp_reg_no', 'exp_reg_date', 'marks_and_nos', 'pre_carriage_by', 'container_size', 'container_no', 'exp_inv_rex', 'remarks', 'final', 'created_by'];

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'exp_date' => 'date', 'ex_factory_date' => 'date', 'on_board_date' => 'date', 'bl_date' => 'date', 'handover_date' => 'date', 'target_collection_date' => 'date', 'exp_reg_date' => 'date', 'adjust_against_advance' => 'boolean', 'is_negotiated' => 'boolean', 'final' => 'boolean', 'invoiced_amount' => 'float', 'penalty_amount' => 'float', 'agency_commission' => 'float', 'sales_discount' => 'float', 'net_amount' => 'float', 'freight_charges' => 'float', 'loc_exchange_rate' => 'float', 'export_percentage' => 'float', 'auto_no' => 'integer', 'exp_part_no' => 'integer', 'payment_days' => 'integer'];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A customer sales order (SO-…): the master data, terms and shipment, its item lines, sizes and particulars; drafted, then submitted, checked, approved and confirmed, closed at the end. Sales & Shipment → Order & Delivery (AnnotechERP 21/02).
 */
class SsSalesOrder extends BaseModel
{
    use BelongsToTenant;

    public const SO_TYPES = ['GENERAL', 'INTER_COMPANY', 'EMBROIDERY', 'SUB_CONTRACT'];

    public const PAY_METHODS = ['CASH', 'LC', 'CHEQUE', 'TT', 'CONSIGNMENT'];

    public const PAY_TERMS = ['At Sight', 'Cash on Delivery', 'Advance', 'Credit 30 days', 'Credit 60 days', 'Credit 90 days'];

    public const SHIP_MODES = ['By Van', 'By Truck', 'By Courier', 'By Air', 'By Sea', 'By Hand'];

    public const INCOTERMS = ['FOB', 'CFR', 'CIF', 'EXW', 'FCA', 'CPT', 'CIP', 'DAP', 'DDP'];

    /** The approval chain, in order. */
    public const APPROVALS = ['DRAFT', 'SUBMITTED', 'CHECKED', 'APPROVED', 'CONFIRMED'];

    protected $table = 'ss_sales_orders';

    protected $fillable = ['company_id', 'so_no', 'search_for', 'is_inter_company', 'is_embroidery', 'so_type', 'customer_id', 'receive_date', 'program_by', 'program_to', 'bill_to_id', 'delivery_target_date', 'delivery_to_id', 'same_as_bill_to', 'lead_time_days', 'delivery_address', 'payment_method', 'currency_id', 'conv_rate', 'payment_term', 'mode_of_shipment', 'po_wo_no', 'others_term', 'pr_po_ref', 'remarks', 'additional_qty_pct', 'season', 'department', 'incoterm', 'destination_country_id', 'destination_port', 'shipment_date', 'item_id', 'is_poly_item', 'approval_status', 'state', 'version', 'submitted_at', 'submitted_by', 'checked_at', 'checked_by', 'approved_at', 'approved_by', 'confirmed_at', 'confirmed_by', 'closed_at', 'closed_by', 'created_by'];

    protected function casts(): array
    {
        return ['receive_date' => 'date', 'delivery_target_date' => 'date', 'shipment_date' => 'date', 'is_inter_company' => 'boolean', 'is_embroidery' => 'boolean', 'same_as_bill_to' => 'boolean', 'is_poly_item' => 'boolean', 'conv_rate' => 'float', 'additional_qty_pct' => 'float', 'lead_time_days' => 'integer', 'version' => 'integer', 'submitted_at' => 'datetime', 'checked_at' => 'datetime', 'approved_at' => 'datetime', 'confirmed_at' => 'datetime', 'closed_at' => 'datetime'];
    }
}

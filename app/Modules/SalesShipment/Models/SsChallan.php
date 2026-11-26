<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A delivery challan (DC-…, gate pass GP-…): goods leaving the gate against a confirmed sales order; submitted to the gate, then delivered — or cancelled. Sales & Shipment → Order & Delivery (AnnotechERP 21/02).
 */
class SsChallan extends BaseModel
{
    use BelongsToTenant;

    public const STATES = ['DRAFT', 'SUBMITTED', 'DELIVERED', 'CANCELLED'];

    /** The states whose quantity has left the gate. */
    public const OUT = ['SUBMITTED', 'DELIVERED'];

    protected $table = 'ss_challans';

    protected $fillable = ['company_id', 'chalan_no', 'gate_pass_no', 'order_id', 'customer_id', 'chalan_date', 'store_id', 'delivery_target_date', 'mode_of_shipment', 'currency_id', 'conv_rate', 'carrier_name', 'carrier_no', 'pr_po_ref', 'remarks', 'is_revised', 'state', 'submitted_at', 'submitted_by', 'delivered_at', 'delivered_by', 'cancelled_at', 'cancelled_by', 'created_by'];

    protected function casts(): array
    {
        return ['chalan_date' => 'date', 'delivery_target_date' => 'date', 'conv_rate' => 'float', 'is_revised' => 'boolean', 'submitted_at' => 'datetime', 'delivered_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}

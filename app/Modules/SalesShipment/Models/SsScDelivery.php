<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A sub-contract delivery chalan (SCD-…, gate pass SGP-…): goods sent out to the party against an S/C programme's lines; delivered, finalized (locked) or cancelled, closed once reconciled. Sales & Shipment → Sub-Contract (AnnotechERP 21/03).
 */
class SsScDelivery extends BaseModel
{
    use BelongsToTenant;

    public const STATES = ['DRAFT', 'DELIVERED', 'CANCELLED'];

    protected $table = 'ss_sc_deliveries';

    protected $fillable = ['company_id', 'chalan_no', 'gate_pass_no', 'programme_id', 'customer_id', 'transfer_from_id', 'chalan_date', 'store_id', 'delivery_target_date', 'mode_of_shipment', 'carrier_name', 'carrier_no', 'pr_po_ref', 'remarks', 'is_revised', 'state', 'finalized', 'closed', 'delivered_at', 'delivered_by', 'finalized_at', 'finalized_by', 'cancelled_at', 'cancelled_by', 'created_by'];

    protected function casts(): array
    {
        return ['chalan_date' => 'date', 'delivery_target_date' => 'date', 'is_revised' => 'boolean', 'finalized' => 'boolean', 'closed' => 'boolean', 'delivered_at' => 'datetime', 'finalized_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}

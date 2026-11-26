<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A shipment (SHP-…): one shipment of a confirmed sales order — planned with its dates, mode, destination and lines, then booked, documented, readied, confirmed (shipped) and closed by the shipment screens, or cancelled. Sales & Shipment → General → Shipment Planning (AnnotechERP 21/04).
 */
class SsShipment extends BaseModel
{
    use BelongsToTenant;

    public const STAGES = ['PLANNING', 'BOOKED', 'DOCUMENTATION', 'READY', 'CONFIRMED', 'CLOSED', 'CANCELLED'];

    /** The stages a shipment is still open in (its plan may be cancelled). */
    public const OPEN = ['PLANNING', 'BOOKED', 'DOCUMENTATION', 'READY'];

    public const MODES = ['Sea', 'Air', 'Road', 'Sea-Air', 'Courier'];

    protected $table = 'ss_shipments';

    protected $fillable = ['company_id', 'shipment_no', 'order_id', 'plan_date', 'shipment_date', 'ex_factory_date', 'shipment_mode', 'destination_country_id', 'destination_port', 'port_of_loading', 'incoterm',
        'remarks', 'stage', 'cancelled_at', 'cancelled_by', 'confirmed_at', 'confirmed_by', 'closed_at', 'closed_by', 'created_by'];

    protected function casts(): array
    {
        return ['plan_date' => 'date', 'shipment_date' => 'date', 'ex_factory_date' => 'date', 'cancelled_at' => 'datetime', 'confirmed_at' => 'datetime', 'closed_at' => 'datetime'];
    }
}

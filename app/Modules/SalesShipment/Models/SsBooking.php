<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A shipment booking (BKG-…) with the forwarder: line, vessel and voyage, ETD / ETA, ports, container type and count; requested, confirmed, completed or cancelled. Sales & Shipment → Shipment (AnnotechERP 21/05).
 */
class SsBooking extends BaseModel
{
    use BelongsToTenant;

    public const CONTAINER_TYPES = ["20' GP", "40' GP", "40' HC", "45' HC", "20' RF", "40' RF", 'LCL', 'Air Pallet'];

    public const STATUSES = ['REQUESTED', 'CONFIRMED', 'COMPLETED', 'CANCELLED'];

    protected $table = 'ss_bookings';

    protected $fillable = ['company_id', 'booking_no', 'shipment_id', 'booking_date', 'forwarder', 'shipping_line', 'vessel_name', 'voyage_no', 'etd', 'eta', 'port_of_loading', 'port_of_discharge', 'final_destination', 'container_type', 'container_qty', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['booking_date' => 'date', 'etd' => 'date', 'eta' => 'date', 'container_qty' => 'integer'];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A container on a shipment: number, seal, weights, CBM, cartons, vessel and dates. Sales & Shipment → Shipment (AnnotechERP 21/05).
 */
class SsContainer extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_containers';

    protected $fillable = ['company_id', 'shipment_id', 'booking_id', 'container_no', 'container_type', 'seal_no', 'gross_weight', 'net_weight', 'cbm', 'carton_qty', 'vessel_name', 'voyage_no', 'loading_date', 'etd', 'eta', 'remarks'];

    protected function casts(): array
    {
        return ['gross_weight' => 'float', 'net_weight' => 'float', 'cbm' => 'float', 'carton_qty' => 'integer', 'loading_date' => 'date', 'etd' => 'date', 'eta' => 'date'];
    }
}

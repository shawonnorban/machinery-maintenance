<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A shipment line: an order size, the planned quantity, cartons and rate, and what was shipped once confirmed. Sales & Shipment → General → Shipment Planning (AnnotechERP 21/04).
 */
class SsShipmentLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_shipment_lines';

    protected $fillable = ['company_id', 'shipment_id', 'size_id', 'qty', 'carton_qty', 'rate', 'shipped_qty', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'carton_qty' => 'integer', 'rate' => 'float', 'shipped_qty' => 'float', 'sort_order' => 'integer'];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A carton run: the shipment line, colour, size, carton range, pieces per carton, weights and volume. Sales & Shipment → Shipment (AnnotechERP 21/05).
 */
class SsPackingLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_packing_lines';

    protected $fillable = ['company_id', 'packing_list_id', 'shipment_line_id', 'color', 'size', 'carton_from', 'carton_to', 'pcs_per_carton', 'net_weight', 'gross_weight', 'cbm', 'sort_order'];

    protected function casts(): array
    {
        return ['carton_from' => 'integer', 'carton_to' => 'integer', 'pcs_per_carton' => 'float', 'net_weight' => 'float', 'gross_weight' => 'float', 'cbm' => 'float', 'sort_order' => 'integer'];
    }
}

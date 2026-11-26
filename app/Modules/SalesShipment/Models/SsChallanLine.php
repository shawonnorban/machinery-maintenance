<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A challan line: an order size going out, its quantity and rate. Sales & Shipment → Order & Delivery (AnnotechERP 21/02).
 */
class SsChallanLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_challan_lines';

    protected $fillable = ['company_id', 'challan_id', 'size_id', 'qty', 'rate', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rate' => 'float', 'sort_order' => 'integer'];
    }
}

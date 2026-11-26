<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A line going out on a sub-contract chalan: the programme line, quantity and rate. Sales & Shipment → Sub-Contract (AnnotechERP 21/03).
 */
class SsScDeliveryLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_sc_delivery_lines';

    protected $fillable = ['company_id', 'delivery_id', 'programme_line_id', 'qty', 'rate', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rate' => 'float', 'sort_order' => 'integer'];
    }
}

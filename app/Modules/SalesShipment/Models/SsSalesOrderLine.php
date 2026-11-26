<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An item line of a sales order: the buyer / order / style references, section, item, colours, rate, unit and weights. Sales & Shipment → Order & Delivery (AnnotechERP 21/02).
 */
class SsSalesOrderLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_sales_order_lines';

    protected $fillable = ['company_id', 'order_id', 'sl_no', 'buyer', 'order_ref', 'style_ref', 'wo_ref', 'section_id', 'item_id', 'gmt_color', 'product_color', 'rate', 'uom', 'specification', 'code', 'gross_weight', 'net_weight'];

    protected function casts(): array
    {
        return ['sl_no' => 'integer', 'rate' => 'float', 'gross_weight' => 'float', 'net_weight' => 'float'];
    }
}

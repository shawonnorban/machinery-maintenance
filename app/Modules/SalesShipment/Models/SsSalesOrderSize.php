<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A size band under an order line: its quantity and rate — what challans, amendments and S/C programmes are counted against. Sales & Shipment → Order & Delivery (AnnotechERP 21/02).
 */
class SsSalesOrderSize extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_sales_order_sizes';

    protected $fillable = ['company_id', 'order_id', 'line_id', 'size', 'pcs_per_size', 'qty', 'rate', 'sort_order'];

    protected function casts(): array
    {
        return ['pcs_per_size' => 'float', 'qty' => 'float', 'rate' => 'float', 'sort_order' => 'integer'];
    }
}

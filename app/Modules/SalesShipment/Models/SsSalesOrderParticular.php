<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A named attribute carried on an order line. Sales & Shipment → Order & Delivery (AnnotechERP 21/02).
 */
class SsSalesOrderParticular extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_sales_order_particulars';

    protected $fillable = ['company_id', 'order_id', 'line_id', 'particular_id', 'value'];
}

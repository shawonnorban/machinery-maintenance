<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** An order in the order book (Style & Order → Order Register, AnnotechERP 06/07). */
class Order extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['BULK_NEW', 'DEVELOPMENT', 'REPEAT', 'PROJECTION', 'BUFFER', 'SPECIAL_SAMPLE'];

    public const UNITS = ['PCS', 'SET', 'DZN'];

    public const STATUSES = ['DRAFT', 'CONFIRMED', 'IN_PRODUCTION', 'SHIPPED', 'CLOSED', 'CANCELLED'];

    protected $table = 'merch_orders';

    protected $fillable = [
        'company_id', 'order_no', 'job_no', 'style_id', 'style_no', 'work_style_no', 'buyer_id', 'buyer_office_id', 'factory_id', 'order_type',
        'item', 'gmt_type', 'order_date', 'conf_date', 'ship_date', 'ship_country_id', 'total_qty', 'unit', 'unit_price', 'currency', 'status', 'created_by',
        'po_no', 'sub_style', 'inquiry_id', 'sales_contract_no', 'projection_only', 'is_multi', 'pcs_per_unit', 'exchange_rate', 'tna_template_id', 'projection_id',
    ];

    protected function casts(): array
    {
        return ['order_date' => 'date', 'conf_date' => 'date', 'ship_date' => 'date', 'total_qty' => 'integer', 'unit_price' => 'float', 'projection_only' => 'boolean', 'is_multi' => 'boolean', 'pcs_per_unit' => 'integer', 'exchange_rate' => 'float'];
    }
}

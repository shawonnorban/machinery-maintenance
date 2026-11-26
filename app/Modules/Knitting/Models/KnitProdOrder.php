<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A fabric production order: one order's fabric and colour, and what the floor owes against it.
 */
class KnitProdOrder extends BaseModel
{
    use BelongsToTenant;

    /** Bulk, Yarn-Dyed, Sample and Flat Bed Rib — one screen each, one register between them. */
    public const KINDS = ['BULK', 'YD', 'SAMPLE', 'FLAT_BED_RIB'];

    public const STATUSES = ['RUNNING', 'COMPLETED', 'HOLD', 'CLOSED', 'CANCELLED'];

    protected $table = 'knit_prod_orders';

    protected $fillable = ['company_id', 'po_no', 'order_kind', 'order_id', 'buyer_id', 'category_code', 'ship_month', 'fabric_type_id', 'fabric_desc', 'colour_id', 'colour_name', 'finish_dia', 'gsm', 'item_image', 'booking_ref', 'req_qty', 'adjusted_qty', 'saving_target_qty', 'yarn_stock_qty', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['gsm' => 'float', 'req_qty' => 'float', 'adjusted_qty' => 'float', 'saving_target_qty' => 'float', 'yarn_stock_qty' => 'float'];
    }
}

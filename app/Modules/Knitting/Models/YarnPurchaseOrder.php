<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A yarn purchase order placed on one spinner. Knit Production → Yarn Procurement (AnnotechERP 15/02).
 */
class YarnPurchaseOrder extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'PLACED', 'PART_RECEIVED', 'RECEIVED', 'CLOSED', 'CANCELLED'];

    /** Placed, or partly received: still owed by the spinner (the pipeline a comparison reads). */
    public const OPEN = ['PLACED', 'PART_RECEIVED'];

    protected $table = 'knit_yarn_pos';

    protected $fillable = ['company_id', 'po_no', 'po_date', 'supplier_id', 'store_id', 'comparison_id', 'requisition_id', 'buyer_id', 'order_id', 'source', 'method', 'inco_term', 'payment_term', 'ship_mode', 'currency_id', 'conv_rate', 'delivery_date', 'shipment_date', 'remarks', 'status', 'placed_at', 'created_by'];

    protected function casts(): array
    {
        return ['po_date' => 'date', 'delivery_date' => 'date', 'shipment_date' => 'date', 'conv_rate' => 'float', 'placed_at' => 'datetime'];
    }
}

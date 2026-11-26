<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Garments sent out for wash against a style, numbered on save and moved a step at a time. Work Study → Floor Operations (AnnotechERP 14/07).
 */
class WsWashRequisition extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_wash_requisitions';

    protected $fillable = [
        'company_id', 'requisition_no', 'requisition_date', 'requested_by', 'attn_to', 'floor_id', 'planning_line_id', 'buyer_id', 'style_no', 'order_no',
        'shipment_date', 'order_qty', 'wash_qty', 'df_process', 'gmt_category', 'hourly_production', 'daily_production', 'start_date', 'close_date', 'remarks', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'requisition_date' => 'date', 'shipment_date' => 'date', 'start_date' => 'date', 'close_date' => 'date',
            'order_qty' => 'integer', 'wash_qty' => 'integer', 'hourly_production' => 'integer', 'daily_production' => 'integer',
        ];
    }
}

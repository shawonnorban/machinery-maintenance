<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One batch lot on a store delivery note: everything the store will rack it by — description, shade, GSM, dia — and what went in against what is handed over. Finishing Line → Fabric Store (AnnotechERP 18/05).
 */
class FinStoreDeliveryLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fin_store_delivery_lines';

    protected $fillable = ['company_id', 'delivery_id', 'receipt_id', 'order_id', 'fabric_type', 'colour', 'fabric_details', 'item_code', 'lot_no', 'tag_batch_no', 'shade_no', 'dia_type', 'fin_gsm', 'fin_dia', 'grey_qty', 'finish_qty', 'finish_m', 'rolls', 'rate', 'aop', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['fin_gsm' => 'decimal:2', 'fin_dia' => 'decimal:2', 'grey_qty' => 'decimal:3', 'finish_qty' => 'decimal:3', 'rolls' => 'integer', 'rate' => 'decimal:2', 'aop' => 'boolean', 'sort_order' => 'integer'];
    }
}

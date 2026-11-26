<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A lot in the finished fabric store, made when a delivery line is received; its balance is the sum of its moves. Finishing Line → Fabric Store (AnnotechERP 18/05).
 */
class FinStoreLot extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fin_store_lots';

    protected $fillable = ['company_id', 'lot_code', 'store_id', 'delivery_line_id', 'receipt_id', 'order_id', 'buyer_id', 'style', 'colour', 'fabric_type', 'fabric_details', 'lot_no', 'shade_no', 'dia_type', 'fin_gsm', 'fin_dia', 'received_kg', 'received_m', 'rolls', 'rate', 'received_on'];

    protected function casts(): array
    {
        return ['fin_gsm' => 'decimal:2', 'fin_dia' => 'decimal:2', 'received_kg' => 'decimal:3', 'rolls' => 'integer', 'rate' => 'decimal:2', 'received_on' => 'date'];
    }
}

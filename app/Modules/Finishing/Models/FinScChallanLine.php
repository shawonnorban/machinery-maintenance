<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One shade on a sub-contract challan: the batch (if it is a finishing card), its order and colour, the process asked for, lot, dia, kilos, rolls and the rate. Finishing Line → S/C Process (AnnotechERP 18/03).
 */
class FinScChallanLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fin_sc_challan_lines';

    protected $fillable = ['company_id', 'challan_id', 'receipt_id', 'buyer_id', 'order_id', 'style', 'colour', 'process_id', 'description', 'lot_no', 'dia_type', 'qty_kg', 'rolls', 'rate', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['qty_kg' => 'decimal:3', 'rolls' => 'integer', 'rate' => 'decimal:2', 'sort_order' => 'integer'];
    }
}

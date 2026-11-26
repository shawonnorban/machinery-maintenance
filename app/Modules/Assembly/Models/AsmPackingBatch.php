<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A packing batch — one PO and colour packed together, from Packing In through the seven processes to Packing Out. Assembly Floor → Packing (AnnotechERP 20/04).
 */
class AsmPackingBatch extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['PACKING_IN', 'IN_PROGRESS', 'PACKED_OUT', 'CANCELLED'];

    protected $table = 'asm_packing_batches';

    protected $fillable = ['company_id', 'packing_no', 'order_id', 'po_no', 'buyer_id', 'style', 'colour', 'section_id', 'floor_id', 'line_id', 'status', 'assort_ratio', 'destination', 'country', 'shipping_mark', 'special_instruction', 'marking_print_count', 'final_packed_qty', 'out_date', 'packed_out_at', 'packed_out_by', 'created_by'];

    protected function casts(): array
    {
        return ['marking_print_count' => 'integer', 'final_packed_qty' => 'integer', 'out_date' => 'date', 'packed_out_at' => 'datetime'];
    }
}

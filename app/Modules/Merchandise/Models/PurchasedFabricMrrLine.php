<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One batch of a fabric receipt: received, rejected and accepted against its PO line, and its share of the landed cost (Trims & Accessories, AnnotechERP 06/06). */
class PurchasedFabricMrrLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_pfab_mrr_lines';

    protected $fillable = [
        'company_id', 'mrr_id', 'po_line_id', 'mp_requisition_line_id', 'fabric_type_id', 'description', 'composition_id', 'colour_id', 'gsm', 'dia', 'batch_no', 'rolls', 'uom', 'receive_qty',
        'reject_qty', 'accept_qty', 'rate', 'amount', 'landed_amount', 'reject_reason', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['rolls' => 'integer', 'receive_qty' => 'float', 'reject_qty' => 'float', 'accept_qty' => 'float', 'rate' => 'float', 'amount' => 'float', 'landed_amount' => 'float', 'sort_order' => 'integer'];
    }
}

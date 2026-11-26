<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One received item of an accessories receipt, the PO line it receives and its share of the landed cost (Trims & Accessories, AnnotechERP 06/06). */
class AccReceiptLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_acc_mrr_lines';

    protected $fillable = ['company_id', 'mrr_id', 'item_id', 'po_line_id', 'mp_requisition_line_id', 'description', 'batch', 'uom', 'qty', 'rate', 'amount', 'landed_amount', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rate' => 'float', 'amount' => 'float', 'landed_amount' => 'float', 'sort_order' => 'integer'];
    }
}

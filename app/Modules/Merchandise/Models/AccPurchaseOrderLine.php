<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One priced item of an accessories purchase order, and the requisition line it orders (Trims & Accessories, AnnotechERP 06/06). */
class AccPurchaseOrderLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_acc_po_lines';

    protected $fillable = [
        'company_id', 'po_id', 'item_id', 'requisition_id', 'requisition_line_id', 'specification', 'uom', 'qty', 'unit_price', 'note', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['qty' => 'float', 'unit_price' => 'float', 'sort_order' => 'integer'];
    }
}

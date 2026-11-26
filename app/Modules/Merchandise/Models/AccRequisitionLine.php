<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One item of an accessories purchase indent: required, in stock and short (Trims & Accessories, AnnotechERP 06/06). */
class AccRequisitionLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_acc_requisition_lines';

    protected $fillable = [
        'company_id', 'requisition_id', 'bom_line_id', 'item_id', 'item_code', 'description', 'uom', 'required_qty', 'stock_qty', 'short_qty',
        'required_date', 'supplier_id', 'remarks', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['required_qty' => 'float', 'stock_qty' => 'float', 'short_qty' => 'float', 'required_date' => 'date', 'sort_order' => 'integer'];
    }
}

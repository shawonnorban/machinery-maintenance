<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One item on a store document: the quantity entered, what reached the books, and its price. General Store → Inventory (AnnotechERP 10/03).
 */
class GsDocumentLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'gs_document_lines';

    protected $fillable = ['company_id', 'gs_document_id', 'gs_item_id', 'quantity', 'movement_qty', 'unit_price', 'suggested_price', 'line_remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'movement_qty' => 'decimal:4', 'unit_price' => 'decimal:4', 'suggested_price' => 'decimal:4'];
    }
}

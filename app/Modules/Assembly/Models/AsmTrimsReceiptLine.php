<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An item on a trims receipt. Assembly Floor → Accessories Store (AnnotechERP 20/08).
 */
class AsmTrimsReceiptLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'asm_trims_receipt_lines';

    protected $fillable = ['company_id', 'receipt_id', 'item_id', 'description', 'uom', 'qty', 'rate', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:4', 'rate' => 'decimal:4', 'sort_order' => 'integer'];
    }
}

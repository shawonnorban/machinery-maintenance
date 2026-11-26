<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One fabric of a purchased fabric PO, ordering one approved indent line (Trims & Accessories, AnnotechERP 06/06). */
class PurchasedFabricPoLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_pfab_po_lines';

    protected $fillable = [
        'company_id', 'po_id', 'indent_id', 'indent_line_id', 'fabric_type_id', 'description', 'construction', 'composition_id', 'colour_id', 'gsm', 'dia', 'uom',
        'qty', 'rate', 'amount', 'remarks', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rate' => 'float', 'amount' => 'float', 'sort_order' => 'integer'];
    }
}

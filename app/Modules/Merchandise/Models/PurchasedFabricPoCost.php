<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One other cost agreed on a purchased fabric PO, with its head's VAT flag as it stood (Trims & Accessories, AnnotechERP 06/06). */
class PurchasedFabricPoCost extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_pfab_po_costs';

    protected $fillable = ['company_id', 'po_id', 'cost_head_id', 'vat_applicable', 'amount', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['vat_applicable' => 'boolean', 'amount' => 'float', 'sort_order' => 'integer'];
    }
}

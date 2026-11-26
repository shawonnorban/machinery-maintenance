<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One other cost a fabric delivery carried, with its head's landed and VAT flags as they stood (Trims & Accessories, AnnotechERP 06/06). */
class PurchasedFabricMrrCost extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_pfab_mrr_costs';

    protected $fillable = ['company_id', 'mrr_id', 'cost_head_id', 'landed', 'vat_applicable', 'amount', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['landed' => 'boolean', 'vat_applicable' => 'boolean', 'amount' => 'float', 'sort_order' => 'integer'];
    }
}

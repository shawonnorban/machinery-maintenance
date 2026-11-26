<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A procurement cost head — landed into stock value or not, inside the VAT base or not (Trims & Accessories, AnnotechERP 06/06). */
class ProcCostHead extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_proc_cost_heads';

    protected $fillable = ['company_id', 'code', 'name', 'landed', 'vat_applicable', 'expense_ledger_id', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['landed' => 'boolean', 'vat_applicable' => 'boolean', 'sort_order' => 'integer', 'active' => 'boolean'];
    }
}

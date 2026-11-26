<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One item a comparative statement prices, and the supplier chosen for it. Dye House → Chemical Store (AnnotechERP 17/06).
 */
class DyeChemCsLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_chem_cs_lines';

    protected $fillable = ['company_id', 'cs_id', 'item_id', 'qty', 'uom', 'chosen_supplier_id', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'float'];
    }
}

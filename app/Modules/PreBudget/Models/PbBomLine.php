<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An accessory line of a BOM style: what was typed, by the key of its column
 * on the BOM worksheet (Acc Budget Column Setup) — item, item code, supplier,
 * nominated, item order qty, extra %, rate, unit, body / item consumption,
 * remarks and the company's own columns. Formulas are worked out (BomSheet).
 */
class PbBomLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'pb_bom_lines';

    protected $fillable = ['company_id', 'bom_style_id', 'sort_order', 'values'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'values' => 'array'];
    }
}

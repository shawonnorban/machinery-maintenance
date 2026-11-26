<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A bill-of-material line of a budget sheet: what, in which unit, how much per piece, at what rate (AnnotechERP 06/09). */
class BudgetLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_budget_lines';

    /** Where a line was loaded from; a line typed in has none. */
    public const SOURCES = ['BOM', 'BOOKING'];

    /** The groups whose lines name an item of the trims master and its supplier. */
    public const ACCESSORY_SECTIONS = ['SEWING_ACC', 'FINISHING_ACC', 'GENERAL_ACC', 'SPECIAL_ACC'];

    protected $fillable = ['company_id', 'budget_id', 'section', 'item_id', 'item_code', 'description', 'uom', 'cons_dzn', 'wastage_pct', 'qty', 'rate', 'supplier_id', 'source', 'sort_order'];

    protected function casts(): array
    {
        return ['cons_dzn' => 'float', 'wastage_pct' => 'float', 'qty' => 'float', 'rate' => 'float', 'sort_order' => 'integer'];
    }

    /** A piece's quantity from consumption per dozen and wastage: Cons/Dzn ÷ 12 × (1 + Wastage %). */
    public static function qtyOf(float $consDzn, ?float $wastagePct): float
    {
        return round($consDzn / 12 * (1 + (float) $wastagePct / 100), 4);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Production\Models;

use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\Item;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One material a style consumes — the bill of materials.
 *
 * `consumption_per_unit` is per garment, in the item's own unit: 1.85 metres
 * of fabric, 6 buttons, 0.4 metres of elastic. `wastage_percent` is the
 * allowance the factory plans for on top, which the Material Requirement
 * formula adds back (Procurement spec §4) — it is planned wastage, not the
 * variance measured after cutting, which is a different number and lives on
 * the issue.
 *
 * Like every other document line that names an item, this one snapshots the
 * item's code and name (Master Data spec §2.10) so a BOM written last season
 * still reads the way it did.
 */
class StyleBomLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'production_style_bom_lines';

    protected $fillable = [
        'company_id', 'style_id', 'item_id', 'item_code', 'item_name',
        'uom_id', 'consumption_per_unit', 'wastage_percent', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'consumption_per_unit' => MoneyCast::class,
            'wastage_percent' => 'decimal:2',
        ];
    }

    public function style(): BelongsTo
    {
        return $this->belongsTo(Style::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * What this line needs for a given number of garments, wastage included.
     *
     * The wastage allowance is part of the requirement, not a separate line:
     * a factory that orders only the net consumption runs short on the first
     * roll that cuts badly.
     */
    public function requiredFor(string $quantity): string
    {
        $net = bcmul((string) $this->consumption_per_unit, $quantity, 4);
        $allowance = bcdiv(bcmul($net, (string) $this->wastage_percent, 4), '100', 4);

        return bcadd($net, $allowance, 4);
    }
}

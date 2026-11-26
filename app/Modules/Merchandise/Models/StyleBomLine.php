<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A material on a BOM version: how much a garment takes, in what unit, bought how and at what rate. */
class StyleBomLine extends BaseModel
{
    use BelongsToTenant;

    public const CONS_TYPES = ['COLOUR', 'SIZE', 'COLOUR_SIZE', 'STYLE'];

    public const UNITS = ['PCS', 'MTR', 'CONE', 'KG', 'YDS', 'DZN'];

    public const CATEGORIES = ['MAIN_FABRIC', 'SEWING_ACCESSORIES', 'TRIMS', 'LABELS', 'PACKAGING', 'ACCESSORIES', 'OTHERS'];

    protected $table = 'merch_style_bom_lines';

    protected $fillable = [
        'company_id', 'version_id', 'item_name', 'cons_type', 'consumption', 'cons_unit', 'per_gmt', 'purchase_unit', 'conversion',
        'apply_addl', 'rate', 'remarks', 'category', 'item_code', 'allow_pct', 'supplier', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'consumption' => 'float', 'per_gmt' => 'float', 'conversion' => 'float', 'rate' => 'float', 'allow_pct' => 'float',
            'apply_addl' => 'boolean', 'sort_order' => 'integer',
        ];
    }
}

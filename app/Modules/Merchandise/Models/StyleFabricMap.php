<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A garment part of an item cut from one of the style's fabrics (Yarn/Fabric Mapping, AnnotechERP 06/05). */
class StyleFabricMap extends BaseModel
{
    use BelongsToTenant;

    public const PROD_CATEGORIES = ['BULK', 'DEVELOP', 'SHORT_BULK', 'PROJECTION'];

    protected $table = 'merch_style_fabric_maps';

    protected $fillable = ['company_id', 'style_id', 'prod_categories', 'item_id', 'garment_part_id', 'fabric_id', 'all_colours', 'gmt_colour_id', 'fab_colour_id', 'ratio', 'sort_order'];

    protected function casts(): array
    {
        return ['prod_categories' => 'array', 'all_colours' => 'boolean', 'ratio' => 'float', 'sort_order' => 'integer'];
    }
}

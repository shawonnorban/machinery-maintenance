<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A colour a style is made in (Item/Combo/Model → Colour Mapping, AnnotechERP 06/05). */
class StyleColour extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['SOLID', 'MELANGE', 'YARN_DYED', 'AOP', 'PRINT'];

    protected $table = 'merch_style_colours';

    protected $fillable = ['company_id', 'style_id', 'colour_id', 'colour_type', 'is_gmt_colour', 'colour_group_id', 'dye_method_id', 'fiber_composition_id', 'colour_ref', 'shade_no', 'swatch', 'pantone_no', 'buyer_colour_name', 'remarks', 'image_id', 'sort_order', 'aop_type', 'yd_type', 'print_type', 'embroidery_type', 'wash_type'];

    protected function casts(): array
    {
        return ['is_gmt_colour' => 'boolean', 'swatch' => 'boolean', 'sort_order' => 'integer'];
    }
}

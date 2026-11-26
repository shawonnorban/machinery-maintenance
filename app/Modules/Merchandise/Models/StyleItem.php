<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A garment item of a style (Item/Combo/Model Registration, AnnotechERP 06/05):
 * registered on one style's desk and serving the styles it is linked to.
 */
class StyleItem extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_style_items';

    protected $fillable = [
        'company_id', 'style_id', 'set_style_id', 'model_no', 'item_group', 'item_type', 'division', 'neck_type', 'sleeve_type',
        'short_name', 'full_name', 'description', 'gender', 'age_group', 'status',
        'is_solid', 'is_yd', 'is_aop', 'is_gmt_wash', 'is_heat_seal', 'is_print', 'is_embroidery',
        'nature_of_work', 'no_of_mc', 'exp_prod_hr', 'smv_marketing', 'tech_spec', 'key_image_id', 'second_image_id', 'item_image_id',
        'certificates', 'care_labels', 'sort_order', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_solid' => 'boolean', 'is_yd' => 'boolean', 'is_aop' => 'boolean', 'is_gmt_wash' => 'boolean', 'is_heat_seal' => 'boolean', 'is_print' => 'boolean', 'is_embroidery' => 'boolean',
            'smv_marketing' => 'decimal:2', 'certificates' => 'array', 'care_labels' => 'array', 'sort_order' => 'integer',
        ];
    }
}

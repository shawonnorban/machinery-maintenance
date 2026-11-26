<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A lab dip recipe: one option tried at a buyer's shade, under the recipe
 * number of its submission (Lab Dip Recipe Entry). The colour type and colour
 * group are the shade's own. Dye House → Lab-Dip (AnnotechERP 17/02).
 */
class DyeLabDipRecipe extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_lab_dip_recipes';

    protected $fillable = ['company_id', 'recipe_no', 'option_no', 'buyer_id', 'style_id', 'colour_id', 'fabric_type_id', 'dye_method_id', 'fiber_composition', 'created_by'];

    protected function casts(): array
    {
        return ['option_no' => 'integer'];
    }
}

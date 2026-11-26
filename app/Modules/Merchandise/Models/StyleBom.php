<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A style's bill of materials, kept in versions (Item/Combo/Model → BOM, AnnotechERP 06/05). */
class StyleBom extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_style_boms';

    protected $fillable = ['company_id', 'style_id', 'bom_no'];
}

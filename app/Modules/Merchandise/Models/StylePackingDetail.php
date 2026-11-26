<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** How every pack of a style is made up and cartoned (Development Style wizard → Packing Instructions, AnnotechERP 06/07). */
class StylePackingDetail extends BaseModel
{
    use BelongsToTenant;

    public const METHODS = ['FOLD_POLY', 'FOLD_POLY_CARTON', 'HANGER_POLY', 'GOH', 'FLAT', 'ROLL'];

    public const CARTONS = ['PLY_3', 'PLY_5', 'PLY_7'];

    protected $table = 'merch_style_packing_details';

    protected $fillable = ['company_id', 'style_id', 'packing_method', 'carton_type', 'pcs_per_poly', 'pcs_per_carton', 'carton_size', 'carton_marking', 'barcode', 'hangtag', 'remarks', 'photo_id'];

    protected function casts(): array
    {
        return ['pcs_per_poly' => 'integer', 'pcs_per_carton' => 'integer', 'barcode' => 'boolean', 'hangtag' => 'boolean'];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A style's engineered variation: manpower, SMV, efficiency and the operation bulletin it was read off. Work Study → Garment Spec Entry (AnnotechERP 14/04).
 */
class WsSpec extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_specs';

    protected $fillable = [
        'company_id', 'style_no', 'variation_no', 'description', 'fabric_class', 'criticality', 'op_manpower', 'aso_manpower', 'sewing_smv', 'iron_smv', 'poly_smv',
        'avg_efficiency', 'pick_efficiency', 'balancing_efficiency', 'ob_file_path', 'is_smv_artwork', 'is_fusing', 'sample_type_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['variation_no' => 'integer', 'op_manpower' => 'integer', 'aso_manpower' => 'integer', 'sewing_smv' => 'float', 'iron_smv' => 'float', 'poly_smv' => 'float', 'avg_efficiency' => 'float', 'pick_efficiency' => 'float', 'balancing_efficiency' => 'float', 'is_smv_artwork' => 'boolean', 'is_fusing' => 'boolean'];
    }
}

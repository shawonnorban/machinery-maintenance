<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One section's defect (Cutting, Sewing and Quality each keep their own list). Quality Tally (AnnotechERP 36).
 */
class QtDefectType extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'qt_defect_types';

    protected $fillable = ['company_id', 'section', 'code', 'name', 'name_bn', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'active' => 'boolean'];
    }
}

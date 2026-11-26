<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A scheduling board batches are placed on. Fabric Requirement → Batch Schedule Board (AnnotechERP 13/02).
 */
class FpSchedulePlan extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fp_schedule_plans';

    protected $fillable = ['company_id', 'plan_no', 'name', 'party_id', 'basis', 'scale', 'cell_width', 'active'];

    protected function casts(): array
    {
        return ['cell_width' => 'float', 'active' => 'boolean'];
    }
}

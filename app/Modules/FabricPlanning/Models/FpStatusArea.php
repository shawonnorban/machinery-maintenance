<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What a constraint family measures and how much it counts; its code is fixed once made. Fabric Requirement → Planning Constraint Ruleset (AnnotechERP 13/02).
 */
class FpStatusArea extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fp_status_areas';

    protected $fillable = ['company_id', 'group_id', 'parent_id', 'sl', 'code', 'name', 'wt_factor', 'active'];

    protected function casts(): array
    {
        return ['sl' => 'integer', 'wt_factor' => 'float', 'active' => 'boolean'];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A constraint family the batch plan is scored on (Critical Date, Capacity …), fixed. Fabric Requirement → Planning Constraint Ruleset (AnnotechERP 13/02).
 */
class FpStatusGroup extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fp_status_groups';

    protected $fillable = ['company_id', 'sl', 'code', 'name'];

    protected function casts(): array
    {
        return ['sl' => 'integer'];
    }
}

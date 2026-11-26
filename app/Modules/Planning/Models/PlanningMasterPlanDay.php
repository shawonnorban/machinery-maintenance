<?php

declare(strict_types=1);

namespace App\Modules\Planning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A master plan's target on one line on one day — a line day the plan holds. Capacity Planning → Master Plan (AnnotechERP 12/02).
 */
class PlanningMasterPlanDay extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'planning_master_plan_days';

    protected $fillable = [
        'company_id', 'master_plan_id', 'planning_line_id', 'day', 'capacity', 'target',
    ];

    protected function casts(): array
    {
        return ['day' => 'date', 'capacity' => 'integer', 'target' => 'integer'];
    }
}

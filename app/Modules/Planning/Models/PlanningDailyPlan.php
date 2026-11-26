<?php

declare(strict_types=1);

namespace App\Modules\Planning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A day's resources and target on one line under a locked master plan, and the output it made. Capacity Planning → Daily Plan (AnnotechERP 12/02).
 */
class PlanningDailyPlan extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['PLANNED', 'CONFIRMED'];

    protected $table = 'planning_daily_plans';

    protected $fillable = [
        'company_id', 'plan_date', 'order_id', 'master_plan_id', 'planning_line_id', 'shift_id', 'manpower', 'machines', 'hours', 'efficiency',
        'smv', 'daily_capacity', 'master_target', 'daily_target', 'typed_output', 'status', 'remarks', 'created_by',
    ];

    protected function casts(): array
    {
        return ['plan_date' => 'date', 'manpower' => 'integer', 'machines' => 'integer', 'hours' => 'float', 'efficiency' => 'float', 'smv' => 'float', 'daily_capacity' => 'integer', 'master_target' => 'integer', 'daily_target' => 'integer', 'typed_output' => 'integer'];
    }
}

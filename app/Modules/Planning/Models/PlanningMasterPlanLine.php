<?php

declare(strict_types=1);

namespace App\Modules\Planning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A line a master plan uses: the resources it was planned on, its capacity, allocated quantity and sewing window. Capacity Planning → Master Plan (AnnotechERP 12/02).
 */
class PlanningMasterPlanLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'planning_master_plan_lines';

    protected $fillable = [
        'company_id', 'master_plan_id', 'planning_line_id', 'manpower', 'machines', 'hours', 'efficiency', 'hourly_capacity', 'daily_capacity',
        'allocated_qty', 'sewing_start', 'sewing_end', 'days', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['manpower' => 'integer', 'machines' => 'integer', 'hours' => 'float', 'efficiency' => 'float', 'hourly_capacity' => 'integer', 'daily_capacity' => 'integer', 'allocated_qty' => 'integer', 'sewing_start' => 'date', 'sewing_end' => 'date', 'days' => 'integer'];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The sample room's target for a day, a week (seven days) or a calendar
 * month (TP-2026-00001), read against the day sheets inside its period.
 */
class SampleTargetPlan extends BaseModel
{
    use BelongsToTenant;

    public const PERIODS = ['DAILY', 'WEEKLY', 'MONTHLY'];

    protected $table = 'proto_target_plans';

    protected $fillable = ['company_id', 'plan_no', 'period', 'period_start', 'period_end', 'sample_room', 'team', 'supervisor_id', 'sample_type_id', 'target_qty', 'available_manpower', 'available_hours', 'planned_hours', 'remarks'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'target_qty' => 'integer', 'available_manpower' => 'integer', 'available_hours' => 'decimal:2', 'planned_hours' => 'decimal:2'];
    }
}

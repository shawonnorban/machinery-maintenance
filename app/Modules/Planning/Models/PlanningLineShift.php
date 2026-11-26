<?php

declare(strict_types=1);

namespace App\Modules\Planning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A shift a planning line runs, its hours, manpower and target (Shift Master). Capacity Planning → Master Data (AnnotechERP 12/01).
 */
class PlanningLineShift extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'planning_line_shifts';

    protected $fillable = ['company_id', 'planning_line_id', 'hr_shift_id', 'shift_name', 'start_time', 'end_time', 'break_minutes', 'manpower', 'target', 'active'];

    protected function casts(): array
    {
        return ['break_minutes' => 'integer', 'manpower' => 'integer', 'target' => 'integer', 'active' => 'boolean'];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A manpower group's overtime plan for one day: who stays to each hour between 6PM and 5AM. Work Study → OT & Salary (AnnotechERP 14/06).
 */
class WsOtPlan extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_ot_plans';

    protected $fillable = ['company_id', 'plan_date', 'floor_id', 'planning_line_id', 'department_id', 'designation_id', 'mp_gen', 'wh_gen', 'prev_wh', 'slots', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['plan_date' => 'date', 'mp_gen' => 'integer', 'wh_gen' => 'decimal:2', 'prev_wh' => 'decimal:2', 'slots' => 'array'];
    }
}

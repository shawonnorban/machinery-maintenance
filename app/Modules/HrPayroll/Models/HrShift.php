<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A shift — times, break, working hours, grace, cross-day and OT settings (Shift). HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class HrShift extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'hr_shifts';

    protected $fillable = ['company_id', 'code', 'name', 'start_time', 'end_time', 'break_start', 'break_min', 'working_hours', 'grace_min', 'cross_day', 'ot_allowed', 'ot_approval', 'effective_date', 'active', 'remarks'];

    protected function casts(): array
    {
        return ['break_min' => 'integer', 'working_hours' => 'decimal:2', 'grace_min' => 'integer', 'cross_day' => 'boolean', 'ot_allowed' => 'boolean', 'ot_approval' => 'boolean', 'effective_date' => 'date', 'active' => 'boolean'];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One posting of an employee — factory, building, floor, department,
 * section, line, designation, grade and shift — from the day it took effect
 * to the day a transfer closed it (open while it is the current one), and
 * why it was made. HR & Payroll → Employee Assignment (AnnotechERP 24/03).
 */
class HrEmployeeAssignment extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'hr_employee_assignments';

    protected $fillable = ['company_id', 'employee_id', ...HrEmployee::PLACEMENT, 'reason', 'effective_from', 'effective_to', 'created_by'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date'];
    }
}

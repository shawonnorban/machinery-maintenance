<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An employee's salary as it was set — structure, Gross A, Gross B and the
 * actual salary — from its effective date (the joining date at
 * registration). HR & Payroll → Employee (AnnotechERP 24/03).
 */
class HrEmployeeSalary extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'hr_employee_salaries';

    protected $fillable = ['company_id', 'employee_id', 'gazette_id', 'employment_type_id', 'gross_a', 'gross_b', 'actual_salary', 'effective_from', 'created_by'];

    protected function casts(): array
    {
        return ['gross_a' => 'decimal:2', 'gross_b' => 'decimal:2', 'actual_salary' => 'decimal:2', 'effective_from' => 'date'];
    }
}

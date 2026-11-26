<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One payroll setting — the salary day basis, late deduction rule, rounding (Payroll Settings). HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class HrPayrollSetting extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'hr_payroll_settings';

    protected $fillable = ['company_id', 'key', 'value'];

    protected function casts(): array
    {
        return [];
    }
}

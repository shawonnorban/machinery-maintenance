<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A leave type, paid or unpaid, with its eligibility and balance rules (Leave Type). HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class HrLeaveType extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'hr_leave_types';

    protected $fillable = ['company_id', 'code', 'color', 'name', 'name_bn', 'paid', 'pay_element_id', 'day_forward', 'forward_days', 'accrual', 'accrual_auto', 'offday_included', 'holiday_included', 'balance_check', 'leave_group', 'min_employment', 'min_unit', 'notify_days', 'max_days', 'min_work_days', 'applicable_for', 'encashable', 'active', 'remarks'];

    protected function casts(): array
    {
        return ['paid' => 'boolean', 'day_forward' => 'boolean', 'forward_days' => 'integer', 'accrual' => 'boolean', 'accrual_auto' => 'boolean', 'offday_included' => 'boolean', 'holiday_included' => 'boolean', 'balance_check' => 'boolean', 'min_employment' => 'integer', 'notify_days' => 'integer', 'max_days' => 'integer', 'min_work_days' => 'integer', 'encashable' => 'boolean', 'active' => 'boolean'];
    }
}

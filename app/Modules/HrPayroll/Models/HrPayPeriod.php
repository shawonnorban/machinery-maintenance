<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A company's pay period in a fiscal year, and where it is in its life (Payroll Settings). HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class HrPayPeriod extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['INITIATED', 'SUBMITTED', 'ACTIVATED', 'CLOSED'];

    protected $table = 'hr_pay_periods';

    protected $fillable = ['company_id', 'period_no', 'period_type', 'fiscal_year_id', 'month', 'start_date', 'end_date', 'pay_date', 'status', 'pay_element_id', 'dept_type', 'remarks', 'show_for_report', 'closed'];

    protected function casts(): array
    {
        return ['month' => 'integer', 'start_date' => 'date', 'end_date' => 'date', 'pay_date' => 'date', 'show_for_report' => 'boolean', 'closed' => 'boolean'];
    }
}

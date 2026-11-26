<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A deduction against an employee for a pay period, by head (Deduction). HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class HrDeduction extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'hr_deductions';

    protected $fillable = ['company_id', 'pay_period_id', 'pay_element_id', 'user_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}

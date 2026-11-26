<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A festival bonus rule by service length, salary band, basis and percentage (Bonus Rule). HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class HrBonusRule extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'hr_bonus_rules';

    protected $fillable = ['company_id', 'rule_set_id', 'service_low', 'service_high', 'salary_low', 'salary_high', 'basis', 'pct', 'fixed', 'employment_type_id', 'department_id', 'designation_group_id', 'shift_team_id', 'active'];

    protected function casts(): array
    {
        return ['service_low' => 'integer', 'service_high' => 'integer', 'salary_low' => 'decimal:2', 'salary_high' => 'decimal:2', 'pct' => 'decimal:2', 'fixed' => 'decimal:2', 'active' => 'boolean'];
    }
}

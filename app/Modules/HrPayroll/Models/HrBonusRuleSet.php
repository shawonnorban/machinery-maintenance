<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A set of bonus rules a festival names (Bonus Rule). HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class HrBonusRuleSet extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'hr_bonus_rule_sets';

    protected $fillable = ['company_id', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}

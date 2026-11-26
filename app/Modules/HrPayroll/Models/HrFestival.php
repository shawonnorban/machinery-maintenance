<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A festival in a year, the month its bonus is paid in and the rule set that prices it (Festival Calendar). HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class HrFestival extends BaseModel
{
    use BelongsToTenant;

    public const BONUS_TYPES = ['ATTENDANCE', 'EID_ADHA', 'EID_FITR', 'PRODUCTION'];

    protected $table = 'hr_festivals';

    protected $fillable = ['company_id', 'name', 'festival_date', 'year', 'payment_month', 'bonus_rule_set_id', 'bonus_type', 'min_service', 'active', 'eligibility', 'reference', 'holiday_id'];

    protected function casts(): array
    {
        return ['festival_date' => 'date', 'year' => 'integer', 'min_service' => 'integer', 'active' => 'boolean'];
    }
}

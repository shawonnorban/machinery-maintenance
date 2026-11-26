<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A holiday in the register attendance reads, and the days it has put on the calendar (Festival Calendar). HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class HrHoliday extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['COMPANY', 'GOVT', 'WEEKLY_OFF'];

    protected $table = 'hr_holidays';

    protected $fillable = ['company_id', 'fiscal_year_id', 'code', 'name', 'type', 'start_date', 'days', 'recurring', 'calendar_days', 'remarks', 'active'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'days' => 'integer', 'recurring' => 'boolean', 'calendar_days' => 'integer', 'active' => 'boolean'];
    }
}

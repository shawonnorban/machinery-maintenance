<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A wage board gazette — number, year, industry, effective date and reference (Wage/Gazette Rule). HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class HrGazette extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'hr_gazettes';

    protected $fillable = ['company_id', 'name', 'number', 'year', 'industry', 'effective_date', 'active', 'reference'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'effective_date' => 'date', 'active' => 'boolean'];
    }
}

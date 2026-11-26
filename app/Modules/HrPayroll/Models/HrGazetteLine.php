<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What a gazette sets for an employee category and pay element (Wage/Gazette Rule). HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class HrGazetteLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'hr_gazette_lines';

    protected $fillable = ['company_id', 'gazette_id', 'employment_type_id', 'pay_element_id', 'amount', 'active'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'active' => 'boolean'];
    }
}

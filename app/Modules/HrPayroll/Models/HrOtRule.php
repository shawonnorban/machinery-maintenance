<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * How an OT hour is priced — wage basis ÷ divisor × multiplier — by effective date, category and grade (OT Rule). HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class HrOtRule extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'hr_ot_rules';

    protected $fillable = ['company_id', 'code', 'name', 'wage_basis', 'divisor', 'multiplier', 'effective_from', 'effective_to', 'employment_type_id', 'grade_id', 'legal_ref', 'remarks', 'active'];

    protected function casts(): array
    {
        return ['divisor' => 'decimal:2', 'multiplier' => 'decimal:2', 'effective_from' => 'date', 'effective_to' => 'date', 'active' => 'boolean'];
    }
}

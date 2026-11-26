<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What a gazette fixes for a grade — basic, house rent, allowances (Gazette Grade Wage). HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class HrGazetteGradeWage extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'hr_gazette_grade_wages';

    protected $fillable = ['company_id', 'gazette_id', 'employment_type_id', 'grade_id', 'basic', 'house_rent_pct', 'medical', 'conveyance', 'food', 'other', 'active'];

    protected function casts(): array
    {
        return ['basic' => 'decimal:2', 'house_rent_pct' => 'decimal:2', 'medical' => 'decimal:2', 'conveyance' => 'decimal:2', 'food' => 'decimal:2', 'other' => 'decimal:2', 'active' => 'boolean'];
    }
}

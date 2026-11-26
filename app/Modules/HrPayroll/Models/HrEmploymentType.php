<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An employment type in its tree — Staff, Worker (Employment Type). HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class HrEmploymentType extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'hr_employment_types';

    protected $fillable = ['company_id', 'name', 'name_bn', 'short_name', 'parent_id', 'level', 'is_leaf', 'active'];

    protected function casts(): array
    {
        return ['level' => 'integer', 'is_leaf' => 'boolean', 'active' => 'boolean'];
    }
}

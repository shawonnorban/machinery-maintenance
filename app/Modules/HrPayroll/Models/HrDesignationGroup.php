<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A category designations are grouped under, and the manpower category it counts as (Employee Category). HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class HrDesignationGroup extends BaseModel
{
    use BelongsToTenant;

    public const MANPOWER = ['OPERATOR', 'QUALITY', 'HELPER', 'SUPERVISOR', 'STAFF'];

    protected $table = 'hr_designation_groups';

    protected $fillable = ['company_id', 'name', 'name_bn', 'short_name', 'parent_id', 'level', 'sort_order', 'manpower_category', 'is_leaf', 'active'];

    protected function casts(): array
    {
        return ['level' => 'integer', 'sort_order' => 'integer', 'is_leaf' => 'boolean', 'active' => 'boolean'];
    }
}

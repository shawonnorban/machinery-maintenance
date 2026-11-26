<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A job title (Company Management → Designation & Grade). The employee
 * category is a plain value until HR & Payroll brings its own master.
 */
class Designation extends BaseModel
{
    use BelongsToTenant;

    public const EMPLOYEE_CATEGORIES = ['WORKER', 'STAFF', 'OFFICER', 'MANAGEMENT'];

    protected $table = 'designations';

    protected $fillable = ['company_id', 'name', 'code', 'department_id', 'employee_category', 'grade_id', 'status'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }
}

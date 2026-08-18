<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Models;

use App\Modules\Tenancy\Models\Factory;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person who does maintenance work. Carries a grade, never a wage
 * (SRS 3.3, ADR-065).
 */
class Technician extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'factory_id', 'user_id', 'labor_grade_id', 'department_id',
        'employee_id', 'name', 'phone', 'email', 'specialization',
        'joining_date', 'max_concurrent_work_orders', 'status',
    ];

    protected function casts(): array
    {
        return ['joining_date' => 'date'];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(LaborRateGrade::class, 'labor_grade_id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(TechnicianSkill::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }
}

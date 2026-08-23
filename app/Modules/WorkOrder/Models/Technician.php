<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Models;

use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person who does maintenance work (SRS 3.3).
 *
 * Carries no wage and no rate. Technicians are salaried employees, so the
 * record is about who they are and what they look after, not what they cost.
 *
 * What they look after is the useful part: a dyeing technician covers the
 * dyeing department, a sewing mechanic covers the sewing floor, and where a
 * factory assigns people to a particular line, the line is named too.
 */
class Technician extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'factory_id', 'user_id', 'department_id', 'production_line_id',
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

    /** The section they are responsible for: dyeing, knitting, sewing. */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Narrower still, where a factory assigns people line by line. */
    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(TechnicianSkill::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    /**
     * Whether this person's remit covers a machine standing in this location.
     *
     * A technician with no area named covers the whole factory, which is how
     * most small factories work. Naming a department narrows them to it, and
     * naming a line narrows them further.
     *
     * Deliberately not a hard rule anywhere: it decides who is offered first,
     * not who may be assigned. A manager at 2am sends whoever is awake.
     */
    public function coversLocation(?string $departmentId, ?string $productionLineId): bool
    {
        if ($this->production_line_id !== null) {
            return $this->production_line_id === $productionLineId;
        }

        if ($this->department_id !== null) {
            return $this->department_id === $departmentId;
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Actions;

use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrderAssignment;
use App\Modules\WorkOrder\Models\WorkOrderLaborEntry;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * The maintenance roster: who works here and what they look after (SRS 25).
 *
 * A technician record carries no money of any kind. Technicians are salaried
 * employees, so what the record is for is knowing who to send: their factory
 * always, the department where a factory is divided into them, and the
 * production line where people are assigned line by line.
 *
 * The area is advisory by design. It decides who the assignment screen offers
 * first, never who may be assigned — a system that refuses at two in the
 * morning is a system that gets worked around, and then nobody's roster is
 * accurate.
 */
class ManageTechnician
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Technician
    {
        return Technician::create($this->values($data) + [
            'company_id' => $this->context->companyId(),
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Technician $technician, array $data): Technician
    {
        $technician->update($this->values($data));

        return $technician->fresh();
    }

    /**
     * Take somebody off the roster, or put them back.
     *
     * Never a delete while they have history: a work order they closed still
     * names them, and a repair whose technician cannot be named is a repair
     * nobody can ask about.
     */
    public function setStatus(Technician $technician, string $status): Technician
    {
        $technician->forceFill(['status' => $status])->save();

        return $technician->fresh();
    }

    public function delete(Technician $technician): void
    {
        $history = WorkOrderAssignment::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('technician_id', $technician->id)
            ->count()
            + WorkOrderLaborEntry::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('technician_id', $technician->id)
                ->count();

        if ($history > 0) {
            throw ValidationException::withMessages([
                'employee_id' => __('technician.in_use', ['count' => $history]),
            ])->status(409);
        }

        $technician->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function values(array $data): array
    {
        $factoryId = $data['factory_id'] ?? null;

        if (! filled($factoryId) || ! $this->context->canAccessFactory((string) $factoryId)) {
            throw ValidationException::withMessages([
                'factory_id' => __('technician.factory_unavailable'),
            ]);
        }

        $departmentId = $this->areaOf(Department::class, $data['department_id'] ?? null, 'department_id');
        $lineId = $this->areaOf(ProductionLine::class, $data['production_line_id'] ?? null, 'production_line_id');

        if ($lineId !== null && $departmentId === null) {
            // A line without its department would leave the roster saying
            // "covers line 3" with nothing to widen to when line 3 is quiet.
            throw ValidationException::withMessages([
                'department_id' => __('technician.line_needs_department'),
            ]);
        }

        return [
            'factory_id' => $factoryId,
            'department_id' => $departmentId,
            'production_line_id' => $lineId,
            'user_id' => filled($data['user_id'] ?? null) ? $data['user_id'] : null,
            'employee_id' => $data['employee_id'],
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'specialization' => $data['specialization'] ?? null,
            'joining_date' => $data['joining_date'] ?? null,
            'max_concurrent_work_orders' => $data['max_concurrent_work_orders'] ?? null,
        ];
    }

    /**
     * @param  class-string  $model
     */
    private function areaOf(string $model, mixed $id, string $field): ?string
    {
        if (! filled($id)) {
            return null;
        }

        // Tenant-scoped, so another company's floor plan is simply not found.
        if (! $model::query()->whereKey($id)->exists()) {
            throw ValidationException::withMessages([
                $field => __('technician.area_unavailable'),
            ]);
        }

        return (string) $id;
    }
}

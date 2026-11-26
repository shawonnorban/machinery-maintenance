<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Actions;

use App\Modules\HrPayroll\Models\HrEmployee;
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
        $values = $this->values($data);

        $this->assertLoginAvailable($values['user_id']);
        $this->assertEmployeeAvailable($values, null);

        return Technician::create($values + [
            'company_id' => $this->context->companyId(),
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Technician $technician, array $data): Technician
    {
        $values = $this->values($data, $technician);

        $this->assertLoginAvailable($values['user_id'], $technician->id);
        $this->assertEmployeeAvailable($values, $technician->id);

        $technician->update($values);

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
    private function values(array $data, ?Technician $technician = null): array
    {
        $employee = $this->employeeOf($data['hr_employee_id'] ?? null, $technician);

        if ($employee !== null) {
            // Who they are comes from HR, never typed twice; where they
            // work defaults to their HR posting when the form left it open.
            $data['name'] = $employee->name;
            $data['employee_id'] = $employee->employee_code;
            $data['phone'] = $employee->mobile ?? ($data['phone'] ?? null);
            $data['email'] = $employee->email ?? ($data['email'] ?? null);
            $data['joining_date'] = $employee->joining_date?->toDateString() ?? ($data['joining_date'] ?? null);

            if (! filled($data['factory_id'] ?? null)) {
                $data['factory_id'] = $employee->factory_id;
                $data['department_id'] = $data['department_id'] ?? $employee->department_id;
                $data['production_line_id'] = $data['production_line_id'] ?? $employee->line_id;
            }
        }

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
            'hr_employee_id' => $employee?->id,
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
     * One login, one technician (`technicians_user_unique`) — a shared login
     * across several technicians is exactly what makes individual work
     * history unrecoverable, so this is refused here with a field-specific
     * message rather than surfacing as a raw database constraint error.
     */
    private function assertLoginAvailable(?string $userId, ?string $excludingTechnicianId = null): void
    {
        if ($userId === null) {
            return;
        }

        $alreadyLinked = Technician::where('user_id', $userId)
            ->when($excludingTechnicianId !== null, fn ($q) => $q->where('id', '!=', $excludingTechnicianId))
            ->first();

        if ($alreadyLinked !== null) {
            throw ValidationException::withMessages([
                'user_id' => __('technician.login_already_linked', ['name' => $alreadyLinked->name]),
            ])->status(409);
        }
    }

    /**
     * The employee picked from HR & Payroll's list. Tenant-scoped, so another
     * company's employee is simply not found; a separated or suspended
     * employee can't be newly put on the roster, though one already linked
     * keeps the link.
     */
    private function employeeOf(mixed $id, ?Technician $technician): ?HrEmployee
    {
        if (! filled($id)) {
            return null;
        }

        $employee = HrEmployee::query()->whereKey($id)->first();
        $keepsLink = $technician !== null && $employee !== null && $technician->hr_employee_id === $employee->id;

        if ($employee === null || ($employee->status !== 'ACTIVE' && ! $keepsLink)) {
            throw ValidationException::withMessages([
                'hr_employee_id' => __('technician.employee_unavailable'),
            ]);
        }

        return $employee;
    }

    /**
     * One employee, one technician (`technicians_hr_employee_unique`), and
     * the HR code they bring must not already be another roster row's
     * employee ID: refused with the field and the name, not a raw
     * constraint error.
     *
     * @param  array<string, mixed>  $values
     */
    private function assertEmployeeAvailable(array $values, ?string $excludingTechnicianId): void
    {
        if ($values['hr_employee_id'] === null) {
            return;
        }

        $taken = Technician::query()
            ->where(fn ($q) => $q->where('hr_employee_id', $values['hr_employee_id'])
                ->orWhere('employee_id', $values['employee_id']))
            ->when($excludingTechnicianId !== null, fn ($q) => $q->where('id', '!=', $excludingTechnicianId))
            ->first();

        if ($taken !== null) {
            throw ValidationException::withMessages([
                'hr_employee_id' => __('technician.employee_already_linked', ['name' => $taken->name]),
            ])->status(409);
        }
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

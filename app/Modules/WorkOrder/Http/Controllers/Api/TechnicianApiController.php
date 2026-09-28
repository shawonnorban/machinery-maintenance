<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\WorkOrder\Actions\ManageTechnician;
use App\Modules\WorkOrder\Http\Controllers\Web\TechnicianSkillController;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\TechnicianSkill;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderAssignment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The maintenance roster, over the wire (API 16; mirrors the web
 * `TechnicianController`, which this delegates every write to unchanged per
 * ADR-003) — who works here and what they look after, carrying no money of
 * any kind.
 */
class TechnicianApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('technician.technician.manage');

        $query = Technician::query()
            ->with(['factory:id,name', 'department:id,name', 'productionLine:id,name'])
            ->whereIn('factory_id', $this->context->accessibleFactoryIds())
            ->when($request->string('search')->trim()->toString(), function ($q, string $term): void {
                $q->where(fn ($w) => $w->where('name', 'like', $term.'%')
                    ->orWhere('employee_id', 'like', $term.'%'));
            });

        $query = $this->applyFilters($query, $request, ['department_id', 'factory_id', 'status']);

        return ApiResponse::paginated(
            $query->orderBy('name')->paginate($this->perPage($request))->withQueryString(),
            fn (Technician $t): array => $this->summary($t),
        );
    }

    public function show(Technician $technician): JsonResponse
    {
        $this->allow('technician.technician.manage');
        $this->assertReachable($technician);

        return ApiResponse::ok($this->detail($technician->load('skills')));
    }

    public function store(Request $request, ManageTechnician $action): JsonResponse
    {
        $this->allow('technician.technician.manage');

        $technician = $action->create($this->validated($request, null));

        return ApiResponse::created($this->detail($technician));
    }

    public function update(Request $request, Technician $technician, ManageTechnician $action): JsonResponse
    {
        $this->allow('technician.technician.manage');
        $this->assertReachable($technician);

        $updated = $action->update($technician, $this->validated($request, $technician));

        return ApiResponse::ok($this->detail($updated));
    }

    public function setActive(Request $request, Technician $technician, ManageTechnician $action): JsonResponse
    {
        $this->allow('technician.technician.manage');
        $this->assertReachable($technician);

        $data = $request->validate(['active' => ['required', 'boolean']]);

        return ApiResponse::ok($this->summary($action->setStatus($technician, $data['active'] ? 'ACTIVE' : 'INACTIVE')));
    }

    public function destroy(Technician $technician, ManageTechnician $action): JsonResponse
    {
        $this->allow('technician.technician.manage');
        $this->assertReachable($technician);

        $action->delete($technician);

        return ApiResponse::noContent();
    }

    /**
     * Everything the roster's own dropdowns need, gated at the roster's own
     * permission tier — not `settings.factory.manage`/`masterdata.manage`,
     * which a role holding `technician.technician.manage` need not also
     * hold (MAINTENANCE_MANAGER is exactly this case: it manages the
     * roster a tier below where those two are first granted). Mirrors
     * `TechnicianController::formOptions()` (web) and the same reasoning
     * `AssetApiController::formOptions()` already documents for assets.
     */
    public function formOptions(): JsonResponse
    {
        $this->allow('technician.technician.manage');

        $companyId = $this->context->companyId();

        return ApiResponse::ok([
            'factories' => Factory::whereIn('id', $this->context->accessibleFactoryIds())
                ->orderBy('name')->get(['id', 'name'])->all(),
            // factory_id/department_id travel with each row so the form can
            // cascade its own dropdowns — a department belongs to a factory,
            // a line to a department (`ProductionLine` has no factory_id of
            // its own), same two-level structure
            // `TechnicianController::formOptions()` (web) exposes.
            'departments' => Department::orderBy('name')->get(['id', 'name', 'factory_id'])->all(),
            'production_lines' => ProductionLine::orderBy('name')->get(['id', 'name', 'department_id'])->all(),
            // This company's own active members only — the pool a technician
            // record can be linked to a login from.
            'users' => User::query()
                ->whereHas('memberships', fn ($q) => $q->where('company_id', $companyId)->where('status', 'ACTIVE'))
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->all(),
            'proficiencies' => TechnicianSkillController::PROFICIENCIES,
        ]);
    }

    /**
     * What this person is actually trained on — separate from the area
     * they cover (SRS 25). Mirrors `TechnicianSkillController::store()`
     * exactly, including the same duplicate-name refusal; there is no
     * shared Action for this on the web side either, so nothing to
     * delegate to per ADR-003 — this is the same direct-model write the
     * web controller already does.
     */
    public function storeSkill(Request $request, Technician $technician): JsonResponse
    {
        $this->allow('technician.technician.manage');
        $this->assertReachable($technician);

        $data = $request->validate([
            'skill_name' => ['required', 'string', 'max:255'],
            'proficiency' => ['required', Rule::in(TechnicianSkillController::PROFICIENCIES)],
        ]);

        $exists = TechnicianSkill::where('technician_id', $technician->id)
            ->where('skill_name', $data['skill_name'])
            ->exists();

        if ($exists) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, __('technician.skill_already_listed'), [
                'skill_name' => [__('technician.skill_already_listed')],
            ]);
        }

        $skill = TechnicianSkill::create($data + [
            'company_id' => $this->context->companyId(),
            'technician_id' => $technician->id,
        ]);

        return ApiResponse::created([
            'id' => $skill->id,
            'skill_name' => $skill->skill_name,
            'proficiency' => $skill->proficiency,
        ]);
    }

    public function destroySkill(Technician $technician, TechnicianSkill $skill): JsonResponse
    {
        $this->allow('technician.technician.manage');
        $this->assertReachable($technician);

        if ($skill->technician_id !== $technician->id) {
            throw ApiException::of(ErrorCode::RESOURCE_NOT_FOUND);
        }

        $skill->delete();

        return ApiResponse::noContent();
    }

    /**
     * How full this person's plate is, against their own concurrency limit
     * (SRS 25) — the same open-assignment count `AssignTechnicians` checks
     * before letting a manager add one more job.
     */
    public function workload(Technician $technician): JsonResponse
    {
        $this->allow('technician.technician.manage');
        $this->assertReachable($technician);

        $openWorkOrders = WorkOrder::query()
            ->whereIn('status', WorkOrder::OPEN_STATUSES)
            ->whereIn('id', WorkOrderAssignment::where('technician_id', $technician->id)
                ->whereNull('unassigned_at')
                ->select('work_order_id'))
            ->with('asset:id,asset_code,name')
            ->orderBy('scheduled_start')
            ->get();

        $limit = $technician->max_concurrent_work_orders;

        return ApiResponse::ok([
            'technician_id' => $technician->id,
            'max_concurrent_work_orders' => $limit,
            'open_work_orders_count' => $openWorkOrders->count(),
            'at_capacity' => $limit !== null && $openWorkOrders->count() >= $limit,
            'open_work_orders' => $openWorkOrders->map(fn (WorkOrder $wo): array => [
                'id' => $wo->id,
                'work_order_number' => $wo->work_order_number,
                'status' => $wo->status,
                'priority' => $wo->priority,
                'asset' => $wo->asset === null ? null : ['id' => $wo->asset->id, 'asset_code' => $wo->asset->asset_code],
                'scheduled_start' => $wo->scheduled_start?->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Technician $technician): array
    {
        $unique = Rule::unique('technicians', 'employee_id')->where('company_id', $this->context->companyId());

        if ($technician !== null) {
            $unique = $unique->ignore($technician->id);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'employee_id' => ['required', 'string', 'max:64', $unique],
            'factory_id' => ['required', 'string', 'size:26'],
            'department_id' => ['nullable', 'string', 'size:26'],
            'production_line_id' => ['nullable', 'string', 'size:26'],
            'user_id' => ['nullable', 'string', 'size:26'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'joining_date' => ['nullable', 'date'],
            'max_concurrent_work_orders' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Technician $technician): array
    {
        return [
            'id' => $technician->id,
            'name' => $technician->name,
            'employee_id' => $technician->employee_id,
            'factory' => $technician->factory === null ? null : ['id' => $technician->factory->id, 'name' => $technician->factory->name],
            'department' => $technician->department?->name,
            'production_line' => $technician->productionLine?->name,
            'status' => $technician->status,
            'phone' => $technician->phone,
            'email' => $technician->email,
            'max_concurrent_work_orders' => $technician->max_concurrent_work_orders,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Technician $technician): array
    {
        return $this->summary($technician) + [
            'user_id' => $technician->user_id,
            // Not on the list summary — needed so an edit form can preselect
            // these without a second round trip, same reasoning as Location's.
            'department_id' => $technician->department_id,
            'production_line_id' => $technician->production_line_id,
            'specialization' => $technician->specialization,
            'joining_date' => $technician->joining_date?->toDateString(),
            'skills' => $technician->relationLoaded('skills') ? $technician->skills->map(fn ($s): array => [
                'id' => $s->id,
                'skill_name' => $s->skill_name,
                'proficiency' => $s->proficiency,
            ])->all() : null,
        ];
    }

    private function assertReachable(Technician $technician): void
    {
        if (! $this->context->canAccessFactory((string) $technician->factory_id)) {
            abort(403);
        }
    }
}

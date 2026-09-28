<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Http\Controllers\Api;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Identity\Models\Team;
use App\Modules\Maintenance\Actions\SaveMaintenancePlan;
use App\Modules\Maintenance\Http\Requests\SaveMaintenancePlanRequest;
use App\Modules\Maintenance\Models\MaintenancePlan;
use App\Modules\Maintenance\Models\MaintenanceTemplate;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Maintenance\Services\DueDatePreview;
use App\Modules\Metering\Models\MeterType;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Maintenance plans, over the wire (API 8; mirrors the web `PlanController`,
 * which this delegates every write to unchanged per ADR-003).
 *
 * `SaveMaintenancePlanRequest` is reused as-is rather than re-validated here:
 * its own `authorize()` already picks `.create` or `.update` from whether a
 * `{plan}` route parameter is bound, which an API route matches by using the
 * same parameter name.
 */
class MaintenancePlanApiController extends ApiController
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('maintenance.plan.view_any');

        $query = MaintenancePlan::query()
            ->with(['asset:id,asset_code,name', 'assetType:id,name', 'maintenanceType:id,name'])
            ->withCount(['schedules as open_schedules_count' => fn ($q) => $q->whereIn('status', ['PLANNED', 'DUE', 'OVERDUE'])])
            ->when($request->query('active') !== null, fn ($q) => $q->where('active', $request->boolean('active')))
            ->orderByDesc('active')
            ->orderBy('name');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (MaintenancePlan $plan): array => $this->summary($plan),
        );
    }

    public function show(MaintenancePlan $plan): JsonResponse
    {
        $this->allow('maintenance.plan.view_any');

        $plan->load(['asset:id,asset_code,name', 'assetType:id,name', 'maintenanceType:id,name', 'rules', 'templateVersion']);

        return ApiResponse::ok($this->detail($plan));
    }

    public function store(SaveMaintenancePlanRequest $request, SaveMaintenancePlan $action): JsonResponse
    {
        $plan = $action->handle($request->validated(), null, $this->caller()->auditUserId());

        return ApiResponse::created($this->detail($plan));
    }

    public function update(
        SaveMaintenancePlanRequest $request,
        MaintenancePlan $plan,
        SaveMaintenancePlan $action,
    ): JsonResponse {
        $updated = $action->handle($request->validated(), $plan, $this->caller()->auditUserId());

        return ApiResponse::ok($this->detail($updated));
    }

    public function activate(MaintenancePlan $plan, SaveMaintenancePlan $action): JsonResponse
    {
        $this->allow('maintenance.plan.activate');

        return ApiResponse::ok($this->detail($action->activate($plan)));
    }

    public function deactivate(MaintenancePlan $plan, SaveMaintenancePlan $action): JsonResponse
    {
        $this->allow('maintenance.plan.activate');

        return ApiResponse::ok($this->detail($action->deactivate($plan)));
    }

    public function destroy(MaintenancePlan $plan, SaveMaintenancePlan $action): JsonResponse
    {
        $this->allow('maintenance.plan.delete');

        $action->delete($plan);

        return ApiResponse::noContent();
    }

    /**
     * Everything the plan builder's dropdowns need, in one call. Mirrors
     * `PlanController::formOptions()` exactly — same `availableTo()`/`active`
     * scoping, same "only reachable factories" rule for assets — but reached
     * over `maintenance.plan.view_any` so a role that can only ever `view_any`
     * (not `create`) can still open a plan to read it without a 403 from its
     * own dropdown data.
     */
    public function formOptions(): JsonResponse
    {
        $this->allow('maintenance.plan.view_any');

        $companyId = $this->context->companyId();

        return ApiResponse::ok([
            'assets' => Asset::query()
                ->whereIn('current_factory_id', $this->context->accessibleFactoryIds())
                ->whereNotIn('status', ['SCRAPPED', 'RETIRED', 'LOST'])
                ->orderBy('asset_code')
                ->get(['id', 'asset_code', 'name', 'current_factory_id'])
                ->all(),
            'asset_types' => AssetType::availableTo($companyId)->where('active', true)->orderBy('name')
                ->get(['id', 'name'])->all(),
            'maintenance_types' => MaintenanceType::availableTo($companyId)->where('active', true)->orderBy('name')
                ->get(['id', 'name'])->all(),
            'meter_types' => MeterType::availableTo($companyId)->where('active', true)->orderBy('name')
                ->get(['id', 'name'])->all(),
            'teams' => Team::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name'])->all(),
            'templates' => MaintenanceTemplate::availableTo($companyId)
                ->with('versions')
                ->orderBy('name')
                ->get()
                ->filter(fn (MaintenanceTemplate $t) => $t->currentVersion() !== null)
                ->map(fn (MaintenanceTemplate $t): array => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'current_version_id' => $t->currentVersion()->id,
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Live preview for the plan builder (Frontend 5.4). Writes nothing.
     */
    public function preview(Request $request, DueDatePreview $preview): JsonResponse
    {
        $this->allow('maintenance.plan.view_any');

        $validated = $request->validate([
            'schedule_mode' => ['nullable', 'in:ROLLING,FIXED'],
            'start_date' => ['nullable', 'date'],
            'interval_value' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'interval_unit' => ['nullable', 'in:HOUR,DAY,WEEK,MONTH,QUARTER,YEAR'],
            'non_working_day_policy' => ['nullable', 'in:NONE,NEXT_WORKING_DAY,PREVIOUS_WORKING_DAY'],
            'factory_id' => ['nullable', 'string', 'size:26'],
        ]);

        if (filled($validated['factory_id'] ?? null)
            && ! $this->context->canAccessFactory($validated['factory_id'])) {
            $validated['factory_id'] = null;
        }

        return ApiResponse::ok($preview->forInput($validated));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(MaintenancePlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'active' => $plan->active,
            'trigger_type' => $plan->trigger_type,
            'schedule_mode' => $plan->schedule_mode,
            'priority' => $plan->priority,
            'asset' => $plan->asset === null ? null : [
                'id' => $plan->asset->id,
                'asset_code' => $plan->asset->asset_code,
                'name' => $plan->asset->name,
            ],
            'asset_type' => $plan->assetType?->name,
            'maintenance_type' => $plan->maintenanceType?->name,
            'open_schedules_count' => $plan->open_schedules_count ?? null,
            'next_due_at' => $plan->next_due_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(MaintenancePlan $plan): array
    {
        return $this->summary($plan) + [
            'asset_id' => $plan->asset_id,
            'asset_type_id' => $plan->asset_type_id,
            'maintenance_type_id' => $plan->maintenance_type_id,
            'template_version_id' => $plan->template_version_id,
            // Mirrors `plans/show.blade.php`'s sidebar ("v2", not just the
            // id) — nothing had asked for the version number itself before.
            'template_version_number' => $plan->templateVersion?->version_number,
            'rule_logic' => $plan->rule_logic,
            'grace_period_minutes' => $plan->grace_period_minutes,
            'lead_time_days' => $plan->lead_time_days,
            'non_working_day_policy' => $plan->non_working_day_policy,
            'requires_shutdown' => $plan->requires_shutdown,
            'assigned_team_id' => $plan->assigned_team_id,
            'estimated_duration_minutes' => $plan->estimated_duration_minutes,
            'start_date' => $plan->start_date?->toDateString(),
            'end_date' => $plan->end_date?->toDateString(),
            'rules' => $plan->relationLoaded('rules') ? $plan->rules->map(fn ($rule): array => [
                'id' => $rule->id,
                'rule_type' => $rule->rule_type,
                'operator' => $rule->operator,
                'value' => $rule->value,
                'unit' => $rule->unit,
                'meter_type_id' => $rule->meter_type_id,
            ])->all() : null,
            'last_generated_at' => $plan->last_generated_at?->toIso8601String(),
            'last_completed_at' => $plan->last_completed_at?->toIso8601String(),
            'created_at' => $plan->created_at?->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Http\Controllers\Web;

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
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): View
    {
        $this->authorize('maintenance.plan.view_any');

        return view('maintenance::plans.index', [
            'plans' => MaintenancePlan::query()
                ->with(['asset:id,asset_code,name', 'assetType:id,name', 'maintenanceType:id,name'])
                ->withCount(['schedules as open_schedules_count' => fn ($q) => $q->whereIn('status', ['PLANNED', 'DUE', 'OVERDUE'])])
                ->when($request->query('active') !== null, fn ($q) => $q->where('active', $request->boolean('active')))
                ->orderByDesc('active')
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function show(MaintenancePlan $plan): View
    {
        $this->authorize('maintenance.plan.view_any');

        $plan->load(['asset:id,asset_code,name', 'assetType:id,name', 'maintenanceType:id,name', 'rules', 'templateVersion']);

        return view('maintenance::plans.show', [
            'plan' => $plan,
            'schedules' => $plan->schedules()
                ->with('asset:id,asset_code,name')
                ->orderBy('due_at')
                ->limit(50)
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('maintenance.plan.create');

        return view('maintenance::plans.create', $this->formOptions());
    }

    public function store(SaveMaintenancePlanRequest $request, SaveMaintenancePlan $action): RedirectResponse
    {
        $plan = $action->handle($request->validated(), null, $request->user()->id);

        return redirect()
            ->route('app.maintenance.plans.show', $plan)
            ->with('status', __('maintenance.plan_created', ['name' => $plan->name]));
    }

    public function edit(MaintenancePlan $plan): View
    {
        $this->authorize('maintenance.plan.update');

        $plan->load('rules');

        return view('maintenance::plans.edit', ['plan' => $plan] + $this->formOptions());
    }

    public function update(
        SaveMaintenancePlanRequest $request,
        MaintenancePlan $plan,
        SaveMaintenancePlan $action,
    ): RedirectResponse {
        $action->handle($request->validated(), $plan, $request->user()->id);

        return redirect()
            ->route('app.maintenance.plans.show', $plan)
            ->with('status', __('maintenance.plan_updated', ['name' => $plan->name]));
    }

    public function activate(MaintenancePlan $plan, SaveMaintenancePlan $action): RedirectResponse
    {
        $this->authorize('maintenance.plan.activate');

        $action->activate($plan);

        return back()->with('status', __('maintenance.plan_activated', ['name' => $plan->name]));
    }

    public function deactivate(MaintenancePlan $plan, SaveMaintenancePlan $action): RedirectResponse
    {
        $this->authorize('maintenance.plan.activate');

        $action->deactivate($plan);

        return back()->with('status', __('maintenance.plan_deactivated', ['name' => $plan->name]));
    }

    /**
     * Live preview for the plan builder (Frontend 5.4). A dry run: it writes
     * nothing and exists so a planner can see what their rule actually does
     * before committing to it.
     */
    public function preview(Request $request, DueDatePreview $preview): JsonResponse
    {
        $this->authorize('maintenance.plan.view_any');

        $validated = $request->validate([
            'schedule_mode' => ['nullable', 'in:ROLLING,FIXED'],
            'start_date' => ['nullable', 'date'],
            'interval_value' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'interval_unit' => ['nullable', 'in:HOUR,DAY,WEEK,MONTH,QUARTER,YEAR'],
            'non_working_day_policy' => ['nullable', 'in:NONE,NEXT_WORKING_DAY,PREVIOUS_WORKING_DAY'],
            'factory_id' => ['nullable', 'string', 'size:26'],
        ]);

        // A factory the user cannot reach would leak the calendar of a site
        // they have no access to.
        if (filled($validated['factory_id'] ?? null)
            && ! $this->context->canAccessFactory($validated['factory_id'])) {
            $validated['factory_id'] = null;
        }

        return response()->json([
            'success' => true,
            'data' => $preview->forInput($validated),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $companyId = $this->context->companyId();

        return [
            'assets' => Asset::query()
                ->whereIn('current_factory_id', $this->context->accessibleFactoryIds())
                ->whereNotIn('status', ['SCRAPPED', 'RETIRED', 'LOST'])
                ->orderBy('asset_code')
                ->get(['id', 'asset_code', 'name', 'current_factory_id']),
            'assetTypes' => AssetType::availableTo($companyId)->where('active', true)->orderBy('name')->get(),
            'maintenanceTypes' => MaintenanceType::availableTo($companyId)->where('active', true)->orderBy('name')->get(),
            'meterTypes' => MeterType::availableTo($companyId)->where('active', true)->orderBy('name')->get(),
            'teams' => Team::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name']),
            // Only published versions: a draft could still change underneath
            // the plan (SRS 12).
            'templates' => MaintenanceTemplate::availableTo($companyId)
                ->with('versions')
                ->orderBy('name')
                ->get()
                ->filter(fn (MaintenanceTemplate $t) => $t->currentVersion() !== null)
                ->values(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Http\Controllers\Web;

use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Breakdown\Http\Requests\ReportBreakdownRequest;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\DowntimeReasonCode;
use App\Modules\Breakdown\Models\FailureCategory;
use App\Modules\Breakdown\Models\FailureCode;
use App\Modules\Breakdown\Models\RootCause;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BreakdownController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): View
    {
        $this->authorize('breakdown.breakdown.view_any');

        $status = $request->query('status');

        $breakdowns = Breakdown::query()
            ->with([
                'asset:id,asset_code,name,criticality',
                'assignedTechnician:id,name',
                'failureCode:id,name,name_bn,code',
            ])
            ->whereIn('factory_id', $this->context->accessibleFactoryIds())
            ->when($status === null || $status === 'OPEN', fn ($q) => $q->whereIn('status', Breakdown::OPEN_STATUSES))
            ->when($status !== null && ! in_array($status, ['OPEN', 'ALL'], true), fn ($q) => $q->where('status', $status))
            ->when(filled($request->query('search')), function ($q) use ($request): void {
                $term = '%'.$request->query('search').'%';
                $q->where(fn ($w) => $w->where('breakdown_number', 'like', $term)
                    ->orWhere('problem_description', 'like', $term)
                    ->orWhereHas('asset', fn ($a) => $a->where('asset_code', 'like', $term)));
            })
            ->when(filled($request->query('priority')), fn ($q) => $q->where('priority', $request->query('priority')))
            // Critical first, then longest down. A machine stopped since
            // yesterday morning outranks one that stopped ten minutes ago.
            ->orderByRaw("FIELD(priority, 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW')")
            ->orderBy('failure_at')
            ->paginate(min(max((int) $request->query('per_page', 25), 10), 100))
            ->withQueryString();

        return view('breakdown::breakdowns.index', [
            'breakdowns' => $breakdowns,
            'status' => $status,
            'counts' => $this->counts(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('breakdown.breakdown.create');

        return view('breakdown::breakdowns.create', $this->formOptions());
    }

    public function store(ReportBreakdownRequest $request, ReportBreakdown $action): RedirectResponse
    {
        $breakdown = $action->handle($request->payload(), $request->user()->id);

        return redirect()
            ->route('app.breakdowns.show', $breakdown)
            ->with('status', __('breakdown.reported_message', [
                'number' => $breakdown->breakdown_number,
            ]));
    }

    public function show(Breakdown $breakdown): View
    {
        $this->authorize('breakdown.breakdown.view');

        $breakdown->load([
            'asset:id,asset_code,name,criticality,current_factory_id',
            'factory:id,name',
            'productionLine:id,name',
            'failureCategory', 'failureCode', 'rootCause', 'downtimeReasonCode',
            'assignedTechnician:id,name,employee_id',
            'statusHistories',
            'productionImpacts.productionLine:id,name',
        ]);

        $companyId = $this->context->companyId();

        return view('breakdown::breakdowns.show', [
            'breakdown' => $breakdown,
            'downtime' => $breakdown->currentDowntime(),
            'workOrders' => WorkOrder::where('breakdown_id', $breakdown->id)
                ->with('maintenanceType:id,name')
                ->orderByDesc('created_at')
                ->get(),
            'recurrences' => $breakdown->recurrences()->orderBy('reported_at')->get(),
            'originalBreakdown' => $breakdown->is_recurrence_of_breakdown_id === null
                ? null
                : Breakdown::find($breakdown->is_recurrence_of_breakdown_id),
            'technicians' => Technician::where('factory_id', $breakdown->factory_id)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(['id', 'name', 'employee_id']),
            'failureCategories' => FailureCategory::availableTo($companyId)
                ->where('active', true)->orderBy('name')->get(),
            'failureCodes' => FailureCode::availableTo($companyId)
                ->where('active', true)->orderBy('name')->get(),
            'rootCauses' => RootCause::availableTo($companyId)
                ->where('active', true)->orderBy('name')->get(),
            'reasonCodes' => DowntimeReasonCode::availableTo($companyId)
                ->where('active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $factoryIds = $this->context->accessibleFactoryIds();
        $base = fn () => Breakdown::query()->whereIn('factory_id', $factoryIds);

        return [
            'open' => $base()->whereIn('status', Breakdown::OPEN_STATUSES)->count(),
            // Reported but not yet acknowledged: nobody has picked this up, and
            // it is the number a maintenance manager should watch.
            'unacknowledged' => $base()->where('status', 'REPORTED')->count(),
            'in_repair' => $base()->where('status', 'IN_REPAIR')->count(),
            'awaiting_closure' => $base()->whereIn('status', ['REPAIRED', 'PRODUCTION_RESUMED'])->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $companyId = $this->context->companyId();
        $factoryIds = $this->context->accessibleFactoryIds();

        return [
            'assets' => Asset::query()
                ->whereIn('current_factory_id', $factoryIds)
                ->whereNotIn('status', ['SCRAPPED', 'RETIRED', 'LOST', 'DRAFT'])
                ->orderBy('asset_code')
                ->get(['id', 'asset_code', 'name', 'current_factory_id']),
            'productionLines' => ProductionLine::orderBy('name')->get(['id', 'name']),
            'failureCategories' => FailureCategory::availableTo($companyId)
                ->where('active', true)->orderBy('name')->get(),
            'failureCodes' => FailureCode::availableTo($companyId)
                ->where('active', true)->orderBy('name')->get(),
            'reasonCodes' => DowntimeReasonCode::availableTo($companyId)
                ->where('active', true)->orderBy('name')->get(),
        ];
    }
}

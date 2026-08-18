<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Http\Controllers\Web;

use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\Team;
use App\Modules\Maintenance\Models\MaintenanceTemplate;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Http\Requests\CreateWorkOrderRequest;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkOrderController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): View
    {
        $this->authorize('work_order.work_order.view_any');

        $status = $request->query('status');

        $workOrders = WorkOrder::query()
            ->with([
                'asset:id,asset_code,name',
                'maintenanceType:id,name',
                'activeAssignments.technician:id,name',
            ])
            ->whereIn('factory_id', $this->context->accessibleFactoryIds())
            ->when($status === 'OPEN' || $status === null, fn ($q) => $q->whereIn('status', WorkOrder::OPEN_STATUSES))
            ->when($status !== null && $status !== 'OPEN' && $status !== 'ALL', fn ($q) => $q->where('status', $status))
            ->when(filled($request->query('search')), function ($q) use ($request): void {
                $term = '%'.$request->query('search').'%';
                $q->where(fn ($w) => $w->where('work_order_number', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhereHas('asset', fn ($a) => $a->where('asset_code', 'like', $term)));
            })
            ->when(filled($request->query('priority')), fn ($q) => $q->where('priority', $request->query('priority')))
            // Critical work first, then oldest promise first: a job scheduled
            // for last Tuesday is more urgent than one scheduled for tomorrow.
            ->orderByRaw("FIELD(priority, 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW')")
            ->orderByRaw('scheduled_start IS NULL, scheduled_start ASC')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return view('work_order::work-orders.index', [
            'workOrders' => $workOrders,
            'status' => $status,
            'counts' => $this->counts(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('work_order.work_order.create');

        return view('work_order::work-orders.create', $this->formOptions());
    }

    public function store(CreateWorkOrderRequest $request, CreateWorkOrder $action): RedirectResponse
    {
        $workOrder = $action->handle($request->payload(), $request->user()->id);

        return redirect()
            ->route('app.work-orders.show', $workOrder)
            ->with('status', __('work_order.created', ['number' => $workOrder->work_order_number]));
    }

    public function show(WorkOrder $workOrder): View
    {
        $this->authorize('work_order.work_order.view');

        $workOrder->load([
            'asset:id,asset_code,name,criticality,current_factory_id',
            'factory:id,name',
            'maintenanceType:id,name',
            'templateVersion',
            'activeAssignments.technician:id,name,employee_id',
            'assignments.technician:id,name',
            'laborEntries.technician:id,name',
            'holds',
            'statusHistories',
            'checklistResults.item',
        ]);

        $items = $workOrder->templateVersion?->items()->orderBy('sequence')->get() ?? collect();

        return view('work_order::work-orders.show', [
            'workOrder' => $workOrder,
            'items' => $items,
            'results' => $workOrder->checklistResults->keyBy('checklist_item_id'),
            'progress' => $workOrder->checklistProgress(),
            'attachments' => FileAttachment::where('attachable_type', 'work_order')
                ->where('attachable_id', $workOrder->id)
                ->latest()
                ->get(),
            // Costs are a separate permission: a technician records their time
            // without being shown what the job costs (SRS 25.1).
            'showCosts' => request()->user()->can('work_order.cost.view'),
            'technicians' => Technician::where('factory_id', $workOrder->factory_id)
                ->where('status', 'ACTIVE')
                ->orderBy('name')
                ->get(['id', 'name', 'employee_id']),
        ]);
    }

    private function perPage(Request $request): int
    {
        // Capped: an unbounded page size is a denial of service handed to the
        // client (API 35.3 rule 4).
        return min(max((int) $request->query('per_page', 25), 10), 100);
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $factoryIds = $this->context->accessibleFactoryIds();

        $base = fn () => WorkOrder::query()->whereIn('factory_id', $factoryIds);

        return [
            'open' => $base()->whereIn('status', WorkOrder::OPEN_STATUSES)->count(),
            'in_progress' => $base()->where('status', 'IN_PROGRESS')->count(),
            'on_hold' => $base()->where('status', 'ON_HOLD')->count(),
            'awaiting_verification' => $base()->where('status', 'COMPLETED')
                ->where('requires_verification', true)->count(),
        ];
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
                ->whereNotIn('status', ['SCRAPPED', 'RETIRED', 'LOST', 'DRAFT'])
                ->orderBy('asset_code')
                ->get(['id', 'asset_code', 'name', 'current_factory_id']),
            'maintenanceTypes' => MaintenanceType::availableTo($companyId)
                ->where('active', true)->orderBy('name')->get(),
            'teams' => Team::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name']),
            // Published versions only: a draft could still change underneath the
            // technician who is working through it.
            'templates' => MaintenanceTemplate::availableTo($companyId)
                ->with('versions')
                ->orderBy('name')
                ->get()
                ->filter(fn (MaintenanceTemplate $t) => $t->currentVersion() !== null)
                ->values(),
        ];
    }
}

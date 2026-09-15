<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Http\Controllers\Api;

use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Actions\TransitionBreakdown;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Identity\Models\Team;
use App\Modules\Maintenance\Models\MaintenanceTemplate;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Actions\TransitionWorkOrder;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderAssignment;
use App\Modules\WorkOrder\Models\WorkOrderStatusHistory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Support\Sql;
use App\Shared\Support\TenantTimezone;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Work orders (API 11).
 *
 * The transitions are named endpoints rather than a status field a client
 * sets, and that is the important decision here. A work order's lifecycle
 * carries rules — a checklist that must be answered, parts that must be
 * reconciled, verification that must happen before closing — and a client
 * PATCHing `status: CLOSED` would either bypass them or fail with an error
 * that names a field rather than a rule. `POST /close` can answer
 * `PARTS_NOT_RECONCILED`, which tells the caller what to do next.
 */
class WorkOrderApiController extends ApiController
{
    private const FILTERS = [
        'status', 'priority', 'source', 'asset_id', 'factory_id',
        'maintenance_type_id', 'assigned_team_id',
    ];

    private const SORTS = ['work_order_number', 'status', 'priority', 'scheduled_start', 'created_at'];

    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('work_order.work_order.view_any');

        $query = WorkOrder::query()
            ->with(['asset:id,asset_code,name', 'factory:id,name'])
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if ($request->query('open') === 'true') {
            $query->whereNotIn('status', WorkOrder::TERMINAL_STATUSES);
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);

        // "My work" (mirrors `MyWorkController::index`) — the queue a
        // technician actually reads, not a filtered version of everyone
        // else's list: in-progress before tomorrow's, priority within
        // that, whichever is scheduled sooner last. A client-supplied
        // `sort` would fight that ordering, so it's the one branch that
        // skips applySort() entirely rather than letting it win the tie.
        if ($request->query('assigned_to_me') === 'true') {
            $user = $this->caller()->user;

            if ($user === null) {
                throw ApiException::of(ErrorCode::FORBIDDEN, __('api.step_needs_a_person'));
            }

            $technician = Technician::where('user_id', $user->id)->first();

            $assignedIds = $technician === null
                ? []
                : WorkOrderAssignment::where('technician_id', $technician->id)
                    ->whereNull('unassigned_at')
                    ->pluck('work_order_id');

            $query->whereIn('id', $assignedIds)
                ->whereIn('status', WorkOrder::OPEN_STATUSES)
                ->orderByRaw(Sql::orderByList('status', ['IN_PROGRESS', 'ON_HOLD', 'ASSIGNED', 'SCHEDULED']))
                ->orderByRaw(Sql::orderByList('priority', ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW']))
                ->orderByRaw('scheduled_start IS NULL, scheduled_start ASC');
        } else {
            $query = $this->applySort($query, $request, self::SORTS, 'created_at');
        }

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (WorkOrder $workOrder): array => $this->summary($workOrder),
        );
    }

    public function show(WorkOrder $workOrder): JsonResponse
    {
        $this->allow('work_order.work_order.view');
        $this->assertReachable($workOrder);

        $workOrder->load(['asset:id,asset_code,name', 'factory:id,name', 'maintenanceType:id,name', 'activeAssignments.technician:id,name']);

        return ApiResponse::ok($this->detail($workOrder));
    }

    /**
     * The KPI strip `WorkOrderController::index()`'s Blade view shows above
     * its own filter pills — dropped when this list was first ported and
     * restored here rather than folded into `index()`'s response, since a
     * paginated `ApiResponse::paginated()` body has no room for sibling
     * fields.
     *
     * @return array<string, int>
     */
    public function counts(): JsonResponse
    {
        $this->allow('work_order.work_order.view_any');

        $factoryIds = $this->context->accessibleFactoryIds();
        $base = fn () => WorkOrder::query()->whereIn('factory_id', $factoryIds);

        return ApiResponse::ok([
            'open' => $base()->whereIn('status', WorkOrder::OPEN_STATUSES)->count(),
            'in_progress' => $base()->where('status', 'IN_PROGRESS')->count(),
            'on_hold' => $base()->where('status', 'ON_HOLD')->count(),
            'awaiting_verification' => $base()->where('status', 'COMPLETED')
                ->where('requires_verification', true)->count(),
        ]);
    }

    /**
     * Everything the create form's dropdowns need, bundled into one call —
     * mirrors `WorkOrderController::formOptions()` exactly (assets
     * excluding SCRAPPED/RETIRED/LOST/DRAFT, active maintenance types,
     * active teams), reached over `work_order.work_order.create` rather
     * than `masterdata.manage` (`MaintenanceType` is a master-data type
     * everywhere else in the API). The same shape of gap found for
     * Asset's and Inventory's create forms: MAINTENANCE_ENGINEER holds
     * `work_order.work_order.create` well below the tier that first gets
     * `masterdata.manage`.
     *
     * Registered ahead of `GET /work-orders/{workOrder}` in the route
     * file so this static segment isn't swallowed by that wildcard.
     */
    public function formOptions(): JsonResponse
    {
        $this->allow('work_order.work_order.create');

        $companyId = $this->context->companyId();

        return ApiResponse::ok([
            'assets' => Asset::query()
                ->whereIn('current_factory_id', $this->context->accessibleFactoryIds())
                ->whereNotIn('status', ['SCRAPPED', 'RETIRED', 'LOST', 'DRAFT'])
                ->orderBy('asset_code')
                ->get(['id', 'asset_code', 'name', 'current_factory_id'])
                ->map(fn (Asset $asset): array => [
                    'id' => $asset->id,
                    'asset_code' => $asset->asset_code,
                    'name' => $asset->name,
                    'factory_id' => $asset->current_factory_id,
                ])->all(),
            'maintenance_types' => MaintenanceType::availableTo($companyId)->where('active', true)
                ->orderBy('name')->get(['id', 'name'])->all(),
            'teams' => Team::where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name'])->all(),
            // Mirrors the web `WorkOrderController::formOptions()` exactly —
            // published versions only, since a draft could still change
            // underneath the technician working through it.
            'templates' => MaintenanceTemplate::availableTo($companyId)
                ->with('versions')
                ->orderBy('name')
                ->get()
                ->map(fn (MaintenanceTemplate $template) => [$template, $template->currentVersion()])
                ->filter(fn (array $pair) => $pair[1] !== null)
                ->map(fn (array $pair): array => [
                    'id' => $pair[0]->id,
                    'name' => $pair[0]->name,
                    'current_version_id' => $pair[1]->id,
                    'version_number' => $pair[1]->version_number,
                ])->values()->all(),
        ]);
    }

    /**
     * Who could take this job, in the same order the web picker shows them
     * (mirrors `WorkOrderController::technicianChoices()` exactly): active
     * technicians in this work order's factory, whoever covers the
     * machine's own department/production line first, alphabetical after
     * that. Gated on `work_order.work_order.assign` — the permission that
     * actually calls this list, not `technician.technician.manage` (the
     * full roster CRUD's permission, which the same engineer role that can
     * assign a work order does not hold).
     */
    /**
     * The same factory-scoped, coverage-sorted technician list the web
     * computes once in `technicianChoices()` and reuses for both the
     * assign picker and the labor form's — TECHNICIAN holds `work_order.
     * labor.manage` (to record their own time) but not `work_order.
     * work_order.assign`, so gating this on `.assign` alone would 403
     * exactly the caller the labor form needs it for.
     */
    public function assignableTechnicians(WorkOrder $workOrder): JsonResponse
    {
        if (! $this->caller()->can('work_order.work_order.assign') && ! $this->caller()->can('work_order.labor.manage')) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }

        $this->assertReachable($workOrder);

        $workOrder->load('asset.location:id,department_id,production_line_id');
        $location = $workOrder->asset?->location;

        $technicians = Technician::query()
            ->where('factory_id', $workOrder->factory_id)
            ->where('status', 'ACTIVE')
            ->with(['department:id,name', 'productionLine:id,name'])
            ->orderBy('name')
            ->get()
            ->sortByDesc(fn (Technician $t): int => (int) $t->coversLocation(
                $location?->department_id,
                $location?->production_line_id,
            ))
            ->values();

        return ApiResponse::ok($technicians->map(fn (Technician $t): array => [
            'id' => $t->id,
            'name' => $t->name,
            'employee_id' => $t->employee_id,
            'department' => $t->department?->name,
            'production_line' => $t->productionLine?->name,
            'covers_location' => $t->coversLocation($location?->department_id, $location?->production_line_id),
        ])->all());
    }

    public function store(Request $request, CreateWorkOrder $action): JsonResponse
    {
        $this->allow('work_order.work_order.create');

        $data = $request->validate([
            'asset_id' => ['required', 'string', 'size:26'],
            'maintenance_type_id' => ['required', 'string', 'size:26'],
            'template_version_id' => ['nullable', 'string', 'size:26'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'])],
            'requires_shutdown' => ['sometimes', 'boolean'],
            'assigned_team_id' => ['nullable', 'string', 'size:26'],
            'scheduled_start' => ['nullable', 'date'],
            'scheduled_end' => ['nullable', 'date', 'after_or_equal:scheduled_start'],
            // Estimates, not actuals. Actual cost is derived from labour and
            // part records and is never accepted from a client (ADR-064).
            'estimated_parts_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
        ]);

        $data['source'] = 'MANUAL';

        // A caller sending a bare "2026-08-20T08:00" (no offset) means that
        // wall time on their own clock, not UTC — the same boundary
        // `CreateWorkOrderRequest::payload()` (the web form's own request
        // class) applies via `ParsesLocalDateTimes` (SRS 47.2). An ISO
        // string that already carries an offset is left alone either way.
        foreach (['scheduled_start', 'scheduled_end'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = app(TenantTimezone::class)->parseFlexible($data[$field]);
            }
        }

        try {
            $workOrder = $action->handle($data, $this->caller()->auditUserId());
        } catch (ValidationException $e) {
            $status = $e->status ?? 422;
            $code = $status === 409 ? ErrorCode::CONFLICT : ErrorCode::VALIDATION_ERROR;

            throw ApiException::of($code, implode(' ', $e->validator->errors()->all()), $e->errors());
        }

        // fresh(), not load(): `version` has a database default the create()
        // call never reads back onto the in-memory model, the same reason
        // Asset's own creation path sets it explicitly rather than relying
        // on this happening for free.
        return ApiResponse::created($this->detail($workOrder->fresh(['asset:id,asset_code,name', 'factory:id,name'])));
    }

    /**
     * DRAFT to SCHEDULED — committing the job to the queue, which is also
     * the point `TransitionWorkOrder::schedule()` requests approval if the
     * work order's terms call for one (Platform-independent — every tenant
     * approval rule already applies here unchanged).
     */
    public function submitForApproval(WorkOrder $workOrder, TransitionWorkOrder $action): JsonResponse
    {
        return $this->step(
            $workOrder,
            'work_order.work_order.update',
            fn (string $userId) => $action->schedule($workOrder, $userId),
        );
    }

    public function reopen(Request $request, WorkOrder $workOrder, TransitionWorkOrder $action): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        return $this->step(
            $workOrder,
            'work_order.work_order.reopen',
            fn (string $userId) => $action->reopen($workOrder, $userId, $data['reason']),
        );
    }

    public function costs(WorkOrder $workOrder): JsonResponse
    {
        $this->allow('work_order.cost.view');
        $this->assertReachable($workOrder);

        return ApiResponse::ok([
            'currency' => $workOrder->currency,
            'estimated_parts_cost' => $workOrder->estimated_parts_cost,
            'actual_parts_cost' => $workOrder->actual_parts_cost,
            'actual_other_cost' => $workOrder->actual_other_cost,
            'actual_cost' => $workOrder->actual_cost,
        ]);
    }

    public function history(WorkOrder $workOrder): JsonResponse
    {
        $this->allow('work_order.work_order.view');
        $this->assertReachable($workOrder);

        $history = $workOrder->statusHistories()->with('changedBy:id,name')->limit(200)->get();

        return ApiResponse::ok($history->map(fn (WorkOrderStatusHistory $h): array => [
            'id' => $h->id,
            'from_status' => $h->from_status,
            'to_status' => $h->to_status,
            'changed_by' => $h->changedBy === null ? null : ['id' => $h->changedBy->id, 'name' => $h->changedBy->name],
            'changed_at' => $h->changed_at?->toIso8601String(),
            'reason' => $h->reason,
        ])->all());
    }

    /**
     * Mirrors `WorkOrderController::start` — a breakdown's own repair chain
     * is driven from here now, not from a separate "Start repair" button on
     * the breakdown itself: the line's whole roster was already put on this
     * work order when it was raised (`RaiseBreakdownWorkOrder::
     * assignRoster()`), so whichever one of them actually starts it is what
     * tells the breakdown its repair has begun. `TransitionBreakdown::
     * startRepair()` re-checks line coverage on its own account (`assert
     * Coverage()`) — this isn't a second, weaker gate, it's the same one.
     */
    public function start(WorkOrder $workOrder, TransitionWorkOrder $action, TransitionBreakdown $breakdownTransition): JsonResponse
    {
        $this->allow('work_order.work_order.start');
        $this->assertReachable($workOrder);

        $userId = $this->caller()->auditUserId();

        if ($userId === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.step_needs_a_person'));
        }

        try {
            // One transaction across both actions: if the breakdown refuses
            // the sync (line coverage, most likely — `assertCoverage()` is
            // the same hard rule either way this is reached), the work
            // order's own `start()` must not silently stick while the
            // breakdown it belongs to is left behind on ACKNOWLEDGED.
            $workOrder = DB::transaction(function () use ($workOrder, $userId, $action, $breakdownTransition): WorkOrder {
                $workOrder = $action->start($workOrder, $userId);

                if ($workOrder->breakdown_id !== null) {
                    $breakdown = Breakdown::find($workOrder->breakdown_id);

                    if ($breakdown !== null && $breakdown->canTransitionTo('IN_REPAIR')) {
                        $breakdownTransition->startRepair($breakdown, $userId);
                    }
                }

                return $workOrder->fresh();
            });
        } catch (ValidationException $e) {
            $status = $e->status ?? 422;
            $code = $status === 403 ? ErrorCode::FORBIDDEN : ($status === 409 ? ErrorCode::CONFLICT : ErrorCode::VALIDATION_ERROR);

            throw ApiException::of($code, implode(' ', $e->validator->errors()->all()), $e->errors());
        }

        return ApiResponse::ok($this->detail($workOrder));
    }

    public function hold(Request $request, WorkOrder $workOrder, TransitionWorkOrder $action): JsonResponse
    {
        $data = $request->validate([
            // A reason code, not free text. "Why did this job stall" is a
            // question somebody asks of a hundred work orders at once, and
            // free text cannot be counted.
            'reason_code' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->step(
            $workOrder,
            'work_order.work_order.start',
            fn (string $userId) => $action->hold($workOrder, $data['reason_code'], $userId, $data['notes'] ?? null),
        );
    }

    public function resume(WorkOrder $workOrder, TransitionWorkOrder $action): JsonResponse
    {
        return $this->step(
            $workOrder,
            'work_order.work_order.start',
            fn (string $userId) => $action->resume($workOrder, $userId),
        );
    }

    public function complete(WorkOrder $workOrder, TransitionWorkOrder $action): JsonResponse
    {
        return $this->step(
            $workOrder,
            'work_order.work_order.complete',
            fn (string $userId) => $action->complete($workOrder, $userId),
        );
    }

    public function verify(WorkOrder $workOrder, TransitionWorkOrder $action): JsonResponse
    {
        return $this->step(
            $workOrder,
            'work_order.work_order.verify',
            fn (string $userId) => $action->verify($workOrder, $userId),
        );
    }

    public function close(WorkOrder $workOrder, TransitionWorkOrder $action): JsonResponse
    {
        return $this->step(
            $workOrder,
            'work_order.work_order.close',
            fn (string $userId) => $action->close($workOrder, $userId),
        );
    }

    public function cancel(Request $request, WorkOrder $workOrder, TransitionWorkOrder $action): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        return $this->step(
            $workOrder,
            'work_order.work_order.cancel',
            fn (string $userId) => $action->cancel($workOrder, $userId, $data['reason']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(WorkOrder $workOrder): array
    {
        return [
            'id' => $workOrder->id,
            'work_order_number' => $workOrder->work_order_number,
            'title' => $workOrder->title,
            'status' => $workOrder->status,
            'priority' => $workOrder->priority,
            'source' => $workOrder->source,
            'asset' => [
                'id' => $workOrder->asset_id,
                'asset_code' => $workOrder->asset?->asset_code,
                'name' => $workOrder->asset?->name,
            ],
            'factory' => ['id' => $workOrder->factory_id, 'name' => $workOrder->factory?->name],
            'scheduled_start' => $workOrder->scheduled_start?->toIso8601String(),
            'scheduled_end' => $workOrder->scheduled_end?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(WorkOrder $workOrder): array
    {
        // loadMissing, not load: most callers already eager-loaded these
        // (index/show do), and every transition endpoint's route-bound
        // $workOrder does not — this makes detail() correct regardless of
        // which one handed it the model, at the cost of one query on the
        // paths that need it.
        $workOrder->loadMissing(['asset:id,asset_code,name', 'factory:id,name', 'maintenanceType:id,name', 'activeAssignments.technician:id,name']);

        return $this->summary($workOrder) + [
            'version' => $workOrder->version,
            'description' => $workOrder->description,
            'requires_verification' => (bool) $workOrder->requires_verification,
            'requires_shutdown' => (bool) $workOrder->requires_shutdown,
            'approval_status' => $workOrder->approval_status,
            'breakdown_id' => $workOrder->breakdown_id,
            'maintenance_schedule_id' => $workOrder->maintenance_schedule_id,
            // Both added for the Next.js migration: the web's show() eager
            // loads these directly on the model and neither ever needed
            // its own field here before now, since nothing but a detail
            // screen reads them.
            'maintenance_type' => $workOrder->maintenanceType?->name,
            'assignments' => $workOrder->relationLoaded('activeAssignments')
                ? $workOrder->activeAssignments->map(fn ($a): array => [
                    'technician_id' => $a->technician_id,
                    'technician_name' => $a->technician?->name,
                    'assigned_at' => $a->assigned_at?->toIso8601String(),
                ])->all()
                : [],
            'created_at' => $workOrder->created_at?->toIso8601String(),
        ];
    }

    private function step(WorkOrder $workOrder, string $permission, callable $transition): JsonResponse
    {
        $this->allow($permission);
        $this->assertReachable($workOrder);

        $userId = $this->caller()->auditUserId();

        if ($userId === null) {
            // Every one of these lands in the work order's history as "who did
            // this". A machine client has no answer to that question, and
            // writing one down that is not true is worse than refusing.
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.step_needs_a_person'));
        }

        return ApiResponse::ok($this->detail($transition($userId)));
    }

    private function assertReachable(WorkOrder $workOrder): void
    {
        if (! $this->context->canAccessFactory((string) $workOrder->factory_id)) {
            abort(404);
        }
    }
}

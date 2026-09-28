<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Http\Controllers\Api;

use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\Requisition\Actions\FulfilFloorRequisition;
use App\Modules\Requisition\Actions\ManageFloorRequisition;
use App\Modules\Requisition\Models\FloorRequisition;
use App\Modules\Requisition\Models\FloorRequisitionIssue;
use App\Modules\Requisition\Models\FloorRequisitionLine;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Floor requisitions over the wire (Floor/Store Requisition spec §3).
 *
 * Anyone with `requisition.floor.create` raises and sees their own; the
 * store team with `requisition.floor.view_any` sees everyone's. The item
 * department list is never narrowed to the caller's own department — a
 * sewing line legitimately asks the chemical store for spot remover.
 */
class FloorRequisitionApiController extends ApiController
{
    public const VIEW = 'requisition.floor.view_any';

    public const CREATE = 'requisition.floor.create';

    /** The store's side: review, issue, reject, close, take returns. */
    public const ISSUE = 'requisition.floor.issue';

    private const FILTERS = ['status', 'priority', 'factory_id', 'target_item_department_id'];

    private const SORTS = ['fr_no', 'fr_date', 'status', 'created_at'];

    private const RELATIONS = [
        'factory:id,name', 'requester:id,name', 'reviewer:id,name', 'requesterDepartment:id,name',
        'targetItemDepartment:id,name', 'shortagePurchaseRequisition:id,pr_no,status',
        'lines.uom:id,code,name', 'lines.issues.bin:id,code,name', 'lines.issues.fabricRoll:id,roll_no',
    ];

    public function __construct(
        private readonly TenantContext $context,
        private readonly ManageFloorRequisition $requisitions,
        private readonly FulfilFloorRequisition $fulfilment,
    ) {}

    public function formOptions(): JsonResponse
    {
        $this->allow(self::CREATE);

        return ApiResponse::ok([
            'factories' => Factory::whereIn('id', $this->context->accessibleFactoryIds())->orderBy('name')->get(['id', 'name'])->all(),
            // Every active item department, whoever is asking (spec §3.1).
            'item_departments' => ItemDepartment::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'default_item_type'])->all(),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name'])->all(),
            'uoms' => UnitOfMeasure::availableTo($this->context->companyId())->where('active', true)
                ->orderBy('code')->get(['id', 'code', 'name'])->all(),
            'priorities' => FloorRequisition::PRIORITIES,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->allowAny();

        $query = FloorRequisition::query()
            ->with(['factory:id,name', 'requester:id,name', 'targetItemDepartment:id,name'])
            ->withCount('lines')
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if (! $this->caller()->can(self::VIEW) || $request->boolean('mine')) {
            $query->where('requested_by', $this->caller()->auditUserId());
        }

        if (is_string($search = $request->query('search')) && $search !== '') {
            $query->whereRaw('LOWER(fr_no) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'created_at');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (FloorRequisition $requisition): array => $this->summary($requisition),
        );
    }

    public function show(FloorRequisition $requisition): JsonResponse
    {
        $this->allowAny();
        $this->assertReachable($requisition);

        return ApiResponse::ok($this->detail($requisition));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow(self::CREATE);

        $data = $this->validated($request, creating: true);

        if (! $this->context->canAccessFactory($data['factory_id'])) {
            abort(403);
        }

        $requisition = $this->requisitions->create(
            Factory::findOrFail($data['factory_id']), $data, $data['lines'], $this->caller()->auditUserId(),
        );

        return ApiResponse::created($this->detail($requisition));
    }

    public function update(Request $request, FloorRequisition $requisition): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($requisition);
        $this->assertOwnOrStore($requisition);

        $data = $this->validated($request, creating: false);

        return ApiResponse::ok($this->detail($this->requisitions->update($requisition, $data, $data['lines'])));
    }

    public function submit(FloorRequisition $requisition): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($requisition);
        $this->assertOwnOrStore($requisition);

        return ApiResponse::ok($this->detail($this->requisitions->submit($requisition, $this->caller()->auditUserId())));
    }

    public function cancel(Request $request, FloorRequisition $requisition): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($requisition);
        $this->assertOwnOrStore($requisition);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return ApiResponse::ok($this->detail($this->requisitions->cancel($requisition, $data['reason'] ?? null)));
    }

    /**
     * What the store could give this requisition right now — read before
     * anybody types a quantity into the issue form.
     */
    public function availability(FloorRequisition $requisition): JsonResponse
    {
        $this->allow(self::ISSUE);
        $this->assertReachable($requisition);

        return ApiResponse::ok(['lines' => $this->fulfilment->availability($requisition)]);
    }

    public function review(Request $request, FloorRequisition $requisition): JsonResponse
    {
        $this->allow(self::ISSUE);
        $this->assertReachable($requisition);

        $data = $request->validate([
            'lines' => ['nullable', 'array'],
            'lines.*.line_id' => ['required', 'string', 'size:26'],
            'lines.*.approved_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::ok($this->detail($this->fulfilment->startReview(
            $requisition, $data['lines'] ?? [], $this->caller()->auditUserId(),
        )));
    }

    public function issue(Request $request, FloorRequisition $requisition): JsonResponse
    {
        $this->allow(self::ISSUE);
        $this->assertReachable($requisition);

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'string', 'size:26'],
            'lines.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.bin_id' => ['nullable', 'string', 'size:26'],
            'lines.*.lot_no' => ['nullable', 'string', 'max:64'],
            'lines.*.fabric_roll_id' => ['nullable', 'string', 'size:26'],
        ]);

        return ApiResponse::ok($this->detail($this->fulfilment->issue(
            $requisition, $data['lines'], $this->caller()->auditUserId(),
        )));
    }

    public function reject(Request $request, FloorRequisition $requisition): JsonResponse
    {
        $this->allow(self::ISSUE);
        $this->assertReachable($requisition);

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return ApiResponse::ok($this->detail($this->fulfilment->reject(
            $requisition, $data['reason'], $this->caller()->auditUserId(),
        )));
    }

    public function close(FloorRequisition $requisition): JsonResponse
    {
        $this->allow(self::ISSUE);
        $this->assertReachable($requisition);

        return ApiResponse::ok($this->detail($this->fulfilment->close($requisition, $this->caller()->auditUserId())));
    }

    /** A borrowed item coming back over the counter (spec §7). */
    public function returnStock(Request $request, FloorRequisition $requisition): JsonResponse
    {
        $this->allow(self::ISSUE);
        $this->assertReachable($requisition);

        $data = $request->validate([
            'issue_id' => ['required', 'string', 'size:26'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $issue = FloorRequisitionIssue::whereHas(
            'line', fn ($q) => $q->where('floor_requisition_id', $requisition->id),
        )->findOrFail($data['issue_id']);

        $this->fulfilment->returnToStore(
            $issue, isset($data['quantity']) ? (string) $data['quantity'] : null, $this->caller()->auditUserId(),
        );

        return ApiResponse::ok($this->detail($requisition->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'factory_id' => [$creating ? 'required' : 'sometimes', 'string', 'size:26'],
            'target_item_department_id' => [$creating ? 'required' : 'sometimes', 'string', 'size:26'],
            'requester_department_id' => ['nullable', 'string', 'size:26'],
            'fr_date' => ['nullable', 'date'],
            'priority' => ['nullable', Rule::in(FloorRequisition::PRIORITIES)],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'string', 'size:26'],
            'lines.*.uom_id' => ['nullable', 'string', 'size:26'],
            'lines.*.requested_qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.purpose' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(FloorRequisition $requisition): array
    {
        return [
            'id' => $requisition->id,
            'fr_no' => $requisition->fr_no,
            'fr_date' => $requisition->fr_date?->toDateString(),
            'priority' => $requisition->priority,
            'status' => $requisition->status,
            'factory' => $requisition->factory === null ? null : ['id' => $requisition->factory->id, 'name' => $requisition->factory->name],
            'requester' => $requisition->requester === null ? null : ['id' => $requisition->requester->id, 'name' => $requisition->requester->name],
            'target_item_department' => $requisition->targetItemDepartment === null ? null : [
                'id' => $requisition->targetItemDepartment->id, 'name' => $requisition->targetItemDepartment->name,
            ],
            'line_count' => $requisition->lines_count ?? ($requisition->relationLoaded('lines') ? $requisition->lines->count() : null),
            'submitted_at' => $requisition->submitted_at?->toIso8601String(),
            'created_at' => $requisition->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(FloorRequisition $requisition): array
    {
        $requisition->loadMissing(self::RELATIONS);

        $mayAct = $this->caller()->can(self::CREATE) && $this->ownsOrStore($requisition);
        $mayIssue = $this->caller()->can(self::ISSUE);

        return $this->summary($requisition) + [
            'requester_department' => $requisition->requesterDepartment === null ? null : [
                'id' => $requisition->requesterDepartment->id, 'name' => $requisition->requesterDepartment->name,
            ],
            'purpose' => $requisition->purpose,
            'rejection_reason' => $requisition->rejection_reason,
            'cancelled_at' => $requisition->cancelled_at?->toIso8601String(),
            'reviewed_by' => $requisition->reviewer === null ? null : ['id' => $requisition->reviewer->id, 'name' => $requisition->reviewer->name],
            'reviewed_at' => $requisition->reviewed_at?->toIso8601String(),
            'closed_at' => $requisition->closed_at?->toIso8601String(),
            'shortage_pr' => $requisition->shortagePurchaseRequisition === null ? null : [
                'id' => $requisition->shortagePurchaseRequisition->id,
                'pr_no' => $requisition->shortagePurchaseRequisition->pr_no,
                'status' => $requisition->shortagePurchaseRequisition->status,
            ],
            'can_edit' => $requisition->isEditable() && $mayAct,
            'can_submit' => $requisition->isEditable() && $mayAct,
            'can_cancel' => $requisition->canTransitionTo('CANCELLED') && $mayAct,
            'can_review' => $requisition->status === 'SUBMITTED' && $mayIssue,
            'can_issue' => $requisition->isIssuable() && $mayIssue,
            'can_close' => $requisition->canTransitionTo('CLOSED') && $mayIssue,
            'lines' => $requisition->lines->map(fn (FloorRequisitionLine $line): array => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item_code' => $line->item_code,
                'item_name' => $line->item_name,
                'uom' => $line->uom === null ? null : ['id' => $line->uom->id, 'code' => $line->uom->code, 'name' => $line->uom->name],
                'requested_qty' => (string) $line->requested_qty,
                'approved_qty' => $line->approved_qty === null ? null : (string) $line->approved_qty,
                'issued_qty' => (string) $line->issued_qty,
                'returned_qty' => (string) $line->returned_qty,
                'shortage_qty' => (string) $line->shortage_qty,
                'status' => $line->status,
                'purpose' => $line->purpose,
                'rejection_reason' => $line->rejection_reason,
                'issues' => $line->issues->map(fn (FloorRequisitionIssue $issue): array => [
                    'id' => $issue->id,
                    'bin' => $issue->bin === null ? null : ['id' => $issue->bin->id, 'code' => $issue->bin->code, 'name' => $issue->bin->name],
                    'lot_no' => $issue->lot_no,
                    'roll_no' => $issue->fabricRoll?->roll_no,
                    'quantity' => (string) $issue->quantity,
                    'returned_qty' => (string) $issue->returned_qty,
                    'outstanding' => $issue->outstandingQty(),
                    'unit_cost' => (string) $issue->unit_cost,
                    'currency' => $issue->currency,
                    'issued_at' => $issue->issued_at?->toIso8601String(),
                ])->all(),
            ])->all(),
        ];
    }

    /** Either permission reads; `index()`/`show()` narrow to own rows for a requester. */
    private function allowAny(): void
    {
        if (! $this->caller()->can(self::VIEW) && ! $this->caller()->can(self::CREATE)) {
            $this->allow(self::VIEW);
        }
    }

    private function ownsOrStore(FloorRequisition $requisition): bool
    {
        return $this->caller()->can(self::VIEW) || $requisition->requested_by === $this->caller()->auditUserId();
    }

    private function assertOwnOrStore(FloorRequisition $requisition): void
    {
        if (! $this->ownsOrStore($requisition)) {
            abort(403);
        }
    }

    private function assertReachable(FloorRequisition $requisition): void
    {
        if (! $this->context->canAccessFactory((string) $requisition->factory_id)) {
            abort(404);
        }

        if (! $this->ownsOrStore($requisition)) {
            abort(404);
        }
    }
}

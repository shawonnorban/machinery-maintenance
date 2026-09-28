<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Controllers\Api;

use App\Modules\Approval\Actions\DecideApproval;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\Procurement\Actions\ManagePurchaseRequisition;
use App\Modules\Procurement\Models\PurchaseRequisition;
use App\Modules\Procurement\Support\Currencies;
use App\Modules\Procurement\Models\PurchaseRequisitionLine;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Purchase requisitions over the wire (Procurement spec §5).
 *
 * Reads are scoped to the factories the caller can reach; writes go through
 * ManagePurchaseRequisition unchanged (ADR-003). Approval decisions are not
 * taken here — they go through `/approval-requests`, and the detail carries
 * the request id and whether this caller may act on it.
 */
class PurchaseRequisitionApiController extends ApiController
{
    public const VIEW = 'procurement.requisition.view_any';

    public const CREATE = 'procurement.requisition.create';

    private const FILTERS = ['status', 'pr_type', 'priority', 'factory_id'];

    private const SORTS = ['pr_no', 'pr_date', 'status', 'created_at', 'estimated_total'];

    private const RELATIONS = ['factory:id,name', 'store:id,name', 'requester:id,name', 'lines.uom:id,code,name'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly ManagePurchaseRequisition $requisitions,
        private readonly DecideApproval $decisions,
    ) {}

    public function formOptions(): JsonResponse
    {
        $this->allow(self::CREATE);

        $factoryIds = $this->context->accessibleFactoryIds();

        return ApiResponse::ok([
            'factories' => Factory::whereIn('id', $factoryIds)->orderBy('name')->get(['id', 'name'])->all(),
            'stores' => Store::query()->where('active', true)->with('warehouse:id,name,factory_id')->orderBy('name')->get()
                ->filter(fn (Store $store): bool => in_array($store->warehouse?->factory_id, $factoryIds, true))
                ->map(fn (Store $store): array => [
                    'id' => $store->id, 'name' => $store->name, 'factory_id' => $store->warehouse?->factory_id,
                ])->values()->all(),
            'item_departments' => ItemDepartment::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->all(),
            'uoms' => UnitOfMeasure::availableTo($this->context->companyId())->where('active', true)
                ->orderBy('code')->get(['id', 'code', 'name'])->all(),
            // A requisition is an estimate, but an imported item is
            // estimated in the currency it will be bought in.
            'currencies' => Currencies::all(),
            'priorities' => PurchaseRequisition::PRIORITIES,
            'pr_types' => ['MANUAL', 'EMERGENCY'],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = PurchaseRequisition::query()
            ->with(['factory:id,name', 'requester:id,name'])
            ->withCount('lines')
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if (is_string($search = $request->query('search')) && $search !== '') {
            $query->whereRaw('LOWER(pr_no) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        if ($request->boolean('mine')) {
            $query->where('requested_by', $this->caller()->auditUserId());
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'created_at');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (PurchaseRequisition $requisition): array => $this->summary($requisition),
        );
    }

    public function show(PurchaseRequisition $requisition): JsonResponse
    {
        $this->allow(self::VIEW);
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
            Factory::findOrFail($data['factory_id']),
            $data,
            $data['lines'],
            $this->caller()->auditUserId(),
        );

        return ApiResponse::created($this->detail($requisition));
    }

    public function update(Request $request, PurchaseRequisition $requisition): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($requisition);

        $data = $this->validated($request, creating: false);

        return ApiResponse::ok($this->detail($this->requisitions->update($requisition, $data, $data['lines'])));
    }

    public function submit(PurchaseRequisition $requisition): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($requisition);

        return ApiResponse::ok($this->detail($this->requisitions->submit($requisition, $this->caller()->auditUserId())));
    }

    public function cancel(Request $request, PurchaseRequisition $requisition): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($requisition);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return ApiResponse::ok($this->detail(
            $this->requisitions->cancel($requisition, $data['reason'] ?? null, $this->caller()->auditUserId()),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'factory_id' => [$creating ? 'required' : 'sometimes', 'string', 'size:26'],
            'store_id' => ['nullable', 'string', 'size:26'],
            'pr_date' => ['nullable', 'date'],
            'pr_type' => ['nullable', Rule::in(['MANUAL', 'EMERGENCY'])],
            'priority' => ['nullable', Rule::in(PurchaseRequisition::PRIORITIES)],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'string', 'size:26'],
            'lines.*.uom_id' => ['nullable', 'string', 'size:26'],
            'lines.*.requested_qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.estimated_rate' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.required_date' => ['nullable', 'date'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(PurchaseRequisition $requisition): array
    {
        return [
            'id' => $requisition->id,
            'pr_no' => $requisition->pr_no,
            'pr_date' => $requisition->pr_date?->toDateString(),
            'pr_type' => $requisition->pr_type,
            'priority' => $requisition->priority,
            'status' => $requisition->status,
            'approval_status' => $requisition->approval_status,
            'factory' => $requisition->factory === null ? null : ['id' => $requisition->factory->id, 'name' => $requisition->factory->name],
            'requester' => $requisition->requester === null ? null : ['id' => $requisition->requester->id, 'name' => $requisition->requester->name],
            'estimated_total' => (string) $requisition->estimated_total,
            'currency' => $requisition->currency,
            'line_count' => $requisition->lines_count ?? ($requisition->relationLoaded('lines') ? $requisition->lines->count() : null),
            'submitted_at' => $requisition->submitted_at?->toIso8601String(),
            'created_at' => $requisition->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(PurchaseRequisition $requisition): array
    {
        $requisition->loadMissing(self::RELATIONS);

        $approval = $requisition->approval_request_id === null
            ? null
            : ApprovalRequest::find($requisition->approval_request_id);
        $viewer = $this->caller()->user;

        return $this->summary($requisition) + [
            'store' => $requisition->store === null ? null : ['id' => $requisition->store->id, 'name' => $requisition->store->name],
            'notes' => $requisition->notes,
            'approved_at' => $requisition->approved_at?->toIso8601String(),
            'rejected_at' => $requisition->rejected_at?->toIso8601String(),
            'rejection_reason' => $requisition->rejection_reason,
            'cancelled_at' => $requisition->cancelled_at?->toIso8601String(),
            'approval_request' => $approval === null ? null : [
                'id' => $approval->id,
                'status' => $approval->status,
                'current_step' => $approval->current_step,
                'total_steps' => $approval->total_steps,
                'can_act' => $viewer !== null && $this->decisions->canAct($approval, $viewer),
            ],
            'can_edit' => $requisition->isEditable() && $this->caller()->can(self::CREATE),
            'can_submit' => $requisition->isEditable() && $this->caller()->can(self::CREATE),
            'can_cancel' => $requisition->canTransitionTo('CANCELLED') && $this->caller()->can(self::CREATE),
            'can_create_order' => $requisition->isConvertible() && $this->caller()->can(PurchaseOrderApiController::CREATE),
            'open_duplicates' => $this->requisitions->openDuplicates($requisition),
            'lines' => $requisition->lines->map(fn (PurchaseRequisitionLine $line): array => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item_code' => $line->item_code,
                'item_name' => $line->item_name,
                'uom' => $line->uom === null ? null : ['id' => $line->uom->id, 'code' => $line->uom->code, 'name' => $line->uom->name],
                'requested_qty' => (string) $line->requested_qty,
                'ordered_qty' => (string) $line->ordered_qty,
                'pending_qty' => $line->pendingQty(),
                'required_date' => $line->required_date?->toDateString(),
                'estimated_rate' => $line->estimated_rate === null ? null : (string) $line->estimated_rate,
                'estimated_amount' => $line->estimatedAmount(),
                'current_stock_qty' => $line->current_stock_qty === null ? null : (string) $line->current_stock_qty,
                'already_ordered_qty' => $line->already_ordered_qty === null ? null : (string) $line->already_ordered_qty,
                'notes' => $line->notes,
            ])->all(),
        ];
    }

    private function assertReachable(PurchaseRequisition $requisition): void
    {
        if (! $this->context->canAccessFactory((string) $requisition->factory_id)) {
            abort(404);
        }
    }
}

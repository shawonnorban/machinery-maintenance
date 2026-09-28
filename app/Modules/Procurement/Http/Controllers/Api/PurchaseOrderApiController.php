<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Controllers\Api;

use App\Modules\Approval\Actions\DecideApproval;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Finance\Models\PaymentType;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\Procurement\Actions\ManagePurchaseOrder;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Support\Currencies;
use App\Modules\Procurement\Models\PurchaseOrderLine;
use App\Modules\Procurement\Models\PurchaseOrderRevision;
use App\Modules\Procurement\Models\PurchaseRequisition;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Purchase orders over the wire (Procurement spec §7, local flow).
 */
class PurchaseOrderApiController extends ApiController
{
    public const VIEW = 'procurement.order.view_any';

    public const CREATE = 'procurement.order.create';

    private const FILTERS = ['status', 'vendor_id', 'factory_id', 'purchase_type'];

    private const SORTS = ['po_no', 'po_date', 'status', 'created_at', 'subtotal'];

    private const RELATIONS = [
        'factory:id,name', 'vendor:id,name,code', 'paymentType:id,name', 'deliveryStore:id,name',
        'creator:id,name', 'lines.uom:id,code,name', 'lines.requisitionLine:id,purchase_requisition_id', 'revisions',
    ];

    public function __construct(
        private readonly TenantContext $context,
        private readonly ManagePurchaseOrder $orders,
        private readonly DecideApproval $decisions,
    ) {}

    public function formOptions(): JsonResponse
    {
        $this->allow(self::CREATE);

        $factoryIds = $this->context->accessibleFactoryIds();
        $companyId = $this->context->companyId();

        return ApiResponse::ok([
            'factories' => Factory::whereIn('id', $factoryIds)->orderBy('name')->get(['id', 'name'])->all(),
            'vendors' => Vendor::query()->where('status', 'ACTIVE')->whereIn('vendor_type', ['SUPPLIER', 'BOTH'])
                ->orderBy('name')->get(['id', 'name', 'code', 'default_currency', 'default_payment_type_id', 'default_payment_terms'])->all(),
            'stores' => Store::query()->where('active', true)->with('warehouse:id,name,factory_id')->orderBy('name')->get()
                ->filter(fn (Store $store): bool => in_array($store->warehouse?->factory_id, $factoryIds, true))
                ->map(fn (Store $store): array => [
                    'id' => $store->id, 'name' => $store->name, 'factory_id' => $store->warehouse?->factory_id,
                ])->values()->all(),
            'payment_types' => PaymentType::availableTo($companyId)->where('active', true)->orderBy('name')->get(['id', 'name'])->all(),
            'item_departments' => ItemDepartment::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->all(),
            'uoms' => UnitOfMeasure::availableTo($companyId)->where('active', true)->orderBy('code')->get(['id', 'code', 'name'])->all(),
            'purchase_types' => PurchaseOrder::PURCHASE_TYPES,
            'currencies' => Currencies::all(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = PurchaseOrder::query()
            ->with(['factory:id,name', 'vendor:id,name,code'])
            ->withCount('lines')
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if (is_string($search = $request->query('search')) && $search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(po_no) LIKE ?', [$term])
                ->orWhereHas('vendor', fn ($v) => $v->whereRaw('LOWER(name) LIKE ?', [$term])));
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'created_at');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (PurchaseOrder $order): array => $this->summary($order),
        );
    }

    public function show(PurchaseOrder $order): JsonResponse
    {
        $this->allow(self::VIEW);
        $this->assertReachable($order);

        return ApiResponse::ok($this->detail($order));
    }

    /** The outstanding lines a goods receipt against this order would pre-fill. */
    public function receivable(PurchaseOrder $order): JsonResponse
    {
        $this->allow(GoodsReceiptApiController::CREATE);
        $this->assertReachable($order);

        $order->loadMissing(['lines.uom:id,code,name', 'lines.item:id,tracking_mode,active']);

        return ApiResponse::ok([
            'order' => $this->summary($order),
            'receivable' => $order->isReceivable(),
            'lines' => $order->lines
                ->filter(fn (PurchaseOrderLine $line): bool => bccomp($line->pendingQty(), '0', 4) > 0)
                ->map(fn (PurchaseOrderLine $line): array => $this->line($line) + [
                    'tracking_mode' => $line->item?->tracking_mode,
                ])->values()->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow(self::CREATE);

        $data = $request->validate($this->headerRules(creating: true) + $this->lineRules());

        if (! $this->context->canAccessFactory($data['factory_id'])) {
            abort(403);
        }

        $order = $this->orders->create(
            Factory::findOrFail($data['factory_id']),
            Vendor::findOrFail($data['vendor_id']),
            $data,
            $data['lines'],
            $this->caller()->auditUserId(),
        );

        return ApiResponse::created($this->detail($order));
    }

    /** `POST /purchase-requisitions/{requisition}/orders` */
    public function storeFromRequisition(Request $request, PurchaseRequisition $requisition): JsonResponse
    {
        $this->allow(self::CREATE);

        if (! $this->context->canAccessFactory((string) $requisition->factory_id)) {
            abort(404);
        }

        $data = $request->validate($this->headerRules(creating: false) + [
            'vendor_id' => ['required', 'string', 'size:26'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.pr_line_id' => ['required', 'string', 'size:26'],
            'lines.*.ordered_qty' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.rate' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.required_date' => ['nullable', 'date'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $order = $this->orders->createFromRequisition(
            $requisition,
            Vendor::findOrFail($data['vendor_id']),
            $data,
            $data['lines'],
            $this->caller()->auditUserId(),
        );

        return ApiResponse::created($this->detail($order));
    }

    public function update(Request $request, PurchaseOrder $order): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($order);

        $data = $request->validate($this->headerRules(creating: false) + $this->lineRules());

        return ApiResponse::ok($this->detail($this->orders->update($order, $data, $data['lines'])));
    }

    public function submit(PurchaseOrder $order): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($order);

        return ApiResponse::ok($this->detail($this->orders->submit($order, $this->caller()->auditUserId())));
    }

    public function revise(Request $request, PurchaseOrder $order): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($order);

        $data = $request->validate($this->headerRules(creating: false) + $this->lineRules() + [
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        return ApiResponse::ok($this->detail(
            $this->orders->revise($order, $data['reason'], $data, $data['lines'], $this->caller()->auditUserId()),
        ));
    }

    public function cancel(Request $request, PurchaseOrder $order): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($order);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return ApiResponse::ok($this->detail(
            $this->orders->cancel($order, $data['reason'] ?? null, $this->caller()->auditUserId()),
        ));
    }

    public function close(PurchaseOrder $order): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($order);

        return ApiResponse::ok($this->detail($this->orders->close($order, $this->caller()->auditUserId())));
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function headerRules(bool $creating): array
    {
        return [
            'factory_id' => [$creating ? 'required' : 'sometimes', 'string', 'size:26'],
            'vendor_id' => [$creating ? 'required' : 'sometimes', 'string', 'size:26'],
            'po_date' => ['nullable', 'date'],
            'purchase_type' => ['nullable', Rule::in(PurchaseOrder::PURCHASE_TYPES)],
            // Import against a customs bond (Shared Systems §3.3).
            'bond_applicable' => ['nullable', 'boolean'],
            'bond_licence_id' => ['nullable', 'string', 'size:26'],
            'duty_exemption_reference' => ['nullable', 'string', 'max:64'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_type_id' => ['nullable', 'string', 'size:26'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'delivery_date' => ['nullable', 'date'],
            'delivery_store_id' => ['nullable', 'string', 'size:26'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function lineRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'string', 'size:26'],
            'lines.*.uom_id' => ['nullable', 'string', 'size:26'],
            'lines.*.ordered_qty' => ['required', 'numeric', 'gt:0'],
            // Optional since rate contracts (spec §14): a line that says
            // nothing takes the agreed price, and one that names a rate
            // keeps it. Omitting it with no contract in force still
            // produces a zero-rate line, which is what it always did.
            'lines.*.rate' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.required_date' => ['nullable', 'date'],
            'lines.*.pr_line_id' => ['nullable', 'string', 'size:26'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(PurchaseOrder $order): array
    {
        return [
            'id' => $order->id,
            'po_no' => $order->po_no,
            'po_date' => $order->po_date?->toDateString(),
            'purchase_type' => $order->purchase_type,
            'bond_applicable' => (bool) $order->bond_applicable,
            'bond_licence_id' => $order->bond_licence_id,
            'duty_exemption_reference' => $order->duty_exemption_reference,
            'status' => $order->status,
            'approval_status' => $order->approval_status,
            'revision_no' => $order->revision_no,
            'factory' => $order->factory === null ? null : ['id' => $order->factory->id, 'name' => $order->factory->name],
            'vendor' => $order->vendor === null ? null : ['id' => $order->vendor->id, 'name' => $order->vendor->name, 'code' => $order->vendor->code],
            'subtotal' => (string) $order->subtotal,
            'currency' => $order->currency,
            'delivery_date' => $order->delivery_date?->toDateString(),
            'line_count' => $order->lines_count ?? ($order->relationLoaded('lines') ? $order->lines->count() : null),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(PurchaseOrder $order): array
    {
        $order->loadMissing(self::RELATIONS);

        $approval = $order->approval_request_id === null ? null : ApprovalRequest::find($order->approval_request_id);
        $viewer = $this->caller()->user;

        return $this->summary($order) + [
            'payment_type' => $order->paymentType === null ? null : ['id' => $order->paymentType->id, 'name' => $order->paymentType->name],
            'payment_terms' => $order->payment_terms,
            'delivery_store' => $order->deliveryStore === null ? null : ['id' => $order->deliveryStore->id, 'name' => $order->deliveryStore->name],
            'creator' => $order->creator === null ? null : ['id' => $order->creator->id, 'name' => $order->creator->name],
            'notes' => $order->notes,
            'approved_subtotal' => $order->approved_subtotal === null ? null : (string) $order->approved_subtotal,
            'submitted_at' => $order->submitted_at?->toIso8601String(),
            'approved_at' => $order->approved_at?->toIso8601String(),
            'closed_at' => $order->closed_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'approval_request' => $approval === null ? null : [
                'id' => $approval->id,
                'status' => $approval->status,
                'current_step' => $approval->current_step,
                'total_steps' => $approval->total_steps,
                'can_act' => $viewer !== null && $this->decisions->canAct($approval, $viewer),
            ],
            'can_edit' => $order->isEditable() && $this->caller()->can(self::CREATE),
            'can_submit' => $order->isEditable() && $this->caller()->can(self::CREATE),
            'can_revise' => in_array($order->status, ['APPROVED', 'PARTIALLY_RECEIVED'], true) && $this->caller()->can(self::CREATE),
            'can_cancel' => $order->canTransitionTo('CANCELLED') && $this->caller()->can(self::CREATE),
            'can_close' => $order->canTransitionTo('CLOSED') && $this->caller()->can(self::CREATE),
            'can_receive' => $order->isReceivable() && $this->caller()->can(GoodsReceiptApiController::CREATE),
            'requisitions' => $order->lines->pluck('requisitionLine')->filter()->unique('purchase_requisition_id')
                ->map(fn ($line): array => ['id' => $line->purchase_requisition_id])->values()->all(),
            'lines' => $order->lines->map(fn (PurchaseOrderLine $line): array => $this->line($line))->all(),
            'revisions' => $order->revisions->map(fn (PurchaseOrderRevision $revision): array => [
                'id' => $revision->id,
                'revision_no' => $revision->revision_no,
                'reason' => $revision->reason,
                'revised_by' => $revision->revised_by,
                'revised_at' => $revision->revised_at?->toIso8601String(),
                'snapshot' => $revision->snapshot_json,
            ])->all(),
            'receipts' => GoodsReceipt::where('po_id', $order->id)->orderByDesc('created_at')->get()
                ->map(fn (GoodsReceipt $receipt): array => [
                    'id' => $receipt->id, 'grn_no' => $receipt->grn_no, 'status' => $receipt->status,
                    'grn_date' => $receipt->grn_date?->toDateString(),
                ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(PurchaseOrderLine $line): array
    {
        return [
            'id' => $line->id,
            'pr_line_id' => $line->pr_line_id,
            'item_id' => $line->item_id,
            'item_code' => $line->item_code,
            'item_name' => $line->item_name,
            'uom' => $line->uom === null ? null : ['id' => $line->uom->id, 'code' => $line->uom->code, 'name' => $line->uom->name],
            'ordered_qty' => (string) $line->ordered_qty,
            'received_qty' => (string) $line->received_qty,
            'pending_qty' => $line->pendingQty(),
            'rate' => (string) $line->rate,
            'amount' => (string) $line->amount,
            'required_date' => $line->required_date?->toDateString(),
            'notes' => $line->notes,
        ];
    }

    private function assertReachable(PurchaseOrder $order): void
    {
        if (! $this->context->canAccessFactory((string) $order->factory_id)) {
            abort(404);
        }
    }
}

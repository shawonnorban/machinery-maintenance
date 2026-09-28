<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Controllers\Api;

use App\Modules\Procurement\Actions\ManageSupplierAdvance;
use App\Modules\Procurement\Models\SupplierAdvance;
use App\Modules\Procurement\Models\SupplierAdvanceAdjustment;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Money paid to a supplier before the goods arrive (Procurement spec §13).
 *
 * The outstanding figure is the point of the screen, so `index` can be
 * narrowed to what is still unaccounted for and every row carries it.
 */
class SupplierAdvanceApiController extends ApiController
{
    public const VIEW = 'procurement.advance.view_any';

    public const MANAGE = 'procurement.advance.manage';

    private const FILTERS = ['status', 'factory_id', 'vendor_id', 'purchase_order_id'];

    private const SORTS = ['advance_no', 'advance_date', 'status', 'amount', 'created_at'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly ManageSupplierAdvance $advances,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = SupplierAdvance::query()
            ->with(['factory:id,name', 'vendor:id,name,code', 'purchaseOrder:id,po_no'])
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if (is_string($search = $request->query('search')) && $search !== '') {
            $query->whereRaw('LOWER(advance_no) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        // The outstanding-advance report of §13 is this filter.
        if ($request->boolean('outstanding')) {
            $query->whereIn('status', ['OPEN', 'PARTIALLY_ADJUSTED']);
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'advance_date');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (SupplierAdvance $advance): array => $this->summary($advance),
        );
    }

    public function show(SupplierAdvance $advance): JsonResponse
    {
        $this->allow(self::VIEW);
        $this->assertReachable($advance);

        return ApiResponse::ok($this->detail($advance));
    }

    /** What one supplier is still holding of ours, across every advance. */
    public function outstanding(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $data = $request->validate(['vendor_id' => ['required', 'string', 'size:26']]);

        return ApiResponse::ok($this->advances->outstandingFor($data['vendor_id']));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow(self::MANAGE);

        $data = $request->validate([
            'factory_id' => ['required', 'string', 'size:26'],
            'vendor_id' => ['required', 'string', 'size:26'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'advance_date' => ['nullable', 'date'],
            'purchase_order_id' => ['nullable', 'string', 'size:26'],
            'payment_type_id' => ['nullable', 'string', 'size:26'],
            'reference_no' => ['nullable', 'string', 'max:64'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $this->context->canAccessFactory($data['factory_id'])) {
            abort(403);
        }

        return ApiResponse::created($this->detail($this->advances->create(
            Factory::findOrFail($data['factory_id']), $data, $this->caller()->auditUserId(),
        )));
    }

    public function adjust(Request $request, SupplierAdvance $advance): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($advance);

        return ApiResponse::ok($this->detail($this->advances->adjust(
            $advance, $this->adjustmentPayload($request), $this->caller()->auditUserId(),
        )));
    }

    public function refund(Request $request, SupplierAdvance $advance): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($advance);

        return ApiResponse::ok($this->detail($this->advances->refund(
            $advance, $this->adjustmentPayload($request), $this->caller()->auditUserId(),
        )));
    }

    public function reverse(Request $request, SupplierAdvanceAdjustment $adjustment): JsonResponse
    {
        $this->allow(self::MANAGE);

        $advance = SupplierAdvance::findOrFail($adjustment->supplier_advance_id);
        $this->assertReachable($advance);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return ApiResponse::ok($this->detail($this->advances->reverse($adjustment, $data['reason'] ?? null)));
    }

    public function cancel(Request $request, SupplierAdvance $advance): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($advance);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return ApiResponse::ok($this->detail($this->advances->cancel($advance, $data['reason'] ?? null)));
    }

    /**
     * @return array<string, mixed>
     */
    private function adjustmentPayload(Request $request): array
    {
        return $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'goods_receipt_id' => ['nullable', 'string', 'size:26'],
            'reference_no' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function canAdjust(SupplierAdvance $advance): bool
    {
        return $advance->isOpen() && $this->caller()->can(self::MANAGE);
    }

    /**
     * Cancelling is only clean while nothing has been drawn against
     * the advance; once it has, the money has to be unwound rather
     * than the row struck out.
     */
    private function canCancel(SupplierAdvance $advance): bool
    {
        return $this->canAdjust($advance)
            && bccomp((string) $advance->adjusted_amount, '0', 4) !== 1
            && bccomp((string) $advance->refunded_amount, '0', 4) !== 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(SupplierAdvance $advance): array
    {
        return [
            'id' => $advance->id,
            'advance_no' => $advance->advance_no,
            'advance_date' => $advance->advance_date?->toDateString(),
            'status' => $advance->status,
            'amount' => (string) $advance->amount,
            'adjusted_amount' => (string) $advance->adjusted_amount,
            'refunded_amount' => (string) $advance->refunded_amount,
            'outstanding' => $advance->outstanding(),
            'currency' => $advance->currency,
            'reference_no' => $advance->reference_no,
            'factory' => $advance->factory === null ? null : ['id' => $advance->factory->id, 'name' => $advance->factory->name],
            'vendor' => $advance->vendor === null ? null : ['id' => $advance->vendor->id, 'name' => $advance->vendor->name, 'code' => $advance->vendor->code],
            'purchase_order' => $advance->purchaseOrder === null ? null : ['id' => $advance->purchaseOrder->id, 'po_no' => $advance->purchaseOrder->po_no],
            'created_at' => $advance->created_at?->toIso8601String(),
            // On the summary, not only the detail: advances have no
            // detail page, so the list's own row menu is the only place
            // these are ever read. Leaving them off left every menu
            // rendering empty, which reads as a broken button rather
            // than as "nothing you may do here".
            'can_adjust' => $this->canAdjust($advance),
            'can_cancel' => $this->canCancel($advance),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(SupplierAdvance $advance): array
    {
        $advance->loadMissing([
            'factory:id,name', 'vendor:id,name,code', 'purchaseOrder:id,po_no',
            'creator:id,name', 'adjustments.goodsReceipt:id,grn_no', 'adjustments.adjuster:id,name',
        ]);

        return $this->summary($advance) + [
            'notes' => $advance->notes,
            'created_by' => $advance->creator === null ? null : ['id' => $advance->creator->id, 'name' => $advance->creator->name],
            'closed_at' => $advance->closed_at?->toIso8601String(),
            'cancelled_at' => $advance->cancelled_at?->toIso8601String(),
            'adjustments' => $advance->adjustments->map(fn (SupplierAdvanceAdjustment $row): array => [
                'id' => $row->id,
                'adjustment_type' => $row->adjustment_type,
                'amount' => (string) $row->amount,
                'goods_receipt' => $row->goodsReceipt === null ? null : ['id' => $row->goodsReceipt->id, 'grn_no' => $row->goodsReceipt->grn_no],
                'reference_no' => $row->reference_no,
                'notes' => $row->notes,
                'adjusted_by' => $row->adjuster?->name,
                'adjusted_at' => $row->adjusted_at?->toIso8601String(),
                'reversed_at' => $row->reversed_at?->toIso8601String(),
                'live' => $row->isLive(),
            ])->all(),
        ];
    }

    private function assertReachable(SupplierAdvance $advance): void
    {
        if (! $this->context->canAccessFactory((string) $advance->factory_id)) {
            abort(404);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Controllers\Api;

use App\Modules\Procurement\Actions\ManageLandedCost;
use App\Modules\Procurement\Models\LandedCostAllocation;
use App\Modules\Procurement\Models\LandedCostCharge;
use App\Modules\Procurement\Models\LandedCostVoucher;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Landed cost over the wire (Procurement spec §11).
 *
 * `preview` exists because posting cannot be undone: the screen shows what
 * each line would take, and by which basis, before anybody commits to it.
 * Posting sits behind its own permission for the same reason.
 */
class LandedCostApiController extends ApiController
{
    public const VIEW = 'procurement.landed_cost.view_any';

    public const MANAGE = 'procurement.landed_cost.manage';

    /** Separate from `.manage`: this is the irreversible one. */
    public const POST = 'procurement.landed_cost.post';

    private const FILTERS = ['status', 'factory_id', 'basis'];

    private const SORTS = ['voucher_no', 'voucher_date', 'status', 'total_charges', 'created_at'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly ManageLandedCost $vouchers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = LandedCostVoucher::query()
            ->with(['factory:id,name'])
            ->withCount(['charges', 'receipts'])
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if (is_string($search = $request->query('search')) && $search !== '') {
            $query->whereRaw('LOWER(voucher_no) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'voucher_date');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (LandedCostVoucher $voucher): array => $this->summary($voucher),
        );
    }

    public function show(LandedCostVoucher $voucher): JsonResponse
    {
        $this->allow(self::VIEW);
        $this->assertReachable($voucher);

        return ApiResponse::ok($this->detail($voucher));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow(self::MANAGE);

        $data = $this->validated($request, creating: true);

        if (! $this->context->canAccessFactory($data['factory_id'])) {
            abort(403);
        }

        return ApiResponse::created($this->detail($this->vouchers->create(
            Factory::findOrFail($data['factory_id']),
            $data,
            $data['charges'],
            $data['goods_receipt_ids'],
            $this->caller()->auditUserId(),
        )));
    }

    public function update(Request $request, LandedCostVoucher $voucher): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($voucher);

        $data = $this->validated($request, creating: false);

        return ApiResponse::ok($this->detail($this->vouchers->update(
            $voucher, $data, $data['charges'], $data['goods_receipt_ids'],
        )));
    }

    /** What each line would take, before anybody commits to it. */
    public function preview(LandedCostVoucher $voucher): JsonResponse
    {
        $this->allow(self::VIEW);
        $this->assertReachable($voucher);

        return ApiResponse::ok([
            'basis' => $voucher->basis,
            'total_charges' => (string) $voucher->total_charges,
            'lines' => $this->vouchers->preview($voucher),
        ]);
    }

    public function post(LandedCostVoucher $voucher): JsonResponse
    {
        $this->allow(self::POST);
        $this->assertReachable($voucher);

        return ApiResponse::ok($this->detail($this->vouchers->post($voucher, $this->caller()->auditUserId())));
    }

    public function cancel(Request $request, LandedCostVoucher $voucher): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($voucher);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return ApiResponse::ok($this->detail($this->vouchers->cancel($voucher, $data['reason'] ?? null)));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'factory_id' => [$creating ? 'required' : 'sometimes', 'string', 'size:26'],
            'voucher_date' => ['nullable', 'date'],
            'basis' => ['nullable', Rule::in(LandedCostVoucher::BASES)],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'goods_receipt_ids' => ['required', 'array', 'min:1'],
            'goods_receipt_ids.*' => ['string', 'size:26'],
            'charges' => ['required', 'array', 'min:1'],
            'charges.*.charge_type' => ['required', Rule::in(LandedCostCharge::TYPES)],
            'charges.*.amount' => ['required', 'numeric', 'gt:0'],
            'charges.*.label' => ['nullable', 'string', 'max:160'],
            'charges.*.vendor_id' => ['nullable', 'string', 'size:26'],
            'charges.*.reference_no' => ['nullable', 'string', 'max:64'],
            'charges.*.notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(LandedCostVoucher $voucher): array
    {
        return [
            'id' => $voucher->id,
            'voucher_no' => $voucher->voucher_no,
            'voucher_date' => $voucher->voucher_date?->toDateString(),
            'basis' => $voucher->basis,
            'currency' => $voucher->currency,
            'total_charges' => (string) $voucher->total_charges,
            'status' => $voucher->status,
            'factory' => $voucher->factory === null ? null : ['id' => $voucher->factory->id, 'name' => $voucher->factory->name],
            'charge_count' => $voucher->charges_count ?? null,
            'receipt_count' => $voucher->receipts_count ?? null,
            'created_at' => $voucher->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(LandedCostVoucher $voucher): array
    {
        $voucher->loadMissing([
            'factory:id,name', 'creator:id,name', 'poster:id,name',
            'charges.vendor:id,name,code', 'receipts.goodsReceipt:id,grn_no,status,grn_date',
            'allocations.item:id,item_code,name',
        ]);

        $mayManage = $this->caller()->can(self::MANAGE);

        return $this->summary($voucher) + [
            'notes' => $voucher->notes,
            'created_by' => $voucher->creator === null ? null : ['id' => $voucher->creator->id, 'name' => $voucher->creator->name],
            'posted_by' => $voucher->poster === null ? null : ['id' => $voucher->poster->id, 'name' => $voucher->poster->name],
            'posted_at' => $voucher->posted_at?->toIso8601String(),
            'cancelled_at' => $voucher->cancelled_at?->toIso8601String(),
            'can_edit' => $voucher->isEditable() && $mayManage,
            'can_post' => $voucher->canTransitionTo('POSTED') && $this->caller()->can(self::POST),
            'can_cancel' => $voucher->canTransitionTo('CANCELLED') && $mayManage,
            'charges' => $voucher->charges->map(fn (LandedCostCharge $charge): array => [
                'id' => $charge->id,
                'charge_type' => $charge->charge_type,
                'label' => $charge->label,
                'vendor' => $charge->vendor === null ? null : ['id' => $charge->vendor->id, 'name' => $charge->vendor->name],
                'amount' => (string) $charge->amount,
                'currency' => $charge->currency,
                'reference_no' => $charge->reference_no,
            ])->all(),
            'receipts' => $voucher->receipts->map(fn ($row): array => [
                'id' => $row->goodsReceipt?->id,
                'grn_no' => $row->goodsReceipt?->grn_no,
                'grn_date' => $row->goodsReceipt?->grn_date?->toDateString(),
                'status' => $row->goodsReceipt?->status,
            ])->all(),
            // Only once posted: before that the numbers live in `preview`.
            'allocations' => $voucher->allocations->map(fn (LandedCostAllocation $row): array => [
                'id' => $row->id,
                'item_code' => $row->item?->item_code,
                'item_name' => $row->item?->name,
                'quantity' => (string) $row->quantity,
                'basis_value' => (string) $row->basis_value,
                'share_amount' => (string) $row->share_amount,
                'unit_uplift' => (string) $row->unit_uplift,
                'previous_unit_cost' => (string) $row->previous_unit_cost,
                'new_unit_cost' => (string) $row->new_unit_cost,
            ])->all(),
        ];
    }

    private function assertReachable(LandedCostVoucher $voucher): void
    {
        if (! $this->context->canAccessFactory((string) $voucher->factory_id)) {
            abort(404);
        }
    }
}

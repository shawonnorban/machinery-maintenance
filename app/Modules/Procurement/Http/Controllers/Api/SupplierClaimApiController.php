<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Controllers\Api;

use App\Modules\Procurement\Actions\ManageSupplierClaim;
use App\Modules\Procurement\Models\SupplierClaim;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Claims against a supplier (Procurement spec §12).
 *
 * Money, not goods: nothing here moves stock. A claim is what is left when
 * the cloth has already been cut and cannot go back.
 */
class SupplierClaimApiController extends ApiController
{
    public const VIEW = 'procurement.claim.view_any';

    public const MANAGE = 'procurement.claim.manage';

    private const FILTERS = ['status', 'factory_id', 'vendor_id', 'reason', 'goods_receipt_id'];

    private const SORTS = ['claim_no', 'claim_date', 'status', 'claim_amount', 'created_at'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly ManageSupplierClaim $claims,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = SupplierClaim::query()
            ->with(['factory:id,name', 'vendor:id,name,code', 'goodsReceipt:id,grn_no'])
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if (is_string($search = $request->query('search')) && $search !== '') {
            $query->whereRaw('LOWER(claim_no) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        // What the buying desk chases: claims still being argued about.
        if ($request->boolean('open')) {
            $query->whereIn('status', ['RAISED', 'ACCEPTED', 'PARTIALLY_SETTLED']);
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'claim_date');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (SupplierClaim $claim): array => $this->detail($claim),
        );
    }

    public function show(SupplierClaim $claim): JsonResponse
    {
        $this->allow(self::VIEW);
        $this->assertReachable($claim);

        return ApiResponse::ok($this->detail($claim));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow(self::MANAGE);

        $data = $request->validate([
            'factory_id' => ['required', 'string', 'size:26'],
            'vendor_id' => ['required', 'string', 'size:26'],
            'reason' => ['required', Rule::in(SupplierClaim::REASONS)],
            'claim_amount' => ['required', 'numeric', 'gt:0'],
            'claim_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'goods_receipt_id' => ['nullable', 'string', 'size:26'],
            'purchase_return_id' => ['nullable', 'string', 'size:26'],
            'fabric_roll_id' => ['nullable', 'string', 'size:26'],
            'item_id' => ['nullable', 'string', 'size:26'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $this->context->canAccessFactory($data['factory_id'])) {
            abort(403);
        }

        return ApiResponse::created($this->detail($this->claims->create(
            Factory::findOrFail($data['factory_id']), $data, $this->caller()->auditUserId(),
        )));
    }

    public function accept(Request $request, SupplierClaim $claim): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($claim);

        $data = $request->validate(['reference' => ['nullable', 'string', 'max:64']]);

        return ApiResponse::ok($this->detail($this->claims->accept($claim, $data['reference'] ?? null)));
    }

    /** Money actually recovered, in whatever instalments it arrives. */
    public function settle(Request $request, SupplierClaim $claim): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($claim);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::ok($this->detail($this->claims->settle($claim, $data)));
    }

    public function reject(Request $request, SupplierClaim $claim): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($claim);

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return ApiResponse::ok($this->detail($this->claims->reject($claim, $data['reason'])));
    }

    public function withdraw(Request $request, SupplierClaim $claim): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($claim);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return ApiResponse::ok($this->detail($this->claims->withdraw($claim, $data['reason'] ?? null)));
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(SupplierClaim $claim): array
    {
        $claim->loadMissing([
            'factory:id,name', 'vendor:id,name,code', 'goodsReceipt:id,grn_no',
            'purchaseReturn:id,return_no', 'fabricRoll:id,roll_no', 'creator:id,name',
        ]);

        $mayManage = $this->caller()->can(self::MANAGE);

        return [
            'id' => $claim->id,
            'claim_no' => $claim->claim_no,
            'claim_date' => $claim->claim_date?->toDateString(),
            'reason' => $claim->reason,
            'status' => $claim->status,
            'claim_amount' => (string) $claim->claim_amount,
            'settled_amount' => (string) $claim->settled_amount,
            'outstanding' => $claim->outstanding(),
            'currency' => $claim->currency,
            'description' => $claim->description,
            'settlement_reference' => $claim->settlement_reference,
            'notes' => $claim->notes,
            'factory' => $claim->factory === null ? null : ['id' => $claim->factory->id, 'name' => $claim->factory->name],
            'vendor' => $claim->vendor === null ? null : ['id' => $claim->vendor->id, 'name' => $claim->vendor->name, 'code' => $claim->vendor->code],
            'goods_receipt' => $claim->goodsReceipt === null ? null : ['id' => $claim->goodsReceipt->id, 'grn_no' => $claim->goodsReceipt->grn_no],
            'purchase_return' => $claim->purchaseReturn === null ? null : ['id' => $claim->purchaseReturn->id, 'return_no' => $claim->purchaseReturn->return_no],
            'roll_no' => $claim->fabricRoll?->roll_no,
            'created_by' => $claim->creator === null ? null : ['id' => $claim->creator->id, 'name' => $claim->creator->name],
            'accepted_at' => $claim->accepted_at?->toIso8601String(),
            'settled_at' => $claim->settled_at?->toIso8601String(),
            'rejected_at' => $claim->rejected_at?->toIso8601String(),
            'withdrawn_at' => $claim->withdrawn_at?->toIso8601String(),
            'can_accept' => $claim->canTransitionTo('ACCEPTED') && $mayManage,
            'can_settle' => $claim->isOpen() && $mayManage,
            'can_reject' => $claim->canTransitionTo('REJECTED') && $mayManage,
            'can_withdraw' => $claim->canTransitionTo('WITHDRAWN') && $mayManage,
            'created_at' => $claim->created_at?->toIso8601String(),
        ];
    }

    private function assertReachable(SupplierClaim $claim): void
    {
        if (! $this->context->canAccessFactory((string) $claim->factory_id)) {
            abort(404);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Controllers\Api;

use App\Modules\Procurement\Actions\ManagePurchaseReturn;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\PurchaseReturn;
use App\Modules\Procurement\Models\PurchaseReturnLine;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Goods back to the supplier (Procurement spec §12).
 *
 * Raising one and sending it out are separate permissions on purpose: the
 * storekeeper who finds the fault writes it up, and somebody else decides
 * the goods leave and the supplier is debited.
 */
class PurchaseReturnApiController extends ApiController
{
    public const VIEW = 'procurement.return.view_any';

    public const CREATE = 'procurement.return.create';

    public const DISPATCH = 'procurement.return.dispatch';

    private const FILTERS = ['status', 'factory_id', 'vendor_id', 'reason', 'goods_receipt_id'];

    private const SORTS = ['return_no', 'return_date', 'status', 'debit_note_amount', 'created_at'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly ManagePurchaseReturn $returns,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = PurchaseReturn::query()
            ->with(['factory:id,name', 'vendor:id,name,code', 'goodsReceipt:id,grn_no'])
            ->withCount('lines')
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if (is_string($search = $request->query('search')) && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(return_no) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(debit_note_no, \'\')) LIKE ?', [$needle]));
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'return_date');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (PurchaseReturn $return): array => $this->summary($return),
        );
    }

    public function show(PurchaseReturn $return): JsonResponse
    {
        $this->allow(self::VIEW);
        $this->assertReachable($return);

        return ApiResponse::ok($this->detail($return));
    }

    /** Raised against the receipt the goods came in on. */
    public function store(Request $request, GoodsReceipt $receipt): JsonResponse
    {
        $this->allow(self::CREATE);

        if (! $this->context->canAccessFactory((string) $receipt->factory_id)) {
            abort(404);
        }

        $data = $request->validate([
            'reason' => ['required', Rule::in(PurchaseReturn::REASONS)],
            'return_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.goods_receipt_line_id' => ['required', 'string', 'size:26'],
            'lines.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.bin_id' => ['nullable', 'string', 'size:26'],
            'lines.*.lot_no' => ['nullable', 'string', 'max:64'],
            'lines.*.fabric_roll_id' => ['nullable', 'string', 'size:26'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::created($this->detail($this->returns->create(
            $receipt, $data, $data['lines'], $this->caller()->auditUserId(),
        )));
    }

    public function submit(PurchaseReturn $return): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($return);

        return ApiResponse::ok($this->detail($this->returns->submit($return, $this->caller()->auditUserId())));
    }

    /** The gate pass, the stock movement and the debit note, together. */
    public function dispatchGoods(Request $request, PurchaseReturn $return): JsonResponse
    {
        $this->allow(self::DISPATCH);
        $this->assertReachable($return);

        $data = $request->validate([
            'gate_pass_no' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::ok($this->detail($this->returns->dispatch(
            $return, $data, $this->caller()->auditUserId(),
        )));
    }

    public function cancel(Request $request, PurchaseReturn $return): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($return);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return ApiResponse::ok($this->detail($this->returns->cancel($return, $data['reason'] ?? null)));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(PurchaseReturn $return): array
    {
        return [
            'id' => $return->id,
            'return_no' => $return->return_no,
            'return_date' => $return->return_date?->toDateString(),
            'reason' => $return->reason,
            'status' => $return->status,
            'approval_status' => $return->approval_status,
            'debit_note_no' => $return->debit_note_no,
            'debit_note_amount' => (string) $return->debit_note_amount,
            'currency' => $return->currency,
            'factory' => $return->factory === null ? null : ['id' => $return->factory->id, 'name' => $return->factory->name],
            'vendor' => $return->vendor === null ? null : ['id' => $return->vendor->id, 'name' => $return->vendor->name, 'code' => $return->vendor->code],
            'goods_receipt' => $return->goodsReceipt === null ? null : ['id' => $return->goodsReceipt->id, 'grn_no' => $return->goodsReceipt->grn_no],
            'line_count' => $return->lines_count ?? null,
            'created_at' => $return->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(PurchaseReturn $return): array
    {
        $return->loadMissing([
            'factory:id,name', 'vendor:id,name,code', 'goodsReceipt:id,grn_no,grn_date',
            'creator:id,name', 'lines.uom:id,code,name', 'lines.bin:id,code,name', 'lines.fabricRoll:id,roll_no',
        ]);

        $mayCreate = $this->caller()->can(self::CREATE);

        return $this->summary($return) + [
            'gate_pass_no' => $return->gate_pass_no,
            'notes' => $return->notes,
            'approval_request_id' => $return->approval_request_id,
            'created_by' => $return->creator === null ? null : ['id' => $return->creator->id, 'name' => $return->creator->name],
            'submitted_at' => $return->submitted_at?->toIso8601String(),
            'approved_at' => $return->approved_at?->toIso8601String(),
            'dispatched_at' => $return->dispatched_at?->toIso8601String(),
            'rejected_at' => $return->rejected_at?->toIso8601String(),
            'cancelled_at' => $return->cancelled_at?->toIso8601String(),
            'can_edit' => $return->isEditable() && $mayCreate,
            'can_submit' => $return->canTransitionTo('PENDING_APPROVAL') && $mayCreate,
            'can_dispatch' => $return->isDispatchable() && $this->caller()->can(self::DISPATCH),
            'can_cancel' => $return->canTransitionTo('CANCELLED') && $mayCreate,
            // The one thing the gate needs to know.
            'stock_moved' => $return->status === 'DISPATCHED',
            'lines' => $return->lines->map(fn (PurchaseReturnLine $line): array => [
                'id' => $line->id,
                'goods_receipt_line_id' => $line->goods_receipt_line_id,
                'item_id' => $line->item_id,
                'item_code' => $line->item_code,
                'item_name' => $line->item_name,
                'uom' => $line->uom === null ? null : ['code' => $line->uom->code, 'name' => $line->uom->name],
                'bin' => $line->bin === null ? null : ['id' => $line->bin->id, 'code' => $line->bin->code],
                'lot_no' => $line->lot_no,
                'roll_no' => $line->fabricRoll?->roll_no,
                'quantity' => (string) $line->quantity,
                'unit_cost' => (string) $line->unit_cost,
                'line_total' => (string) $line->line_total,
                'notes' => $line->notes,
            ])->all(),
        ];
    }

    private function assertReachable(PurchaseReturn $return): void
    {
        if (! $this->context->canAccessFactory((string) $return->factory_id)) {
            abort(404);
        }
    }
}

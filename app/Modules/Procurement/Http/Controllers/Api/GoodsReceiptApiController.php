<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Controllers\Api;

use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\Procurement\Actions\ManageGoodsReceipt;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\GoodsReceiptLine;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Support\Currencies;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Goods receipts over the wire (Procurement spec §9).
 *
 * A receipt is drafted against an order (`po_id`) or directly, and stock is
 * posted only by `complete`, through ManageGoodsReceipt (ADR-003).
 */
class GoodsReceiptApiController extends ApiController
{
    public const VIEW = 'procurement.receipt.view_any';

    public const CREATE = 'procurement.receipt.create';

    public const COMPLETE = 'procurement.receipt.complete';

    private const FILTERS = ['status', 'vendor_id', 'factory_id', 'store_id', 'po_id'];

    private const SORTS = ['grn_no', 'grn_date', 'status', 'created_at'];

    private const RELATIONS = [
        'factory:id,name', 'store:id,name', 'vendor:id,name,code', 'order:id,po_no,status', 'creator:id,name',
        'lines.uom:id,code,name', 'lines.bin:id,code,name', 'lines.item:id,tracking_mode',
        'lines.rolls:id,goods_receipt_line_id,roll_no,status',
    ];

    public function __construct(
        private readonly TenantContext $context,
        private readonly ManageGoodsReceipt $receipts,
    ) {}

    public function formOptions(): JsonResponse
    {
        $this->allow(self::CREATE);

        $factoryIds = $this->context->accessibleFactoryIds();
        $companyId = $this->context->companyId();

        $stores = Store::query()->where('active', true)->with('warehouse:id,name,factory_id')->orderBy('name')->get()
            ->filter(fn (Store $store): bool => in_array($store->warehouse?->factory_id, $factoryIds, true));

        return ApiResponse::ok([
            'factories' => Factory::whereIn('id', $factoryIds)->orderBy('name')->get(['id', 'name'])->all(),
            'stores' => $stores->map(fn (Store $store): array => [
                'id' => $store->id, 'name' => $store->name, 'factory_id' => $store->warehouse?->factory_id,
            ])->values()->all(),
            // Bins carry their store so the picker narrows once a store is chosen.
            'bins' => Bin::query()->where('active', true)->where('is_in_transit', false)
                ->whereIn('store_id', $stores->pluck('id'))->orderBy('code')
                ->get(['id', 'code', 'name', 'store_id'])->all(),
            'vendors' => Vendor::query()->where('status', 'ACTIVE')->whereIn('vendor_type', ['SUPPLIER', 'BOTH'])
                ->orderBy('name')->get(['id', 'name', 'code', 'default_currency'])->all(),
            // A direct delivery has no order to take a currency from,
            // and suppliers do not all invoice in taka.
            'currencies' => Currencies::all(),
            'open_orders' => PurchaseOrder::query()->whereIn('status', PurchaseOrder::RECEIVABLE)
                ->whereIn('factory_id', $factoryIds)->with('vendor:id,name')->orderByDesc('po_date')
                ->get()->map(fn (PurchaseOrder $order): array => [
                    'id' => $order->id, 'po_no' => $order->po_no, 'vendor' => $order->vendor?->name,
                    'factory_id' => $order->factory_id, 'delivery_store_id' => $order->delivery_store_id,
                ])->all(),
            'item_departments' => ItemDepartment::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->all(),
            'uoms' => UnitOfMeasure::availableTo($companyId)->where('active', true)->orderBy('code')->get(['id', 'code', 'name'])->all(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = GoodsReceipt::query()
            ->with(['factory:id,name', 'store:id,name', 'vendor:id,name,code', 'order:id,po_no'])
            ->withCount('lines')
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if (is_string($search = $request->query('search')) && $search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(grn_no) LIKE ?', [$term])
                ->orWhereRaw('LOWER(gate_pass_no) LIKE ?', [$term]));
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'created_at');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (GoodsReceipt $receipt): array => $this->summary($receipt),
        );
    }

    public function show(GoodsReceipt $receipt): JsonResponse
    {
        $this->allow(self::VIEW);
        $this->assertReachable($receipt);

        return ApiResponse::ok($this->detail($receipt));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow(self::CREATE);

        $data = $request->validate([
            'po_id' => ['nullable', 'string', 'size:26'],
            'factory_id' => ['required_without:po_id', 'nullable', 'string', 'size:26'],
            'store_id' => ['required', 'string', 'size:26'],
            'vendor_id' => ['nullable', 'string', 'size:26'],
            'grn_date' => ['nullable', 'date'],
            // Ignored when the receipt is against an order: that
            // document already decided what the goods are priced in.
            'currency' => ['nullable', 'string', 'size:3'],
            'gate_pass_no' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required_without:lines.*.po_line_id', 'nullable', 'string', 'size:26'],
            'lines.*.po_line_id' => ['nullable', 'string', 'size:26'],
            'lines.*.uom_id' => ['nullable', 'string', 'size:26'],
            // Against an order the quantity defaults to what is outstanding.
            'lines.*.received_qty' => ['required_without:lines.*.po_line_id', 'nullable', 'numeric', 'gt:0'],
            'lines.*.rate' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.bin_id' => ['nullable', 'string', 'size:26'],
            'lines.*.lot_no' => ['nullable', 'string', 'max:64'],
            // Read off the drum at the gate: what the lot number means
            // (Inventory spec §12). Only a lot-tracked item uses them.
            'lines.*.lot_manufactured_date' => ['nullable', 'date'],
            'lines.*.lot_expiry_date' => ['nullable', 'date'],
            'lines.*.supplier_lot_no' => ['nullable', 'string', 'max:64'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $store = Store::findOrFail($data['store_id']);
        $userId = $this->caller()->auditUserId();

        if (! empty($data['po_id'])) {
            $order = PurchaseOrder::findOrFail($data['po_id']);

            if (! $this->context->canAccessFactory((string) $order->factory_id)) {
                abort(404);
            }

            $receipt = $this->receipts->createFromOrder($order, $store, $data, $data['lines'], $userId);
        } else {
            if (! $this->context->canAccessFactory((string) $data['factory_id'])) {
                abort(403);
            }

            $receipt = $this->receipts->create(Factory::findOrFail($data['factory_id']), $store, $data, $data['lines'], $userId);
        }

        return ApiResponse::created($this->detail($receipt));
    }

    public function update(Request $request, GoodsReceipt $receipt): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($receipt);

        $data = $request->validate([
            'vendor_id' => ['nullable', 'string', 'size:26'],
            'currency' => ['nullable', 'string', 'size:3'],
            'grn_date' => ['nullable', 'date'],
            'gate_pass_no' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', 'string', 'size:26'],
            'lines.*.po_line_id' => ['nullable', 'string', 'size:26'],
            'lines.*.uom_id' => ['nullable', 'string', 'size:26'],
            'lines.*.received_qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.rate' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.bin_id' => ['nullable', 'string', 'size:26'],
            'lines.*.lot_no' => ['nullable', 'string', 'max:64'],
            // Read off the drum at the gate: what the lot number means
            // (Inventory spec §12). Only a lot-tracked item uses them.
            'lines.*.lot_manufactured_date' => ['nullable', 'date'],
            'lines.*.lot_expiry_date' => ['nullable', 'date'],
            'lines.*.supplier_lot_no' => ['nullable', 'string', 'max:64'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::ok($this->detail($this->receipts->update($receipt, $data, $data['lines'])));
    }

    public function complete(Request $request, GoodsReceipt $receipt): JsonResponse
    {
        $this->allow(self::COMPLETE);
        $this->assertReachable($receipt);

        $data = $request->validate([
            'bins' => ['nullable', 'array'],
            'bins.*' => ['nullable', 'string', 'size:26'],
            // Roll entries for ROLL-tracked lines (Inventory spec §3.1),
            // keyed by line id — every physical roll received on that line.
            'rolls' => ['nullable', 'array'],
            'rolls.*' => ['array'],
            'rolls.*.*.roll_no' => ['required', 'string', 'max:64'],
            'rolls.*.*.bin_id' => ['required', 'string', 'size:26'],
            'rolls.*.*.lot_no' => ['nullable', 'string', 'max:64'],
            'rolls.*.*.shade' => ['nullable', 'string', 'max:64'],
            'rolls.*.*.shade_band' => ['nullable', 'string', 'max:32'],
            'rolls.*.*.nominal_width' => ['nullable', 'numeric', 'min:0'],
            'rolls.*.*.gross_length' => ['nullable', 'numeric', 'min:0'],
            'rolls.*.*.net_length' => ['nullable', 'numeric', 'min:0'],
            'rolls.*.*.gross_weight' => ['nullable', 'numeric', 'min:0'],
            'rolls.*.*.net_weight' => ['nullable', 'numeric', 'min:0'],
        ]);

        return ApiResponse::ok($this->detail(
            $this->receipts->complete(
                $receipt,
                array_filter($data['bins'] ?? []),
                $this->caller()->auditUserId(),
                $data['rolls'] ?? [],
            ),
        ));
    }

    public function cancel(Request $request, GoodsReceipt $receipt): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($receipt);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return ApiResponse::ok($this->detail($this->receipts->cancel($receipt, $data['reason'] ?? null)));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(GoodsReceipt $receipt): array
    {
        return [
            'id' => $receipt->id,
            'grn_no' => $receipt->grn_no,
            'grn_date' => $receipt->grn_date?->toDateString(),
            'grn_type' => $receipt->grn_type,
            'status' => $receipt->status,
            'gate_pass_no' => $receipt->gate_pass_no,
            'factory' => $receipt->factory === null ? null : ['id' => $receipt->factory->id, 'name' => $receipt->factory->name],
            'store' => $receipt->store === null ? null : ['id' => $receipt->store->id, 'name' => $receipt->store->name],
            'vendor' => $receipt->vendor === null ? null : ['id' => $receipt->vendor->id, 'name' => $receipt->vendor->name, 'code' => $receipt->vendor->code],
            'order' => $receipt->order === null ? null : ['id' => $receipt->order->id, 'po_no' => $receipt->order->po_no, 'status' => $receipt->order->status],
            'line_count' => $receipt->lines_count ?? ($receipt->relationLoaded('lines') ? $receipt->lines->count() : null),
            // Part of a posted valuation, so its rates cannot move again
            // (spec §11). The landed-cost picker reads this.
            'rate_locked' => $receipt->isRateLocked(),
            'completed_at' => $receipt->completed_at?->toIso8601String(),
            'created_at' => $receipt->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(GoodsReceipt $receipt): array
    {
        $receipt->loadMissing(self::RELATIONS);

        return $this->summary($receipt) + [
            'notes' => $receipt->notes,
            'creator' => $receipt->creator === null ? null : ['id' => $receipt->creator->id, 'name' => $receipt->creator->name],
            'cancelled_at' => $receipt->cancelled_at?->toIso8601String(),
            'can_edit' => $receipt->isEditable() && $this->caller()->can(self::CREATE),
            'can_complete' => $receipt->canTransitionTo('COMPLETED') && $this->caller()->can(self::COMPLETE),
            'can_cancel' => $receipt->canTransitionTo('CANCELLED') && $this->caller()->can(self::CREATE),
            'lines' => $receipt->lines->map(fn (GoodsReceiptLine $line): array => [
                'id' => $line->id,
                'po_line_id' => $line->po_line_id,
                'item_id' => $line->item_id,
                'item_code' => $line->item_code,
                'item_name' => $line->item_name,
                'tracking_mode' => $line->item?->tracking_mode,
                'uom' => $line->uom === null ? null : ['id' => $line->uom->id, 'code' => $line->uom->code, 'name' => $line->uom->name],
                'bin' => $line->bin === null ? null : ['id' => $line->bin->id, 'code' => $line->bin->code, 'name' => $line->bin->name],
                'received_qty' => (string) $line->received_qty,
                'accepted_qty' => $line->accepted_qty === null ? null : (string) $line->accepted_qty,
                'lot_no' => $line->lot_no,
                'rate' => $line->rate === null ? null : (string) $line->rate,
                'amount' => bcmul((string) $line->received_qty, (string) ($line->rate ?? '0'), 4),
                'currency' => $line->currency,
                'inspection_status' => $line->inspection_status,
                'notes' => $line->notes,
                // Present once the receipt has been completed for this
                // line (Inventory spec §3) — the roll-entry panel uses an
                // empty array here to know a line still needs rolls entered.
                'rolls' => $line->relationLoaded('rolls')
                    ? $line->rolls->map(fn (FabricRoll $roll): array => ['id' => $roll->id, 'roll_no' => $roll->roll_no, 'status' => $roll->status])->all()
                    : [],
            ])->all(),
        ];
    }

    private function assertReachable(GoodsReceipt $receipt): void
    {
        if (! $this->context->canAccessFactory((string) $receipt->factory_id)) {
            abort(404);
        }
    }
}

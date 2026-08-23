<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Api;

use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\SparePart;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Spare parts and what is on the shelf (API 13).
 *
 * The reason an ERP wants this endpoint is reordering, so the answer is
 * shaped around that question: on hand, reserved, and available, which is the
 * only one of the three that answers "can I fit this tonight". Reserved stock
 * is physically present and already promised, and a purchasing system that
 * reads on-hand alone will decide not to order a part the store cannot give it.
 *
 * Writing stock is not here. Issuing a part belongs to a work order, where the
 * cost lands and where the failure history can find it, and an endpoint that
 * moved stock without one would be a way to lose the connection between a
 * bearing and the machine it went into.
 */
class SparePartApiController extends ApiController
{
    private const FILTERS = ['category_id', 'is_critical_spare', 'active', 'unit'];

    private const SORTS = ['part_number', 'name', 'created_at'];

    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('inventory.part.view_any');

        $query = SparePart::query()->with('category:id,name');

        if (is_string($search = $request->query('search')) && $search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn ($q) => $q->where('part_number', 'like', $term)
                ->orWhere('name', 'like', $term)
                ->orWhere('brand', 'like', $term));
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'part_number', 'asc');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (SparePart $part): array => $this->summary($part),
        );
    }

    public function show(SparePart $part): JsonResponse
    {
        $this->allow('inventory.part.view_any');

        return ApiResponse::ok($this->summary($part->load('category:id,name')) + [
            'brand' => $part->brand,
            'manufacturer' => $part->manufacturer,
            'lead_time_days' => $part->lead_time_days,
            'hazardous' => (bool) $part->hazardous,
            'notes' => $part->notes,
        ]);
    }

    /**
     * Where this part is, bin by bin.
     */
    public function stock(SparePart $part): JsonResponse
    {
        $this->allow('inventory.stock.view');

        $balances = InventoryBalance::where('spare_part_id', $part->id)
            ->with('bin.store.warehouse')
            ->get()
            ->filter(fn (InventoryBalance $balance) => in_array(
                $balance->bin?->factoryId(), $this->context->accessibleFactoryIds(), true,
            ));

        return ApiResponse::ok([
            'part_number' => $part->part_number,
            'unit' => $part->unit,
            'total_on_hand' => $part->totalOnHand(),
            'locations' => $balances->map(fn (InventoryBalance $balance): array => [
                'bin_id' => $balance->bin_id,
                'bin' => $balance->bin?->fullPath(),
                'in_transit' => (bool) $balance->bin?->is_in_transit,
                'on_hand' => $balance->quantity_on_hand,
                'reserved' => $balance->quantity_reserved,
                // The one a caller deciding whether to order should read.
                'available' => $balance->available(),
            ])->values()->all(),
        ]);
    }

    /**
     * The ledger for one part.
     *
     * Cursor-paginated, because this is append-only and a fast-moving part
     * accumulates thousands of rows (API 29).
     */
    public function transactions(Request $request, SparePart $part): JsonResponse
    {
        $this->allow('inventory.stock.view');

        $transactions = InventoryTransaction::where('spare_part_id', $part->id)
            ->with(['bin:id,code', 'workOrder:id,work_order_number'])
            ->orderByDesc('transaction_at')
            ->orderByDesc('id')
            ->cursorPaginate($this->perPage($request));

        return ApiResponse::cursor($transactions, fn (InventoryTransaction $row): array => [
            'id' => $row->id,
            'transaction_type' => $row->transaction_type,
            'quantity' => $row->quantity,
            'signed_quantity' => $row->signedQuantity(),
            'balance_after' => $row->balance_after,
            'bin' => $row->bin?->code,
            'work_order' => $row->workOrder?->work_order_number,
            'transaction_at' => $row->transaction_at?->toIso8601String(),
            // A reversal is a row of its own, never an edit. A client
            // replaying the ledger has to be able to see which.
            'reverses' => $row->reverses_transaction_id,
            'notes' => $row->notes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(SparePart $part): array
    {
        return [
            'id' => $part->id,
            'part_number' => $part->part_number,
            'name' => $part->name,
            'category' => $part->category?->name,
            'unit' => $part->unit,
            'minimum_stock' => $part->minimum_stock,
            'reorder_level' => $part->reorder_level,
            'is_critical_spare' => (bool) $part->is_critical_spare,
            'active' => (bool) $part->active,
        ];
    }
}

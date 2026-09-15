<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Api;

use App\Modules\Asset\Models\AssetModel;
use App\Modules\Inventory\Actions\DeleteSparePart;
use App\Modules\Inventory\Actions\ReceiveStock;
use App\Modules\Inventory\Actions\ReserveStock;
use App\Modules\Inventory\Actions\SaveSparePart;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\InventoryBalance;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\SparePartCategory;
use App\Modules\Inventory\Models\SparePartReservation;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Spare parts and what is on the shelf (API 13).
 *
 * The reason an ERP wants this endpoint is reordering, so the answer is
 * shaped around that question: on hand, reserved, and available, which is the
 * only one of the three that answers "can I fit this tonight". Reserved stock
 * is physically present and already promised, and a purchasing system that
 * reads on-hand alone will decide not to order a part the store cannot give it.
 *
 * `reserve`/`release`/`issue`/`return`/`adjust` mirror the web
 * `WorkOrderPartsController` (reserve/release) and `StockController`
 * (issue/return/adjust) unchanged per ADR-003. Fitting a part to a machine
 * still belongs to that machine's work order (`WorkOrderPartApiController`),
 * where the cost lands and the failure history can find it — `issue`/
 * `return` here are the unattributed-consumable case the web calls
 * `StockController::issue()`: a box of gloves, a roll of tape, nothing a
 * repair was charged for. `adjust` mirrors `StockController::adjust()`
 * exactly, including its one-directional shape — it is ADJUSTMENT_OUT/SCRAP
 * only; the increasing direction after a physical count is a receipt
 * (`ReceiveStock::handle()` with `ADJUSTMENT_IN`), which has no endpoint of
 * its own in this spec.
 */
class SparePartApiController extends ApiController
{
    private const FILTERS = ['category_id', 'is_critical_spare', 'active', 'unit'];

    private const SORTS = ['part_number', 'name', 'created_at'];

    public function __construct(private readonly TenantContext $context) {}

    /**
     * Every bin this caller can post stock against — mirrors the web
     * `StockController::accessibleBins()` exactly (active bins, filtered
     * to reachable factories). Added for the Next.js migration: receiving/
     * issuing/adjusting stock all need a bin to pick from, and nothing in
     * the API exposed one before this — `Bin`/`Store`/`Warehouse` are
     * master-data types reached elsewhere only through
     * `GET /master-data/{type}`, which sits behind `masterdata.manage`.
     * STOREKEEPER has `inventory.stock.receive` but not that permission,
     * so it would have 403'd exactly the caller this lookup exists for —
     * the same gap `GET /assets/form-options` closed for Asset create/edit.
     */
    /**
     * Every balance line, across every part and bin — mirrors the web
     * `StockController::index` exactly (no Action class on either side;
     * both read `InventoryBalance` directly), including its own
     * `totals()` (value, in-transit quantity, line count). What's on the
     * shelf right now, bin by bin, as opposed to `index()`'s catalogue of
     * what a part is.
     */
    public function balances(Request $request): JsonResponse
    {
        $this->allow('inventory.stock.view');

        $query = InventoryBalance::query()
            ->with(['sparePart:id,part_number,name,unit,minimum_stock,reorder_level', 'bin.store.warehouse'])
            ->whereHas('bin', fn ($q) => $q->where('is_in_transit', false))
            ->when(is_string($search = $request->query('search')) && $search !== '', function ($q) use ($search): void {
                $term = '%'.$search.'%';
                $q->whereHas('sparePart', fn ($p) => $p->where('part_number', 'like', $term)
                    ->orWhere('name', 'like', $term));
            })
            ->when(is_string($binId = $request->query('bin_id')) && $binId !== '', fn ($q) => $q->where('bin_id', $binId))
            ->orderByDesc('quantity_on_hand');

        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return ApiResponse::ok(
            collect($paginator->items())->map(fn (InventoryBalance $balance): array => [
                'id' => $balance->id,
                'spare_part' => $balance->sparePart === null ? null : [
                    'id' => $balance->spare_part_id,
                    'part_number' => $balance->sparePart->part_number,
                    'name' => $balance->sparePart->name,
                    'unit' => $balance->sparePart->unit,
                    'reorder_level' => $balance->sparePart->reorder_level,
                ],
                'bin' => $balance->bin === null ? null : ['id' => $balance->bin_id, 'full_path' => $balance->bin->fullPath()],
                'on_hand' => $balance->quantity_on_hand,
                'reserved' => $balance->quantity_reserved,
                'available' => $balance->available(),
                'unit_cost' => $balance->weighted_average_cost,
                'total_value' => $balance->totalValue(),
                'currency' => $balance->currency,
            ])->values()->all(),
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totals' => $this->balanceTotals(),
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    private function balanceTotals(): array
    {
        $balances = InventoryBalance::with('bin')->get();

        $value = '0.0000';
        $inTransit = '0.0000';

        foreach ($balances as $balance) {
            $value = bcadd($value, $balance->totalValue(), 4);

            if ($balance->bin?->is_in_transit) {
                $inTransit = bcadd($inTransit, (string) $balance->quantity_on_hand, 4);
            }
        }

        return [
            'value' => $value,
            'in_transit' => $inTransit,
            'lines' => (string) $balances->count(),
        ];
    }

    /**
     * The category and asset-model dropdowns the create/edit and
     * compatibility ("fits a...") forms need. Gated on `inventory.part.
     * view_any` rather than `masterdata.manage`: STORE_MANAGER holds
     * `inventory.part.create`/`.update` without it (`RoleSeeder`'s
     * `$storeManager` never merges from `$factoryManager`, which is where
     * `masterdata.manage` is granted) — the same gap `GET /spare-parts/
     * bins` closed for receive/issue/adjust.
     */
    public function formOptions(): JsonResponse
    {
        $this->allow('inventory.part.view_any');

        $companyId = $this->context->companyId();

        return ApiResponse::ok([
            'categories' => SparePartCategory::availableTo($companyId)->where('active', true)
                ->orderBy('name')->get(['id', 'name'])->all(),
            'asset_models' => AssetModel::availableTo($companyId)->where('active', true)
                ->with('manufacturer:id,name')
                ->orderBy('model')
                ->get()
                ->map(fn (AssetModel $model): array => [
                    'id' => $model->id,
                    'model' => $model->model,
                    'manufacturer' => $model->manufacturer?->name,
                ])->all(),
        ]);
    }

    public function bins(): JsonResponse
    {
        $this->allow('inventory.stock.view');

        $bins = Bin::where('active', true)
            ->with('store.warehouse')
            ->get()
            ->filter(fn (Bin $bin) => in_array($bin->factoryId(), $this->context->accessibleFactoryIds(), true))
            ->values();

        return ApiResponse::ok($bins->map(fn (Bin $bin): array => [
            'id' => $bin->id,
            'full_path' => $bin->fullPath(),
            'is_in_transit' => (bool) $bin->is_in_transit,
        ])->all());
    }

    /**
     * Parts at or below their reorder level (mirrors `StockController::lowStock`
     * unchanged per ADR-003) — below the reorder level, not below zero: by
     * the time stock is out, the lead time has already been lost.
     *
     * Registered ahead of `GET /spare-parts/{part}` in the route file so
     * this static segment isn't swallowed by that wildcard.
     */
    public function lowStock(): JsonResponse
    {
        $this->allow('inventory.stock.view');

        $parts = SparePart::query()
            ->with('category:id,name')
            ->where('active', true)
            ->withSum('balances as on_hand', 'quantity_on_hand')
            ->get()
            ->filter(function (SparePart $part): bool {
                $onHand = number_format((float) ($part->on_hand ?? 0), 4, '.', '');

                return bccomp($onHand, (string) ($part->reorder_level ?? '0'), 4) <= 0;
            })
            // Critical spares first: a part whose absence stops a critical
            // machine is not the same problem as one that is merely low.
            ->sortByDesc(fn (SparePart $part): array => [$part->is_critical_spare ? 1 : 0])
            ->values();

        return ApiResponse::ok($parts->map(fn (SparePart $part): array => $this->summary($part) + [
            'on_hand' => number_format((float) ($part->on_hand ?? 0), 4, '.', ''),
        ])->all());
    }

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

        return ApiResponse::ok($this->detail($part->load('category:id,name')));
    }

    /**
     * The catalogue only — what a part is, what it costs to buy and when to
     * reorder it. Quantity is never set here; stock enters through the
     * ledger as a receipt with a cost and a bin (SRS 22), which is exactly
     * why this controller has no reserve/issue/adjust endpoints of its own.
     */
    public function store(Request $request, SaveSparePart $action): JsonResponse
    {
        $this->allow('inventory.part.create');

        $part = $action->create($this->validated($request, null));

        return ApiResponse::created($this->detail($part));
    }

    public function update(Request $request, SparePart $part, SaveSparePart $action): JsonResponse
    {
        $this->allow('inventory.part.update');

        $updated = $action->update($part, $this->validated($request, $part));

        return ApiResponse::ok($this->detail($updated));
    }

    /**
     * Retire a part from the catalogue, or bring it back (mirrors
     * `SparePartController::toggle`). Never a delete: the ledger points at
     * this row, and a part nobody stocks any more is still the part that
     * was fitted to a machine two years ago.
     */
    public function setActive(Request $request, SparePart $part, SaveSparePart $action): JsonResponse
    {
        $this->allow('inventory.part.update');

        $data = $request->validate(['active' => ['required', 'boolean']]);

        return ApiResponse::ok($this->detail($action->setActive($part, $data['active'])));
    }

    /**
     * Remove a catalogue entry that should never have been created (mirrors
     * `SparePartController::destroy`). Narrow on purpose: `DeleteSparePart`
     * refuses anything the ledger, a reservation or a work order points at
     * — those are retired via `setActive()` instead. There is no separate
     * delete permission; this is gated the same as any other catalogue
     * edit (`inventory.part.update`).
     */
    public function destroy(SparePart $part, DeleteSparePart $action): JsonResponse
    {
        $this->allow('inventory.part.update');

        try {
            $action->handle($part);
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::CONFLICT, implode(' ', $e->validator->errors()->all()));
        }

        return ApiResponse::noContent();
    }

    /**
     * Stock arriving into a bin (mirrors `StockController::store`, which
     * this delegates to unchanged per ADR-003). The received price sets the
     * weighted average, so it is required rather than defaulted.
     */
    public function receive(Request $request, SparePart $part, ReceiveStock $action): JsonResponse
    {
        $this->allow('inventory.stock.receive');

        $data = $request->validate([
            'bin_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'transaction_type' => ['required', Rule::in(['RECEIPT', 'OPENING_BALANCE', 'ADJUSTMENT_IN'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $transaction = $action->handle(
                $part,
                Bin::findOrFail($data['bin_id']),
                (string) $data['quantity'],
                (string) $data['unit_cost'],
                $this->caller()->auditUserId(),
                $data['notes'] ?? null,
                $data['transaction_type'],
            );
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        return ApiResponse::created($this->transactionSummary($transaction));
    }

    /**
     * Undoes a posted movement with an opposing row (mirrors
     * `StockController::reverse`). The original stays exactly as it was —
     * deleting it would leave the balance right and the history wrong.
     */
    public function reverseTransaction(Request $request, string $transaction, InventoryLedger $ledger): JsonResponse
    {
        $this->allow('inventory.adjustment.create');

        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        $original = InventoryTransaction::findOrFail($transaction);

        try {
            $reversal = $ledger->reverse($original, $this->caller()->auditUserId(), $data['reason']);
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        return ApiResponse::created($this->transactionSummary($reversal));
    }

    /**
     * Proof rather than assertion, bin by bin: whether the ledger still
     * replays to the balance the store is showing right now (mirrors the
     * web `SparePartController::show`'s `verification` data via
     * `InventoryLedger::verify()`, unchanged per ADR-003).
     */
    public function verify(SparePart $part, InventoryLedger $ledger): JsonResponse
    {
        $this->allow('inventory.stock.view');

        $balances = InventoryBalance::where('spare_part_id', $part->id)
            ->with('bin.store.warehouse')
            ->get()
            ->filter(fn (InventoryBalance $balance) => in_array(
                $balance->bin?->factoryId(), $this->context->accessibleFactoryIds(), true,
            ));

        return ApiResponse::ok($balances->map(fn (InventoryBalance $balance): array => [
            'bin_id' => $balance->bin_id,
            'bin' => $balance->bin?->fullPath(),
            ...$ledger->verify($part, $balance->bin),
        ])->values()->all());
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
     * Promise stock to a work order without moving anything yet (mirrors
     * `WorkOrderPartsController::reserve`). Reached from the part rather
     * than the work order, so the body carries `work_order_id` — the same
     * `ReserveStock::handle()` a work-order-scoped caller would reach.
     */
    public function reserve(Request $request, SparePart $part, ReserveStock $action): JsonResponse
    {
        $this->allow('inventory.reservation.manage');

        $data = $request->validate([
            'work_order_id' => ['required', 'string', 'size:26'],
            'bin_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $reservation = $action->handle(
                $part,
                Bin::findOrFail($data['bin_id']),
                WorkOrder::findOrFail($data['work_order_id']),
                (string) $data['quantity'],
                $this->caller()->auditUserId(),
            );
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        return ApiResponse::created($this->reservationSummary($reservation));
    }

    /**
     * Hand back stock a reservation was holding (mirrors
     * `WorkOrderPartsController::release`). `reservation_id` is scoped to
     * this part, the same protection the web route gets from being nested
     * under one work order.
     */
    public function release(Request $request, SparePart $part, ReserveStock $action): JsonResponse
    {
        $this->allow('inventory.reservation.manage');

        $data = $request->validate([
            'reservation_id' => ['required', 'string', 'size:26'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $reservation = SparePartReservation::where('spare_part_id', $part->id)
            ->where('id', $data['reservation_id'])
            ->firstOrFail();

        try {
            $released = $action->release(
                $reservation,
                isset($data['quantity']) ? (string) $data['quantity'] : null,
                $this->caller()->auditUserId(),
            );
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        return ApiResponse::ok($this->reservationSummary($released));
    }

    /**
     * Stock leaving with no work order behind it — a consumable, not
     * something fitted to a machine (mirrors `StockController::issue`,
     * `transaction_type=ISSUE`). Anything charged to a repair goes through
     * that work order's own parts endpoint instead.
     */
    public function issue(Request $request, SparePart $part, InventoryLedger $ledger): JsonResponse
    {
        $this->allow('inventory.stock.issue');

        return $this->postUnattributed($request, $part, $ledger, 'ISSUE');
    }

    public function returnToStore(Request $request, SparePart $part, InventoryLedger $ledger): JsonResponse
    {
        $this->allow('inventory.stock.return');

        return $this->postUnattributed($request, $part, $ledger, 'RETURN');
    }

    /**
     * A physical-count correction, stock decreasing (mirrors
     * `StockController::adjust` exactly, including its one-directional
     * shape — ADJUSTMENT_OUT/SCRAP only; the increasing direction is a
     * receipt, which this spec does not expose an endpoint for).
     */
    public function adjust(Request $request, SparePart $part, ReceiveStock $action): JsonResponse
    {
        $this->allow('inventory.adjustment.create');

        $data = $request->validate([
            'bin_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'transaction_type' => ['required', Rule::in(['ADJUSTMENT_OUT', 'SCRAP'])],
            // Stock that moves without an explanation is indistinguishable
            // from loss, so the reason is required rather than optional.
            'notes' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $transaction = $action->adjustOut(
                $part,
                Bin::findOrFail($data['bin_id']),
                (string) $data['quantity'],
                $data['notes'],
                $this->caller()->auditUserId(),
                $data['transaction_type'],
            );
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        return ApiResponse::created($this->transactionSummary($transaction));
    }

    private function postUnattributed(Request $request, SparePart $part, InventoryLedger $ledger, string $type): JsonResponse
    {
        $data = $request->validate([
            'bin_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            // Stock that moves with no work order behind it and no
            // explanation is indistinguishable from loss.
            'notes' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $transaction = $ledger->post(
                $part,
                Bin::findOrFail($data['bin_id']),
                $type,
                (string) $data['quantity'],
                null,
                [
                    'performed_by' => $this->caller()->auditUserId(),
                    'notes' => $data['notes'],
                    'transaction_at' => now(),
                ],
            );
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        return ApiResponse::created($this->transactionSummary($transaction));
    }

    private function translate(ValidationException $e): ApiException
    {
        $status = $e->status ?? 422;
        $code = $status === 409 ? ErrorCode::CONFLICT : ErrorCode::VALIDATION_ERROR;

        return ApiException::of($code, implode(' ', $e->validator->errors()->all()), $e->errors());
    }

    /**
     * @return array<string, mixed>
     */
    private function reservationSummary(SparePartReservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'spare_part_id' => $reservation->spare_part_id,
            'work_order_id' => $reservation->work_order_id,
            'bin_id' => $reservation->bin_id,
            'quantity' => $reservation->quantity,
            'quantity_released' => $reservation->quantity_released,
            'quantity_issued' => $reservation->quantity_issued,
            'status' => $reservation->status,
            'reserved_at' => $reservation->reserved_at?->toIso8601String(),
            'expires_at' => $reservation->expires_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionSummary(InventoryTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'transaction_type' => $transaction->transaction_type,
            'quantity' => $transaction->quantity,
            'signed_quantity' => $transaction->signedQuantity(),
            'balance_after' => $transaction->balance_after,
            'bin_id' => $transaction->bin_id,
            'transaction_at' => $transaction->transaction_at?->toIso8601String(),
            'notes' => $transaction->notes,
        ];
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
            // {id, name} rather than a bare name — the edit form (Next.js)
            // needs the id to preselect the dropdown, same reasoning as
            // Asset's own type/category/manufacturer fields.
            'category' => $part->category === null ? null : ['id' => $part->category_id, 'name' => $part->category->name],
            'unit' => $part->unit,
            'minimum_stock' => $part->minimum_stock,
            'reorder_level' => $part->reorder_level,
            'is_critical_spare' => (bool) $part->is_critical_spare,
            'active' => (bool) $part->active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(SparePart $part): array
    {
        return $this->summary($part) + [
            'brand' => $part->brand,
            'manufacturer' => $part->manufacturer,
            'lead_time_days' => $part->lead_time_days,
            'shelf_life_days' => $part->shelf_life_days,
            'unit_cost' => $part->unit_cost,
            'currency' => $part->currency,
            'hazardous' => (bool) $part->hazardous,
            'notes' => $part->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?SparePart $part): array
    {
        $unique = Rule::unique('spare_parts')->where('company_id', $this->context->companyId());

        if ($part !== null) {
            $unique = $unique->ignore($part->id);
        }

        $validated = $request->validate([
            'part_number' => ['required', 'string', 'max:64', $unique],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'string', 'size:26'],
            'brand' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'unit' => ['required', Rule::in(SparePart::UNITS)],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'shelf_life_days' => ['nullable', 'integer', 'min:0', 'max:36500'],
            'is_critical_spare' => ['sometimes', 'boolean'],
            'hazardous' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['is_critical_spare'] = $request->boolean('is_critical_spare');
        $validated['hazardous'] = $request->boolean('hazardous');

        return $validated;
    }
}

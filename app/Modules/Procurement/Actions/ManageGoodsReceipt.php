<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Actions;

use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\Store;
use App\Modules\ItemMaster\Actions\ReceiveFabricRolls;
use App\Modules\ItemMaster\Actions\ReserveItemStock;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemLot;
use App\Modules\ItemMaster\Services\ItemLedger;
use App\Modules\ItemMaster\Services\ItemLineResolver;
use App\Modules\ItemMaster\Services\ItemNotifier;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\GoodsReceiptLine;
use App\Modules\Finance\Services\GlPoster;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseOrderLine;
use App\Modules\Procurement\Support\Currencies;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Settings\Services\SettingsResolver;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The goods receipt's life (Procurement spec §9).
 *
 * A receipt is drafted at the gate — against a purchase order or directly.
 * A QUANTITY/LOT line is posted to the item ledger the moment the receipt
 * is COMPLETED, so it is never half on the shelf. A ROLL-tracked (fabric)
 * line works differently (Inventory spec §4.2): completing it creates
 * `FabricRoll` rows in UNDER_INSPECTION rather than posting to the ledger,
 * and the receipt itself lands in UNDER_INSPECTION rather than COMPLETED
 * until every roll it produced has been inspected — see
 * `InspectFabricRoll`, which moves the receipt on to COMPLETED once they
 * have. `SERIAL` items stay out of scope entirely: serialised equipment
 * goes through the Asset module's register, not this ledger.
 */
class ManageGoodsReceipt
{
    public function __construct(
        private readonly NumberSequenceGenerator $numbers,
        private readonly ItemLineResolver $lines,
        private readonly ItemLedger $ledger,
        private readonly ManagePurchaseOrder $orders,
        private readonly SettingsResolver $settings,
        private readonly ReceiveFabricRolls $rolls,
        private readonly ReserveItemStock $reservations,
        private readonly ItemNotifier $notifier,
        private readonly GlPoster $gl,
    ) {}

    /**
     * A direct receipt with no purchase order behind it.
     *
     * @param  array<string, mixed>  $data  vendor_id?, grn_date?, gate_pass_no?, notes?
     * @param  list<array<string, mixed>>  $lines  item_id, received_qty, rate?, bin_id?, lot_no?, uom_id?, notes?
     */
    public function create(Factory $factory, Store $store, array $data, array $lines, ?string $userId = null): GoodsReceipt
    {
        $this->assertStoreInFactory($store, $factory);

        $resolved = $this->lines->resolve($lines, 'received_qty');

        return DB::transaction(function () use ($factory, $store, $data, $resolved, $userId): GoodsReceipt {
            $receipt = $this->newHeader($factory, $store, $data, null, $userId);
            $this->writeLines($receipt, $resolved, $data['currency'] ?? null);

            return $receipt->fresh();
        });
    }

    /**
     * A receipt against an approved order. Lines name the order line they
     * deliver on; quantities default to what is still outstanding.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{po_line_id: string, received_qty?: string, bin_id?: string, lot_no?: string, notes?: string}>  $lines
     */
    public function createFromOrder(PurchaseOrder $order, Store $store, array $data, array $lines, ?string $userId = null): GoodsReceipt
    {
        if (! $order->isReceivable()) {
            throw ValidationException::withMessages([
                'status' => __('procurement.po_not_receivable', ['status' => $order->status]),
            ])->status(409);
        }

        $factory = Factory::findOrFail($order->factory_id);
        $this->assertStoreInFactory($store, $factory);

        $orderLines = $order->lines()->get()->keyBy('id');

        $picked = collect($lines)->values()->map(function (array $line, int $index) use ($orderLines): array {
            /** @var PurchaseOrderLine|null $orderLine */
            $orderLine = $orderLines->get((string) ($line['po_line_id'] ?? ''));

            if ($orderLine === null) {
                throw ValidationException::withMessages(["lines.$index.po_line_id" => __('procurement.unknown_po_line')]);
            }

            return [
                'item_id' => $orderLine->item_id,
                'uom_id' => $orderLine->uom_id,
                'received_qty' => $line['received_qty'] ?? $orderLine->pendingQty(),
                'rate' => (string) $orderLine->rate,
                'bin_id' => $line['bin_id'] ?? null,
                'lot_no' => $line['lot_no'] ?? null,
                'lot_manufactured_date' => $line['lot_manufactured_date'] ?? null,
                'lot_expiry_date' => $line['lot_expiry_date'] ?? null,
                'supplier_lot_no' => $line['supplier_lot_no'] ?? null,
                'notes' => $line['notes'] ?? null,
                'po_line_id' => $orderLine->id,
            ];
        });

        // Lines a supplier delivers on can name a retired item: the order
        // was signed while it was live, and the stock is real either way.
        $resolved = $this->lines->resolve($picked->all(), 'received_qty', requireActive: false);

        return DB::transaction(function () use ($factory, $store, $data, $resolved, $order, $userId): GoodsReceipt {
            $receipt = $this->newHeader($factory, $store, $data + ['vendor_id' => $order->vendor_id], $order, $userId);
            // The order's currency, not the caller's: a receipt that
            // settles an order in a different currency than the order
            // was placed in cannot be reconciled against it.
            $this->writeLines($receipt, $resolved, $order->currency);

            return $receipt->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $lines
     */
    public function update(GoodsReceipt $receipt, array $data, array $lines): GoodsReceipt
    {
        $this->assertEditable($receipt);

        $order = $receipt->po_id === null ? null : PurchaseOrder::findOrFail($receipt->po_id);

        if ($order !== null) {
            // Against an order, the lines are the order's lines: a receipt
            // cannot pick up a new item on the way in.
            $orderLines = $order->lines()->get()->keyBy('id');

            $lines = collect($lines)->values()->map(function (array $line, int $index) use ($orderLines): array {
                $orderLine = $orderLines->get((string) ($line['po_line_id'] ?? ''));

                if ($orderLine === null) {
                    throw ValidationException::withMessages(["lines.$index.po_line_id" => __('procurement.unknown_po_line')]);
                }

                return $line + ['item_id' => $orderLine->item_id, 'uom_id' => $orderLine->uom_id, 'rate' => (string) $orderLine->rate];
            })->all();
        }

        $resolved = $this->lines->resolve($lines, 'received_qty', requireActive: $order === null);

        // The order still decides when there is one — the same rule as
        // on create, so an edit cannot do what a create was refused.
        // Read before the rewrite below deletes the lines that hold it:
        // an edit that does not mention the currency means "leave it",
        // and asking the rows once they are gone would quietly reset a
        // dollar receipt to taka.
        $currency = $order?->currency ?? $data['currency'] ?? $receipt->lines()->value('currency');

        return DB::transaction(function () use ($receipt, $data, $resolved, $currency): GoodsReceipt {
            $receipt->forceFill([
                'grn_date' => $data['grn_date'] ?? $receipt->grn_date,
                'gate_pass_no' => array_key_exists('gate_pass_no', $data) ? $data['gate_pass_no'] : $receipt->gate_pass_no,
                'vendor_id' => array_key_exists('vendor_id', $data) ? $data['vendor_id'] : $receipt->vendor_id,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $receipt->notes,
            ])->save();

            $receipt->lines()->delete();
            $this->writeLines($receipt, $resolved, $currency);

            return $receipt->fresh();
        });
    }

    /**
     * Posts every QUANTITY/LOT line to the ledger, creates `FabricRoll` rows
     * for every ROLL line, and moves the receipt to COMPLETED — or, if any
     * line produced rolls, to UNDER_INSPECTION until they are resolved
     * (Inventory spec §4.2). One transaction, so a refused line never
     * leaves an earlier one half-posted.
     *
     * @param  array<string, string>  $binsByLineId  line id → bin id (QUANTITY/LOT lines only)
     * @param  array<string, list<array<string, mixed>>>  $rollsByLineId  line id → roll entries (ROLL lines only)
     */
    public function complete(GoodsReceipt $receipt, array $binsByLineId = [], ?string $userId = null, array $rollsByLineId = []): GoodsReceipt
    {
        $receipt->load('lines');

        if ($receipt->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('procurement.needs_lines')]);
        }

        $store = Store::findOrFail($receipt->store_id);
        $bins = Bin::where('store_id', $store->id)->where('active', true)->get()->keyBy('id');
        $items = Item::whereIn('id', $receipt->lines->pluck('item_id')->unique()->all())->get()->keyBy('id');
        $order = $receipt->po_id === null ? null : PurchaseOrder::findOrFail($receipt->po_id);
        $allowOver = $order !== null && (bool) $this->settings->get('procurement.allow_over_receipt', factoryId: $receipt->factory_id);

        // Checked before any line-level validation, exactly as a plain
        // status check always was: a retried completion is refused for
        // being the wrong status, not for whatever the first line happens
        // to validate to on a receipt that already has stock on the shelf.
        $target = $receipt->lines->contains(fn (GoodsReceiptLine $line): bool => ($items->get($line->item_id))?->usesRollLedger() ?? false)
            ? 'UNDER_INSPECTION' : 'COMPLETED';
        $this->assertTransition($receipt, $target);

        // Everything is checked before anything is posted, so a refused
        // third line does not leave the first two on the shelf.
        $plan = $receipt->lines->map(function (GoodsReceiptLine $line, int $index) use ($bins, $items, $binsByLineId, $rollsByLineId, $order, $allowOver, $store, $receipt): array {
            /** @var Item|null $item */
            $item = $items->get($line->item_id);
            $field = "lines.$index";

            if ($item === null) {
                throw ValidationException::withMessages(["$field.item_id" => __('procurement.unknown_item')]);
            }

            if ($item->tracking_mode === 'SERIAL') {
                throw ValidationException::withMessages([
                    "$field.item_id" => __('itemmaster.roll_ledger_not_available', ['item' => $item->item_code]),
                ]);
            }

            if ($item->usesRollLedger()) {
                $rollPlan = $this->rolls->plan(
                    $line, $item, $store->id, $receipt->grn_date?->toDateString() ?? now()->toDateString(),
                    $rollsByLineId[$line->id] ?? [], "$field.rolls",
                );

                // What arrived against the order is the line's own received
                // quantity (set when the line was drafted, in the item's
                // UOM) — not a sum over the roll entries, which may not use
                // the same unit (net length vs net weight) and are not
                // required to reconcile with it in Phase 2. The over-receipt
                // guard is skipped for rolls: a factory counts what came off
                // the truck regardless of what the order said.
                return ['line' => $line, 'item' => $item, 'rolls' => $rollPlan, 'order_line' => $this->orderLineFor($order, $line)];
            }

            $binId = $binsByLineId[$line->id] ?? $line->bin_id;
            $bin = $binId === null ? null : $bins->get($binId);

            if ($bin === null) {
                throw ValidationException::withMessages([
                    "$field.bin_id" => __('procurement.bin_required_in_store', ['item' => $item->item_code]),
                ]);
            }

            if ($item->isLotTracked() && blank($line->lot_no)) {
                throw ValidationException::withMessages([
                    "$field.lot_no" => __('itemmaster.lot_required', ['item' => $item->item_code]),
                ]);
            }

            $quantity = $line->postableQty();
            $orderLine = $this->orderLineFor($order, $line);

            if ($orderLine !== null && ! $allowOver && bccomp($quantity, $orderLine->pendingQty(), 4) > 0) {
                throw ValidationException::withMessages([
                    "$field.received_qty" => __('procurement.over_receipt', [
                        'item' => $item->item_code,
                        'pending' => rtrim(rtrim($orderLine->pendingQty(), '0'), '.'),
                    ]),
                ]);
            }

            return ['line' => $line, 'item' => $item, 'bin' => $bin, 'quantity' => $quantity, 'order_line' => $orderLine];
        });

        /** @var list<array{item: Item, quantity: string, roll: bool}> $arrivals */
        $arrivals = [];

        $completed = DB::transaction(function () use ($receipt, $plan, $userId, $target, &$arrivals): GoodsReceipt {
            foreach ($plan as $step) {
                /** @var GoodsReceiptLine $line */
                $line = $step['line'];

                if (isset($step['rolls'])) {
                    foreach ($step['rolls'] as $rollAttributes) {
                        FabricRoll::create($rollAttributes);
                    }

                    if ($step['order_line'] !== null) {
                        $this->orders->recordReceipt($step['order_line'], $line->postableQty());
                    }

                    // The rolls are not stock yet — they are inspected first
                    // — so nothing is filled here. Anybody waiting on this
                    // item is told once inspection passes them.
                    continue;
                }

                if ($line->bin_id !== $step['bin']->id) {
                    $line->forceFill(['bin_id' => $step['bin']->id])->save();
                }

                $this->ledger->post($step['item'], $step['bin'], 'RECEIPT', $step['quantity'], (string) ($line->rate ?? '0'), [
                    'lot_no' => $line->lot_no,
                    'currency' => $line->currency ?? 'BDT',
                    'reference_type' => 'goods_receipt',
                    'reference_id' => $receipt->id,
                    'performed_by' => $userId,
                    // A retried completion posts nothing twice.
                    'idempotency_key' => 'grn:'.$line->id,
                    'notes' => $receipt->grn_no,
                ]);

                if ($step['order_line'] !== null) {
                    $this->orders->recordReceipt($step['order_line'], $step['quantity']);
                }

                $this->recordLot($step['item'], $line, $receipt);

                // Stock that somebody has been waiting on is handed to them
                // before it can be issued to whoever asks next (Inventory
                // spec §5). Inside this transaction, so the encumbrance and
                // the receipt that justifies it stand or fall together.
                $filled = $this->reservations->fillPendingEarmarks(
                    $step['item'], $step['bin'], $step['quantity'], $line->lot_no,
                );

                if ($filled->isNotEmpty()) {
                    $arrivals[] = ['item' => $step['item'], 'quantity' => $step['quantity'], 'roll' => false];
                }
            }

            $receipt->forceFill([
                'status' => $target,
                'completed_at' => $target === 'COMPLETED' ? CarbonImmutable::now() : null,
            ])->save();

            return $receipt->fresh();
        });

        // Books after stock, and outside the transaction: GL posting is a
        // side effect (Shared Systems §5.1), and a chart of accounts nobody
        // has finished must not be able to stop a delivery at the gate.
        $this->gl->post('GRN_COMPLETE', $completed->totalValue(), [
            'source_type' => 'goods_receipt',
            'source_id' => $completed->id,
            'source_no' => $completed->grn_no,
            'factory_id' => $completed->factory_id,
            'currency' => $completed->lines->first()?->currency ?? 'BDT',
            'date' => $completed->grn_date,
            'memo' => $completed->grn_no,
            'user_id' => $userId,
        ]);

        // After the receipt is committed: a notification that fails must not
        // roll back the stock it was announcing.
        foreach ($arrivals as $arrival) {
            $this->notifier->earmarkStockArrived(
                $arrival['item'], $arrival['quantity'], $receipt->factory_id, $arrival['roll'],
            );
        }

        return $completed;
    }

    /**
     * What this lot number means, recorded the one time it can be: at the
     * gate, off the drum (Inventory spec §12).
     *
     * Kept per (item, lot) rather than per receipt — a second delivery of
     * the same lot is the same chemical with the same expiry, and two rows
     * would be two answers to one question. The first receipt's dates win;
     * a later one only fills in what was left blank.
     */
    private function recordLot(Item $item, GoodsReceiptLine $line, GoodsReceipt $receipt): void
    {
        $lotNo = trim((string) $line->lot_no);

        if (! $item->isLotTracked() || $lotNo === '') {
            return;
        }

        $lot = ItemLot::where('item_id', $item->id)->where('lot_no', $lotNo)->first();

        if ($lot === null) {
            ItemLot::create([
                'item_id' => $item->id,
                'lot_no' => $lotNo,
                'manufactured_date' => $line->lot_manufactured_date,
                'expiry_date' => $line->lot_expiry_date,
                'first_received_date' => $receipt->grn_date ?? CarbonImmutable::today(),
                'supplier_lot_no' => $line->supplier_lot_no,
            ]);

            return;
        }

        $lot->forceFill([
            'manufactured_date' => $lot->manufactured_date ?? $line->lot_manufactured_date,
            'expiry_date' => $lot->expiry_date ?? $line->lot_expiry_date,
            'supplier_lot_no' => $lot->supplier_lot_no ?? $line->supplier_lot_no,
        ])->save();
    }

    public function cancel(GoodsReceipt $receipt, ?string $reason = null): GoodsReceipt
    {
        $this->assertTransition($receipt, 'CANCELLED');

        if (FabricRoll::where('goods_receipt_id', $receipt->id)->where('status', 'UNDER_INSPECTION')->exists()) {
            throw ValidationException::withMessages([
                'status' => __('inspection.cannot_cancel_pending_inspection'),
            ])->status(409);
        }

        $receipt->forceFill([
            'status' => 'CANCELLED',
            'cancelled_at' => CarbonImmutable::now(),
            'notes' => $reason === null ? $receipt->notes : trim(($receipt->notes ?? '')."\n".$reason),
        ])->save();

        return $receipt->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function newHeader(Factory $factory, Store $store, array $data, ?PurchaseOrder $order, ?string $userId): GoodsReceipt
    {
        return GoodsReceipt::create([
            'factory_id' => $factory->id,
            'store_id' => $store->id,
            'vendor_id' => $data['vendor_id'] ?? null,
            'po_id' => $order?->id,
            'grn_no' => $this->numbers->next('GOODS_RECEIPT', $factory),
            'grn_date' => $data['grn_date'] ?? CarbonImmutable::today(),
            'gate_pass_no' => $data['gate_pass_no'] ?? null,
            'grn_type' => 'LOCAL',
            'status' => 'DRAFT',
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
        ]);
    }

    /**
     * @param  Collection<int, array{item: Item, uom_id: string, quantity: string, input: array<string, mixed>}>  $resolved
     */
    private function writeLines(GoodsReceipt $receipt, Collection $resolved, ?string $currency = null): void
    {
        foreach ($resolved as $line) {
            /** @var Item $item */
            $item = $line['item'];
            $input = $line['input'];

            GoodsReceiptLine::create([
                'goods_receipt_id' => $receipt->id,
                'item_id' => $item->id,
                'bin_id' => $input['bin_id'] ?? null,
                'item_code' => $item->item_code,
                'item_name' => $item->name,
                'po_line_id' => $input['po_line_id'] ?? null,
                'received_qty' => $line['quantity'],
                'uom_id' => $line['uom_id'],
                'lot_no' => isset($input['lot_no']) && trim((string) $input['lot_no']) !== '' ? trim((string) $input['lot_no']) : null,
                // What the lot number means, read at the gate (§12).
                'lot_manufactured_date' => $input['lot_manufactured_date'] ?? null,
                'lot_expiry_date' => $input['lot_expiry_date'] ?? null,
                'supplier_lot_no' => $input['supplier_lot_no'] ?? null,
                'rate' => ItemLineResolver::money($input['rate'] ?? '0'),
                'currency' => strtoupper((string) ($input['currency'] ?? $currency ?? Currencies::DEFAULT)),
                'inspection_status' => 'PENDING',
                'notes' => $input['notes'] ?? null,
            ]);
        }
    }

    private function orderLineFor(?PurchaseOrder $order, GoodsReceiptLine $line): ?PurchaseOrderLine
    {
        if ($order === null || $line->po_line_id === null) {
            return null;
        }

        return $order->lines()->whereKey($line->po_line_id)->first();
    }

    private function assertStoreInFactory(Store $store, Factory $factory): void
    {
        $store->loadMissing('warehouse');

        if ($store->warehouse?->factory_id !== $factory->id) {
            throw ValidationException::withMessages(['store_id' => __('procurement.store_not_in_factory')]);
        }
    }

    private function assertEditable(GoodsReceipt $receipt): void
    {
        if (! $receipt->isEditable()) {
            throw ValidationException::withMessages([
                'status' => __('procurement.only_draft_editable', ['status' => $receipt->status]),
            ])->status(409);
        }
    }

    private function assertTransition(GoodsReceipt $receipt, string $to): void
    {
        if (! $receipt->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $receipt->status, 'to' => $to]),
            ])->status(409);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Actions;

use App\Modules\Approval\Actions\RequestApproval;
use App\Modules\Finance\Services\GlPoster;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\Store;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\ItemMaster\Models\ItemStockTransaction;
use App\Modules\ItemMaster\Models\StockCount;
use App\Modules\ItemMaster\Models\StockCountLine;
use App\Modules\ItemMaster\Services\ItemLedger;
use App\Modules\ItemMaster\Services\ItemLineResolver;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Physical inventory, start to finish (Inventory spec §12).
 *
 * Three things here are load-bearing, and all three are about not letting
 * the count invent its own findings:
 *
 *  - **The sheet is the freeze.** System quantities are copied onto the
 *    lines when the sheet is generated and never re-read. A receipt posted
 *    while people are walking the aisles is a real movement, not a
 *    variance, and re-reading at posting time would call it one.
 *  - **The adjustment posts the difference, not the answer.** It moves
 *    `counted − system_at_freeze`, rather than forcing the balance to the
 *    counted figure. Forcing it would silently erase every movement that
 *    happened between the freeze and the posting — including the ones the
 *    count itself has no quarrel with.
 *  - **An uncounted line is not a zero.** Submitting a sheet with shelves
 *    nobody visited is refused, because the alternative is a count that
 *    quietly writes off whatever the counters ran out of time for.
 *
 * ROLL-tracked items are not on the sheet. Their stock lives in
 * `fabric_rolls` as individually identified pieces with no balance row to
 * adjust, so reconciling them is a register check ("is roll R-1183 on the
 * rack?") rather than a quantity count — a different job, not this one.
 */
class ConductStockCount
{
    private const SCALE = 4;

    public function __construct(
        private readonly NumberSequenceGenerator $numbers,
        private readonly ItemLedger $ledger,
        private readonly RequestApproval $approvals,
        private readonly GlPoster $gl,
    ) {}

    /**
     * Opens a count and generates its sheet in one go.
     *
     * @param  array<string, mixed>  $data  count_type?, is_blind?, count_date?, notes?,
     *                                      item_department_id?, item_category_id?, bin_ids?, item_ids?
     */
    public function open(Factory $factory, Store $store, array $data, ?string $userId = null): StockCount
    {
        $type = strtoupper((string) ($data['count_type'] ?? 'CYCLE'));

        if (! in_array($type, StockCount::TYPES, true)) {
            throw ValidationException::withMessages([
                'count_type' => __('validation.in', ['attribute' => 'count_type']),
            ]);
        }

        // Two open counts over the same shelves would each freeze a
        // different system quantity, and whichever posted second would
        // adjust against a figure the first had already moved.
        $open = StockCount::where('store_id', $store->id)
            ->whereIn('status', ['COUNTING', 'UNDER_REVIEW', 'PENDING_APPROVAL', 'APPROVED'])
            ->first();

        if ($open !== null) {
            throw ValidationException::withMessages([
                'store_id' => __('itemmaster.count_already_open', ['number' => $open->count_no]),
            ])->status(409);
        }

        $scope = array_filter([
            'item_department_id' => $data['item_department_id'] ?? null,
            'item_category_id' => $data['item_category_id'] ?? null,
            'bin_ids' => $data['bin_ids'] ?? null,
            'item_ids' => $data['item_ids'] ?? null,
        ]);

        if ($type === 'CYCLE' && $scope === []) {
            // A cycle count with no scope is a full count wearing the wrong
            // label, and the distinction is what the schedule is built on.
            throw ValidationException::withMessages([
                'count_type' => __('itemmaster.cycle_count_needs_scope'),
            ]);
        }

        $rows = $this->balancesFor($store, $scope);

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'store_id' => __('itemmaster.count_nothing_to_count'),
            ])->status(409);
        }

        return DB::transaction(function () use ($factory, $store, $type, $data, $scope, $rows, $userId): StockCount {
            $count = StockCount::create([
                'factory_id' => $factory->id,
                'store_id' => $store->id,
                'count_no' => $this->numbers->next('STOCK_COUNT', $factory),
                'count_type' => $type,
                'status' => 'COUNTING',
                'is_blind' => (bool) ($data['is_blind'] ?? true),
                'count_date' => $data['count_date'] ?? CarbonImmutable::today(),
                'scope_json' => $scope === [] ? null : $scope,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($rows as $row) {
                /** @var Item $item */
                $item = $row['item'];
                /** @var ItemStockBalance $balance */
                $balance = $row['balance'];

                StockCountLine::create([
                    'stock_count_id' => $count->id,
                    'item_id' => $item->id,
                    'item_code' => $item->item_code,
                    'item_name' => $item->name,
                    'uom_id' => $item->uom_id,
                    'bin_id' => $balance->bin_id,
                    'lot_no' => (string) $balance->lot_no,
                    // The freeze.
                    'system_qty' => (string) $balance->quantity_on_hand,
                    'unit_cost' => (string) $balance->weighted_average_cost,
                    'status' => 'PENDING',
                ]);
            }

            return $count->fresh();
        });
    }

    /**
     * Writes what was found on the shelves.
     *
     * @param  list<array<string, mixed>>  $entries  line_id, counted_qty, notes?
     */
    public function record(StockCount $count, array $entries, ?string $userId = null): StockCount
    {
        if (! $count->isCounting()) {
            throw ValidationException::withMessages([
                'status' => __('itemmaster.count_not_open', ['status' => $count->status]),
            ])->status(409);
        }

        if ($entries === []) {
            throw ValidationException::withMessages(['lines' => __('itemmaster.count_needs_lines')]);
        }

        DB::transaction(function () use ($count, $entries, $userId): void {
            foreach ($entries as $index => $entry) {
                $line = StockCountLine::where('stock_count_id', $count->id)
                    ->find($entry['line_id'] ?? null);

                if ($line === null) {
                    throw ValidationException::withMessages([
                        "lines.$index.line_id" => __('itemmaster.count_unknown_line'),
                    ]);
                }

                $counted = ItemLineResolver::money($entry['counted_qty'] ?? 0);

                if (bccomp($counted, '0', self::SCALE) === -1) {
                    throw ValidationException::withMessages([
                        "lines.$index.counted_qty" => __('itemmaster.count_qty_not_negative'),
                    ]);
                }

                $this->applyCount($line, $counted, $entry['notes'] ?? null, $userId);
            }
        });

        return $count->fresh();
    }

    /**
     * Stock found on a shelf the system had no row for at all.
     *
     * This is half the value of a count: a balance that does not exist is
     * invisible to every report, so without a way to write one down the
     * only discrepancies a count can ever find are the ones the system
     * already half knew about.
     */
    public function recordFound(
        StockCount $count,
        Item $item,
        Bin $bin,
        string $countedQty,
        ?string $lotNo = null,
        ?string $userId = null,
    ): StockCountLine {
        if (! $count->isCounting()) {
            throw ValidationException::withMessages([
                'status' => __('itemmaster.count_not_open', ['status' => $count->status]),
            ])->status(409);
        }

        // The sheet only covers the store being counted.
        if ($bin->store_id !== $count->store_id) {
            throw ValidationException::withMessages([
                'bin_id' => __('itemmaster.count_bin_outside_store'),
            ]);
        }

        $this->assertCountable($item);

        $lot = $item->isLotTracked() ? trim((string) $lotNo) : '';

        if ($item->isLotTracked() && $lot === '') {
            throw ValidationException::withMessages([
                'lot_no' => __('itemmaster.lot_required', ['item' => $item->item_code]),
            ]);
        }

        $existing = StockCountLine::where('stock_count_id', $count->id)
            ->where('item_id', $item->id)
            ->where('bin_id', $bin->id)
            ->where('lot_no', $lot)
            ->first();

        if ($existing !== null) {
            // Already on the sheet — this is a count, not a new find.
            $this->applyCount($existing, ItemLineResolver::money($countedQty), null, $userId);

            return $existing->fresh();
        }

        // Whatever the system does know about this (item, bin, lot) is the
        // honest system figure, even when the sheet was scoped past it.
        $balance = ItemStockBalance::where('item_id', $item->id)
            ->where('bin_id', $bin->id)
            ->where('lot_no', $lot)
            ->first();

        $line = StockCountLine::create([
            'stock_count_id' => $count->id,
            'item_id' => $item->id,
            'item_code' => $item->item_code,
            'item_name' => $item->name,
            'uom_id' => $item->uom_id,
            'bin_id' => $bin->id,
            'lot_no' => $lot,
            'system_qty' => (string) ($balance->quantity_on_hand ?? '0'),
            'unit_cost' => $balance !== null
                ? (string) $balance->weighted_average_cost
                : $this->prevailingCost($item),
            'status' => 'PENDING',
            'is_unexpected' => $balance === null,
        ]);

        $this->applyCount($line, ItemLineResolver::money($countedQty), null, $userId);

        return $line->fresh();
    }

    /** Sends one line back to the shelf rather than accepting its figure. */
    public function flagForRecount(StockCountLine $line, ?string $reason = null): StockCountLine
    {
        $count = StockCount::findOrFail($line->stock_count_id);

        if (! in_array($count->status, ['COUNTING', 'UNDER_REVIEW'], true)) {
            throw ValidationException::withMessages([
                'status' => __('itemmaster.count_not_open', ['status' => $count->status]),
            ])->status(409);
        }

        $line->forceFill([
            'status' => 'RECOUNT',
            // Cleared on purpose: a line going back to the shelf has no
            // answer until somebody returns with one.
            'counted_qty' => null,
            'variance_qty' => '0',
            'variance_value' => '0',
            'notes' => $reason ?? $line->notes,
        ])->save();

        return $line->fresh();
    }

    /**
     * Closes the counting and sends the sheet for review.
     */
    public function submit(StockCount $count, ?string $userId = null): StockCount
    {
        $this->assertTransition($count, 'UNDER_REVIEW');

        $count->load('lines');

        $uncounted = $count->lines->filter(
            fn (StockCountLine $line): bool => $line->isUncounted(),
        );

        if ($uncounted->isNotEmpty()) {
            // Letting this through would write off whatever the counters
            // ran out of time for.
            throw ValidationException::withMessages([
                'lines' => __('itemmaster.count_has_uncounted', ['count' => $uncounted->count()]),
            ])->status(409);
        }

        $variance = $count->lines->reduce(
            // Absolute: being 50,000 short of one item and 50,000 over on
            // another is two problems, not none.
            fn (string $sum, StockCountLine $line): string => bcadd(
                $sum,
                $this->absolute((string) $line->variance_value),
                self::SCALE,
            ),
            '0.0000',
        );

        $count->forceFill([
            'status' => 'UNDER_REVIEW',
            'variance_value' => $variance,
            'submitted_at' => CarbonImmutable::now(),
        ])->save();

        // The engine decides: a workflow may hold it, or approve it at once
        // when none matches. Either way `onApprovalDecided` moves the sheet.
        $this->approvals->forEntity($count->fresh(), $userId);

        return $count->fresh();
    }

    /**
     * Posts the adjustments the count found.
     *
     * Every line moves through the ordinary ledger as an
     * `ADJUSTMENT_IN`/`ADJUSTMENT_OUT`, not a movement type of its own: the
     * reports that group by type already know those two words, and a third
     * would have to be taught to every one of them.
     */
    public function post(StockCount $count, ?string $userId = null): StockCount
    {
        if (! $count->isPostable()) {
            throw ValidationException::withMessages([
                'status' => __('itemmaster.count_not_postable', ['status' => $count->status]),
            ])->status(409);
        }

        $count->load('lines');

        $posted = DB::transaction(function () use ($count, $userId): StockCount {
            foreach ($count->lines as $line) {
                $quantity = $this->absolute($line->adjustmentQty());

                if (bccomp($quantity, '0', self::SCALE) !== 1) {
                    $line->forceFill(['status' => 'MATCHED'])->save();

                    continue;
                }

                $item = Item::findOrFail($line->item_id);
                $bin = Bin::findOrFail($line->bin_id);

                $transaction = $this->ledger->post(
                    $item,
                    $bin,
                    $line->isOver() ? 'ADJUSTMENT_IN' : 'ADJUSTMENT_OUT',
                    $quantity,
                    // What it was worth when the sheet was frozen; stock
                    // found on a shelf carries the average it would have
                    // had, not a price invented today.
                    (string) $line->unit_cost,
                    [
                        'lot_no' => $line->lot_no,
                        'reference_type' => 'stock_count',
                        'reference_id' => $count->id,
                        'performed_by' => $userId,
                        // A retried post cannot double the adjustment.
                        'idempotency_key' => 'count:'.$line->id,
                        'notes' => $count->count_no,
                    ],
                );

                $line->forceFill([
                    'status' => 'ADJUSTED',
                    'item_stock_transaction_id' => $transaction->id,
                ])->save();
            }

            $count->forceFill([
                'status' => 'POSTED',
                'posted_by' => $userId,
                'posted_at' => CarbonImmutable::now(),
            ])->save();

            return $count->fresh();
        });

        // The books, after the stock, and outside the transaction: GL
        // posting is a side effect and must not be able to roll back an
        // adjustment it was only reporting.
        //
        // Net here rather than absolute: what the count did to the value of
        // the store is one figure, and for that question a shortage on one
        // shelf genuinely is offset by a surplus on another.
        $net = $count->lines->reduce(
            fn (string $sum, StockCountLine $line): string => bcadd($sum, (string) $line->variance_value, self::SCALE),
            '0.0000',
        );

        $this->gl->post('STOCK_ADJUSTMENT', $this->absolute($net), [
            'source_type' => 'stock_count',
            'source_id' => $posted->id,
            'source_no' => $posted->count_no,
            'factory_id' => $posted->factory_id,
            'date' => $posted->count_date,
            'memo' => $posted->count_no,
            'user_id' => $userId,
            // More on the shelf than the books said: stock up and the
            // variance credited — the same two accounts, the other way round.
            'reverse_sides' => bccomp($net, '0', self::SCALE) === 1,
        ]);

        return $posted;
    }

    public function cancel(StockCount $count, ?string $reason = null): StockCount
    {
        $this->assertTransition($count, 'CANCELLED');

        $count->forceFill([
            'status' => 'CANCELLED',
            'cancelled_at' => CarbonImmutable::now(),
            'notes' => $reason === null ? $count->notes : trim(($count->notes ?? '')."\n".$reason),
        ])->save();

        return $count->fresh();
    }

    /**
     * The shelves a sheet covers.
     *
     * Built from `item_stock_balances` rather than from the item library:
     * a balance row is the system's claim that something is on a shelf,
     * and a count exists to test exactly those claims. Rows already at zero
     * stay in — "the system says nothing is here" is a claim too, and one
     * worth checking.
     *
     * @param  array<string, mixed>  $scope
     * @return Collection<int, array{item: Item, balance: ItemStockBalance}>
     */
    private function balancesFor(Store $store, array $scope): Collection
    {
        $binIds = Bin::where('store_id', $store->id)->where('active', true)->pluck('id');

        if (isset($scope['bin_ids'])) {
            $binIds = $binIds->intersect((array) $scope['bin_ids']);
        }

        if ($binIds->isEmpty()) {
            return collect();
        }

        $balances = ItemStockBalance::whereIn('bin_id', $binIds->all())->get();

        if ($balances->isEmpty()) {
            return collect();
        }

        $items = Item::whereIn('id', $balances->pluck('item_id')->unique()->all())->get()->keyBy('id');

        return $balances
            ->map(fn (ItemStockBalance $balance): ?array => ($item = $items->get($balance->item_id)) === null
                ? null
                : ['item' => $item, 'balance' => $balance])
            ->filter()
            ->filter(function (array $row) use ($scope): bool {
                /** @var Item $item */
                $item = $row['item'];

                // ROLL and SERIAL stock has no balance to adjust; see the
                // class note. Belt and braces — it should not be here.
                if (! $this->isCountable($item)) {
                    return false;
                }

                if (isset($scope['item_department_id']) && $item->item_department_id !== $scope['item_department_id']) {
                    return false;
                }

                if (isset($scope['item_category_id']) && $item->category_id !== $scope['item_category_id']) {
                    return false;
                }

                if (isset($scope['item_ids']) && ! in_array($item->id, (array) $scope['item_ids'], true)) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    private function applyCount(StockCountLine $line, string $counted, ?string $notes, ?string $userId): void
    {
        $variance = bcsub($counted, (string) $line->system_qty, self::SCALE);
        $matched = bccomp($variance, '0', self::SCALE) === 0;

        $line->forceFill([
            'counted_qty' => $counted,
            'variance_qty' => $variance,
            'variance_value' => bcmul($variance, (string) $line->unit_cost, self::SCALE),
            'status' => $matched ? 'MATCHED' : 'VARIANCE',
            'counted_by' => $userId,
            'counted_at' => CarbonImmutable::now(),
            'notes' => $notes ?? $line->notes,
        ])->save();
    }

    /**
     * What stock found on an unrecorded shelf is worth.
     *
     * An `ADJUSTMENT_IN` moves the weighted average, so bringing found
     * stock in at zero would not merely understate it — it would drag the
     * bin's average down towards nothing and quietly mis-value every unit
     * already on that shelf. The item's prevailing average elsewhere is the
     * closest honest answer; failing that, what it last came in at.
     *
     * Zero only when the ledger has never seen the item at all, which is a
     * genuinely unknown value rather than a cheap one.
     */
    private function prevailingCost(Item $item): string
    {
        $balance = ItemStockBalance::where('item_id', $item->id)
            ->where('quantity_on_hand', '>', 0)
            ->orderByDesc('quantity_on_hand')
            ->first();

        if ($balance !== null) {
            return (string) $balance->weighted_average_cost;
        }

        $inbound = ItemStockTransaction::where('item_id', $item->id)
            ->whereIn('transaction_type', ItemStockTransaction::AFFECTS_WAC)
            ->orderByDesc('transaction_at')
            ->first();

        return (string) ($inbound->unit_cost ?? '0');
    }

    private function isCountable(Item $item): bool
    {
        return ! in_array($item->tracking_mode, ['ROLL', 'SERIAL'], true);
    }

    private function assertCountable(Item $item): void
    {
        if (! $this->isCountable($item)) {
            throw ValidationException::withMessages([
                'item_id' => __('itemmaster.count_item_not_countable', ['item' => $item->item_code]),
            ]);
        }
    }

    private function absolute(string $number): string
    {
        return bccomp($number, '0', self::SCALE) === -1
            ? bcmul($number, '-1', self::SCALE)
            : $number;
    }

    private function assertTransition(StockCount $count, string $to): void
    {
        if (! $count->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $count->status, 'to' => $to]),
            ])->status(409);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Actions;

use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Actions\ReserveItemStock;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemReservation;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\ItemMaster\Services\ItemLedger;
use App\Modules\ItemMaster\Services\ItemNotifier;
use App\Modules\Procurement\Actions\ManagePurchaseRequisition;
use App\Modules\Procurement\Models\PurchaseRequisition;
use App\Modules\Requisition\Models\FloorRequisition;
use App\Modules\Requisition\Models\FloorRequisitionIssue;
use App\Modules\Requisition\Models\FloorRequisitionLine;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The store's side of a floor requisition (Floor/Store Requisition spec
 * §4–§7).
 *
 * Review, issue, shortage, return, close. Three things here are deliberate
 * and worth stating:
 *
 *  - **The shortage is not optional.** A request the store cannot fill does
 *    not just sit there part-issued: every short line raises a claim on the
 *    stock that is coming and goes onto one automatic purchase requisition
 *    (§5.3), left in DRAFT so the store manager sees it before it is
 *    submitted. Skipping that is how a floor ends up chasing the store by
 *    phone for a week.
 *  - **The balance lock is the concurrency guard.** Two storekeepers
 *    issuing the last 50 metres at the same moment both read "50 available"
 *    unless the row is locked; one of them has to be refused, and the lock
 *    inside `ItemLedger` is what refuses them.
 *  - **A returnable item is borrowed, not consumed** (§7). It leaves the
 *    store the same way, but the line lands in BORROWED and is not finished
 *    until it comes back.
 */
class FulfilFloorRequisition
{
    private const SCALE = 4;

    public function __construct(
        private readonly ItemLedger $ledger,
        private readonly ReserveItemStock $reservations,
        private readonly ManagePurchaseRequisition $purchaseRequisitions,
        private readonly ItemNotifier $notifier,
    ) {}

    /**
     * The store takes the request on and says what it will give. An
     * approved quantity below what was asked for is the normal case — the
     * floor asks for a week's worth, the store issues a day's.
     *
     * @param  list<array{line_id: string, approved_qty?: string, rejection_reason?: string}>  $lines
     */
    public function startReview(FloorRequisition $requisition, array $lines = [], ?string $userId = null): FloorRequisition
    {
        if ($requisition->status === 'SUBMITTED') {
            $this->assertTransition($requisition, 'UNDER_REVIEW');
        } elseif (! $requisition->isIssuable()) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $requisition->status, 'to' => 'UNDER_REVIEW']),
            ])->status(409);
        }

        $known = $requisition->lines()->get()->keyBy('id');

        return DB::transaction(function () use ($requisition, $lines, $userId, $known): FloorRequisition {
            foreach ($lines as $index => $payload) {
                $line = $known->get($payload['line_id'] ?? '');

                if ($line === null) {
                    throw ValidationException::withMessages(["lines.$index.line_id" => __('requisition.unknown_line')]);
                }

                $approved = (string) ($payload['approved_qty'] ?? $line->targetQty());

                if (bccomp($approved, '0', self::SCALE) < 0) {
                    throw ValidationException::withMessages(["lines.$index.approved_qty" => __('inventory.quantity_must_be_positive')]);
                }

                // Approving nothing is a refusal of that line, and a refusal
                // owes the floor a reason.
                $rejected = bccomp($approved, '0', self::SCALE) === 0;

                $line->forceFill([
                    'approved_qty' => $approved,
                    'status' => $rejected ? 'REJECTED' : $line->status,
                    'rejection_reason' => $payload['rejection_reason'] ?? $line->rejection_reason,
                ])->save();
            }

            if ($requisition->status === 'SUBMITTED') {
                $requisition->forceFill([
                    'status' => 'UNDER_REVIEW',
                    'reviewed_by' => $userId,
                    'reviewed_at' => CarbonImmutable::now(),
                ])->save();
            }

            return $requisition->fresh();
        });
    }

    /**
     * Hands stock over the counter.
     *
     * Everything happens in one transaction: a line that cannot be issued
     * must not leave the earlier lines half-given, because the floor has
     * already walked away with the trolley.
     *
     * @param  list<array{line_id: string, quantity?: string, bin_id?: string, lot_no?: string, fabric_roll_id?: string}>  $lines
     */
    public function issue(FloorRequisition $requisition, array $lines, ?string $userId = null): FloorRequisition
    {
        if ($requisition->status === 'SUBMITTED') {
            // The store skipped the review step and went straight to the
            // shelf; the review is implied rather than demanded.
            $requisition = $this->startReview($requisition, [], $userId);
        }

        if (! $requisition->isIssuable()) {
            throw ValidationException::withMessages([
                'status' => __('requisition.not_issuable', ['status' => $requisition->status]),
            ])->status(409);
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('procurement.needs_lines')]);
        }

        $known = $requisition->lines()->get()->keyBy('id');
        $items = Item::whereIn('id', $known->pluck('item_id')->unique()->all())->get()->keyBy('id');

        /** @var Collection<int, FloorRequisitionLine> $shortLines */
        $shortLines = DB::transaction(function () use ($requisition, $lines, $userId, $known, $items): Collection {
            foreach ($lines as $index => $payload) {
                /** @var FloorRequisitionLine|null $line */
                $line = $known->get($payload['line_id'] ?? '');
                $field = "lines.$index";

                if ($line === null) {
                    throw ValidationException::withMessages(["$field.line_id" => __('requisition.unknown_line')]);
                }

                /** @var Item|null $item */
                $item = $items->get($line->item_id);

                if ($item === null) {
                    throw ValidationException::withMessages(["$field.line_id" => __('procurement.unknown_item')]);
                }

                if ($line->status === 'REJECTED') {
                    throw ValidationException::withMessages(["$field.line_id" => __('requisition.line_rejected')]);
                }

                $item->usesRollLedger()
                    ? $this->issueRoll($line, $item, $payload, $field, $userId)
                    : $this->issueQuantity($requisition, $line, $item, $payload, $field, $userId);
            }

            // Inside the same transaction as the issue, because the spec
            // makes the shortage handling compulsory: stock that left the
            // store without its claim and its purchase requisition would be
            // exactly the hole this is here to close.
            return $this->recordShortage($requisition->fresh(), $userId);
        });

        $requisition = $this->settle($requisition->fresh(), $userId);

        // Notifications after the commit: one that fails must not roll back
        // stock that has already crossed the counter.
        $issuedLines = $requisition->lines()->whereIn('status', ['ISSUED', 'PARTIALLY_ISSUED', 'BORROWED'])->count();
        $this->notifier->floorRequisitionIssued($requisition, $issuedLines, $shortLines->count());

        if ($shortLines->isNotEmpty()) {
            $this->notifier->floorRequisitionShortage(
                $requisition,
                $shortLines->count(),
                PurchaseRequisition::find($requisition->shortage_pr_id)?->pr_no,
            );
        }

        return $requisition;
    }

    /**
     * The store cannot fill the request and says so outright — a different
     * thing from issuing nothing, which leaves the request open.
     */
    public function reject(FloorRequisition $requisition, string $reason, ?string $userId = null): FloorRequisition
    {
        if ($requisition->status === 'SUBMITTED') {
            $requisition = $this->startReview($requisition, [], $userId);
        }

        $this->assertTransition($requisition, 'REJECTED');

        return DB::transaction(function () use ($requisition, $reason, $userId): FloorRequisition {
            $this->releaseClaims($requisition, $userId, 'CANCELLED');

            $requisition->lines()->whereNotIn('status', ['ISSUED', 'BORROWED', 'RETURNED'])
                ->update(['status' => 'REJECTED', 'rejection_reason' => $reason]);

            $requisition->forceFill([
                'status' => 'REJECTED',
                'rejection_reason' => $reason,
                'reviewed_by' => $requisition->reviewed_by ?? $userId,
            ])->save();

            return $requisition->fresh();
        });
    }

    /**
     * Ends the requisition. Whatever was never issued is given up on — the
     * claims it held are released so the stock stops being invisible to
     * everybody else.
     */
    public function close(FloorRequisition $requisition, ?string $userId = null): FloorRequisition
    {
        $this->assertTransition($requisition, 'CLOSED');

        $borrowed = $requisition->lines()->where('status', 'BORROWED')->count();

        if ($borrowed > 0) {
            throw ValidationException::withMessages([
                'status' => __('requisition.close_blocked_by_borrowed', ['count' => (string) $borrowed]),
            ])->status(409);
        }

        return DB::transaction(function () use ($requisition, $userId): FloorRequisition {
            $this->releaseClaims($requisition, $userId);

            $requisition->forceFill([
                'status' => 'CLOSED',
                'closed_at' => CarbonImmutable::now(),
            ])->save();

            return $requisition->fresh();
        });
    }

    /**
     * A borrowed item coming back (spec §7).
     *
     * Posted at the price it left at, not at whatever the weighted average
     * has drifted to since: the store is getting back the same goods it
     * lent, and re-pricing them would quietly move value between cost
     * centres.
     */
    public function returnToStore(FloorRequisitionIssue $issue, ?string $quantity = null, ?string $userId = null): FloorRequisitionIssue
    {
        $issue->loadMissing('line');
        $outstanding = $issue->outstandingQty();
        $quantity ??= $outstanding;

        if (bccomp($quantity, '0', self::SCALE) !== 1) {
            throw ValidationException::withMessages(['quantity' => __('inventory.quantity_must_be_positive')]);
        }

        if (bccomp($quantity, $outstanding, self::SCALE) > 0) {
            throw ValidationException::withMessages([
                'quantity' => __('requisition.return_exceeds_issue'),
            ])->status(409);
        }

        $line = $issue->line;
        $item = Item::findOrFail($line->item_id);

        return DB::transaction(function () use ($issue, $line, $item, $quantity, $userId): FloorRequisitionIssue {
            $transactionId = null;

            if ($item->usesRollLedger()) {
                $roll = FabricRoll::find($issue->fabric_roll_id);

                if ($roll !== null && $roll->canTransitionTo('AVAILABLE')) {
                    $roll->forceFill(['status' => 'AVAILABLE'])->save();
                }
            } else {
                $transaction = $this->ledger->post(
                    $item, Bin::findOrFail($issue->bin_id), 'RETURN', $quantity, (string) $issue->unit_cost, [
                        'lot_no' => $issue->lot_no,
                        'currency' => $issue->currency,
                        'reference_type' => 'floor_requisition',
                        'reference_id' => $line->floor_requisition_id,
                        'performed_by' => $userId,
                        'notes' => $line->item_code,
                    ],
                );

                $transactionId = $transaction->id;
            }

            $returned = bcadd((string) $issue->returned_qty, $quantity, self::SCALE);

            $issue->forceFill([
                'returned_qty' => $returned,
                'returned_at' => CarbonImmutable::now(),
                'return_transaction_id' => $transactionId ?? $issue->return_transaction_id,
            ])->save();

            $lineReturned = bcadd((string) $line->returned_qty, $quantity, self::SCALE);
            $fullyBack = bccomp($lineReturned, (string) $line->issued_qty, self::SCALE) >= 0;

            $line->forceFill([
                'returned_qty' => $lineReturned,
                'status' => $fullyBack ? 'RETURNED' : $line->status,
            ])->save();

            return $issue->fresh();
        });
    }

    /**
     * What the store could actually give this requisition right now, per
     * line — the numbers the fulfilment screen needs before anybody types a
     * quantity.
     *
     * Stock held for somebody else is not counted (§4): it is physically
     * there and is not available. Stock held for *this* requisition is,
     * because that is the whole point of having held it.
     *
     * @return list<array<string, mixed>>
     */
    public function availability(FloorRequisition $requisition): array
    {
        $requisition->loadMissing('lines');

        $binIds = Bin::whereHas('store.warehouse', fn ($q) => $q->where('factory_id', $requisition->factory_id))
            ->where('active', true)
            ->pluck('id', 'id');

        $items = Item::whereIn('id', $requisition->lines->pluck('item_id')->unique()->all())->get()->keyBy('id');

        return $requisition->lines->map(function (FloorRequisitionLine $line) use ($items, $binIds): array {
            /** @var Item|null $item */
            $item = $items->get($line->item_id);

            $sources = $item === null ? [] : ($item->usesRollLedger()
                ? $this->rollSources($item, $line, $binIds->all())
                : $this->binSources($item, $line, $binIds->all()));

            $available = array_reduce(
                $sources,
                fn (string $sum, array $source): string => bcadd($sum, $source['available'], self::SCALE),
                '0.0000',
            );

            return [
                'line_id' => $line->id,
                'item_id' => $line->item_id,
                'item_code' => $line->item_code,
                'item_name' => $line->item_name,
                'tracking_mode' => $item?->tracking_mode,
                'is_returnable' => (bool) ($item?->is_returnable),
                'requested_qty' => (string) $line->requested_qty,
                'approved_qty' => $line->approved_qty === null ? null : (string) $line->approved_qty,
                'issued_qty' => (string) $line->issued_qty,
                'outstanding_qty' => $line->outstandingQty(),
                'available_qty' => $available,
                'status' => $line->status,
                'earmarked_qty' => $this->earmarkedFor($line),
                'sources' => $sources,
            ];
        })->all();
    }

    /**
     * A QUANTITY/LOT issue: the balance is locked, the claim this line
     * holds is drawn down first, then the stock moves.
     *
     * @param  array<string, mixed>  $payload
     */
    private function issueQuantity(
        FloorRequisition $requisition,
        FloorRequisitionLine $line,
        Item $item,
        array $payload,
        string $field,
        ?string $userId,
    ): void {
        $quantity = (string) ($payload['quantity'] ?? $line->outstandingQty());

        if (bccomp($quantity, '0', self::SCALE) !== 1) {
            throw ValidationException::withMessages(["$field.quantity" => __('inventory.quantity_must_be_positive')]);
        }

        if (bccomp($quantity, $line->outstandingQty(), self::SCALE) > 0) {
            throw ValidationException::withMessages([
                "$field.quantity" => __('requisition.issue_exceeds_approved', [
                    'item' => $line->item_name,
                    'outstanding' => $this->trim($line->outstandingQty()),
                ]),
            ])->status(409);
        }

        $binId = $payload['bin_id'] ?? null;

        if (! is_string($binId) || $binId === '') {
            throw ValidationException::withMessages([
                "$field.bin_id" => __('procurement.bin_required_in_store', ['item' => $line->item_code]),
            ]);
        }

        $bin = Bin::findOrFail($binId);
        $lotNo = $item->isLotTracked() ? trim((string) ($payload['lot_no'] ?? '')) : ItemStockBalance::NO_LOT;

        if ($item->isLotTracked() && $lotNo === '') {
            throw ValidationException::withMessages([
                "$field.lot_no" => __('itemmaster.lot_required', ['item' => $item->item_code]),
            ]);
        }

        // Two storekeepers issuing the last unit at the same moment: this
        // lock is what makes one of them lose.
        $this->ledger->lockBalance($item, $bin, $lotNo);

        $fromReservation = $this->consumeClaims($line, $bin, $lotNo, $quantity);

        $transaction = $this->ledger->post($item, $bin, 'ISSUE', $quantity, null, [
            'lot_no' => $lotNo,
            'reference_type' => 'floor_requisition',
            'reference_id' => $requisition->id,
            'performed_by' => $userId,
            'notes' => $requisition->fr_no,
            // Stock this line was already holding is not blocked by its own
            // encumbrance; the ledger checks it against what is on hand.
            'against_reservation' => bccomp($fromReservation, '0', self::SCALE) === 1,
        ]);

        FloorRequisitionIssue::create([
            'floor_requisition_line_id' => $line->id,
            'bin_id' => $bin->id,
            'lot_no' => $lotNo === '' ? null : $lotNo,
            'quantity' => $quantity,
            'unit_cost' => (string) $transaction->unit_cost,
            'currency' => $transaction->currency,
            'item_stock_transaction_id' => $transaction->id,
            'issued_by' => $userId,
            'issued_at' => CarbonImmutable::now(),
        ]);

        $this->advanceLine($line, $item, $quantity);
    }

    /**
     * A ROLL issue names the roll. Nothing is posted to the stock ledger —
     * for a roll-tracked item the roll's own status *is* the ledger.
     *
     * @param  array<string, mixed>  $payload
     */
    private function issueRoll(
        FloorRequisitionLine $line,
        Item $item,
        array $payload,
        string $field,
        ?string $userId,
    ): void {
        $rollId = $payload['fabric_roll_id'] ?? null;

        if (! is_string($rollId) || $rollId === '') {
            throw ValidationException::withMessages([
                "$field.fabric_roll_id" => __('requisition.roll_required', ['item' => $line->item_name]),
            ]);
        }

        $roll = FabricRoll::where('item_id', $item->id)->lockForUpdate()->find($rollId);

        if ($roll === null) {
            throw ValidationException::withMessages(["$field.fabric_roll_id" => __('requisition.unknown_roll')]);
        }

        if (! $roll->canTransitionTo('ISSUED')) {
            throw ValidationException::withMessages([
                "$field.fabric_roll_id" => __('requisition.roll_not_issuable', [
                    'roll' => $roll->roll_no, 'status' => $roll->status,
                ]),
            ])->status(409);
        }

        // A roll is issued whole: its measured length is what leaves, not a
        // number somebody types.
        $quantity = (string) ($roll->net_length ?? $roll->net_weight ?? '1');

        $this->consumeRollClaim($roll);

        $roll->forceFill(['status' => 'ISSUED'])->save();

        FloorRequisitionIssue::create([
            'floor_requisition_line_id' => $line->id,
            'bin_id' => $roll->bin_id,
            'lot_no' => $roll->lot_no,
            'fabric_roll_id' => $roll->id,
            'quantity' => $quantity,
            'unit_cost' => (string) $roll->unit_cost,
            'currency' => $roll->currency ?? 'BDT',
            'issued_by' => $userId,
            'issued_at' => CarbonImmutable::now(),
        ]);

        $this->advanceLine($line, $item, $quantity);
    }

    /**
     * Draws down whatever this line was holding in that bin, oldest claim
     * first, and returns how much of the issue came out of a claim.
     */
    private function consumeClaims(FloorRequisitionLine $line, Bin $bin, string $lotNo, string $quantity): string
    {
        $claims = ItemReservation::where('reference_type', 'floor_requisition_line')
            ->where('reference_id', $line->id)
            ->whereIn('status', ItemReservation::HOLDING_STATUSES)
            ->where('bin_id', $bin->id)
            ->where('lot_no', $lotNo)
            ->orderBy('reserved_at')
            ->orderBy('id')
            ->get();

        $remaining = $quantity;
        $consumed = '0.0000';

        foreach ($claims as $claim) {
            if (bccomp($remaining, '0', self::SCALE) !== 1) {
                break;
            }

            $outstanding = $claim->outstanding();
            $take = bccomp($outstanding, $remaining, self::SCALE) > 0 ? $remaining : $outstanding;

            if (bccomp($take, '0', self::SCALE) !== 1) {
                continue;
            }

            $this->reservations->consume($claim, $take);

            $remaining = bcsub($remaining, $take, self::SCALE);
            $consumed = bcadd($consumed, $take, self::SCALE);
        }

        return $consumed;
    }

    private function consumeRollClaim(FabricRoll $roll): void
    {
        $claim = ItemReservation::where('fabric_roll_id', $roll->id)
            ->whereIn('status', ItemReservation::HOLDING_STATUSES)
            ->first();

        if ($claim === null) {
            return;
        }

        $this->reservations->consume($claim, $claim->outstanding());
    }

    /** Moves the line's own quantities and status after an issue. */
    private function advanceLine(FloorRequisitionLine $line, Item $item, string $quantity): void
    {
        $issued = bcadd((string) $line->issued_qty, $quantity, self::SCALE);
        $complete = bccomp($issued, $line->targetQty(), self::SCALE) >= 0;

        $shortage = bcsub($line->targetQty(), $issued, self::SCALE);

        $line->forceFill([
            'issued_qty' => $issued,
            // Recomputed on every issue, so a shortage filled later stops
            // reading as a shortage.
            'shortage_qty' => bccomp($shortage, '0', self::SCALE) === 1 ? $shortage : '0.0000',
            // A returnable item is lent, not given: the line is not finished
            // until it comes back (spec §7).
            'status' => match (true) {
                (bool) $item->is_returnable => 'BORROWED',
                $complete => 'ISSUED',
                default => 'PARTIALLY_ISSUED',
            },
        ])->save();
    }

    /**
     * Every line still short gets a claim on the stock that is coming, and
     * all of them go onto one automatic purchase requisition — left in
     * DRAFT, because a document nobody looked at is not a document anybody
     * trusts.
     *
     * Runs inside `issue()`'s transaction; there is no parameter to skip it.
     *
     * @return Collection<int, FloorRequisitionLine>
     */
    private function recordShortage(FloorRequisition $requisition, ?string $userId): Collection
    {
        $short = $requisition->lines()->get()->filter(
            fn (FloorRequisitionLine $line): bool => $line->status !== 'REJECTED'
                && bccomp($line->outstandingQty(), '0', self::SCALE) === 1,
        )->values();

        if ($short->isEmpty()) {
            return $short;
        }

        $fresh = $short->filter(fn (FloorRequisitionLine $line): bool => $line->item_reservation_id === null)->values();

        DB::transaction(function () use ($short, $fresh, $requisition, $userId): void {
            foreach ($short as $line) {
                $line->forceFill([
                    'shortage_qty' => $line->outstandingQty(),
                    'status' => bccomp((string) $line->issued_qty, '0', self::SCALE) === 1
                        ? $line->status
                        : 'SHORT',
                ])->save();
            }

            foreach ($fresh as $line) {
                $claim = $this->reservations->earmark(
                    Item::findOrFail($line->item_id),
                    $line->outstandingQty(),
                    [
                        'reservation_type' => 'FLOOR_REQUISITION',
                        'reference_type' => 'floor_requisition_line',
                        'reference_id' => $line->id,
                        'reserved_by' => $userId,
                    ],
                );

                $line->forceFill(['item_reservation_id' => $claim->id])->save();
            }

            if ($fresh->isNotEmpty() && $requisition->shortage_pr_id === null) {
                $requisition->forceFill([
                    'shortage_pr_id' => $this->raisePurchaseRequisition($requisition, $fresh, $userId)->id,
                ])->save();
            }
        });

        return $short;
    }

    /**
     * @param  Collection<int, FloorRequisitionLine>  $lines
     */
    private function raisePurchaseRequisition(FloorRequisition $requisition, Collection $lines, ?string $userId): PurchaseRequisition
    {
        return $this->purchaseRequisitions->create(
            Factory::findOrFail($requisition->factory_id),
            [
                'pr_type' => 'FROM_FLOOR_REQUISITION',
                // A ULID column: the requisition's id, not its number. The
                // number a person reads is in the notes.
                'source_reference' => $requisition->id,
                'priority' => $requisition->priority,
                'notes' => __('requisition.auto_pr_notes', ['number' => $requisition->fr_no]),
            ],
            $lines->map(fn (FloorRequisitionLine $line): array => [
                'item_id' => $line->item_id,
                'requested_qty' => $line->outstandingQty(),
                'uom_id' => $line->uom_id,
                'notes' => $line->purpose,
            ])->all(),
            $userId,
        );
    }

    /** Where the requisition stands once an issue round is over. */
    private function settle(FloorRequisition $requisition, ?string $userId): FloorRequisition
    {
        $outstanding = $requisition->lines()->get()->contains(
            fn (FloorRequisitionLine $line): bool => $line->status !== 'REJECTED'
                && bccomp($line->outstandingQty(), '0', self::SCALE) === 1,
        );

        $status = $outstanding ? 'PARTIALLY_FULFILLED' : 'FULLY_FULFILLED';

        $requisition->forceFill([
            'status' => $status,
            'reviewed_by' => $requisition->reviewed_by ?? $userId,
            'reviewed_at' => $requisition->reviewed_at ?? CarbonImmutable::now(),
        ])->save();

        return $requisition->fresh();
    }

    /**
     * @param  list<string>  $binIds
     * @return list<array<string, mixed>>
     */
    private function binSources(Item $item, FloorRequisitionLine $line, array $binIds): array
    {
        $balances = ItemStockBalance::where('item_id', $item->id)
            ->whereIn('bin_id', $binIds)
            ->with('bin:id,code,name,store_id')
            // Oldest first is the FIFO suggestion the picker shows at the top.
            ->orderBy('created_at')
            ->orderBy('lot_no')
            ->get();

        $ownClaims = ItemReservation::where('reference_type', 'floor_requisition_line')
            ->where('reference_id', $line->id)
            ->whereIn('status', ItemReservation::HOLDING_STATUSES)
            ->get();

        return $balances->map(function (ItemStockBalance $balance) use ($ownClaims): array {
            $mine = $ownClaims
                ->where('bin_id', $balance->bin_id)
                ->where('lot_no', $balance->lot_no)
                ->reduce(fn (string $sum, ItemReservation $r): string => bcadd($sum, $r->outstanding(), self::SCALE), '0.0000');

            // Free stock, plus whatever this line is already holding here.
            $available = bcadd($balance->available(), $mine, self::SCALE);

            return [
                'bin_id' => $balance->bin_id,
                'bin' => $balance->bin === null ? null : ['code' => $balance->bin->code, 'name' => $balance->bin->name],
                'lot_no' => $balance->lot_no === '' ? null : $balance->lot_no,
                'fabric_roll_id' => null,
                'roll_no' => null,
                'on_hand' => (string) $balance->quantity_on_hand,
                'available' => bccomp($available, '0', self::SCALE) === 1 ? $available : '0.0000',
                'reserved_for_this_line' => $mine,
            ];
        })->filter(fn (array $source): bool => bccomp($source['available'], '0', self::SCALE) === 1)
            ->values()->all();
    }

    /**
     * @param  list<string>  $binIds
     * @return list<array<string, mixed>>
     */
    private function rollSources(Item $item, FloorRequisitionLine $line, array $binIds): array
    {
        $heldHere = ItemReservation::where('reference_type', 'floor_requisition_line')
            ->where('reference_id', $line->id)
            ->whereIn('status', ItemReservation::HOLDING_STATUSES)
            ->whereNotNull('fabric_roll_id')
            ->pluck('fabric_roll_id')
            ->all();

        return FabricRoll::where('item_id', $item->id)
            ->whereIn('bin_id', $binIds)
            ->where(fn ($q) => $q->where('status', 'AVAILABLE')->orWhereIn('id', $heldHere))
            ->with('bin:id,code,name')
            // Oldest roll first: fabric ages, and a store that always
            // reaches for the nearest roll ends up scrapping the back row.
            ->orderBy('received_date')
            ->orderBy('roll_no')
            ->get()
            ->map(fn (FabricRoll $roll): array => [
                'bin_id' => $roll->bin_id,
                'bin' => $roll->bin === null ? null : ['code' => $roll->bin->code, 'name' => $roll->bin->name],
                'lot_no' => $roll->lot_no,
                'fabric_roll_id' => $roll->id,
                'roll_no' => $roll->roll_no,
                'shade' => $roll->shade,
                'on_hand' => (string) ($roll->net_length ?? $roll->net_weight ?? '0'),
                'available' => (string) ($roll->net_length ?? $roll->net_weight ?? '0'),
                'reserved_for_this_line' => in_array($roll->id, $heldHere, true) ? '1' : '0',
            ])->all();
    }

    private function earmarkedFor(FloorRequisitionLine $line): string
    {
        return ItemReservation::where('reference_type', 'floor_requisition_line')
            ->where('reference_id', $line->id)
            ->where('status', 'PENDING_ARRIVAL')
            ->get()
            ->reduce(fn (string $sum, ItemReservation $r): string => bcadd($sum, $r->outstanding(), self::SCALE), '0.0000');
    }

    /**
     * Gives back everything this requisition's lines are holding — claims
     * are keyed to the line, not to the header, because a line can be
     * part-filled and re-earmarked on its own.
     */
    private function releaseClaims(FloorRequisition $requisition, ?string $userId, string $status = 'RELEASED'): void
    {
        foreach ($requisition->lines()->get() as $line) {
            $this->reservations->releaseFor('floor_requisition_line', $line->id, $userId, $status);
        }
    }

    private function assertTransition(FloorRequisition $requisition, string $to): void
    {
        if (! $requisition->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $requisition->status, 'to' => $to]),
            ])->status(409);
        }
    }

    private function trim(string $value): string
    {
        return rtrim(rtrim($value, '0'), '.');
    }
}

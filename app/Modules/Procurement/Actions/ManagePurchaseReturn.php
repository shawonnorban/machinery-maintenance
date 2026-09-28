<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Actions;

use App\Modules\Approval\Actions\RequestApproval;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\Finance\Services\GlPoster;
use App\Modules\ItemMaster\Services\ItemLedger;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\GoodsReceiptLine;
use App\Modules\Procurement\Models\PurchaseReturn;
use App\Modules\Procurement\Models\PurchaseReturnLine;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Goods going back to the supplier (Procurement spec §12, Inventory §11).
 *
 * The order the spec sets out is the whole point:
 *
 *     approval → gate pass → stock down → debit note
 *
 * Nothing leaves the shelf when the return is drafted or even approved. The
 * stock moves at dispatch, in the same transaction as the gate pass and the
 * debit note, because those three are one event at the gate: the driver
 * takes the goods, the guard signs the pass, and the supplier owes us the
 * money. Splitting them leaves a state where one of the three is true and
 * the other two are not, and every one of those states is somebody's
 * afternoon spent reconciling.
 *
 * **Which rolls may go back** is Inventory §11's rule, and it is narrow on
 * purpose: `AVAILABLE`, `DAMAGED`, or a roll rejected at inspection. An
 * issued roll is on the cutting table and a split or merged one no longer
 * exists as itself — neither can be handed to a driver, so both are claims
 * against the supplier rather than returns.
 */
class ManagePurchaseReturn
{
    private const SCALE = 4;

    /** Inventory spec §11: only these may physically go back. */
    private const RETURNABLE_ROLL_STATUSES = ['AVAILABLE', 'DAMAGED', 'RETURNED'];

    public function __construct(
        private readonly NumberSequenceGenerator $numbers,
        private readonly ItemLedger $ledger,
        private readonly RequestApproval $approvals,
        private readonly GlPoster $gl,
    ) {}

    /**
     * @param  array<string, mixed>  $data  reason, return_date?, notes?
     * @param  list<array<string, mixed>>  $lines  goods_receipt_line_id, quantity?, bin_id?, lot_no?, fabric_roll_id?, notes?
     */
    public function create(GoodsReceipt $receipt, array $data, array $lines, ?string $userId = null): PurchaseReturn
    {
        if ($receipt->status !== 'COMPLETED') {
            // Nothing has been posted to the shelf yet, so there is nothing
            // to take back off it.
            throw ValidationException::withMessages([
                'goods_receipt_id' => __('procurement.return_receipt_not_posted', [
                    'number' => $receipt->grn_no, 'status' => $receipt->status,
                ]),
            ])->status(409);
        }

        if ($receipt->vendor_id === null) {
            throw ValidationException::withMessages([
                'goods_receipt_id' => __('procurement.return_needs_vendor'),
            ]);
        }

        $reason = (string) ($data['reason'] ?? 'OTHER');

        if (! in_array($reason, PurchaseReturn::REASONS, true)) {
            throw ValidationException::withMessages(['reason' => __('validation.in', ['attribute' => 'reason'])]);
        }

        $planned = $this->plan($receipt, $lines);

        return DB::transaction(function () use ($receipt, $data, $reason, $planned, $userId): PurchaseReturn {
            $factory = Factory::findOrFail($receipt->factory_id);

            $return = PurchaseReturn::create([
                'factory_id' => $factory->id,
                'return_no' => $this->numbers->next('PURCHASE_RETURN', $factory),
                'return_date' => $data['return_date'] ?? CarbonImmutable::today(),
                'vendor_id' => $receipt->vendor_id,
                'goods_receipt_id' => $receipt->id,
                'reason' => $reason,
                'status' => 'DRAFT',
                'approval_status' => 'NOT_REQUIRED',
                'currency' => $receipt->lines->first()?->currency ?? 'BDT',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $total = '0.0000';

            foreach ($planned as $step) {
                PurchaseReturnLine::create([
                    'purchase_return_id' => $return->id,
                    'goods_receipt_line_id' => $step['line']->id,
                    'item_id' => $step['item']->id,
                    'item_code' => $step['item']->item_code,
                    'item_name' => $step['item']->name,
                    'uom_id' => $step['line']->uom_id,
                    'bin_id' => $step['bin_id'],
                    'lot_no' => $step['lot_no'],
                    'fabric_roll_id' => $step['roll']?->id,
                    'quantity' => $step['quantity'],
                    'unit_cost' => $step['unit_cost'],
                    'line_total' => $step['total'],
                    'notes' => $step['notes'],
                ]);

                $total = bcadd($total, $step['total'], self::SCALE);
            }

            $return->forceFill(['debit_note_amount' => $total])->save();

            return $return->fresh();
        });
    }

    /**
     * Sends the return for approval. Nothing physical happens here — the
     * goods are still on the shelf, where they should be until somebody
     * with authority says they can go.
     */
    public function submit(PurchaseReturn $return, ?string $userId = null): PurchaseReturn
    {
        $this->assertTransition($return, 'PENDING_APPROVAL');

        $return->load('lines');

        if ($return->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('procurement.needs_lines')]);
        }

        $return->forceFill([
            'status' => 'PENDING_APPROVAL',
            'approval_status' => 'PENDING',
            'submitted_at' => CarbonImmutable::now(),
        ])->save();

        // No matching workflow means the engine decides NOT_REQUIRED, which
        // takes the return straight to APPROVED through its own hook.
        $this->approvals->forEntity($return->fresh(), $userId);

        return $return->fresh();
    }

    /**
     * The goods leave. Gate pass, stock and debit note in one transaction,
     * because at the gate they are one event.
     *
     * @param  array<string, mixed>  $data  gate_pass_no?, notes?
     */
    public function dispatch(PurchaseReturn $return, array $data = [], ?string $userId = null): PurchaseReturn
    {
        if (! $return->isDispatchable()) {
            throw ValidationException::withMessages([
                'status' => __('procurement.return_not_dispatchable', ['status' => $return->status]),
            ])->status(409);
        }

        $return->load('lines');

        $dispatched = DB::transaction(function () use ($return, $data, $userId): PurchaseReturn {
            $factory = Factory::findOrFail($return->factory_id);

            foreach ($return->lines as $line) {
                $item = Item::findOrFail($line->item_id);

                if ($item->usesRollLedger()) {
                    $this->dispatchRoll($line, $return);

                    continue;
                }

                $bin = Bin::findOrFail($line->bin_id);

                // ADJUSTMENT_OUT, not ISSUE: nothing was consumed by the
                // factory, the goods went back out of the gate. The ledger's
                // own type vocabulary has no RETURN_OUT, and inventing one
                // would mean every report that groups by type learns a new
                // word for what is already an outward correction.
                $transaction = $this->ledger->post($item, $bin, 'ADJUSTMENT_OUT', (string) $line->quantity, (string) $line->unit_cost, [
                    'lot_no' => $line->lot_no,
                    'currency' => $return->currency,
                    'reference_type' => 'purchase_return',
                    'reference_id' => $return->id,
                    'performed_by' => $userId,
                    'notes' => $return->return_no,
                ]);

                $line->forceFill(['item_stock_transaction_id' => $transaction->id])->save();
            }

            $return->forceFill([
                'status' => 'DISPATCHED',
                'gate_pass_no' => $data['gate_pass_no'] ?? null,
                // Allocated here, not at draft: a cancelled return must not
                // leave a hole in the debit note series.
                'debit_note_no' => $this->numbers->next('DEBIT_NOTE', $factory),
                'dispatched_by' => $userId,
                'dispatched_at' => CarbonImmutable::now(),
                'notes' => isset($data['notes'])
                    ? trim(($return->notes ?? "\n").$data['notes'])
                    : $return->notes,
            ])->save();

            return $return->fresh();
        });

        // The debit note's other half: the supplier owes this back, and the
        // goods are off the shelf (Shared Systems §2).
        $this->gl->post('PURCHASE_RETURN', $dispatched->totalValue(), [
            'source_type' => 'purchase_return',
            'source_id' => $dispatched->id,
            'source_no' => $dispatched->debit_note_no ?? $dispatched->return_no,
            'factory_id' => $dispatched->factory_id,
            'currency' => $dispatched->currency,
            'date' => $dispatched->return_date,
            'memo' => $dispatched->return_no,
            'user_id' => $userId,
        ]);

        return $dispatched;
    }

    public function cancel(PurchaseReturn $return, ?string $reason = null): PurchaseReturn
    {
        $this->assertTransition($return, 'CANCELLED');

        $return->forceFill([
            'status' => 'CANCELLED',
            'cancelled_at' => CarbonImmutable::now(),
            'notes' => $reason === null ? $return->notes : trim(($return->notes ?? '')."\n".$reason),
        ])->save();

        return $return->fresh();
    }

    /**
     * A roll goes back whole and its status says so (Inventory spec §11).
     * Nothing is posted to the quantity ledger — a ROLL item never had a
     * balance row to take it off.
     */
    private function dispatchRoll(PurchaseReturnLine $line, PurchaseReturn $return): void
    {
        $roll = FabricRoll::lockForUpdate()->findOrFail($line->fabric_roll_id);

        if (! in_array($roll->status, self::RETURNABLE_ROLL_STATUSES, true)) {
            throw ValidationException::withMessages([
                'lines' => __('procurement.roll_not_returnable', [
                    'roll' => $roll->roll_no, 'status' => $roll->status,
                ]),
            ])->status(409);
        }

        $roll->forceFill([
            'status' => 'RETURNED',
            'inspection_notes' => trim(($roll->inspection_notes ?? '')."\n".$return->return_no),
        ])->save();
    }

    /**
     * Checks every line before anything is written: what can go back, how
     * much of it is left after earlier returns, and what it is worth.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function plan(GoodsReceipt $receipt, array $lines): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('procurement.needs_lines')]);
        }

        $receipt->load('lines');
        $known = $receipt->lines->keyBy('id');
        $planned = [];

        foreach ($lines as $index => $payload) {
            $field = "lines.$index";
            /** @var GoodsReceiptLine|null $line */
            $line = $known->get($payload['goods_receipt_line_id'] ?? '');

            if ($line === null) {
                throw ValidationException::withMessages(["$field.goods_receipt_line_id" => __('procurement.unknown_receipt_line')]);
            }

            $item = Item::findOrFail($line->item_id);

            if ($item->usesRollLedger()) {
                $planned[] = $this->planRoll($line, $item, $payload, $field);

                continue;
            }

            $quantity = $this->scale($payload['quantity'] ?? 0);

            if (bccomp($quantity, '0', self::SCALE) !== 1) {
                throw ValidationException::withMessages(["$field.quantity" => __('inventory.quantity_must_be_positive')]);
            }

            $returnable = $this->returnableQty($line);

            if (bccomp($quantity, $returnable, self::SCALE) > 0) {
                throw ValidationException::withMessages([
                    "$field.quantity" => __('procurement.return_exceeds_received', [
                        'item' => $item->item_code,
                        'available' => rtrim(rtrim($returnable, '0'), '.'),
                    ]),
                ])->status(409);
            }

            $binId = $payload['bin_id'] ?? $line->bin_id;

            if (! is_string($binId) || $binId === '') {
                throw ValidationException::withMessages([
                    "$field.bin_id" => __('procurement.bin_required_in_store', ['item' => $item->item_code]),
                ]);
            }

            $lotNo = $item->isLotTracked()
                ? (string) ($payload['lot_no'] ?? $line->lot_no)
                : ItemStockBalance::NO_LOT;

            $unitCost = $this->scale($line->rate ?? '0');

            $planned[] = [
                'line' => $line,
                'item' => $item,
                'roll' => null,
                'bin_id' => $binId,
                'lot_no' => $lotNo === '' ? null : $lotNo,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total' => bcmul($quantity, $unitCost, self::SCALE),
                'notes' => $payload['notes'] ?? null,
            ];
        }

        return $planned;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function planRoll(GoodsReceiptLine $line, Item $item, array $payload, string $field): array
    {
        $rollId = $payload['fabric_roll_id'] ?? null;

        if (! is_string($rollId) || $rollId === '') {
            throw ValidationException::withMessages([
                "$field.fabric_roll_id" => __('requisition.roll_required', ['item' => $item->name]),
            ]);
        }

        $roll = FabricRoll::where('goods_receipt_line_id', $line->id)->find($rollId);

        if ($roll === null) {
            throw ValidationException::withMessages(["$field.fabric_roll_id" => __('requisition.unknown_roll')]);
        }

        if (! in_array($roll->status, self::RETURNABLE_ROLL_STATUSES, true)) {
            throw ValidationException::withMessages([
                "$field.fabric_roll_id" => __('procurement.roll_not_returnable', [
                    'roll' => $roll->roll_no, 'status' => $roll->status,
                ]),
            ])->status(409);
        }

        $quantity = $roll->measuredQuantity();
        $unitCost = (string) $roll->unit_cost;

        return [
            'line' => $line,
            'item' => $item,
            'roll' => $roll,
            'bin_id' => $roll->bin_id,
            'lot_no' => $roll->lot_no,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total' => bcmul($quantity, $unitCost, self::SCALE),
            'notes' => $payload['notes'] ?? null,
        ];
    }

    /**
     * What is left of a receipt line after earlier returns. A line cannot
     * be sent back twice over, however many return documents exist.
     */
    private function returnableQty(GoodsReceiptLine $line): string
    {
        $returned = PurchaseReturnLine::where('goods_receipt_line_id', $line->id)
            ->whereHas('purchaseReturn', fn ($q) => $q->whereNotIn('status', ['CANCELLED', 'REJECTED']))
            ->get(['quantity'])
            ->reduce(fn (string $sum, PurchaseReturnLine $row): string => bcadd($sum, (string) $row->quantity, self::SCALE), '0.0000');

        $remaining = bcsub($this->scale($line->postableQty()), $returned, self::SCALE);

        return bccomp($remaining, '0', self::SCALE) === 1 ? $remaining : '0.0000';
    }

    private function assertTransition(PurchaseReturn $return, string $to): void
    {
        if (! $return->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $return->status, 'to' => $to]),
            ])->status(409);
        }
    }

    private function scale(mixed $value): string
    {
        return number_format((float) ($value ?? 0), self::SCALE, '.', '');
    }
}

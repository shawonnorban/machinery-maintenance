<?php

declare(strict_types=1);

namespace App\Modules\Production\Actions;

use App\Modules\Approval\Actions\RequestApproval;
use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemReservation;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\Finance\Services\GlPoster;
use App\Modules\ItemMaster\Services\ItemLedger;
use App\Modules\Production\Models\CuttingProgram;
use App\Modules\Production\Models\MaterialIssue;
use App\Modules\Production\Models\StyleBomLine;
use App\Modules\Settings\Services\SettingsResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issuing material to a cut, and the BOM variance it produces (Inventory
 * spec §9).
 *
 *     Variance   = issued to date − BOM required for this programme
 *     Variance % = variance / required × 100
 *
 * Inside the factory's tolerance the issue is final. Outside it, the row is
 * written PENDING_APPROVAL and **nothing leaves the store** until the
 * approval engine says so — a control rather than a report. The tolerance
 * itself is a setting because the spec says the number has not been agreed
 * yet and will differ by factory.
 *
 * Issuing against a ROLL item takes the whole roll, as the floor
 * requisition does: what was measured is what goes, and a roll cut in half
 * is a split (Phase 4), not an issue of part of one.
 */
class IssueToProduction
{
    private const SCALE = 4;

    public function __construct(
        private readonly ItemLedger $ledger,
        private readonly SettingsResolver $settings,
        private readonly RequestApproval $approvals,
        private readonly GlPoster $gl,
    ) {}

    /**
     * @param  array<string, mixed>  $data  item_id, quantity?, bin_id?, lot_no?, fabric_roll_id?, reason?
     */
    public function handle(CuttingProgram $program, array $data, ?string $userId = null): MaterialIssue
    {
        if (! $program->isOpen()) {
            throw ValidationException::withMessages([
                'status' => __('production.program_not_issuable', ['status' => $program->status]),
            ])->status(409);
        }

        $item = Item::findOrFail($data['item_id']);
        $bomLine = StyleBomLine::where('style_id', $program->style_id)
            ->where('item_id', $item->id)
            ->first();

        if ($bomLine === null) {
            // Not on the bill of materials at all: there is no requirement to
            // measure against, so there is nothing for a tolerance to mean.
            throw ValidationException::withMessages([
                'item_id' => __('production.item_not_in_bom', ['item' => $item->item_code]),
            ])->status(409);
        }

        $roll = null;
        $quantity = null;

        if ($item->usesRollLedger()) {
            $roll = $this->rollFor($item, $data);
            $quantity = (string) ($roll->net_length ?? $roll->net_weight ?? '1');
        } else {
            $quantity = number_format((float) ($data['quantity'] ?? 0), self::SCALE, '.', '');

            if (bccomp($quantity, '0', self::SCALE) !== 1) {
                throw ValidationException::withMessages(['quantity' => __('inventory.quantity_must_be_positive')]);
            }
        }

        $required = $bomLine->requiredFor((string) $program->program_qty);
        $issuedToDate = bcadd($this->issuedToDate($program, $item->id), $quantity, self::SCALE);
        $variance = bcsub($issuedToDate, $required, self::SCALE);
        $variancePercent = bccomp($required, '0', self::SCALE) === 1
            ? bcdiv(bcmul($variance, '100', self::SCALE), $required, 2)
            : '0.00';

        $tolerance = (string) (int) $this->settings->get(
            'production.bom_variance_tolerance_percent',
            factoryId: $program->factory_id,
        );

        $needsApproval = bccomp($variancePercent, $tolerance, 2) > 0;

        $issue = DB::transaction(function () use (
            $program, $item, $bomLine, $roll, $quantity, $required, $issuedToDate,
            $variance, $variancePercent, $needsApproval, $data, $userId
        ): MaterialIssue {
            $issue = MaterialIssue::create([
                'cutting_program_id' => $program->id,
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'item_name' => $item->name,
                'uom_id' => $bomLine->uom_id,
                'bin_id' => $roll?->bin_id ?? ($data['bin_id'] ?? null),
                'lot_no' => $roll?->lot_no ?? ($data['lot_no'] ?? null),
                'fabric_roll_id' => $roll?->id,
                'quantity' => $quantity,
                'unit_cost' => $roll === null ? $this->costOf($item, $data) : (string) $roll->unit_cost,
                'currency' => $roll?->currency ?? 'BDT',
                'required_qty' => $required,
                'issued_to_date' => $issuedToDate,
                'variance_qty' => $variance,
                'variance_percent' => $variancePercent,
                'is_extra' => bccomp($variance, '0', self::SCALE) === 1,
                'reason' => $data['reason'] ?? null,
                'status' => $needsApproval ? 'PENDING_APPROVAL' : 'POSTED',
                'approval_status' => $needsApproval ? 'PENDING' : 'NOT_REQUIRED',
                'issued_by' => $userId,
                'issued_at' => CarbonImmutable::now(),
            ]);

            if ($program->status === 'CONFIRMED') {
                $program->forceFill(['status' => 'IN_PROGRESS'])->save();
            }

            if (! $needsApproval) {
                $this->post($issue->fresh());
            }

            return $issue->fresh();
        });

        if ($needsApproval) {
            // Outside the transaction: if no workflow matches, the engine
            // decides NOT_REQUIRED straight away, which posts the stock —
            // and that post should not sit inside the same transaction as
            // the row it is deciding on.
            $this->approvals->forEntity($issue, $userId);
        }

        return $issue->fresh();
    }

    /**
     * Moves the stock. Called either straight away, or by the approval
     * engine's verdict through `MaterialIssue::onApprovalDecided()`.
     */
    public function post(MaterialIssue $issue): MaterialIssue
    {
        if ($issue->item_stock_transaction_id !== null) {
            // A retried decision must not post the movement twice.
            return $issue;
        }

        $item = Item::findOrFail($issue->item_id);
        $program = CuttingProgram::findOrFail($issue->cutting_program_id);

        // Set inside the transaction, read after it: what the issue is
        // worth depends on which ledger the item lives in.
        $value = '0';

        $posted = DB::transaction(function () use ($issue, $item, $program, &$value): MaterialIssue {
            $transactionId = null;

            if ($item->usesRollLedger()) {
                $roll = FabricRoll::lockForUpdate()->findOrFail($issue->fabric_roll_id);

                if (! $roll->canTransitionTo('ISSUED')) {
                    throw ValidationException::withMessages([
                        'fabric_roll_id' => __('requisition.roll_not_issuable', [
                            'roll' => $roll->roll_no, 'status' => $roll->status,
                        ]),
                    ])->status(409);
                }

                $this->consumeRollClaim($roll);
                $value = $roll->totalValue();
                $roll->forceFill(['status' => 'ISSUED'])->save();
            } else {
                $bin = Bin::findOrFail($issue->bin_id);
                $lotNo = $item->isLotTracked() ? (string) $issue->lot_no : ItemStockBalance::NO_LOT;

                // The same lock the store counter takes: two cuts drawing the
                // last roll's worth at once must not both succeed.
                $this->ledger->lockBalance($item, $bin, $lotNo);

                $transaction = $this->ledger->post($item, $bin, 'ISSUE', (string) $issue->quantity, null, [
                    'lot_no' => $lotNo,
                    'reference_type' => 'cutting_program',
                    'reference_id' => $program->id,
                    'performed_by' => $issue->issued_by,
                    'notes' => $program->program_no,
                ]);

                $transactionId = $transaction->id;
                $value = (string) $transaction->total_cost;
            }

            $issue->forceFill([
                'status' => 'POSTED',
                'item_stock_transaction_id' => $transactionId,
            ])->save();

            return $issue->fresh();
        });

        // What left the shelf, moved out of stock and into work in progress
        // (Shared Systems §2).
        $this->gl->post('ISSUE_TO_PRODUCTION', $value, [
            'source_type' => 'material_issue',
            'source_id' => $posted->id,
            'source_no' => $program->program_no,
            'factory_id' => $program->factory_id,
            'date' => $posted->issued_at,
            'memo' => $program->program_no,
            'user_id' => $posted->issued_by,
        ]);

        return $posted;
    }

    /**
     * The variance so far, per item, for one programme — the report of
     * spec §9 in the form the screen needs.
     *
     * @return list<array<string, mixed>>
     */
    public function variance(CuttingProgram $program): array
    {
        $bom = StyleBomLine::where('style_id', $program->style_id)->with('uom:id,code,name')->get();

        return $bom->map(function (StyleBomLine $line) use ($program): array {
            $required = $line->requiredFor((string) $program->program_qty);
            $issued = $this->issuedToDate($program, $line->item_id);
            $variance = bcsub($issued, $required, self::SCALE);

            return [
                'item_id' => $line->item_id,
                'item_code' => $line->item_code,
                'item_name' => $line->item_name,
                'uom' => $line->uom === null ? null : ['code' => $line->uom->code, 'name' => $line->uom->name],
                'required_qty' => $required,
                'issued_qty' => $issued,
                'variance_qty' => $variance,
                'variance_percent' => bccomp($required, '0', self::SCALE) === 1
                    ? bcdiv(bcmul($variance, '100', self::SCALE), $required, 2)
                    : '0.00',
                'pending_approval' => MaterialIssue::where('cutting_program_id', $program->id)
                    ->where('item_id', $line->item_id)
                    ->where('status', 'PENDING_APPROVAL')
                    ->exists(),
            ];
        })->all();
    }

    /**
     * What has actually left the store for this item on this programme.
     * A pending issue has not moved, so it does not count — otherwise the
     * variance would include fabric nobody has handed over.
     */
    private function issuedToDate(CuttingProgram $program, string $itemId): string
    {
        return MaterialIssue::where('cutting_program_id', $program->id)
            ->where('item_id', $itemId)
            ->where('status', 'POSTED')
            ->get(['quantity'])
            ->reduce(fn (string $sum, MaterialIssue $i): string => bcadd($sum, (string) $i->quantity, self::SCALE), '0.0000');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function rollFor(Item $item, array $data): FabricRoll
    {
        $rollId = $data['fabric_roll_id'] ?? null;

        if (! is_string($rollId) || $rollId === '') {
            throw ValidationException::withMessages([
                'fabric_roll_id' => __('requisition.roll_required', ['item' => $item->name]),
            ]);
        }

        $roll = FabricRoll::where('item_id', $item->id)->find($rollId);

        if ($roll === null) {
            throw ValidationException::withMessages(['fabric_roll_id' => __('requisition.unknown_roll')]);
        }

        if (! $roll->canTransitionTo('ISSUED')) {
            throw ValidationException::withMessages([
                'fabric_roll_id' => __('requisition.roll_not_issuable', [
                    'roll' => $roll->roll_no, 'status' => $roll->status,
                ]),
            ])->status(409);
        }

        return $roll;
    }

    private function consumeRollClaim(FabricRoll $roll): void
    {
        $claim = ItemReservation::where('fabric_roll_id', $roll->id)
            ->whereIn('status', ItemReservation::HOLDING_STATUSES)
            ->first();

        if ($claim === null) {
            return;
        }

        $claim->forceFill([
            'quantity_issued' => (string) $claim->quantity,
            'status' => 'ISSUED',
        ])->save();
    }

    /**
     * What the issue is valued at before it posts — the bin's weighted
     * average, so the approval threshold sees a real number rather than
     * zero. The ledger writes the authoritative cost when it posts.
     *
     * @param  array<string, mixed>  $data
     */
    private function costOf(Item $item, array $data): string
    {
        $binId = $data['bin_id'] ?? null;

        if (! is_string($binId) || $binId === '') {
            throw ValidationException::withMessages([
                'bin_id' => __('procurement.bin_required_in_store', ['item' => $item->item_code]),
            ]);
        }

        $balance = ItemStockBalance::where('item_id', $item->id)
            ->where('bin_id', $binId)
            ->where('lot_no', $item->isLotTracked() ? (string) ($data['lot_no'] ?? '') : ItemStockBalance::NO_LOT)
            ->first();

        return (string) ($balance?->weighted_average_cost ?? '0');
    }
}

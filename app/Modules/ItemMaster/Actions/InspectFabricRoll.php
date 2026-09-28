<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Actions;

use App\Modules\ItemMaster\Models\FabricDefect;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\FabricRollDefect;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemReservation;
use App\Modules\ItemMaster\Services\ItemNotifier;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\GoodsReceiptLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Inspecting one roll (Inventory spec §4.2–§4.3) — Grade is a single field
 * with four values (A/B/C/Reject); a Reject additionally names a
 * disposition (Damaged or Returned), which becomes the roll's status.
 * A/B/C become AVAILABLE. No claim/return-to-vendor workflow is triggered —
 * that is Phase 5's Purchase Return + Claim; `RETURNED` here is only the
 * roll's own terminal status.
 *
 * After recording the decision, the result is synced back to the GRN line
 * (and, once every roll on the receipt is resolved, to the receipt itself)
 * — the "GRN → Inspection Pending → Result sync ফিরে GRN-এ" step. These are
 * plain model updates, the same way Procurement already references
 * `Item`/`ItemStockBalance` directly; no Action-to-Action coupling is
 * introduced in either direction.
 */
class InspectFabricRoll
{
    public function __construct(private readonly ItemNotifier $notifier) {}

    /**
     * @param  array<string, mixed>  $data  actual_width?, shade_match?, grade,
     *         disposition (required if grade=REJECT), defect_ids?, notes?
     */
    public function handle(FabricRoll $roll, array $data, string $userId): FabricRoll
    {
        if (! $roll->isPendingInspection()) {
            throw ValidationException::withMessages([
                'status' => __('inspection.roll_not_pending', ['status' => $roll->status]),
            ])->status(409);
        }

        // defectFamily() reads item.fabricAttributes; loaded explicitly
        // rather than lazily (preventLazyLoading is on outside production).
        $roll->loadMissing('item.fabricAttributes');

        $grade = $data['grade'];

        if (! in_array($grade, FabricRoll::GRADES, true)) {
            throw ValidationException::withMessages(['grade' => __('validation.in', ['attribute' => 'grade'])]);
        }

        $disposition = null;

        if ($grade === 'REJECT') {
            $disposition = $data['disposition'] ?? null;

            if (! in_array($disposition, FabricRoll::DISPOSITIONS, true)) {
                throw ValidationException::withMessages(['disposition' => __('inspection.disposition_required')]);
            }
        }

        $defectIds = array_values(array_unique($data['defect_ids'] ?? []));
        $family = $roll->defectFamily();

        if ($defectIds !== []) {
            $defects = FabricDefect::whereIn('id', $defectIds)->get()->keyBy('id');

            foreach ($defectIds as $defectId) {
                $defect = $defects->get($defectId);

                if ($defect === null || $defect->fabric_family !== $family) {
                    throw ValidationException::withMessages([
                        'defect_ids' => __('inspection.defect_wrong_family', [
                            'defect' => $defect?->name ?? $defectId,
                            'family' => $family,
                        ]),
                    ]);
                }
            }
        }

        $inspected = DB::transaction(function () use ($roll, $data, $grade, $disposition, $defectIds, $userId): FabricRoll {
            $roll->forceFill([
                'actual_width' => $data['actual_width'] ?? $roll->actual_width,
                'shade_match' => array_key_exists('shade_match', $data) ? (bool) $data['shade_match'] : $roll->shade_match,
                'inspection_grade' => $grade,
                'inspection_points' => $data['inspection_points'] ?? $roll->inspection_points,
                'inspection_notes' => $data['notes'] ?? $roll->inspection_notes,
                'inspected_by' => $userId,
                'inspected_at' => CarbonImmutable::now(),
                'status' => $grade === 'REJECT' ? $disposition : 'AVAILABLE',
            ])->save();

            // Not ->sync(): a pivot's bulk insert bypasses Eloquent's
            // creating event, and that event is what gives the row its
            // ULID (BaseModel's HasUlids). A roll is inspected once — no
            // prior rows to remove — but the delete keeps a retry safe.
            FabricRollDefect::where('fabric_roll_id', $roll->id)->delete();

            foreach ($defectIds as $defectId) {
                FabricRollDefect::create(['fabric_roll_id' => $roll->id, 'fabric_defect_id' => $defectId]);
            }

            $this->syncGoodsReceipt($roll->fresh());

            return $roll->fresh();
        });

        $this->notifyWaitingClaims($inspected);

        return $inspected;
    }

    /**
     * A passed roll is stock somebody may have been waiting on. It is not
     * allocated automatically the way a quantity receipt is: which roll goes
     * to which order is a shade-and-width decision a person makes (spec
     * §7.1), so the store is told and chooses.
     */
    private function notifyWaitingClaims(FabricRoll $roll): void
    {
        if ($roll->status !== 'AVAILABLE') {
            return;
        }

        $waiting = ItemReservation::where('item_id', $roll->item_id)
            ->where('status', 'PENDING_ARRIVAL')
            ->exists();

        if (! $waiting) {
            return;
        }

        $item = Item::find($roll->item_id);

        if ($item === null) {
            return;
        }

        $this->notifier->earmarkStockArrived(
            $item,
            (string) ($roll->net_length ?? $roll->net_weight ?? '1'),
            GoodsReceipt::find($roll->goods_receipt_id)?->factory_id,
            needsAllocation: true,
        );
    }

    /**
     * Rolls in a garment factory are usually inspected one at a time as
     * they come off the trolley, not all at once — so both the line and
     * the receipt's status are recomputed from whatever has been decided
     * so far, not assumed to finish in one call.
     */
    private function syncGoodsReceipt(FabricRoll $roll): void
    {
        $lineRolls = FabricRoll::where('goods_receipt_line_id', $roll->goods_receipt_line_id)->get(['status']);

        $lineStatus = match (true) {
            $lineRolls->contains('status', 'UNDER_INSPECTION') => 'PENDING',
            $lineRolls->every(fn (FabricRoll $r): bool => $r->status === 'AVAILABLE') => 'PASSED',
            $lineRolls->every(fn (FabricRoll $r): bool => in_array($r->status, ['DAMAGED', 'RETURNED'], true)) => 'FAILED',
            default => 'PARTIAL',
        };

        GoodsReceiptLine::whereKey($roll->goods_receipt_line_id)->update(['inspection_status' => $lineStatus]);

        $stillPending = FabricRoll::where('goods_receipt_id', $roll->goods_receipt_id)
            ->where('status', 'UNDER_INSPECTION')
            ->exists();

        if (! $stillPending) {
            GoodsReceipt::whereKey($roll->goods_receipt_id)
                ->where('status', 'UNDER_INSPECTION')
                ->update(['status' => 'COMPLETED', 'completed_at' => CarbonImmutable::now()]);
        }
    }
}

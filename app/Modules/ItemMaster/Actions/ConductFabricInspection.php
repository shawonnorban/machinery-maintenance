<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Actions;

use App\Modules\ItemMaster\Models\FabricDefect;
use App\Modules\ItemMaster\Models\FabricInspection;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\FabricRollDefect;
use App\Modules\ItemMaster\Services\FourPointScore;
use App\Modules\ItemMaster\Services\ItemNotifier;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Settings\Services\SettingsResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A four-point inspection pass, from opening the sheet to the verdict
 * (Inventory spec §4.3).
 *
 * The shape follows the mill's own paperwork, because that is the process
 * that actually happens: a sheet is opened against a delivery, rolls are
 * tallied onto it one at a time, and only when it is submitted does the
 * pooled figure decide the lot. Recording a roll does not move stock —
 * grading the rolls happens at approval, once somebody has accepted the
 * lot verdict.
 *
 * **A failed pass is never rewritten.** Escalating opens a *new* sheet at
 * the wider sample pointing back at the one that failed, so the file can
 * answer why a supplier's whole delivery was opened. Editing the first
 * sheet into a pass would destroy exactly the evidence a claim rests on.
 */
class ConductFabricInspection
{
    private const SCALE = 2;

    public function __construct(
        private readonly FourPointScore $score,
        private readonly NumberSequenceGenerator $numbers,
        private readonly SettingsResolver $settings,
        private readonly ItemNotifier $notifier,
    ) {}

    /**
     * Open a sheet against a receipt.
     *
     * @param  array<string, mixed>  $data
     */
    public function open(GoodsReceipt $receipt, string $itemId, array $data, ?string $userId = null): FabricInspection
    {
        $receipt->loadMissing('factory');

        $stage = (string) ($data['stage'] ?? 'FIRST');

        if (! in_array($stage, FabricInspection::STAGES, true)) {
            throw ValidationException::withMessages([
                'stage' => __('validation.in', ['attribute' => 'stage']),
            ]);
        }

        $factoryId = (string) $receipt->factory_id;
        $rolls = FabricRoll::where('goods_receipt_id', $receipt->id)->where('item_id', $itemId);

        $rollsInLot = (int) ($data['rolls_in_lot'] ?? $rolls->clone()->count());
        $yardsInLot = (string) ($data['yards_in_lot'] ?? $rolls->clone()->sum('net_length'));

        return DB::transaction(fn (): FabricInspection => FabricInspection::create([
            'factory_id' => $factoryId,
            'goods_receipt_id' => $receipt->id,
            'item_id' => $itemId,
            'vendor_id' => $receipt->vendor_id,
            'inspection_no' => $this->numbers->next('FABRIC_INSPECTION', $receipt->factory),
            'buyer_reference' => $data['buyer_reference'] ?? null,
            'style_reference' => $data['style_reference'] ?? null,
            'order_reference' => $data['order_reference'] ?? null,
            'colour' => $data['colour'] ?? null,
            'composition' => $data['composition'] ?? null,
            'lot_no' => $data['lot_no'] ?? null,
            'stage' => $stage,
            'sampling_percent' => $this->samplingFor($stage, $factoryId),
            'rolls_in_lot' => $rollsInLot,
            'yards_in_lot' => $yardsInLot,
            // Copied now, not read at approval: moving the bar later must
            // not silently re-decide a lot somebody already signed for.
            'acceptance_threshold' => $this->thresholdFor($factoryId),
            'status' => 'DRAFT',
            'inspected_by' => $userId,
            'inspection_date' => $data['inspection_date'] ?? CarbonImmutable::now()->toDateString(),
            'remarks' => $data['remarks'] ?? null,
            'supersedes_id' => $data['supersedes_id'] ?? null,
        ]));
    }

    /**
     * Tally one roll onto the sheet.
     *
     * Re-recording the same roll replaces its tally rather than adding to
     * it — an inspector correcting a miscount means the new figures, not
     * both sets.
     *
     * @param  array<string, mixed>  $data  width_start?, width_middle?, width_end?,
     *                                      marked_length?, inspected_length, shade_assessment?,
     *                                      defects?: list<{defect_id, point_class, occurrences}>, notes?
     */
    public function recordRoll(FabricInspection $inspection, FabricRoll $roll, array $data): FabricRoll
    {
        $this->assertOpen($inspection);

        if ((string) $roll->goods_receipt_id !== (string) $inspection->goods_receipt_id) {
            throw ValidationException::withMessages([
                'roll' => __('inspection.roll_not_on_receipt', ['roll' => $roll->roll_no]),
            ])->status(409);
        }

        $roll->loadMissing('item.fabricAttributes');
        $family = $roll->defectFamily();
        $tally = $this->validatedTally($data['defects'] ?? [], $family, $roll->roll_no);

        $inspectedLength = $this->decimal($data['inspected_length'] ?? $roll->net_length ?? '0');

        if (bccomp($inspectedLength, '0', 4) !== 1) {
            throw ValidationException::withMessages([
                'inspected_length' => __('inspection.length_required', ['roll' => $roll->roll_no]),
            ]);
        }

        $width = $this->score->effectiveWidth(
            [
                isset($data['width_start']) ? $this->decimal($data['width_start']) : null,
                isset($data['width_middle']) ? $this->decimal($data['width_middle']) : null,
                isset($data['width_end']) ? $this->decimal($data['width_end']) : null,
            ],
            $roll->nominal_width === null ? null : (string) $roll->nominal_width,
        );

        if ($width === null) {
            throw ValidationException::withMessages([
                'width_start' => __('inspection.width_required', ['roll' => $roll->roll_no]),
            ]);
        }

        $points = $this->score->pointsFrom($tally);

        return DB::transaction(function () use ($inspection, $roll, $data, $tally, $points, $width, $inspectedLength): FabricRoll {
            FabricRollDefect::where('fabric_roll_id', $roll->id)->delete();

            foreach ($tally as $row) {
                FabricRollDefect::create([
                    'fabric_roll_id' => $roll->id,
                    'fabric_defect_id' => $row['defect_id'],
                    'point_class' => $row['point_class'],
                    'occurrences' => $row['occurrences'],
                    'notes' => $row['notes'] ?? null,
                ]);
            }

            $roll->update([
                'fabric_inspection_id' => $inspection->id,
                'width_start' => $data['width_start'] ?? null,
                'width_middle' => $data['width_middle'] ?? null,
                'width_end' => $data['width_end'] ?? null,
                'actual_width' => $width,
                'marked_length' => $data['marked_length'] ?? null,
                'inspected_length' => $inspectedLength,
                'shade_assessment' => $data['shade_assessment'] ?? null,
                // Kept in step with the grid so the old boolean keeps
                // meaning what it always did for anything still reading it:
                // true only when every cell came back clean.
                'shade_match' => isset($data['shade_assessment'])
                    ? $this->gridIsClean($data['shade_assessment'])
                    : $roll->shade_match,
                'inspection_points' => $points,
                'penalty_percent' => $this->score->penaltyPercent($points, $inspectedLength, $width),
                'inspection_notes' => $data['notes'] ?? $roll->inspection_notes,
            ]);

            $this->recalculate($inspection);

            return $roll->refresh();
        });
    }

    /**
     * Freeze the arithmetic and let the sheet be judged.
     */
    public function submit(FabricInspection $inspection, ?string $userId = null): FabricInspection
    {
        $this->assertOpen($inspection);
        $this->recalculate($inspection);
        $inspection->refresh();

        if ($inspection->rolls_inspected < 1) {
            throw ValidationException::withMessages([
                'status' => __('inspection.nothing_inspected'),
            ])->status(409);
        }

        $inspection->update([
            'status' => 'SUBMITTED',
            'inspected_by' => $inspection->inspected_by ?? $userId,
            'inspected_at' => CarbonImmutable::now(),
            'verified_by' => $userId,
            'verified_at' => CarbonImmutable::now(),
        ]);

        return $inspection->refresh();
    }

    /**
     * Accept the lot on this pass's evidence and grade its rolls.
     *
     * Grading is deliberately here rather than at recording time: a roll
     * that scores badly inside a lot which passes is still usable fabric,
     * and the decision to take the delivery belongs to whoever signs for
     * it, not to the arithmetic.
     */
    public function approve(FabricInspection $inspection, ?string $userId = null): FabricInspection
    {
        $this->assertTransition($inspection, 'APPROVED');

        return DB::transaction(function () use ($inspection, $userId): FabricInspection {
            $inspection->update([
                'status' => 'APPROVED',
                'approved_by' => $userId,
                'approved_at' => CarbonImmutable::now(),
            ]);

            foreach ($inspection->rolls()->get() as $roll) {
                if (! $roll->isPendingInspection()) {
                    continue;
                }

                $roll->update([
                    'inspection_grade' => $this->gradeFor($roll, (string) $inspection->acceptance_threshold),
                    'status' => 'AVAILABLE',
                    'inspected_by' => $inspection->inspected_by,
                    'inspected_at' => CarbonImmutable::now(),
                ]);
            }

            return $inspection->refresh();
        });
    }

    /**
     * Refuse the lot on this pass.
     *
     * Nothing is graded and no roll moves: a refused sample is a reason to
     * look wider or to send the delivery back, not a verdict on each roll.
     * `escalate()` is the next step while a wider sample is still possible.
     */
    public function reject(FabricInspection $inspection, ?string $reason = null, ?string $userId = null): FabricInspection
    {
        $this->assertTransition($inspection, 'REJECTED');

        $inspection->update([
            'status' => 'REJECTED',
            'approved_by' => $userId,
            'approved_at' => CarbonImmutable::now(),
            'remarks' => $reason ?? $inspection->remarks,
        ]);

        $this->notifier->fabricInspectionFailed($inspection->refresh());

        return $inspection;
    }

    /**
     * Open the wider pass a failure calls for (10% → 15% → 100%).
     */
    public function escalate(FabricInspection $inspection, ?string $userId = null): FabricInspection
    {
        if ($inspection->status !== 'REJECTED') {
            throw ValidationException::withMessages([
                'status' => __('inspection.escalate_needs_a_failure'),
            ])->status(409);
        }

        $next = $inspection->nextStage();

        if ($next === null) {
            // 100% has already been opened; there is no larger sample than
            // all of it, and what happens now is a commercial decision.
            throw ValidationException::withMessages([
                'stage' => __('inspection.escalation_exhausted'),
            ])->status(409);
        }

        $inspection->loadMissing('goodsReceipt');

        return $this->open($inspection->goodsReceipt, (string) $inspection->item_id, [
            'stage' => $next,
            'buyer_reference' => $inspection->buyer_reference,
            'style_reference' => $inspection->style_reference,
            'order_reference' => $inspection->order_reference,
            'colour' => $inspection->colour,
            'composition' => $inspection->composition,
            'lot_no' => $inspection->lot_no,
            'rolls_in_lot' => $inspection->rolls_in_lot,
            'yards_in_lot' => (string) $inspection->yards_in_lot,
            'supersedes_id' => $inspection->id,
        ], $userId);
    }

    public function cancel(FabricInspection $inspection, ?string $reason = null): FabricInspection
    {
        $this->assertTransition($inspection, 'CANCELLED');

        $inspection->update(['status' => 'CANCELLED', 'remarks' => $reason ?? $inspection->remarks]);

        return $inspection->refresh();
    }

    /**
     * Pool the inspected rolls back onto the header.
     */
    public function recalculate(FabricInspection $inspection): void
    {
        $rolls = FabricRoll::where('fabric_inspection_id', $inspection->id)
            ->whereNotNull('inspected_length')
            ->get(['inspection_points', 'inspected_length', 'actual_width']);

        $pooled = $this->score->pool($rolls->map(fn (FabricRoll $roll): array => [
            'points' => (string) ($roll->inspection_points ?? '0'),
            'yards' => (string) $roll->inspected_length,
            'width' => (string) ($roll->actual_width ?? '0'),
        ]));

        $percent = $pooled['penalty_percent'];

        $inspection->update([
            'rolls_inspected' => $pooled['rolls'],
            'yards_inspected' => $pooled['yards'],
            'total_points' => $pooled['points'],
            'penalty_percent' => $percent ?? 0,
            'result' => $percent === null
                ? null
                : ($this->score->passes($percent, (string) $inspection->acceptance_threshold) ? 'PASS' : 'FAIL'),
        ]);
    }

    /**
     * A/B/C off the roll's own percentage against the lot's bar.
     *
     * Within the bar is an A; up to half as bad again is a B, which is
     * fabric a cutting room can still lay with care; beyond that it is a C
     * and somebody should be told before it is cut.
     */
    private function gradeFor(FabricRoll $roll, string $threshold): string
    {
        $percent = $roll->penalty_percent;

        if ($percent === null) {
            return 'B';
        }

        $percent = (string) $percent;

        if (bccomp($percent, $threshold, self::SCALE) <= 0) {
            return 'A';
        }

        return bccomp($percent, bcmul($threshold, '1.5', self::SCALE), self::SCALE) <= 0 ? 'B' : 'C';
    }

    /**
     * @param  array<int|string, mixed>  $grid
     */
    private function gridIsClean(array $grid): bool
    {
        foreach ($grid as $result) {
            if ($result === false || $result === 'FAIL') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $defects
     * @return list<array{defect_id: string, point_class: int, occurrences: int, notes: string|null}>
     */
    private function validatedTally(array $defects, string $family, string $rollNo): array
    {
        if ($defects === []) {
            return [];
        }

        $ids = array_values(array_unique(array_map(
            fn (array $row): string => (string) ($row['defect_id'] ?? ''),
            $defects,
        )));

        $known = FabricDefect::whereIn('id', $ids)->get()->keyBy('id');
        $tally = [];

        foreach ($defects as $row) {
            $defectId = (string) ($row['defect_id'] ?? '');
            $defect = $known->get($defectId);

            if ($defect === null || $defect->fabric_family !== $family) {
                throw ValidationException::withMessages([
                    'defects' => __('inspection.defect_not_in_family', ['family' => $family]),
                ]);
            }

            $class = (int) ($row['point_class'] ?? 0);

            if (! isset(FourPointScore::POINT_CLASSES[$class])) {
                throw ValidationException::withMessages([
                    'defects' => __('inspection.bad_point_class', ['roll' => $rollNo]),
                ]);
            }

            $occurrences = (int) ($row['occurrences'] ?? 0);

            if ($occurrences < 1) {
                continue;
            }

            $tally[] = [
                'defect_id' => $defectId,
                'point_class' => $class,
                'occurrences' => $occurrences,
                'notes' => $row['notes'] ?? null,
            ];
        }

        return $tally;
    }

    private function samplingFor(string $stage, string $factoryId): string
    {
        $key = match ($stage) {
            'FIRST' => 'inspection.sampling_first_percent',
            'SECOND' => 'inspection.sampling_second_percent',
            default => null,
        };

        if ($key === null) {
            return '100';
        }

        return (string) $this->settings->get($key, factoryId: $factoryId);
    }

    private function thresholdFor(string $factoryId): string
    {
        return (string) $this->settings->get('inspection.four_point_acceptance', factoryId: $factoryId);
    }

    private function assertOpen(FabricInspection $inspection): void
    {
        if (! $inspection->isOpen()) {
            throw ValidationException::withMessages([
                'status' => __('inspection.sheet_not_open', ['status' => $inspection->status]),
            ])->status(409);
        }
    }

    private function assertTransition(FabricInspection $inspection, string $status): void
    {
        if (! $inspection->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => __('inspection.bad_transition', [
                    'from' => $inspection->status,
                    'to' => $status,
                ]),
            ])->status(409);
        }
    }

    private function decimal(mixed $value): string
    {
        return bcadd((string) $value, '0', 4);
    }
}

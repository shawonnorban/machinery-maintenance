<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Actions;

use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\ItemReservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cutting one roll into several (Inventory spec §5).
 *
 * A store splits a roll to issue part of it, to separate a damaged section,
 * or to make a big roll handleable. The parent does not shrink — it stops
 * existing as a roll (`SPLIT`, a closed status) and its length reappears in
 * the children. That is why the children must add up to the parent exactly:
 * a split that loses half a metre is a stock adjustment wearing a split's
 * clothes, and it should be posted as one so somebody sees it.
 *
 * Cost rides along per unit, not per roll. The parent's `unit_cost` is what
 * a metre of it cost; a metre of each child cost the same, so the total
 * value divides in proportion to length without anybody computing a ratio.
 */
class SplitFabricRoll
{
    private const SCALE = 2;

    public function __construct(private readonly ReserveItemStock $reservations) {}

    /**
     * @param  list<array<string, mixed>>  $children  roll_no, net_length|net_weight, gross_length?, gross_weight?
     * @return Collection<int, FabricRoll>
     */
    public function handle(FabricRoll $roll, array $children, ?string $userId = null): Collection
    {
        if (! $roll->isOnShelf()) {
            throw ValidationException::withMessages([
                'status' => __('inspection.roll_not_splittable', ['roll' => $roll->roll_no, 'status' => $roll->status]),
            ])->status(409);
        }

        if (count($children) < 2) {
            // A "split" into one piece is a rename, and renaming a roll is
            // not something the ledger should have to explain later.
            throw ValidationException::withMessages([
                'children' => __('inspection.split_needs_two'),
            ]);
        }

        $measure = $roll->measure();
        $parentQuantity = $roll->measuredQuantity();

        $total = '0.00';
        $numbers = [];

        foreach ($children as $index => $child) {
            $field = "children.$index";
            $quantity = $this->scale($child[$measure] ?? null);

            if (bccomp($quantity, '0', self::SCALE) !== 1) {
                throw ValidationException::withMessages([
                    "$field.$measure" => __('inventory.quantity_must_be_positive'),
                ]);
            }

            $rollNo = trim((string) ($child['roll_no'] ?? ''));

            if ($rollNo === '') {
                throw ValidationException::withMessages([
                    "$field.roll_no" => __('validation.required', ['attribute' => 'roll_no']),
                ]);
            }

            if (isset($numbers[$rollNo])) {
                throw ValidationException::withMessages([
                    "$field.roll_no" => __('inspection.duplicate_roll_no', ['roll_no' => $rollNo]),
                ]);
            }

            $numbers[$rollNo] = true;
            $total = bcadd($total, $quantity, self::SCALE);
        }

        $taken = FabricRoll::whereIn('roll_no', array_keys($numbers))->pluck('roll_no');

        if ($taken->isNotEmpty()) {
            throw ValidationException::withMessages([
                'children' => __('inspection.duplicate_roll_no', ['roll_no' => $taken->implode(', ')]),
            ]);
        }

        if (bccomp($total, $this->scale($parentQuantity), self::SCALE) !== 0) {
            throw ValidationException::withMessages([
                'children' => __('inspection.split_must_balance', [
                    'parent' => $this->trim($parentQuantity),
                    'children' => $this->trim($total),
                ]),
            ])->status(409);
        }

        return DB::transaction(function () use ($roll, $children, $measure, $parentQuantity, $userId): Collection {
            $claim = $this->liveClaim($roll);
            $created = collect();

            foreach ($children as $child) {
                $quantity = $this->scale($child[$measure] ?? null);

                $created->push(FabricRoll::create([
                    'item_id' => $roll->item_id,
                    'goods_receipt_id' => $roll->goods_receipt_id,
                    'goods_receipt_line_id' => $roll->goods_receipt_line_id,
                    'bin_id' => $roll->bin_id,
                    'roll_no' => trim((string) $child['roll_no']),
                    // Shade, lot, item and bin are the parent's — a split
                    // moves nothing and changes nothing about the cloth.
                    'lot_no' => $roll->lot_no,
                    'shade' => $roll->shade,
                    'shade_band' => $roll->shade_band,
                    'nominal_width' => $roll->nominal_width,
                    'actual_width' => $roll->actual_width,
                    $measure => $quantity,
                    // The gross figures are split in the same proportion:
                    // nobody re-weighs a roll they have just cut.
                    'gross_length' => $this->proportional($roll->gross_length, $quantity, $parentQuantity),
                    'gross_weight' => $this->proportional($roll->gross_weight, $quantity, $parentQuantity),
                    'status' => 'AVAILABLE',
                    'parent_roll_id' => $roll->id,
                    'unit_cost' => (string) $roll->unit_cost,
                    'currency' => $roll->currency,
                    'cost_adjusted_after_issue' => (bool) $roll->cost_adjusted_after_issue,
                    'inspection_grade' => $roll->inspection_grade,
                    'inspection_points' => $roll->inspection_points,
                    'shade_match' => $roll->shade_match,
                    'inspected_by' => $roll->inspected_by,
                    'inspected_at' => $roll->inspected_at,
                    'received_date' => $roll->received_date,
                ]));
            }

            $roll->forceFill(['status' => 'SPLIT'])->save();

            if ($claim !== null) {
                // The hold follows the cloth: the parent's claim is closed
                // and one is placed on each child for the same requisition,
                // so splitting a reserved roll does not quietly hand it back
                // to whoever asks next.
                $this->reservations->release($claim, null, $userId, 'RELEASED', __('inspection.claim_moved_to_children', [
                    'roll' => $roll->roll_no,
                ]));

                foreach ($created as $child) {
                    $this->reservations->allocateRoll($child->fresh(), [
                        'reservation_type' => $claim->reservation_type,
                        'reference_type' => $claim->reference_type,
                        'reference_id' => $claim->reference_id,
                        'reserved_by' => $userId ?? $claim->reserved_by,
                        'expires_at' => $claim->expires_at,
                    ]);
                }
            }

            return $created->map(fn (FabricRoll $child): FabricRoll => $child->fresh());
        });
    }

    private function liveClaim(FabricRoll $roll): ?ItemReservation
    {
        return ItemReservation::where('fabric_roll_id', $roll->id)
            ->whereIn('status', ItemReservation::HOLDING_STATUSES)
            ->first();
    }

    /**
     * Divides a figure the child has no measurement of its own for, in the
     * same ratio as the measure that was split.
     */
    private function proportional(mixed $parentValue, string $childQuantity, string $parentQuantity): ?string
    {
        if ($parentValue === null || bccomp($this->scale($parentQuantity), '0', self::SCALE) !== 1) {
            return null;
        }

        return bcdiv(bcmul($this->scale($parentValue), $childQuantity, 4), $this->scale($parentQuantity), self::SCALE);
    }

    private function scale(mixed $value): string
    {
        return number_format((float) ($value ?? 0), self::SCALE, '.', '');
    }

    private function trim(string $value): string
    {
        return rtrim(rtrim($value, '0'), '.');
    }
}

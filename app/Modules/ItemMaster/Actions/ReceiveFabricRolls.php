<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Actions;

use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\Procurement\Models\GoodsReceiptLine;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Turns a GRN line's `rolls[]` payload into `FabricRoll` rows (Inventory
 * spec §3.1) — the "Roll Entry" step of §4.2's inspection process, done at
 * goods-receipt completion time. Each roll lands in `UNDER_INSPECTION` with
 * a Provisional Unit Cost off the line's own rate (§10); nothing is posted
 * to the item stock ledger for these lines — the rolls themselves are the
 * stock.
 */
class ReceiveFabricRolls
{
    /**
     * @param  list<array<string, mixed>>  $rolls
     * @return Collection<int, array<string, mixed>> attributes ready for FabricRoll::create()
     */
    public function plan(GoodsReceiptLine $line, Item $item, string $storeId, string $receivedDate, array $rolls, string $fieldPrefix): Collection
    {
        if ($rolls === []) {
            throw ValidationException::withMessages([
                $fieldPrefix => __('inspection.rolls_required', ['item' => $item->item_code]),
            ]);
        }

        $bins = Bin::where('store_id', $storeId)->where('active', true)->get()->keyBy('id');
        $seenRollNumbers = [];

        return collect($rolls)->values()->map(function (array $roll, int $index) use ($line, $item, $bins, &$seenRollNumbers, $fieldPrefix, $receivedDate): array {
            $field = "$fieldPrefix.$index";
            $rollNo = trim((string) ($roll['roll_no'] ?? ''));

            if ($rollNo === '') {
                throw ValidationException::withMessages(["$field.roll_no" => __('inspection.roll_no_required')]);
            }

            if (isset($seenRollNumbers[$rollNo]) || FabricRoll::where('roll_no', $rollNo)->exists()) {
                throw ValidationException::withMessages(["$field.roll_no" => __('inspection.roll_no_duplicate', ['roll_no' => $rollNo])]);
            }

            $seenRollNumbers[$rollNo] = true;

            $binId = $roll['bin_id'] ?? null;
            $bin = $binId === null ? null : $bins->get($binId);

            if ($bin === null) {
                throw ValidationException::withMessages(["$field.bin_id" => __('inspection.roll_bin_required', ['roll_no' => $rollNo])]);
            }

            return [
                'item_id' => $item->id,
                'goods_receipt_id' => $line->goods_receipt_id,
                'goods_receipt_line_id' => $line->id,
                'bin_id' => $bin->id,
                'roll_no' => $rollNo,
                'lot_no' => $this->blankToNull($roll['lot_no'] ?? null),
                'shade' => $this->blankToNull($roll['shade'] ?? null),
                'shade_band' => $this->blankToNull($roll['shade_band'] ?? null),
                'nominal_width' => $roll['nominal_width'] ?? null,
                'gross_length' => $roll['gross_length'] ?? null,
                'net_length' => $roll['net_length'] ?? null,
                'gross_weight' => $roll['gross_weight'] ?? null,
                'net_weight' => $roll['net_weight'] ?? null,
                'status' => 'UNDER_INSPECTION',
                'unit_cost' => (string) ($line->rate ?? '0'),
                'currency' => $line->currency ?? 'BDT',
                'received_date' => $receivedDate,
            ];
        });
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}

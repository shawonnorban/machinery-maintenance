<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Actions;

use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Actions\RecalculateRollCosts;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\Finance\Services\GlPoster;
use App\Modules\ItemMaster\Services\ItemLedger;
use App\Modules\ItemMaster\Services\ItemLineResolver;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\GoodsReceiptLine;
use App\Modules\Procurement\Models\LandedCostAllocation;
use App\Modules\Procurement\Models\LandedCostCharge;
use App\Modules\Procurement\Models\LandedCostReceipt;
use App\Modules\Procurement\Models\LandedCostVoucher;
use App\Modules\Settings\Services\NumberSequenceGenerator;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Vendor\Models\Vendor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Spreading freight, duty and clearing over the goods they brought in
 * (Procurement spec §11).
 *
 * The distribution itself is simple arithmetic; what makes it worth its own
 * action is everything around it:
 *
 *  - **The rounding has to land exactly.** Splitting 47,000 over seven lines
 *    by value leaves fractions, and a distribution that quietly loses two
 *    paisa puts the stock ledger out by two paisa for ever. The last line
 *    takes the remainder, so the parts always add back to the whole.
 *  - **Two kinds of stock, two ways of applying it.** A quantity or lot item
 *    carries its value on a balance, so the uplift goes through the ledger as
 *    a zero-quantity revaluation. Fabric carries its value per roll, so the
 *    roll ledger is re-priced instead — and that correction has to follow
 *    rolls that have since been split or merged (`RecalculateRollCosts`).
 *  - **Posting is one-way.** By the time it is done the average has moved and
 *    rolls have been re-priced, some of which are already cut or issued.
 *    A mistake is corrected with a second voucher carrying the opposite
 *    charges, the way a wrong ledger row is corrected with an opposing row.
 *
 * After posting, the receipts on the voucher are rate-locked: their rates
 * are now part of a valuation somebody has reported.
 */
class ManageLandedCost
{
    private const SCALE = 4;

    public function __construct(
        private readonly NumberSequenceGenerator $numbers,
        private readonly ItemLedger $ledger,
        private readonly RecalculateRollCosts $rollCosts,
        private readonly GlPoster $gl,
    ) {}

    /**
     * @param  array<string, mixed>  $data  voucher_date?, basis?, currency?, notes?
     * @param  list<array<string, mixed>>  $charges  charge_type, amount, label?, vendor_id?, reference_no?, notes?
     * @param  list<string>  $goodsReceiptIds
     */
    public function create(Factory $factory, array $data, array $charges, array $goodsReceiptIds, ?string $userId = null): LandedCostVoucher
    {
        $basis = strtoupper((string) ($data['basis'] ?? 'VALUE'));

        if (! in_array($basis, LandedCostVoucher::BASES, true)) {
            throw ValidationException::withMessages(['basis' => __('validation.in', ['attribute' => 'basis'])]);
        }

        $receipts = $this->receiptsFor($factory, $goodsReceiptIds);
        $this->assertCharges($charges);

        return DB::transaction(function () use ($factory, $data, $basis, $charges, $receipts, $userId): LandedCostVoucher {
            $voucher = LandedCostVoucher::create([
                'factory_id' => $factory->id,
                'voucher_no' => $this->numbers->next('LANDED_COST', $factory),
                'voucher_date' => $data['voucher_date'] ?? CarbonImmutable::today(),
                'basis' => $basis,
                'currency' => $data['currency'] ?? 'BDT',
                'status' => 'DRAFT',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $this->writeCharges($voucher, $charges);
            $this->writeReceipts($voucher, $receipts);

            return $voucher->fresh();
        });
    }

    /**
     * A draft is replaced wholesale, the same as every other document here:
     * the distribution depends on the whole set, so editing one charge in
     * place would leave a half-changed basis.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $charges
     * @param  list<string>  $goodsReceiptIds
     */
    public function update(LandedCostVoucher $voucher, array $data, array $charges, array $goodsReceiptIds): LandedCostVoucher
    {
        $this->assertEditable($voucher);

        $factory = Factory::findOrFail($voucher->factory_id);
        $basis = strtoupper((string) ($data['basis'] ?? $voucher->basis));

        if (! in_array($basis, LandedCostVoucher::BASES, true)) {
            throw ValidationException::withMessages(['basis' => __('validation.in', ['attribute' => 'basis'])]);
        }

        $receipts = $this->receiptsFor($factory, $goodsReceiptIds);
        $this->assertCharges($charges);

        return DB::transaction(function () use ($voucher, $data, $basis, $charges, $receipts): LandedCostVoucher {
            $voucher->forceFill([
                'voucher_date' => $data['voucher_date'] ?? $voucher->voucher_date,
                'basis' => $basis,
                'currency' => $data['currency'] ?? $voucher->currency,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $voucher->notes,
            ])->save();

            $voucher->charges()->delete();
            $voucher->receipts()->delete();

            $this->writeCharges($voucher, $charges);
            $this->writeReceipts($voucher, $receipts);

            return $voucher->fresh();
        });
    }

    /**
     * What the charges would come to per line, without posting anything.
     *
     * The screen shows this before the decision, because posting cannot be
     * undone and a distribution basis is easy to pick wrongly.
     *
     * @return list<array<string, mixed>>
     */
    public function preview(LandedCostVoucher $voucher): array
    {
        return $this->distribute($voucher)
            ->map(fn (array $row): array => [
                'goods_receipt_line_id' => $row['line']->id,
                'goods_receipt_id' => $row['line']->goods_receipt_id,
                'item_id' => $row['line']->item_id,
                'item_code' => $row['line']->item_code,
                'item_name' => $row['line']->item_name,
                'quantity' => $row['quantity'],
                'basis_value' => $row['basis_value'],
                'share_amount' => $row['share'],
                'unit_uplift' => $row['uplift'],
                'previous_unit_cost' => $row['previous'],
                'new_unit_cost' => $row['new'],
                'tracking_mode' => $row['item']->tracking_mode,
            ])->all();
    }

    /**
     * Applies the voucher. One transaction: a distribution that got halfway
     * would leave some of the consignment revalued and the rest not, which
     * is worse than not applying it at all.
     */
    public function post(LandedCostVoucher $voucher, ?string $userId = null): LandedCostVoucher
    {
        $this->assertTransition($voucher, 'POSTED');

        $rows = $this->distribute($voucher);

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'goods_receipt_ids' => __('procurement.landed_cost_nothing_to_apply'),
            ])->status(409);
        }

        DB::transaction(function () use ($voucher, $rows, $userId): void {
            /** @var array<string, array<string, string>> $rollUplift */
            $rollUplift = [];

            foreach ($rows as $row) {
                /** @var GoodsReceiptLine $line */
                $line = $row['line'];
                /** @var Item $item */
                $item = $row['item'];

                LandedCostAllocation::create([
                    'landed_cost_voucher_id' => $voucher->id,
                    'goods_receipt_id' => $line->goods_receipt_id,
                    'goods_receipt_line_id' => $line->id,
                    'item_id' => $item->id,
                    'basis_value' => $row['basis_value'],
                    'quantity' => $row['quantity'],
                    'share_amount' => $row['share'],
                    'unit_uplift' => $row['uplift'],
                    'previous_unit_cost' => $row['previous'],
                    'new_unit_cost' => $row['new'],
                ]);

                // The receipt line's own rate becomes the landed rate, so a
                // reprint of the GRN shows what the goods actually cost.
                $line->forceFill(['rate' => $row['new']])->save();

                if ($item->usesRollLedger()) {
                    // Collected and applied per receipt below: the roll
                    // cascade walks children and merges, and doing that once
                    // per receipt is both cheaper and easier to reason about.
                    $rollUplift[(string) $line->goods_receipt_id][$item->id] = $row['new'];

                    continue;
                }

                if (bccomp($row['share'], '0', self::SCALE) !== 1 || $line->bin_id === null) {
                    continue;
                }

                $this->ledger->revalue($item, Bin::findOrFail($line->bin_id), $row['share'], [
                    'lot_no' => $line->lot_no,
                    'currency' => $voucher->currency,
                    'reference_type' => 'landed_cost_voucher',
                    'reference_id' => $voucher->id,
                    'performed_by' => $userId,
                    'notes' => $voucher->voucher_no,
                ]);
            }

            foreach ($rollUplift as $goodsReceiptId => $costByItem) {
                $this->rollCosts->forGoodsReceipt($goodsReceiptId, $costByItem);
            }

            // Rate lock (§11): these receipts are now part of a valuation.
            GoodsReceipt::whereIn('id', $rows->pluck('line.goods_receipt_id')->unique()->all())
                ->update(['landed_cost_posted_at' => CarbonImmutable::now()]);

            $voucher->forceFill([
                'status' => 'POSTED',
                'posted_by' => $userId,
                'posted_at' => CarbonImmutable::now(),
                'total_charges' => $this->totalCharges($voucher),
            ])->save();
        });

        $posted = $voucher->fresh();

        // Only what was actually distributed: a charge that found no line
        // to land on has not changed what stock is worth.
        $this->gl->post('LANDED_COST_POST', $rows->reduce(
            fn (string $sum, array $row): string => bcadd($sum, $row['share'], self::SCALE),
            '0.0000',
        ), [
            'source_type' => 'landed_cost_voucher',
            'source_id' => $posted->id,
            'source_no' => $posted->voucher_no,
            'factory_id' => $posted->factory_id,
            'currency' => $posted->currency,
            'date' => $posted->voucher_date,
            'memo' => $posted->voucher_no,
            'user_id' => $userId,
        ]);

        return $posted;
    }

    public function cancel(LandedCostVoucher $voucher, ?string $reason = null): LandedCostVoucher
    {
        $this->assertTransition($voucher, 'CANCELLED');

        $voucher->forceFill([
            'status' => 'CANCELLED',
            'cancelled_at' => CarbonImmutable::now(),
            'notes' => $reason === null ? $voucher->notes : trim(($voucher->notes ?? '')."\n".$reason),
        ])->save();

        return $voucher->fresh();
    }

    /**
     * The distribution itself.
     *
     * @return Collection<int, array{line: GoodsReceiptLine, item: Item, quantity: string, basis_value: string, share: string, uplift: string, previous: string, new: string}>
     */
    private function distribute(LandedCostVoucher $voucher): Collection
    {
        $voucher->loadMissing(['charges', 'receipts']);

        $total = $this->totalCharges($voucher);
        $receiptIds = $voucher->receipts->pluck('goods_receipt_id')->all();

        if ($receiptIds === [] || bccomp($total, '0', self::SCALE) !== 1) {
            return collect();
        }

        $lines = GoodsReceiptLine::whereIn('goods_receipt_id', $receiptIds)
            ->orderBy('goods_receipt_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $items = Item::whereIn('id', $lines->pluck('item_id')->unique()->all())->get()->keyBy('id');

        $rows = $lines->map(function (GoodsReceiptLine $line) use ($items, $voucher): ?array {
            $item = $items->get($line->item_id);

            if ($item === null) {
                return null;
            }

            $quantity = $this->scale($line->postableQty());
            $rate = $this->scale($line->rate ?? '0');

            // By value the weight is what the line was worth; by quantity it
            // is simply how much came in. A line of zero either way takes
            // nothing — it cannot, and forcing it to would divide by zero.
            $basis = $voucher->basis === 'QTY' ? $quantity : bcmul($quantity, $rate, self::SCALE);

            return [
                'line' => $line,
                'item' => $item,
                'quantity' => $quantity,
                'basis_value' => $basis,
                'previous' => $rate,
            ];
        })->filter()->filter(
            fn (array $row): bool => bccomp($row['basis_value'], '0', self::SCALE) === 1
        )->values();

        $basisTotal = $rows->reduce(
            fn (string $sum, array $row): string => bcadd($sum, $row['basis_value'], self::SCALE),
            '0.0000',
        );

        if (bccomp($basisTotal, '0', self::SCALE) !== 1) {
            return collect();
        }

        $distributed = '0.0000';
        $last = $rows->count() - 1;

        return $rows->map(function (array $row, int $index) use ($total, $basisTotal, &$distributed, $last): array {
            // The last line takes whatever is left rather than its own
            // rounded share, so the parts add back to the whole exactly.
            $share = $index === $last
                ? bcsub($total, $distributed, self::SCALE)
                : bcdiv(bcmul($total, $row['basis_value'], self::SCALE), $basisTotal, self::SCALE);

            $distributed = bcadd($distributed, $share, self::SCALE);

            $uplift = bccomp($row['quantity'], '0', self::SCALE) === 1
                ? bcdiv($share, $row['quantity'], self::SCALE)
                : '0.0000';

            return $row + [
                'share' => $share,
                'uplift' => $uplift,
                'new' => bcadd($row['previous'], $uplift, self::SCALE),
            ];
        });
    }

    private function totalCharges(LandedCostVoucher $voucher): string
    {
        return $voucher->charges()->get(['amount'])->reduce(
            fn (string $sum, LandedCostCharge $charge): string => bcadd($sum, (string) $charge->amount, self::SCALE),
            '0.0000',
        );
    }

    /**
     * @param  list<array<string, mixed>>  $charges
     */
    private function assertCharges(array $charges): void
    {
        if ($charges === []) {
            throw ValidationException::withMessages(['charges' => __('procurement.landed_cost_needs_charges')]);
        }

        foreach ($charges as $index => $charge) {
            if (! in_array($charge['charge_type'] ?? '', LandedCostCharge::TYPES, true)) {
                throw ValidationException::withMessages([
                    "charges.$index.charge_type" => __('validation.in', ['attribute' => 'charge_type']),
                ]);
            }

            if (bccomp($this->scale($charge['amount'] ?? 0), '0', self::SCALE) !== 1) {
                throw ValidationException::withMessages([
                    "charges.$index.amount" => __('inventory.quantity_must_be_positive'),
                ]);
            }

            if (isset($charge['vendor_id']) && $charge['vendor_id'] !== null
                && Vendor::find($charge['vendor_id']) === null) {
                throw ValidationException::withMessages([
                    "charges.$index.vendor_id" => __('itemmaster.unknown_vendor'),
                ]);
            }
        }
    }

    /**
     * @param  list<string>  $goodsReceiptIds
     * @return Collection<int, GoodsReceipt>
     */
    private function receiptsFor(Factory $factory, array $goodsReceiptIds): Collection
    {
        $ids = array_values(array_unique($goodsReceiptIds));

        if ($ids === []) {
            throw ValidationException::withMessages([
                'goods_receipt_ids' => __('procurement.landed_cost_needs_receipts'),
            ]);
        }

        $receipts = GoodsReceipt::whereIn('id', $ids)->where('factory_id', $factory->id)->get();

        if ($receipts->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'goods_receipt_ids' => __('procurement.unknown_receipt'),
            ]);
        }

        // A receipt that has not posted its stock has no valuation to lift.
        $unposted = $receipts->firstWhere(fn (GoodsReceipt $receipt): bool => ! in_array($receipt->status, ['COMPLETED', 'UNDER_INSPECTION'], true));

        if ($unposted !== null) {
            throw ValidationException::withMessages([
                'goods_receipt_ids' => __('procurement.landed_cost_receipt_not_posted', [
                    'number' => $unposted->grn_no, 'status' => $unposted->status,
                ]),
            ])->status(409);
        }

        return $receipts;
    }

    /**
     * @param  list<array<string, mixed>>  $charges
     */
    private function writeCharges(LandedCostVoucher $voucher, array $charges): void
    {
        foreach ($charges as $charge) {
            LandedCostCharge::create([
                'landed_cost_voucher_id' => $voucher->id,
                'charge_type' => $charge['charge_type'],
                'label' => $charge['label'] ?? null,
                'vendor_id' => $charge['vendor_id'] ?? null,
                'amount' => ItemLineResolver::money($charge['amount']),
                'currency' => $voucher->currency,
                'reference_no' => $charge['reference_no'] ?? null,
                'notes' => $charge['notes'] ?? null,
            ]);
        }

        $voucher->forceFill(['total_charges' => $this->totalCharges($voucher)])->save();
    }

    /**
     * @param  Collection<int, GoodsReceipt>  $receipts
     */
    private function writeReceipts(LandedCostVoucher $voucher, Collection $receipts): void
    {
        foreach ($receipts as $receipt) {
            LandedCostReceipt::create([
                'landed_cost_voucher_id' => $voucher->id,
                'goods_receipt_id' => $receipt->id,
            ]);
        }
    }

    private function assertEditable(LandedCostVoucher $voucher): void
    {
        if (! $voucher->isEditable()) {
            throw ValidationException::withMessages([
                'status' => __('procurement.only_draft_editable', ['status' => $voucher->status]),
            ])->status(409);
        }
    }

    private function assertTransition(LandedCostVoucher $voucher, string $to): void
    {
        if (! $voucher->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $voucher->status, 'to' => $to]),
            ])->status(409);
        }
    }

    private function scale(mixed $value): string
    {
        return number_format((float) ($value ?? 0), self::SCALE, '.', '');
    }
}

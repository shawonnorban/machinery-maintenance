<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Actions;

use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Modules\Procurement\Models\GoodsReceiptLine;
use App\Modules\Procurement\Models\PurchaseReturn;
use App\Modules\Procurement\Models\PurchaseReturnLine;
use App\Modules\Procurement\Models\SupplierClaim;
use App\Modules\Procurement\Models\SupplierScorecard;
use App\Modules\Vendor\Models\Vendor;
use Carbon\CarbonImmutable;

/**
 * Scoring a supplier on what the system already recorded
 * (Procurement spec §15).
 *
 * The rule running through all four measures is that **absent data is not
 * a bad score**. A supplier nobody ordered from has no on-time percentage;
 * an item only one mill sells has no price comparison. Scoring those as
 * zero would rank a supplier nobody used below one that actually failed,
 * so a component with nothing behind it is left null and dropped from the
 * weighting, and the card says which ones counted.
 *
 * The four:
 *
 *  - **On-time** — receipts against an order that promised a date. An
 *    order with no delivery date is excluded rather than counted as met:
 *    there is nothing to be late against, and counting it would flatter
 *    every supplier the factory never pinned down.
 *  - **Quality** — by **value**, not by count, because a rejected roll and
 *    a rejected button are not the same failure. Returned goods plus rolls
 *    graded REJECT, and a roll that was both is counted once (see below).
 *  - **Price** — this supplier's weighted average for an item against what
 *    every supplier charged for it. Null when nobody else sold it.
 *  - **Claims** — money claimed against the supplier, per receipt.
 */
class CalculateSupplierScorecard
{
    private const SCALE = 4;

    /**
     * Rolls the factory rejected at the gate.
     *
     * @var list<string>
     */
    private const REJECTED_ROLL_STATUSES = ['DAMAGED', 'RETURNED'];

    public function forVendor(
        Vendor $vendor,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $userId = null,
    ): SupplierScorecard {
        $receipts = GoodsReceipt::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'COMPLETED')
            ->whereBetween('grn_date', [$from->toDateString(), $to->toDateString()])
            ->with('order:id,delivery_date')
            ->get();

        $onTime = $this->onTime($receipts);
        $quality = $this->quality($vendor, $receipts, $from, $to);
        $price = $this->price($vendor, $receipts);
        $claims = $this->claims($vendor, $receipts->count(), $from, $to);

        $components = array_filter([
            'on_time' => $onTime['score'],
            'quality' => $quality['score'],
            'price' => $price['score'],
            'claims' => $claims['score'],
        ], static fn (?string $score): bool => $score !== null);

        $overall = $this->weightedMean($components);

        $card = SupplierScorecard::updateOrCreate(
            [
                'vendor_id' => $vendor->id,
                'period_start' => $from->toDateString(),
                'period_end' => $to->toDateString(),
            ],
            [
                'receipt_count' => $receipts->count(),
                'dated_receipt_count' => $onTime['dated'],
                'on_time_count' => $onTime['on_time'],
                'on_time_percent' => $onTime['percent'],
                'received_value' => $quality['received'],
                'rejected_value' => $quality['rejected'],
                'quality_reject_percent' => $quality['percent'],
                'price_index' => $price['index'],
                'price_score' => $price['score'],
                'claim_count' => $claims['count'],
                'claim_frequency_percent' => $claims['percent'],
                'overall_score' => $overall,
                // Which measures actually had data behind them, so a 70
                // can be read for what it is.
                'components_json' => array_keys($components),
                'computed_at' => CarbonImmutable::now(),
                'computed_by' => $userId,
            ],
        );

        $card = $card->fresh();

        // The supplier master's own figure (Master Data §3), so a buyer
        // picking from a list sees it without opening a scorecard.
        if ($card->overall_score !== null) {
            $vendor->forceFill(['rating' => $card->asRating()])->save();
        }

        return $card;
    }

    /**
     * @return array{cards: int, vendors: int}
     */
    public function forAllVendors(CarbonImmutable $from, CarbonImmutable $to, ?string $userId = null): array
    {
        $vendors = Vendor::query()
            ->whereIn('vendor_type', ['SUPPLIER', 'BOTH'])
            ->where('status', '!=', 'INACTIVE')
            ->get();

        $cards = 0;

        foreach ($vendors as $vendor) {
            $card = $this->forVendor($vendor, $from, $to, $userId);

            if (! $card->isEmpty()) {
                $cards++;
            }
        }

        return ['cards' => $cards, 'vendors' => $vendors->count()];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, GoodsReceipt>  $receipts
     * @return array{dated: int, on_time: int, percent: ?string, score: ?string}
     */
    private function onTime($receipts): array
    {
        $dated = 0;
        $onTime = 0;

        foreach ($receipts as $receipt) {
            $promised = $receipt->order?->delivery_date;

            if ($promised === null) {
                // Nothing was promised, so nothing was missed.
                continue;
            }

            $dated++;

            if ($receipt->grn_date !== null && $receipt->grn_date->startOfDay()->lessThanOrEqualTo($promised->startOfDay())) {
                $onTime++;
            }
        }

        if ($dated === 0) {
            return ['dated' => 0, 'on_time' => 0, 'percent' => null, 'score' => null];
        }

        $percent = bcmul(bcdiv((string) $onTime, (string) $dated, 6), '100', 2);

        return ['dated' => $dated, 'on_time' => $onTime, 'percent' => $percent, 'score' => $percent];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, GoodsReceipt>  $receipts
     * @return array{received: string, rejected: string, percent: ?string, score: ?string}
     */
    private function quality(Vendor $vendor, $receipts, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $receiptIds = $receipts->pluck('id')->all();

        $received = $receiptIds === [] ? '0' : (string) GoodsReceiptLine::whereIn('goods_receipt_id', $receiptIds)
            ->selectRaw('COALESCE(SUM(received_qty * COALESCE(rate, 0)), 0) as total')
            ->value('total');

        if (bccomp($received, '0', self::SCALE) !== 1) {
            return ['received' => '0', 'rejected' => '0', 'percent' => null, 'score' => null];
        }

        // Goods formally sent back.
        $returned = (string) PurchaseReturn::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'DISPATCHED')
            ->whereBetween('return_date', [$from->toDateString(), $to->toDateString()])
            ->sum('debit_note_amount');

        /*
         * Rolls the factory rejected at the gate. A roll that was also
         * sent back on a purchase return is already in the figure above,
         * so those are excluded here rather than counted twice — a
         * scorecard that over-reports failure is one a supplier can
         * dismiss, which costs more than it gains.
         */
        $returnedRollIds = PurchaseReturnLine::query()
            ->whereNotNull('fabric_roll_id')
            ->whereIn('purchase_return_id', PurchaseReturn::query()
                ->where('vendor_id', $vendor->id)
                ->where('status', 'DISPATCHED')
                ->select('id'))
            ->pluck('fabric_roll_id')
            ->all();

        $rejectedRolls = $receiptIds === [] ? '0' : (string) FabricRoll::query()
            ->whereIn('goods_receipt_id', $receiptIds)
            ->where('inspection_grade', 'REJECT')
            ->whereIn('status', self::REJECTED_ROLL_STATUSES)
            ->when($returnedRollIds !== [], fn ($q) => $q->whereNotIn('id', $returnedRollIds))
            ->selectRaw('COALESCE(SUM(COALESCE(net_length, net_weight, 0) * COALESCE(unit_cost, 0)), 0) as total')
            ->value('total');

        $rejected = bcadd($returned, $rejectedRolls, self::SCALE);
        $percent = bcmul(bcdiv($rejected, $received, 6), '100', 2);

        // Capped: a period whose returns exceed its receipts (goods sent
        // back from an earlier delivery) is a zero, not a negative score.
        $score = $this->clamp(bcsub('100', $percent, 2));

        return ['received' => $received, 'rejected' => $rejected, 'percent' => $percent, 'score' => $score];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, GoodsReceipt>  $receipts
     * @return array{index: ?string, score: ?string}
     */
    private function price(Vendor $vendor, $receipts): array
    {
        $receiptIds = $receipts->pluck('id')->all();

        if ($receiptIds === []) {
            return ['index' => null, 'score' => null];
        }

        $mine = GoodsReceiptLine::whereIn('goods_receipt_id', $receiptIds)
            ->selectRaw('item_id, SUM(received_qty) as qty, SUM(received_qty * COALESCE(rate, 0)) as value')
            ->groupBy('item_id')
            ->get();

        $weightedIndex = '0';
        $weight = '0';

        foreach ($mine as $row) {
            if (bccomp((string) $row->qty, '0', self::SCALE) !== 1) {
                continue;
            }

            $market = $this->marketRate((string) $row->item_id, $vendor->id);

            if ($market === null) {
                // Nobody else sold it, so there is nothing to be dear or
                // cheap against.
                continue;
            }

            $mineRate = bcdiv((string) $row->value, (string) $row->qty, 6);

            if (bccomp($mineRate, '0', self::SCALE) !== 1) {
                continue;
            }

            // Weighted by what was actually spent, so a large purchase
            // counts for more than a sample order.
            $weightedIndex = bcadd($weightedIndex, bcmul(bcdiv($mineRate, $market, 6), (string) $row->value, 6), 6);
            $weight = bcadd($weight, (string) $row->value, 6);
        }

        if (bccomp($weight, '0', self::SCALE) !== 1) {
            return ['index' => null, 'score' => null];
        }

        $index = bcdiv($weightedIndex, $weight, 4);

        // 1.00 is the market rate and scores 100. Every 1% above market
        // costs two points, so 10% dear lands at 80.
        $score = $this->clamp(bcsub('100', bcmul(bcsub($index, '1', 6), '200', 2), 2));

        return ['index' => $index, 'score' => $score];
    }

    /**
     * What every other supplier charged for an item, weighted by quantity.
     */
    private function marketRate(string $itemId, string $vendorId): ?string
    {
        $row = GoodsReceiptLine::query()
            ->join('goods_receipts', 'goods_receipt_lines.goods_receipt_id', '=', 'goods_receipts.id')
            ->where('goods_receipt_lines.item_id', $itemId)
            ->where('goods_receipts.status', 'COMPLETED')
            ->where(fn ($q) => $q->whereNull('goods_receipts.vendor_id')->orWhere('goods_receipts.vendor_id', '!=', $vendorId))
            ->selectRaw('SUM(goods_receipt_lines.received_qty) as qty, SUM(goods_receipt_lines.received_qty * COALESCE(goods_receipt_lines.rate, 0)) as value')
            ->first();

        if ($row === null || bccomp((string) ($row->qty ?? '0'), '0', self::SCALE) !== 1) {
            return null;
        }

        $rate = bcdiv((string) $row->value, (string) $row->qty, 6);

        return bccomp($rate, '0', self::SCALE) === 1 ? $rate : null;
    }

    /**
     * @return array{count: int, percent: ?string, score: ?string}
     */
    private function claims(Vendor $vendor, int $receiptCount, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $count = SupplierClaim::query()
            ->where('vendor_id', $vendor->id)
            // Withdrawn claims were the factory's own mistake, so they are
            // not held against the supplier.
            ->where('status', '!=', 'WITHDRAWN')
            ->whereBetween('claim_date', [$from->toDateString(), $to->toDateString()])
            ->count();

        if ($receiptCount === 0) {
            return ['count' => $count, 'percent' => null, 'score' => null];
        }

        $percent = bcmul(bcdiv((string) $count, (string) $receiptCount, 6), '100', 2);

        return ['count' => $count, 'percent' => $percent, 'score' => $this->clamp(bcsub('100', $percent, 2))];
    }

    /**
     * The weighted mean of whichever components had data.
     *
     * @param  array<string, string>  $components
     */
    private function weightedMean(array $components): ?string
    {
        if ($components === []) {
            return null;
        }

        $total = '0';
        $weight = 0;

        foreach ($components as $key => $score) {
            $w = SupplierScorecard::WEIGHTS[$key];
            $total = bcadd($total, bcmul($score, (string) $w, 4), 4);
            $weight += $w;
        }

        // Renormalised over the components that counted, so a card built
        // from two measures is still out of 100.
        return bcdiv($total, (string) $weight, 2);
    }

    private function clamp(string $score): string
    {
        if (bccomp($score, '0', 2) === -1) {
            return '0.00';
        }

        return bccomp($score, '100', 2) === 1 ? '100.00' : $score;
    }
}

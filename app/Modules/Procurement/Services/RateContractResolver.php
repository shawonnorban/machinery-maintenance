<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Services;

use App\Modules\ItemMaster\Models\Item;
use App\Modules\Procurement\Models\RateContract;
use App\Modules\Procurement\Models\RateContractLine;
use App\Modules\Vendor\Models\Vendor;
use Carbon\CarbonImmutable;

/**
 * What was agreed for this item, with this supplier, on this day
 * (Procurement spec §14).
 *
 * The whole point of a rate contract is that a buyer raising an order in
 * September does not have to remember what was negotiated in March. So
 * this is consulted whenever a purchase order line arrives **without** a
 * rate — not whenever it disagrees with one. A buyer who types a price is
 * making a decision, and a system that silently overwrites it has taken
 * that decision away and left no trace.
 *
 * Where two contracts could both apply, the **narrower** one wins: a
 * factory-specific agreement beats a company-wide one, and a later-starting
 * contract beats an older one. `ManageRateContract` refuses to activate a
 * contract that would genuinely collide with another, so this tie-break is
 * a safety net rather than the mechanism.
 */
class RateContractResolver
{
    private const SCALE = 4;

    /**
     * @return array{rate: string, line: RateContractLine, contract: RateContract}|null
     */
    public function resolve(
        Vendor $vendor,
        Item $item,
        string $quantity = '0',
        ?string $factoryId = null,
        ?CarbonImmutable $on = null,
    ): ?array {
        $date = $on ?? CarbonImmutable::today();

        $contracts = RateContract::query()
            ->where('vendor_id', $vendor->id)
            ->liveOn($date)
            ->forFactory($factoryId)
            // Narrower first: a factory's own agreement, then the newest.
            ->orderByRaw('factory_id IS NULL')
            ->orderByDesc('valid_from')
            ->with(['lines' => fn ($q) => $q->where('item_id', $item->id)->orderByDesc('min_quantity')])
            ->get();

        foreach ($contracts as $contract) {
            foreach ($contract->lines as $line) {
                // Lines come highest-band first, so the first one the order
                // qualifies for is the best price it has earned.
                if ($line->coversQuantity($quantity)) {
                    return [
                        'rate' => (string) $line->rate,
                        'line' => $line,
                        'contract' => $contract,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * The rate alone, for callers that only want the number.
     */
    public function rateFor(
        Vendor $vendor,
        Item $item,
        string $quantity = '0',
        ?string $factoryId = null,
        ?CarbonImmutable $on = null,
    ): ?string {
        return $this->resolve($vendor, $item, $quantity, $factoryId, $on)['rate'] ?? null;
    }

    /**
     * Whether a contract about to be activated would collide with one
     * already live.
     *
     * Two agreements covering the same item, for the same supplier, over
     * overlapping dates and the same quantity band leave no way to say
     * which is the price — so activation refuses rather than letting the
     * resolver's tie-break quietly decide something a buyer never agreed.
     *
     * @return list<array{item_code: string, contract_no: string}>
     */
    public function collisionsFor(RateContract $contract): array
    {
        $contract->loadMissing('lines');

        if ($contract->lines->isEmpty()) {
            return [];
        }

        $rivals = RateContract::query()
            ->where('vendor_id', $contract->vendor_id)
            ->where('id', '!=', $contract->id)
            ->where('status', 'ACTIVE')
            // Overlapping periods: each starts before the other ends.
            ->whereDate('valid_from', '<=', $contract->valid_to->toDateString())
            ->whereDate('valid_to', '>=', $contract->valid_from->toDateString())
            ->with('lines')
            ->get()
            ->filter(fn (RateContract $rival): bool => $this->sharesScope($rival, $contract));

        $collisions = [];

        foreach ($contract->lines as $line) {
            foreach ($rivals as $rival) {
                foreach ($rival->lines as $rivalLine) {
                    if ($rivalLine->item_id !== $line->item_id) {
                        continue;
                    }

                    if (bccomp((string) $rivalLine->min_quantity, (string) $line->min_quantity, self::SCALE) !== 0) {
                        // A different band is a price break, not a clash.
                        continue;
                    }

                    $collisions[] = [
                        'item_code' => $line->item_code,
                        'contract_no' => $rival->contract_no,
                    ];
                }
            }
        }

        return $collisions;
    }

    /**
     * Whether two contracts could ever both apply to one order.
     *
     * A company-wide contract shares scope with everything; two
     * factory-specific ones only clash inside the same factory.
     */
    private function sharesScope(RateContract $a, RateContract $b): bool
    {
        return $a->factory_id === null
            || $b->factory_id === null
            || $a->factory_id === $b->factory_id;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Services;

use App\Modules\Inventory\Models\Bin;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemLot;
use App\Modules\ItemMaster\Models\ItemStockBalance;

/**
 * Which lot to draw from (Inventory spec §12: yarn lot-wise issue, chemical
 * FIFO / nearest expiry).
 *
 * A storekeeper reaching for the nearest drum is how a store ends up
 * scrapping the back row. The order here is the one the spec asks for and
 * the one a store actually wants:
 *
 *  1. the lot that expires soonest, because that is the one that will be
 *     thrown away if it is not used;
 *  2. failing an expiry, the oldest received — plain FIFO;
 *  3. an expired lot is listed last and flagged, never silently first. It
 *     is still listed: somebody has to decide to scrap it, and a lot that
 *     vanishes from the screen is a lot nobody writes off.
 *
 * A suggestion, not a rule. The issue action does not force the order —
 * a dye lot may be reserved for a shade match, and the storekeeper knows
 * that and the system does not.
 */
class LotSuggestion
{
    private const SCALE = 4;

    /**
     * @param  list<string>  $binIds  restrict to these bins (a factory's, usually)
     * @return list<array<string, mixed>>
     */
    public function forItem(Item $item, array $binIds = []): array
    {
        if (! $item->isLotTracked()) {
            return [];
        }

        $balances = ItemStockBalance::where('item_id', $item->id)
            ->when($binIds !== [], fn ($q) => $q->whereIn('bin_id', $binIds))
            ->with('bin:id,code,name')
            ->get();

        $lots = ItemLot::where('item_id', $item->id)
            ->get()
            ->keyBy('lot_no');

        $rows = [];

        foreach ($balances as $balance) {
            $available = bcsub((string) $balance->quantity_on_hand, (string) $balance->quantity_reserved, self::SCALE);

            if (bccomp($available, '0', self::SCALE) !== 1) {
                continue;
            }

            /** @var ItemLot|null $lot */
            $lot = $lots->get($balance->lot_no);

            $rows[] = [
                'lot_no' => $balance->lot_no === '' ? null : $balance->lot_no,
                'bin_id' => $balance->bin_id,
                'bin' => $balance->bin === null ? null : ['code' => $balance->bin->code, 'name' => $balance->bin->name],
                'available' => $available,
                'expiry_date' => $lot?->expiry_date?->toDateString(),
                'days_to_expiry' => $lot?->daysToExpiry(),
                'expired' => (bool) $lot?->hasExpired(),
                'first_received_date' => $lot?->first_received_date?->toDateString(),
            ];
        }

        usort($rows, function (array $a, array $b): int {
            // Expired last, whatever else is true of it.
            if ($a['expired'] !== $b['expired']) {
                return $a['expired'] ? 1 : -1;
            }

            // Nearest expiry first; lots without one sort after those with.
            if ($a['expiry_date'] !== $b['expiry_date']) {
                if ($a['expiry_date'] === null) {
                    return 1;
                }

                if ($b['expiry_date'] === null) {
                    return -1;
                }

                return $a['expiry_date'] <=> $b['expiry_date'];
            }

            // Then plain FIFO on when the lot first arrived.
            return ($a['first_received_date'] ?? '9999-12-31') <=> ($b['first_received_date'] ?? '9999-12-31');
        });

        return $rows;
    }

    /**
     * The one lot the screen should pre-select, or null when there is
     * nothing to draw from.
     *
     * @param  list<string>  $binIds
     * @return array<string, mixed>|null
     */
    public function bestFor(Item $item, array $binIds = []): ?array
    {
        $rows = $this->forItem($item, $binIds);

        // An expired lot is never the suggestion, even if it is all there is.
        foreach ($rows as $row) {
            if (! $row['expired']) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $binIds
     */
    public function binIdsForFactory(string $factoryId): array
    {
        return Bin::whereHas('store.warehouse', fn ($q) => $q->where('factory_id', $factoryId))
            ->pluck('id')
            ->all();
    }
}

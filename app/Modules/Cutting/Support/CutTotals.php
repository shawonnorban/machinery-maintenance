<?php

declare(strict_types=1);

namespace App\Modules\Cutting\Support;

use App\Modules\Cutting\Models\CutBundle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What a PO's colour was ordered and has been cut, the one way the Cutting
 * List and the Additional Cutting List both count it: the buyer's order of
 * that colour (its size breakdown, else the PO's total), and the cut as the
 * part cut most — a garment is cut once whatever its parts — over every card
 * not cancelled.
 */
final class CutTotals
{
    public static function key(string $orderId, string $colour): string
    {
        return $orderId.'|'.mb_strtolower(trim($colour));
    }

    /**
     * @param  Collection<int, string>  $orderIds
     * @return Collection<string, array{order_qty: int, cut: int, additional: int, additional_pct: ?float}>  keyed by self::key()
     */
    public static function forOrders(Collection $orderIds): Collection
    {
        $ids = $orderIds->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }
        $sizes = DB::table('merch_order_sizes')->whereIn('order_id', $ids)->get(['order_id', 'colour', 'qty'])
            ->groupBy(fn ($r) => self::key($r->order_id, (string) $r->colour))->map(fn ($g) => (int) $g->sum('qty'));
        $totals = DB::table('merch_orders')->whereIn('id', $ids)->pluck('total_qty', 'id');

        return CutBundle::query()->whereIn('order_id', $ids)->where('status', '!=', 'CANCELLED')->get(['order_id', 'colour', 'parts_name', 'qty'])
            ->groupBy(fn ($b) => self::key($b->order_id, (string) $b->colour))
            ->map(function (Collection $g, string $key) use ($sizes, $totals) {
                $orderQty = (int) ($sizes[$key] ?? 0) ?: (int) ($totals[$g->first()->order_id] ?? 0);
                $cut = (int) $g->groupBy('parts_name')->map(fn ($p) => $p->sum('qty'))->max();
                $additional = max(0, $cut - $orderQty);

                return ['order_qty' => $orderQty, 'cut' => $cut, 'additional' => $additional,
                    'additional_pct' => $orderQty > 0 ? round(100 * $additional / $orderQty, 2) : null];
            });
    }
}

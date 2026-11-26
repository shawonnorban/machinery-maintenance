<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sales & Shipment → Sub-Contract (AnnotechERP 21/03): the figures every
 * screen reads the same way.
 *
 * Per contract (S/C programme) line: `held` — on every chalan not
 * cancelled, less `except` chalan. Per chalan line: `sent`, `received` and
 * `rejected` (on receipts not cancelled, less `except` receipt), `returned`
 * (on confirmed returns — sent back out to the party) and `out`, what is
 * still with the party: sent − received − rejected + returned.
 */
final class SubconBook
{
    /**
     * @param  list<string>  $programmeLineIds
     * @return Collection<string, float>
     */
    public static function held(array $programmeLineIds, ?string $exceptDelivery = null): Collection
    {
        $rows = DB::table('ss_sc_delivery_lines as l')->join('ss_sc_deliveries as d', 'd.id', '=', 'l.delivery_id')->whereIn('l.programme_line_id', $programmeLineIds)
            ->where('d.state', '!=', 'CANCELLED')->when($exceptDelivery, fn ($q) => $q->where('d.id', '!=', $exceptDelivery))
            ->groupBy('l.programme_line_id')->selectRaw('l.programme_line_id, sum(l.qty) as q')->pluck('q', 'programme_line_id');

        return collect($programmeLineIds)->mapWithKeys(fn ($id) => [$id => (float) ($rows[$id] ?? 0)]);
    }

    /**
     * @param  list<string>  $lineIds
     * @return Collection<string, array{sent: float, received: float, rejected: float, returned: float, out: float}>
     */
    public static function lines(array $lineIds, ?string $exceptReceipt = null): Collection
    {
        if (! $lineIds) {
            return collect();
        }
        $sent = DB::table('ss_sc_delivery_lines')->whereIn('id', $lineIds)->pluck('qty', 'id');
        $rcv = DB::table('ss_sc_receipt_lines as rl')->join('ss_sc_receipts as r', 'r.id', '=', 'rl.receipt_id')->whereIn('rl.delivery_line_id', $lineIds)->where('r.state', '!=', 'CANCELLED')
            ->when($exceptReceipt, fn ($q) => $q->where('r.id', '!=', $exceptReceipt))
            ->groupBy('rl.delivery_line_id')->selectRaw('rl.delivery_line_id, sum(rl.qty) as q, sum(rl.rejected_qty) as rj')->get()->keyBy('delivery_line_id');
        $ret = DB::table('ss_sc_return_lines as tl')->join('ss_sc_returns as t', 't.id', '=', 'tl.return_id')->join('ss_sc_receipt_lines as rl', 'rl.id', '=', 'tl.receipt_line_id')
            ->whereIn('rl.delivery_line_id', $lineIds)->where('t.state', 'CONFIRMED')->groupBy('rl.delivery_line_id')->selectRaw('rl.delivery_line_id, sum(tl.qty) as q')->pluck('q', 'delivery_line_id');

        return $sent->mapWithKeys(function ($q, $id) use ($rcv, $ret) {
            $r = (float) ($rcv[$id]->q ?? 0);
            $j = (float) ($rcv[$id]->rj ?? 0);
            $t = (float) ($ret[$id] ?? 0);

            return [$id => ['sent' => (float) $q, 'received' => $r, 'rejected' => $j, 'returned' => $t, 'out' => round((float) $q - $r - $j + $t, 4)]];
        });
    }

    /** "SO · Item · Colour · Size" of programme lines, keyed by programme line id. @return array<string, string> */
    public static function labels(SalesBook $book, array $programmeLineIds): array
    {
        $rows = DB::table('ss_sc_programme_lines as p')->join('ss_sales_order_sizes as s', 's.id', '=', 'p.size_id')->join('ss_sales_orders as o', 'o.id', '=', 's.order_id')
            ->whereIn('p.id', $programmeLineIds)->get(['p.id', 'p.size_id', 'o.so_no']);
        $sizes = $book->sizeLabels($rows->pluck('size_id')->all());

        return $rows->mapWithKeys(fn ($r) => [$r->id => $r->so_no.' · '.($sizes[$r->size_id] ?? '—')])->all();
    }
}

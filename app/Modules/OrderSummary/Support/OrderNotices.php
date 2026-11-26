<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Support;

use App\Modules\Erp\Support\SheetNotices;
use App\Modules\OrderSummary\Models\OsChange;
use App\Modules\OrderSummary\Models\OsOrder;
use App\Shared\Formula\Formula;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Collection;

/**
 * Order Summary's notifications (user, 2026-11-21).
 *
 *  - OS_ORDERS_CHANGED: orders created, changed or deleted — told to whoever
 *    the edit mail goes to (the buyer's merchandisers, their team heads and
 *    department heads: OrderScope) and to the orders' authors, at once, one
 *    notification per save.
 *  - OS_PENDING_WORK: each morning, to the same people for their buyers'
 *    open orders — RFI/SD gone by in the last 30 days, RFI/SD in the coming
 *    7 days, and fabric or trims booking still pending with LDD/ETD within 14
 *    days. A dropped or cancelled order is not work.
 */
final class OrderNotices
{
    public const LIST = '/order-summary/list';

    /** One saved batch: who should hear of it. */
    public static function batch(string $batch): void
    {
        $changes = OsChange::query()->where('batch', $batch)->where('kind', '!=', 'RECALC')->get(['order_id', 'kind', 'column_key', 'changed_by']);
        if ($changes->isEmpty()) {
            return;
        }
        $actor = $changes->first()->changed_by;
        $kinds = $changes->pluck('kind')->unique();
        $kind = $kinds->contains('DELETE') ? 'DELETE' : ($kinds->contains('CREATE') ? 'CREATE' : 'EDIT');
        $orders = OsOrder::withTrashed()->whereKey($changes->pluck('order_id')->unique())->get(['id', 'buyer_id', 'created_by', 'values']);
        $allowed = SheetNotices::holders('ordersummary.view')->flip();

        // Per person: the orders of this save they look after.
        $mine = [];
        foreach ($orders->groupBy('buyer_id') as $buyer => $group) {
            $people = collect($buyer ? OrderScope::recipientsFor((string) $buyer) : [])->merge($group->pluck('created_by'));
            foreach ($people->filter()->unique() as $u) {
                if (isset($allowed[$u])) {
                    $mine[$u] = ($mine[$u] ?? collect())->merge($group);
                }
            }
        }
        $by = SheetNotices::name($actor);
        $cells = $changes->whereNotNull('column_key')->groupBy('order_id')->map->count();
        SheetNotices::send(array_keys($mine), 'OS_ORDERS_CHANGED', function (string $locale, string $u) use ($mine, $by, $kind, $cells) {
            $list = $mine[$u];
            $buyers = $list->map(fn (OsOrder $o) => Formula::text($o->values['buyer'] ?? ''))->filter()->unique();

            return [
                'by' => $by, 'action' => __("notification.sheet.kind.{$kind}", [], $locale), 'n' => $list->count(),
                'buyers' => SheetNotices::list($buyers) ?: '—', 'cells' => (int) $list->sum(fn (OsOrder $o) => $cells[$o->id] ?? 0),
                'orders' => SheetNotices::list($list->map(fn (OsOrder $o) => '#'.(Formula::text($o->values['serial_no'] ?? '') ?: '?'))),
            ];
        }, self::link($kind === 'DELETE' ? null : $orders), 'INFO', $actor);
    }

    /**
     * The morning's pending work for one company (its context set).
     *
     * @return int notifications sent
     */
    public static function pendingWork(): int
    {
        $sheet = new Sheet(app(TenantContext::class)->companyId());
        $today = Formula::day(Sheet::today());
        $open = OsOrder::query()->get()->map(fn (OsOrder $o) => ['buyer_id' => $o->buyer_id, 'created_by' => $o->created_by, 'v' => $sheet->read($o)])
            ->reject(fn ($r) => OrderPending::shipped($r['v']) || preg_match('/drop|cancel/i', Formula::text($r['v']['order_type'] ?? '')));
        $rows = [];
        foreach ($open as $r) {
            $v = $r['v'];
            $days = Formula::num($v['days_left_in_hand'] ?? null);
            $ldd = Formula::day($v['po_wise_ldd_etd'] ?? null);
            $flags = [
                'overdue' => $days !== null && $days < 0 && $days >= -30,
                'week' => $days !== null && $days >= 0 && $days <= 7,
                'booking' => $ldd !== null && $ldd - $today <= 14 && $ldd >= $today
                    && (OrderPending::rows(collect([$v]), 'fabric_booking_pending')->isNotEmpty() || OrderPending::rows(collect([$v]), 'trims_booking_pending')->isNotEmpty()),
            ];
            if (! in_array(true, $flags, true)) {
                continue;
            }
            $people = collect($r['buyer_id'] ? OrderScope::recipientsFor((string) $r['buyer_id']) : [])->push($r['created_by'])->filter()->unique();
            foreach ($people as $u) {
                foreach ($flags as $k => $on) {
                    $rows[$u][$k] = ($rows[$u][$k] ?? 0) + ($on ? 1 : 0);
                }
                $rows[$u]['buyers'][] = Formula::text($v['buyer'] ?? '');
            }
        }
        $allowed = SheetNotices::holders('ordersummary.view')->flip();
        $rows = array_filter($rows, fn ($r, $u) => isset($allowed[$u]), ARRAY_FILTER_USE_BOTH);

        return SheetNotices::send(array_keys($rows), 'OS_PENDING_WORK', fn (string $locale, string $u) => [
            'overdue' => $rows[$u]['overdue'], 'week' => $rows[$u]['week'], 'booking' => $rows[$u]['booking'], 'buyers' => SheetNotices::list($rows[$u]['buyers']) ?: '—',
        ], '/order-summary/dashboard', 'WARNING');
    }

    /** View All, filtered to the one buyer a save touched. @param  Collection<int, OsOrder>|null  $orders */
    private static function link(?Collection $orders): string
    {
        $buyers = $orders?->map(fn (OsOrder $o) => Formula::text($o->values['buyer'] ?? ''))->filter()->unique();

        return $buyers && $buyers->count() === 1 ? self::LIST.'?f[buyer][]='.rawurlencode((string) $buyers->first()) : self::LIST;
    }
}

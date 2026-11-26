<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Http\Controllers\Api;

use App\Modules\OrderSummary\Models\OsChange;
use App\Modules\OrderSummary\Models\OsOrder;
use App\Modules\OrderSummary\Support\OrderPending;
use App\Modules\OrderSummary\Support\OrderScope;
use App\Modules\OrderSummary\Support\Sheet;
use App\Shared\Formula\Formula;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Order Summary's dashboard and reports (AnnotechERP 32), over the orders of
 * the user's own buyers: the main dashboard; Norban's LDD and RFI Pending
 * Summaries (what is still open — fabric or trims booking, base hanger, black
 * seal, FFP/GSS — against the LDD/ETD or RFI/SD date, most urgent first);
 * Buyer Month Wise (buyer → department → month: quantity, styles, value);
 * and the Audit Log (every save, cell by cell).
 */
class ReportApiController extends ApiController
{
    public const PENDING_TYPES = OrderPending::TYPES;

    public function dashboard(): JsonResponse
    {
        $this->allow('ordersummary.view');
        $sheet = new Sheet($this->caller()->companyId, $this->caller()->user);
        $rows = $this->rows($sheet);
        $open = $rows->reject(fn ($v) => $this->shipped($v));
        $bucket = function (array $v): string {
            $d = Formula::num($v['days_left_in_hand'] ?? null);

            return $d === null ? 'none' : ($d < 0 ? 'overdue' : ($d <= 7 ? 'week' : ($d <= 30 ? 'month' : 'later')));
        };
        $byBuyer = $rows->groupBy(fn ($v) => $v['buyer'] ?? '')->map(fn ($g, $b) => [
            'buyer' => $b, 'orders' => $g->count(), 'qty' => $this->sum($g, 'quantity_pcs'), 'value' => $this->sum($g, 'fob_value'),
            'shipped' => $g->filter(fn ($v) => $this->shipped($v))->count(),
        ])->sortByDesc('value')->values();
        $byMonth = $rows->groupBy(fn ($v) => substr((string) ($v['po_wise_ldd_etd'] ?? ''), 0, 7))->map(fn ($g, $m) => [
            'month' => $m, 'orders' => $g->count(), 'qty' => $this->sum($g, 'quantity_pcs'), 'value' => $this->sum($g, 'fob_value'),
        ])->sortKeys()->values();
        $users = DB::table('users')->pluck('name', 'id');

        return ApiResponse::ok([
            'totals' => [
                'orders' => $rows->count(), 'styles' => $rows->flatMap(fn ($v) => (array) ($v['style_number_name'] ?? []))->unique()->count(),
                'qty' => $this->sum($rows, 'quantity_pcs'), 'value' => $this->sum($rows, 'fob_value'),
                'shipped' => $rows->count() - $open->count(), 'shipout_value' => $this->sum($rows, 'shipout_value_usd'),
            ],
            // Styles, not rows (user, 2026-11-23): a style with several open rows in a bucket counts once there.
            'days_left' => collect(['overdue', 'week', 'month', 'later', 'none'])->mapWithKeys(fn ($b) => [$b => $open->filter(fn ($v) => $bucket($v) === $b)
                ->pipe(fn ($g) => $this->styleCount($g))]),
            'days_left_filters' => $this->daysLeftFilters($open),
            'by_buyer' => $byBuyer, 'by_month' => $byMonth,
            // Styles, not rows (user, 2026-11-23), as the days-left card.
            'pending' => collect(self::PENDING_TYPES)->mapWithKeys(fn ($t) => [$t => $this->styleCount($this->pendingRows($open, $t, 'po_wise_ldd_etd')->pluck('values'))]),
            'recent' => OsChange::query()->whereIn('order_id', $this->scoped()->select('id'))->where('kind', '!=', 'RECALC')
                ->orderByDesc('changed_at')->limit(300)->get()->groupBy('batch')->take(8)->map(fn ($g) => [
                    'at' => $g->first()->changed_at?->toIso8601String(), 'kind' => $g->first()->kind, 'by' => $users[$g->first()->changed_by] ?? null,
                    'orders' => $g->pluck('order_id')->unique()->count(), 'cells' => $g->whereNotNull('column_key')->count(),
                ])->values(),
        ]);
    }

    /** LDD (or RFI) wise Pending Summary. */
    public function pending(Request $request, string $basis): JsonResponse
    {
        $this->allow('ordersummary.report');
        $data = $request->validate(['type' => ['nullable', Rule::in(self::PENDING_TYPES)], 'buyer' => ['nullable', 'string', 'max:120'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $sheet = new Sheet($this->caller()->companyId, $this->caller()->user);
        $date = $basis === 'rfi' ? 'po_wise_rfi_sd' : 'po_wise_ldd_etd';
        $type = $data['type'] ?? ($basis === 'rfi' ? 'trims_booking_pending' : 'fabric_booking_pending');
        $rows = $this->rows($sheet)->reject(fn ($v) => $this->shipped($v))
            ->filter(fn ($v) => empty($data['buyer']) || ($v['buyer'] ?? null) === $data['buyer'])
            ->filter(fn ($v) => empty($data['from']) || ($v[$date] ?? '') >= $data['from'])
            ->filter(fn ($v) => empty($data['to']) || (($v[$date] ?? '') !== '' && $v[$date] <= $data['to']));
        $today = Formula::day(Sheet::today());
        $list = $this->pendingRows($rows, $type, $date)->map(function ($p) use ($date, $today) {
            $v = $p['values'];
            $days = isset($v[$date]) && Formula::day($v[$date]) !== null ? Formula::day($v[$date]) - $today : null;

            return [
                'serial_no' => $v['serial_no'] ?? null, 'buyer' => $v['buyer'] ?? null, 'item' => $v['item'] ?? null,
                'style' => implode(', ', (array) ($v['style_number_name'] ?? [])), 'job_no' => implode(', ', (array) ($v['job_no'] ?? [])),
                'po' => implode(', ', (array) ($v['po_number'] ?? [])), 'date' => $v[$date] ?? null, 'days' => $days,
                'urgency' => $days === null ? 'none' : ($days <= 7 ? 'critical' : ($days <= 15 ? 'high' : ($days <= 30 ? 'medium' : 'low'))),
                'pending' => $p['pending'], 'qty' => Formula::num($v['quantity_pcs'] ?? null), 'value' => Formula::num($v['fob_value'] ?? null), 'status' => $p['status'],
            ];
        })->sortBy(fn ($r) => $r['days'] ?? PHP_INT_MAX)->values();

        return ApiResponse::ok([
            'type' => $type, 'types' => self::PENDING_TYPES, 'rows' => $list,
            'summary' => ['critical' => $list->where('urgency', 'critical')->count(), 'high' => $list->where('urgency', 'high')->count(), 'medium' => $list->where('urgency', 'medium')->count(), 'low' => $list->whereIn('urgency', ['low', 'none'])->count(),
                'qty' => $list->sum('qty'), 'value' => round((float) $list->sum('value'), 2), 'styles' => $this->styleCount($this->pendingRows($rows, $type, $date)->pluck('values'))],
            'buyers' => $this->rows($sheet)->pluck('buyer')->filter()->unique()->sort()->values(),
        ]);
    }

    /** Buyer Month Wise: buyer → department → month, by LDD/ETD or RFI/SD. */
    public function buyerMonth(Request $request, string $basis): JsonResponse
    {
        $this->allow('ordersummary.report');
        $data = $request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100'], 'buyer' => ['nullable', 'string', 'max:120']]);
        $sheet = new Sheet($this->caller()->companyId, $this->caller()->user);
        $date = $basis === 'rfi' ? 'po_wise_rfi_sd' : 'po_wise_ldd_etd';
        $year = (string) ($data['year'] ?? now(Sheet::TIMEZONE)->year);
        $rows = $this->rows($sheet)->filter(fn ($v) => str_starts_with((string) ($v[$date] ?? ''), $year))
            ->filter(fn ($v) => empty($data['buyer']) || ($v['buyer'] ?? null) === $data['buyer']);
        $cell = fn (Collection $g) => ['qty' => $this->sum($g, 'quantity_pcs'), 'styles' => $g->flatMap(fn ($v) => (array) ($v['style_number_name'] ?? []))->unique()->count(), 'value' => $this->sum($g, 'fob_value')];
        $buyers = $rows->groupBy(fn ($v) => $v['buyer'] ?? '')->sortKeys()->map(fn ($g, $b) => [
            'buyer' => $b, 'total' => $cell($g),
            'months' => $g->groupBy(fn ($v) => (int) substr($v[$date], 5, 2))->map($cell),
            'departments' => $g->groupBy(fn ($v) => $v['department'] ?? '')->sortKeys()->map(fn ($d, $name) => ['department' => $name, 'total' => $cell($d), 'months' => $d->groupBy(fn ($v) => (int) substr($v[$date], 5, 2))->map($cell)])->values(),
        ])->values();

        return ApiResponse::ok([
            'year' => (int) $year, 'basis' => $basis, 'buyers' => $buyers, 'total' => $cell($rows),
            'months' => $rows->groupBy(fn ($v) => (int) substr($v[$date], 5, 2))->map($cell),
            'years' => $this->rows($sheet)->map(fn ($v) => substr((string) ($v[$date] ?? ''), 0, 4))->filter()->unique()->sortDesc()->values(),
            'buyer_list' => $this->rows($sheet)->pluck('buyer')->filter()->unique()->sort()->values(),
        ]);
    }

    /** Audit Log: saves, newest first, each with its changed cells. */
    public function audit(Request $request): JsonResponse
    {
        $this->allow('ordersummary.view');
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'kind' => ['nullable', Rule::in(['CREATE', 'EDIT', 'DELETE', 'RECALC'])], 'user' => ['nullable', 'string', 'size:26'], 'q' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1']]);
        $sheet = new Sheet($this->caller()->companyId);
        $labels = $sheet->columns->pluck('label', 'key');
        $users = DB::table('users')->pluck('name', 'id');
        $base = OsChange::query()->whereIn('order_id', $this->scoped(true)->select('id'))
            ->when($data['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))
            ->when($data['user'] ?? null, fn ($q, $u) => $q->where('changed_by', $u))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereRaw("(changed_at at time zone 'Asia/Dhaka')::date >= ?", [$d]))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereRaw("(changed_at at time zone 'Asia/Dhaka')::date <= ?", [$d]))
            ->when($data['q'] ?? null, fn ($q, $s) => $q->whereIn('order_id', OsOrder::withTrashed()->whereRaw('LOWER("values"::text) LIKE ?', ['%'.mb_strtolower(addcslashes($s, '\\%_')).'%'])->select('id')));
        $batches = (clone $base)->toBase()->selectRaw('batch, order_id, max(changed_at) as at')->groupBy('batch', 'order_id')->orderByDesc('at')->paginate(50, ['*'], 'page', (int) ($data['page'] ?? 1));
        $cells = (clone $base)->whereIn('batch', collect($batches->items())->pluck('batch'))->get()->groupBy(fn ($c) => $c->batch.'|'.$c->order_id);
        $orders = OsOrder::withTrashed()->whereKey(collect($batches->items())->pluck('order_id'))->get()->keyBy('id');

        return ApiResponse::ok([
            'entries' => collect($batches->items())->map(function ($b) use ($cells, $orders, $labels, $users) {
                $g = $cells[$b->batch.'|'.$b->order_id] ?? collect();
                $o = $orders[$b->order_id] ?? null;
                $v = $o?->values ?? [];

                return [
                    'at' => $g->first()?->changed_at?->toIso8601String(), 'kind' => $g->first()?->kind, 'by' => $users[$g->first()?->changed_by] ?? null,
                    'serial_no' => $o?->serial_no, 'order' => trim(implode(' / ', array_filter([implode(', ', (array) ($v['style_number_name'] ?? [])), implode(', ', (array) ($v['job_no'] ?? []))]))),
                    'buyer' => $v['buyer'] ?? null, 'deleted' => (bool) $o?->trashed(),
                    'cells' => $g->whereNotNull('column_key')->map(fn ($c) => ['column' => $labels[$c->column_key] ?? $c->column_key, 'old' => $c->old_value, 'new' => $c->new_value])->values(),
                ];
            })->values(),
            'page' => ['current' => $batches->currentPage(), 'last' => $batches->lastPage(), 'total' => $batches->total()],
            'users' => OsChange::query()->distinct()->pluck('changed_by')->filter()->map(fn ($id) => ['value' => $id, 'label' => $users[$id] ?? $id])->values(),
        ]);
    }

    /* ── Helpers ────────────────────────────────────────────────────── */

    private function scoped(bool $withTrashed = false): Builder
    {
        $query = $withTrashed ? OsOrder::withTrashed() : OsOrder::query();
        $me = $this->caller()->auditUserId();
        $scope = $me === null ? null : OrderScope::buyerIds($me, $this->caller()->can('ordersummary.view_all'));
        if ($scope !== null) {
            $query->where(fn ($q) => $q->whereIn('buyer_id', $scope)->orWhere('created_by', $me));
        }

        return $query;
    }

    /** Every scoped order's values as the sheet reads them (day-dependent columns included). */
    private function rows(Sheet $sheet): Collection
    {
        static $cache = [];
        $key = spl_object_id($sheet);

        return $cache[$key] ??= $this->scoped()->get()->map(fn (OsOrder $o) => $sheet->read($o))->values();
    }

    /**
     * Each days-left bucket as the sheet's own filters, so a click lists exactly the orders it counted:
     * a Days Left In Hand range (or its blanks) — the column itself, worked out for today — and "open"
     * as the Shipment Status values not shipped.
     *
     * @param  Collection<int, array>  $open
     * @return array<string, list<array{0: string, 1: string}>> query pairs per bucket
     */
    private function daysLeftFilters(Collection $open): array
    {
        $status = $open->map(fn ($v) => trim((string) ($v['shipment_status'] ?? '')))->unique()->sort()->values()
            ->map(fn ($s) => ['f[shipment_status][]', $s])->all();
        $range = fn (?int $from, ?int $to) => array_values(array_filter([
            $from === null ? null : ['r[days_left_in_hand][from]', (string) $from],
            $to === null ? null : ['r[days_left_in_hand][to]', (string) $to],
        ]));

        return [
            'overdue' => [...$status, ...$range(null, -1)],
            'week' => [...$status, ...$range(0, 7)],
            'month' => [...$status, ...$range(8, 30)],
            'later' => [...$status, ...$range(31, null)],
            'none' => [...$status, ['f[days_left_in_hand][]', '']],
        ];
    }

    /** How many styles the rows hold (each name of Style Number / Name once). @param  Collection<int, array>  $rows */
    private function styleCount(Collection $rows): int
    {
        return $rows->flatMap(fn ($v) => (array) ($v['style_number_name'] ?? []))->map(fn ($s) => trim((string) $s))->filter(fn ($s) => $s !== '')->unique()->count();
    }

    private function shipped(array $v): bool
    {
        return OrderPending::shipped($v);
    }

    private function sum(Collection $rows, string $key): float
    {
        return round((float) $rows->sum(fn ($v) => Formula::num($v[$key] ?? null) ?? 0), 2);
    }

    /** @return Collection<int, array{values: array, pending: string, status: ?string}> */
    private function pendingRows(Collection $rows, string $type, string $date): Collection
    {
        return OrderPending::rows($rows, $type);
    }
}

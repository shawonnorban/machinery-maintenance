<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Http\Controllers\Api;

use App\Modules\SalesShipment\Support\SalesBook;
use App\Modules\SalesShipment\Support\SsSettings;
use App\Modules\SalesShipment\Support\SubconBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sales & Shipment → Report (AnnotechERP 21/07): Brickland's Sales & Shipment
 * Reports — one screen, a report picked from the list, the boxes it reads
 * (company, store, item, status, supplier/customer, style, SO #, from / to;
 * a box the report does not read is disabled, not hidden), Preview or Export
 * to Excel. Every report reads the module's own books: orders and their
 * sizes, challans and returns, check bills, sub-contract papers, shipments
 * and their invoices.
 *
 * Values are in BDT — a challan at its own rate, order lines and shipped
 * goods at the order's rate. Pending is Order Qty less the larger of net
 * delivered and shipped, so goods delivered and then shipped count once. A
 * status applies only to the reports that read its kind.
 */
class SalesReportApiController extends ApiController
{
    /** Each report, the boxes it reads (Brickland's needs* flags) and the status kinds it takes. */
    public const REPORTS = [
        'sales-order-summary' => [['buyer', 'order', 'from', 'to'], []],
        'sales-and-delivery' => [['store', 'item'], []],
        'sales-order-balancing' => [['buyer', 'style', 'order', 'from', 'to'], []],
        'challan-statement' => [['buyer', 'store', 'from', 'to'], []],
        'sales-order-statement' => [['style', 'order', 'item', 'from', 'to'], []],
        'sc-balance-summary' => [['buyer', 'from', 'to'], []],
        'delivery-challan-summary' => [['style', 'order', 'item', 'from', 'to'], []],
        'sales-order-report' => [['buyer', 'style', 'order', 'item', 'status', 'from', 'to'], ['approval', 'order', 'delivery', 'ship']],
        'delivery-challan-report' => [['buyer', 'style', 'order', 'store', 'item', 'status', 'from', 'to'], ['challan']],
        'delivery-pending' => [['buyer', 'style', 'order', 'item', 'status', 'from', 'to'], ['approval', 'order', 'delivery']],
        'sc-delivery-report' => [['buyer', 'style', 'order', 'store', 'item', 'status', 'from', 'to'], ['sc']],
        'sc-receipt-report' => [['buyer', 'style', 'order', 'store', 'item', 'status', 'from', 'to'], ['sc']],
        'sc-pending-report' => [['buyer', 'style', 'order', 'store', 'item', 'from', 'to'], []],
        'shipment-status' => [['buyer', 'style', 'order', 'item', 'status', 'from', 'to'], ['stage']],
        'shipment-pending' => [['buyer', 'style', 'order', 'item', 'status', 'from', 'to'], ['approval', 'order', 'ship']],
        'buyer-wise-sales' => [['buyer', 'style', 'order', 'item', 'from', 'to'], []],
        'order-wise-delivery' => [['buyer', 'style', 'order', 'item', 'status', 'from', 'to'], ['approval', 'order', 'delivery']],
        'order-wise-shipment' => [['buyer', 'style', 'order', 'item', 'status', 'from', 'to'], ['approval', 'order', 'ship']],
        'style-wise-shipment' => [['buyer', 'style', 'order', 'item', 'from', 'to'], []],
        'monthly-sales-summary' => [['buyer', 'style', 'order', 'item', 'from', 'to'], []],
        'sales-shipment-summary' => [['buyer', 'style', 'order', 'item', 'from', 'to'], []],
    ];

    /** The status box's choices, kind by kind, as Brickland groups them. */
    public const STATUSES = [
        'approval' => ['DRAFT', 'SUBMITTED', 'CHECKED', 'APPROVED', 'CONFIRMED'],
        'order' => ['OPEN', 'CLOSED'],
        'delivery' => ['WAITING', 'PARTIAL', 'DONE'],
        'challan' => ['DRAFT', 'SUBMITTED', 'DELIVERED', 'CANCELLED'],
        'ship' => ['NOT_PLANNED', 'PLANNED', 'PARTIAL', 'SHIPPED'],
        'stage' => ['PLANNING', 'BOOKED', 'DOCUMENTATION', 'READY', 'CONFIRMED', 'CLOSED', 'CANCELLED'],
        'sc' => ['DRAFT', 'DELIVERED', 'RECEIVED', 'CLOSED', 'CANCELLED'],
    ];

    private const MAX_RANGE_DAYS = 366;

    /** Columns whose total means nothing (rates, day counts). */
    private const NO_TOTAL = ['rate', 'days', 'orders', 'challans', 'shipments'];

    private ?SalesBook $book = null;

    private array $q = [];

    public function options(): JsonResponse
    {
        $this->allow('salesshipment.report.view');
        $c = $this->caller()->companyId;
        $b = $this->book();

        return ApiResponse::ok([
            'today' => CarbonImmutable::now('Asia/Dhaka')->toDateString(),
            'reports' => collect(self::REPORTS)->map(fn ($r, $kind) => ['kind' => $kind, 'params' => $r[0], 'statuses' => $r[1]])->values()->all(),
            'companies' => [['value' => $c, 'label' => $b->company()]],
            'stores' => DB::table('stores')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])
                ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name.($s->code ? ' ('.$s->code.')' : '')])->all(),
            'items' => DB::table('ss_sales_order_lines')->where('company_id', $c)->whereNotNull('item_id')->distinct()->pluck('item_id')
                ->map(fn ($id) => ['value' => $id, 'label' => $b->items()[$id] ?? '—'])->sortBy('label')->values()->all(),
            'statuses' => collect(self::STATUSES)->flatMap(fn ($vals, $kind) => array_map(fn ($v) => ['value' => "{$kind}:{$v}", 'kind' => $kind], $vals))->values()->all(),
            'buyers' => collect($b->customers())->map(fn ($label, $id) => ['value' => $id, 'label' => $label])->values()->all(),
            'styles' => DB::table('ss_sales_order_lines')->where('company_id', $c)->whereNotNull('style_ref')->where('style_ref', '!=', '')->distinct()->orderBy('style_ref')->pluck('style_ref')
                ->map(fn ($s) => ['value' => $s, 'label' => $s])->values()->all(),
            'orders' => DB::table('ss_sales_orders')->where('company_id', $c)->orderByDesc('receive_date')->orderByDesc('so_no')->get(['so_no', 'customer_id'])
                ->map(fn ($o) => ['value' => $o->so_no, 'label' => $o->so_no, 'customer_id' => $o->customer_id])->values()->all(),
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $this->allow('salesshipment.report.view');
        $kind = $request->validate(['kind' => ['required', Rule::in(array_keys(self::REPORTS))]])['kind'];
        [$need, $kinds] = self::REPORTS[$kind];
        $c = $this->caller()->companyId;
        $q = $request->validate([
            'company' => ['nullable', Rule::in([$c])],
            'store' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $c)],
            'item' => ['nullable', Rule::exists('mp_items', 'id')->where('company_id', $c)],
            'buyer' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $c)],
            'style' => ['nullable', 'string', 'max:120'],
            'order' => ['nullable', Rule::exists('ss_sales_orders', 'so_no')->where('company_id', $c)],
            'status' => ['nullable', 'string', 'max:40'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);
        $q = array_filter(array_intersect_key($q, array_flip($need)), fn ($v) => $v !== null && $v !== '');
        if (! empty($q['from']) && ! empty($q['to'])) {
            if ($q['to'] < $q['from']) {
                throw ValidationException::withMessages(['to' => __('salesshipment.v.sr_range')]);
            }
            if (CarbonImmutable::parse($q['from'])->diffInDays(CarbonImmutable::parse($q['to'])) + 1 > self::MAX_RANGE_DAYS) {
                throw ValidationException::withMessages(['to' => __('salesshipment.v.sr_range_max', ['days' => self::MAX_RANGE_DAYS])]);
            }
        }
        if (! empty($q['status'])) {
            [$sk, $sv] = array_pad(explode(':', $q['status'], 2), 2, '');
            if (! in_array($sv, self::STATUSES[$sk] ?? [], true)) {
                throw ValidationException::withMessages(['status' => __('validation.in', ['attribute' => 'status'])]);
            }
            if (! in_array($sk, $kinds, true)) {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.sr_status', [
                    'report' => __("salesshipment.sr.kind.{$kind}"),
                    'kinds' => implode(', ', array_map(fn ($k) => __("salesshipment.sr.skind.{$k}"), $kinds)) ?: '—',
                ])]);
            }
            $q['status'] = [$sk, $sv];
        }
        $this->q = $q;
        [$columns, $rows] = $this->{'r'.str_replace('-', '', ucwords($kind, '-'))}();
        $columns = array_map(function ($col) {
            [$key, $type] = array_pad(explode(':', $col, 2), 2, 'text');

            return ['key' => $key, 'type' => $type, 'numeric' => in_array($type, ['qty', 'money', 'num'], true)];
        }, $columns);
        $totals = [];
        foreach ($columns as $col) {
            if ($col['numeric'] && ! in_array($col['key'], self::NO_TOTAL, true)) {
                $totals[$col['key']] = round((float) collect($rows)->sum($col['key']), $col['type'] === 'money' ? 2 : 4);
            }
        }

        return ApiResponse::ok(['kind' => $kind, 'columns' => $columns, 'rows' => array_values($rows), 'totals' => $rows ? $totals : []]);
    }

    /* ── Order-side reports, read from each order size ─────────────── */

    private function rSalesOrderSummary(): array
    {
        $rows = $this->facts()->groupBy('order_id')->map(fn (Collection $g) => $this->orderRow($g) + [
            'target_date' => $g->first()->delivery_target_date,
        ]);

        return [['so_no', 'so_date:date', 'customer', 'target_date:date', 'currency', 'order_qty:qty', 'order_value:money', 'net_delivered:qty', 'shipped:qty', 'pending_qty:qty', 'pending_value:money', 'status'], $rows->all()];
    }

    private function rSalesAndDelivery(): array
    {
        $facts = $this->facts();
        $store = $this->q['store'] ?? null;
        $sizeIds = $facts->pluck('size_id')->all();
        $out = $this->challanTotals($sizeIds, $store);
        $rows = $facts->groupBy('item_id')->map(function (Collection $g) use ($out) {
            $d = $g->sum(fn ($f) => $out[$f->size_id]->qty ?? 0);
            $r = $g->sum(fn ($f) => $out[$f->size_id]->ret ?? 0);

            return [
                'item' => $this->book()->items()[$g->first()->item_id] ?? '—', 'orders' => $g->pluck('order_id')->unique()->count(), 'order_qty' => $g->sum('qty'), 'order_value' => round($g->sum('order_value'), 2),
                'delivered' => $d, 'returned' => $r, 'net_delivered' => $d - $r, 'delivered_value' => round($g->sum(fn ($f) => ($out[$f->size_id]->val ?? 0) - ($out[$f->size_id]->ret_val ?? 0)), 2),
                'pending_qty' => $g->sum('pending'),
            ];
        })->sortBy('item');

        return [['item', 'orders:num', 'order_qty:qty', 'order_value:money', 'delivered:qty', 'returned:qty', 'net_delivered:qty', 'delivered_value:money', 'pending_qty:qty'], $rows->all()];
    }

    private function rSalesOrderBalancing(): array
    {
        $rows = $this->facts()->map(fn ($f) => $this->sizeRow($f) + ['net_delivered' => $f->net, 'shipped' => $f->shipped, 'balance' => $f->pending, 'balance_value' => round($f->pending_value, 2)]);

        return [['so_no', 'style', 'item', 'color', 'size', 'order_qty:qty', 'net_delivered:qty', 'shipped:qty', 'balance:qty', 'balance_value:money'], $rows->all()];
    }

    private function rSalesOrderStatement(): array
    {
        $rows = $this->facts()->groupBy('line_id')->map(function (Collection $g) {
            $f = $g->first();

            return ['so_no' => $f->so_no, 'so_date' => $f->receive_date, 'style' => $f->style_ref, 'item' => $this->book()->items()[$f->item_id] ?? '—', 'color' => $f->gmt_color, 'uom' => $f->uom,
                'order_qty' => $g->sum('qty'), 'rate' => $g->sum('qty') > 0 ? round($g->sum(fn ($x) => $x->qty * $x->rate) / $g->sum('qty'), 4) : 0, 'currency' => $f->currency,
                'line_value' => round($g->sum(fn ($x) => $x->qty * $x->rate), 2), 'order_value' => round($g->sum('order_value'), 2)];
        });

        return [['so_no', 'so_date:date', 'style', 'item', 'color', 'uom', 'order_qty:qty', 'rate:num', 'currency', 'line_value:money', 'order_value:money'], $rows->all()];
    }

    private function rSalesOrderReport(): array
    {
        $rows = $this->facts()->map(fn ($f) => $this->sizeRow($f) + [
            'so_date' => $f->receive_date, 'customer' => $this->book()->customerNames()[$f->customer_id] ?? '—', 'buyer' => $f->buyer, 'rate' => $f->rate, 'currency' => $f->currency,
            'order_value' => round($f->order_value, 2), 'target_date' => $f->delivery_target_date, 'status' => $this->statusLabel($f),
        ]);

        return [['so_no', 'so_date:date', 'customer', 'buyer', 'style', 'item', 'color', 'size', 'order_qty:qty', 'rate:num', 'currency', 'order_value:money', 'target_date:date', 'status'], $rows->all()];
    }

    private function rDeliveryPending(): array
    {
        $today = CarbonImmutable::now('Asia/Dhaka')->startOfDay();
        $rows = $this->facts('receive_date', true)->filter(fn ($f) => $f->qty - $f->net > 0.00005)->map(fn ($f) => $this->sizeRow($f) + [
            'customer' => $this->book()->customerNames()[$f->customer_id] ?? '—', 'target_date' => $f->delivery_target_date, 'net_delivered' => $f->net,
            'pending_qty' => round($f->qty - $f->net, 4), 'pending_value' => round(($f->qty - $f->net) * $f->rate * $f->conv_rate, 2),
            'days' => (int) $today->diffInDays(CarbonImmutable::parse($f->delivery_target_date, 'Asia/Dhaka')->startOfDay(), false),
        ]);

        return [['so_no', 'customer', 'style', 'item', 'color', 'size', 'target_date:date', 'order_qty:qty', 'net_delivered:qty', 'pending_qty:qty', 'pending_value:money', 'days:num'], $rows->all()];
    }

    private function rShipmentPending(): array
    {
        $against = (bool) SsSettings::get()->ship_against_delivered;
        $rows = $this->facts('receive_date', true)->filter(fn ($f) => $f->qty - $f->shipped > 0.00005)->map(fn ($f) => $this->sizeRow($f) + [
            'customer' => $this->book()->customerNames()[$f->customer_id] ?? '—', 'shipment_date' => $f->shipment_date, 'planned' => $f->planned, 'shipped' => $f->shipped,
            'pending_qty' => round($f->qty - $f->shipped, 4), 'available' => round(max(0.0, $against ? $f->net - $f->shipped : $f->qty - $f->shipped), 4),
            'pending_value' => round(($f->qty - $f->shipped) * $f->rate * $f->conv_rate, 2),
        ]);

        return [['so_no', 'customer', 'style', 'item', 'color', 'size', 'shipment_date:date', 'order_qty:qty', 'planned:qty', 'shipped:qty', 'pending_qty:qty', 'available:qty', 'pending_value:money'], $rows->all()];
    }

    private function rBuyerWiseSales(): array
    {
        $names = $this->book()->customers();
        $rows = $this->facts()->groupBy('customer_id')->map(fn (Collection $g) => ['customer' => $names[$g->first()->customer_id] ?? '—'] + $this->sums($g))->sortBy('customer');

        return [['customer', 'orders:num', 'order_qty:qty', 'order_value:money', 'net_delivered:qty', 'delivered_value:money', 'shipped:qty', 'shipped_value:money', 'pending_qty:qty', 'pending_value:money'], $rows->all()];
    }

    private function rOrderWiseDelivery(): array
    {
        $facts = $this->facts();
        $challans = DB::table('ss_challans')->whereIn('order_id', $facts->pluck('order_id')->unique()->all())->whereIn('state', ['SUBMITTED', 'DELIVERED'])
            ->groupBy('order_id')->selectRaw('order_id, count(*) as n, max(chalan_date) as last')->get()->keyBy('order_id');
        $rows = $facts->groupBy('order_id')->map(function (Collection $g) use ($challans) {
            $f = $g->first();
            $ordered = $g->sum('qty');
            $net = $g->sum('net');

            return ['so_no' => $f->so_no, 'customer' => $this->book()->customerNames()[$f->customer_id] ?? '—', 'target_date' => $f->delivery_target_date, 'order_qty' => $ordered,
                'challans' => (int) ($challans[$f->order_id]->n ?? 0), 'delivered' => $g->sum('out'), 'returned' => $g->sum('returned'), 'net_delivered' => $net,
                'delivery_balance' => round(max(0.0, $ordered - $net), 4), 'last_challan' => $challans[$f->order_id]->last ?? null, 'status' => __('salesshipment.sr.status.delivery.'.$f->delivery_status)];
        });

        return [['so_no', 'customer', 'target_date:date', 'order_qty:qty', 'challans:num', 'delivered:qty', 'returned:qty', 'net_delivered:qty', 'delivery_balance:qty', 'last_challan:date', 'status'], $rows->all()];
    }

    private function rOrderWiseShipment(): array
    {
        $facts = $this->facts();
        $ships = DB::table('ss_shipments')->whereIn('order_id', $facts->pluck('order_id')->unique()->all())->where('stage', '!=', 'CANCELLED')
            ->groupBy('order_id')->selectRaw("order_id, count(*) as n, max(case when stage in ('CONFIRMED', 'CLOSED') then shipment_date end) as last")->get()->keyBy('order_id');
        $rows = $facts->groupBy('order_id')->map(function (Collection $g) use ($ships) {
            $f = $g->first();
            $ordered = $g->sum('qty');
            $shipped = $g->sum('shipped');

            return ['so_no' => $f->so_no, 'customer' => $this->book()->customerNames()[$f->customer_id] ?? '—', 'shipment_date' => $f->shipment_date, 'order_qty' => $ordered,
                'shipments' => (int) ($ships[$f->order_id]->n ?? 0), 'planned' => $g->sum('planned'), 'shipped' => $shipped, 'shipped_value' => round($g->sum('shipped_value'), 2),
                'shipment_balance' => round(max(0.0, $ordered - $shipped), 4), 'last_shipment' => $ships[$f->order_id]->last ?? null, 'status' => __('salesshipment.sr.status.ship.'.$f->ship_status)];
        });

        return [['so_no', 'customer', 'shipment_date:date', 'order_qty:qty', 'shipments:num', 'planned:qty', 'shipped:qty', 'shipped_value:money', 'shipment_balance:qty', 'last_shipment:date', 'status'], $rows->all()];
    }

    private function rStyleWiseShipment(): array
    {
        $rows = $this->facts()->groupBy(fn ($f) => $f->style_ref ?: '—')->map(fn (Collection $g, $style) => [
            'style' => $style, 'orders' => $g->pluck('order_id')->unique()->count(), 'order_qty' => $g->sum('qty'), 'planned' => $g->sum('planned'), 'shipped' => $g->sum('shipped'),
            'shipment_balance' => round(max(0.0, $g->sum('qty') - $g->sum('shipped')), 4), 'shipped_value' => round($g->sum('shipped_value'), 2),
        ])->sortBy('style');

        return [['style', 'orders:num', 'order_qty:qty', 'planned:qty', 'shipped:qty', 'shipment_balance:qty', 'shipped_value:money'], $rows->all()];
    }

    private function rMonthlySalesSummary(): array
    {
        $rows = $this->facts()->groupBy(fn ($f) => substr((string) $f->receive_date, 0, 7))->sortKeys()
            ->map(fn (Collection $g, $month) => ['month' => CarbonImmutable::parse($month.'-01')->format('M Y')] + $this->sums($g));

        return [['month', 'orders:num', 'order_qty:qty', 'order_value:money', 'net_delivered:qty', 'delivered_value:money', 'shipped:qty', 'shipped_value:money', 'pending_qty:qty', 'pending_value:money'], $rows->all()];
    }

    private function rSalesShipmentSummary(): array
    {
        $sections = $this->book()->sectionNames();
        $items = $this->book()->itemNames();
        $rows = $this->facts()->groupBy(fn ($f) => ($f->section_id ?? '').'|'.($f->item_id ?? ''))->map(function (Collection $g) use ($sections, $items) {
            $s = $this->sums($g);
            $f = $g->first();

            return ['section' => $sections[$f->section_id] ?? '—', 'particular' => $items[$f->item_id] ?? '—', 'orders' => $s['orders'], 'order_qty' => $s['order_qty'], 'order_value' => $s['order_value'],
                'delivered_qty' => $s['net_delivered'], 'delivered_value' => $s['delivered_value'], 'shipment_qty' => $s['shipped'], 'shipment_value' => $s['shipped_value'],
                'pending_qty' => $s['pending_qty'], 'pending_value' => $s['pending_value']];
        })->sortBy(fn ($r) => $r['section'].'|'.$r['particular']);

        return [['section', 'particular', 'orders:num', 'order_qty:qty', 'order_value:money', 'delivered_qty:qty', 'delivered_value:money', 'shipment_qty:qty', 'shipment_value:money', 'pending_qty:qty', 'pending_value:money'], $rows->all()];
    }

    /* ── Challan reports ───────────────────────────────────────────── */

    private function rChallanStatement(): array
    {
        $lines = $this->challanLines();
        $fig = $this->book()->lineFigures($lines->pluck('line_id')->all());
        $rows = $lines->groupBy('challan_id')->map(function (Collection $g) use ($fig) {
            $c = $g->first();
            $ret = $g->sum(fn ($l) => $fig[$l->line_id]['confirmed'] ?? 0);

            return ['chalan_no' => $c->chalan_no, 'chalan_date' => $c->chalan_date, 'gate_pass_no' => $c->gate_pass_no, 'customer' => $this->book()->customerNames()[$c->customer_id] ?? '—', 'so_no' => $c->so_no,
                'store' => $this->book()->stores()[$c->store_id] ?? '—', 'qty' => $g->sum('qty'), 'returned' => $ret, 'net_qty' => $g->sum('qty') - $ret,
                'value' => round($g->sum(fn ($l) => ($l->qty - ($fig[$l->line_id]['confirmed'] ?? 0)) * $l->rate * $l->conv_rate), 2), 'billed' => $g->sum(fn ($l) => $fig[$l->line_id]['billed'] ?? 0),
                'status' => __('salesshipment.sr.status.challan.'.$c->state)];
        });

        return [['chalan_no', 'chalan_date:date', 'gate_pass_no', 'customer', 'so_no', 'store', 'qty:qty', 'returned:qty', 'net_qty:qty', 'value:money', 'billed:qty', 'status'], $rows->all()];
    }

    private function rDeliveryChallanSummary(): array
    {
        $lines = $this->challanLines();
        $fig = $this->book()->lineFigures($lines->pluck('line_id')->all());
        $rows = $lines->groupBy(fn ($l) => $l->order_id.'|'.$l->item_id)->map(function (Collection $g) use ($fig) {
            $l = $g->first();
            $ret = $g->sum(fn ($x) => $fig[$x->line_id]['confirmed'] ?? 0);

            return ['so_no' => $l->so_no, 'style' => $l->style_ref, 'item' => $this->book()->items()[$l->item_id] ?? '—', 'challans' => $g->pluck('challan_id')->unique()->count(),
                'delivered' => $g->sum('qty'), 'returned' => $ret, 'net_delivered' => $g->sum('qty') - $ret,
                'value' => round($g->sum(fn ($x) => ($x->qty - ($fig[$x->line_id]['confirmed'] ?? 0)) * $x->rate * $x->conv_rate), 2)];
        })->sortBy(fn ($r) => $r['so_no'].'|'.$r['item']);

        return [['so_no', 'style', 'item', 'challans:num', 'delivered:qty', 'returned:qty', 'net_delivered:qty', 'value:money'], $rows->all()];
    }

    private function rDeliveryChallanReport(): array
    {
        $lines = $this->challanLines(true);
        $fig = $this->book()->lineFigures($lines->pluck('line_id')->all());
        $codes = $this->book()->currencyCodes();
        $rows = $lines->map(fn ($l) => [
            'chalan_no' => $l->chalan_no, 'chalan_date' => $l->chalan_date, 'store' => $this->book()->stores()[$l->store_id] ?? '—', 'customer' => $this->book()->customerNames()[$l->customer_id] ?? '—',
            'so_no' => $l->so_no, 'style' => $l->style_ref, 'item' => $this->book()->items()[$l->item_id] ?? '—', 'color' => $l->gmt_color, 'size' => $l->size, 'qty' => (float) $l->qty, 'rate' => (float) $l->rate,
            'currency' => $codes[$l->currency_id] ?? '', 'value' => round($l->qty * $l->rate * $l->conv_rate, 2), 'returned' => $fig[$l->line_id]['confirmed'] ?? 0, 'status' => __('salesshipment.sr.status.challan.'.$l->state),
        ]);

        return [['chalan_no', 'chalan_date:date', 'store', 'customer', 'so_no', 'style', 'item', 'color', 'size', 'qty:qty', 'rate:num', 'currency', 'value:money', 'returned:qty', 'status'], $rows->all()];
    }

    /* ── Sub-contract reports ──────────────────────────────────────── */

    private function rScBalanceSummary(): array
    {
        $c = $this->caller()->companyId;
        $progs = DB::table('ss_sc_programmes as p')->join('ss_sales_orders as o', 'o.id', '=', 'p.order_id')->where('p.company_id', $c)
            ->when($this->q['buyer'] ?? null, fn ($w, $v) => $w->where('p.party_id', $v))->when($this->q['from'] ?? null, fn ($w, $v) => $w->where('p.sc_date', '>=', $v))->when($this->q['to'] ?? null, fn ($w, $v) => $w->where('p.sc_date', '<=', $v))
            ->orderBy('p.sc_date')->orderBy('p.sc_no')->get(['p.id', 'p.sc_no', 'p.sc_date', 'p.target_date', 'p.process', 'p.party_id', 'o.so_no']);
        $plines = DB::table('ss_sc_programme_lines')->whereIn('programme_id', $progs->pluck('id'))->get(['id', 'programme_id', 'qty']);
        $fig = $this->scFigures($plines->pluck('id')->all());
        $rows = $progs->map(function ($p) use ($plines, $fig) {
            $mine = $plines->where('programme_id', $p->id);
            $sum = fn (string $k) => $mine->sum(fn ($l) => $fig[$l->id][$k] ?? 0);

            return ['sc_no' => $p->sc_no, 'sc_date' => $p->sc_date, 'so_no' => $p->so_no, 'party' => $this->book()->customerNames()[$p->party_id] ?? '—', 'process' => $p->process, 'target_date' => $p->target_date,
                'contract_qty' => (float) $mine->sum('qty'), 'delivered' => $sum('sent'), 'received' => $sum('received'), 'rejected' => $sum('rejected'), 'with_party' => $sum('out'),
                'to_deliver' => round(max(0.0, (float) $mine->sum('qty') - $sum('sent')), 4)];
        });

        return [['sc_no', 'sc_date:date', 'so_no', 'party', 'process', 'target_date:date', 'contract_qty:qty', 'delivered:qty', 'received:qty', 'rejected:qty', 'with_party:qty', 'to_deliver:qty'], $rows->all()];
    }

    private function rScDeliveryReport(): array
    {
        $lines = $this->scDeliveryLines();
        $fig = SubconBook::lines($lines->pluck('line_id')->all());
        $rows = $lines->map(fn ($l) => $this->scRow($l) + [
            'chalan_no' => $l->chalan_no, 'chalan_date' => $l->chalan_date, 'qty' => (float) $l->qty, 'received' => $fig[$l->line_id]['received'] ?? 0, 'with_party' => $fig[$l->line_id]['out'] ?? 0,
            'status' => __('salesshipment.sr.status.sc.'.($l->closed ? 'CLOSED' : $l->state)),
        ]);

        return [['chalan_no', 'chalan_date:date', 'sc_no', 'party', 'store', 'so_no', 'style', 'item', 'color', 'size', 'qty:qty', 'received:qty', 'with_party:qty', 'status'], $rows->all()];
    }

    private function rScReceiptReport(): array
    {
        $c = $this->caller()->companyId;
        [$sk, $sv] = $this->q['status'] ?? [null, null];
        $lines = DB::table('ss_sc_receipt_lines as rl')->join('ss_sc_receipts as r', 'r.id', '=', 'rl.receipt_id')->join('ss_sc_delivery_lines as dl', 'dl.id', '=', 'rl.delivery_line_id')
            ->join('ss_sc_deliveries as d', 'd.id', '=', 'r.delivery_id')->join('ss_sc_programme_lines as pl', 'pl.id', '=', 'dl.programme_line_id')->join('ss_sc_programmes as p', 'p.id', '=', 'pl.programme_id')
            ->join('ss_sales_order_sizes as s', 's.id', '=', 'pl.size_id')->join('ss_sales_order_lines as ol', 'ol.id', '=', 's.line_id')->join('ss_sales_orders as o', 'o.id', '=', 's.order_id')
            ->where('r.company_id', $c)->tap(fn ($w) => $this->orderFilters($w, 'o', 'ol'))->when($this->q['buyer'] ?? null, fn ($w, $v) => $w->where('r.customer_id', $v))
            ->when($this->q['store'] ?? null, fn ($w, $v) => $w->where('r.store_id', $v))->when($this->q['from'] ?? null, fn ($w, $v) => $w->where('r.mrr_date', '>=', $v))->when($this->q['to'] ?? null, fn ($w, $v) => $w->where('r.mrr_date', '<=', $v))
            ->when($sk === 'sc', fn ($w) => $w->where('r.state', $sv))
            ->orderBy('r.mrr_date')->orderBy('r.mrr_no')->orderBy('rl.sort_order')
            ->get(['r.mrr_no', 'r.mrr_date', 'r.receive_type', 'r.state', 'r.store_id', 'r.customer_id as party_id', 'd.chalan_no', 'p.sc_no', 'o.so_no', 'ol.style_ref', 'ol.item_id', 'ol.gmt_color', 's.size', 'rl.qty', 'rl.rejected_qty']);
        $rows = $lines->map(fn ($l) => $this->scRow($l) + [
            'mrr_no' => $l->mrr_no, 'mrr_date' => $l->mrr_date, 'chalan_no' => $l->chalan_no, 'receive_type' => __('salesshipment.sr.rtype.'.$l->receive_type),
            'received' => (float) $l->qty, 'rejected' => (float) $l->rejected_qty, 'status' => __('salesshipment.sr.status.sc.'.$l->state),
        ]);

        return [['mrr_no', 'mrr_date:date', 'chalan_no', 'sc_no', 'party', 'store', 'receive_type', 'so_no', 'style', 'item', 'color', 'size', 'received:qty', 'rejected:qty', 'status'], $rows->all()];
    }

    private function rScPendingReport(): array
    {
        $c = $this->caller()->companyId;
        $lines = DB::table('ss_sc_programme_lines as pl')->join('ss_sc_programmes as p', 'p.id', '=', 'pl.programme_id')->join('ss_sales_order_sizes as s', 's.id', '=', 'pl.size_id')
            ->join('ss_sales_order_lines as ol', 'ol.id', '=', 's.line_id')->join('ss_sales_orders as o', 'o.id', '=', 's.order_id')
            ->where('p.company_id', $c)->tap(fn ($w) => $this->orderFilters($w, 'o', 'ol'))->when($this->q['buyer'] ?? null, fn ($w, $v) => $w->where('p.party_id', $v))
            ->when($this->q['from'] ?? null, fn ($w, $v) => $w->where('p.sc_date', '>=', $v))->when($this->q['to'] ?? null, fn ($w, $v) => $w->where('p.sc_date', '<=', $v))
            ->orderBy('p.sc_no')->orderBy('s.sort_order')
            ->get(['pl.id', 'pl.qty', 'p.sc_no', 'p.party_id', 'p.target_date', 'o.so_no', 'ol.style_ref', 'ol.item_id', 'ol.gmt_color', 's.size']);
        $fig = $this->scFigures($lines->pluck('id')->all(), $this->q['store'] ?? null);
        $rows = $lines->map(function ($l) use ($fig) {
            $f = $fig[$l->id] ?? ['sent' => 0, 'received' => 0, 'rejected' => 0, 'out' => 0];

            return $this->scRow($l) + ['target_date' => $l->target_date, 'contract_qty' => (float) $l->qty, 'delivered' => $f['sent'], 'received' => $f['received'], 'with_party' => $f['out'],
                'to_deliver' => round(max(0.0, $l->qty - $f['sent']), 4)];
        })->filter(fn ($r) => $r['with_party'] > 0.00005 || $r['to_deliver'] > 0.00005);

        return [['sc_no', 'party', 'so_no', 'style', 'item', 'color', 'size', 'target_date:date', 'contract_qty:qty', 'delivered:qty', 'received:qty', 'with_party:qty', 'to_deliver:qty'], $rows->all()];
    }

    /* ── Shipment report ───────────────────────────────────────────── */

    private function rShipmentStatus(): array
    {
        $c = $this->caller()->companyId;
        [$sk, $sv] = $this->q['status'] ?? [null, null];
        $lines = DB::table('ss_shipment_lines as sl')->join('ss_shipments as sh', 'sh.id', '=', 'sl.shipment_id')->join('ss_sales_order_sizes as s', 's.id', '=', 'sl.size_id')
            ->join('ss_sales_order_lines as ol', 'ol.id', '=', 's.line_id')->join('ss_sales_orders as o', 'o.id', '=', 'sh.order_id')
            ->where('sh.company_id', $c)->tap(fn ($w) => $this->orderFilters($w, 'o', 'ol'))->when($this->q['buyer'] ?? null, fn ($w, $v) => $w->where('o.customer_id', $v))
            ->when($this->q['from'] ?? null, fn ($w, $v) => $w->where('sh.plan_date', '>=', $v))->when($this->q['to'] ?? null, fn ($w, $v) => $w->where('sh.plan_date', '<=', $v))
            ->when($sk === 'stage', fn ($w) => $w->where('sh.stage', $sv))
            ->get(['sh.id', 'sh.shipment_no', 'sh.plan_date', 'sh.shipment_date', 'sh.ex_factory_date', 'sh.shipment_mode', 'sh.destination_port', 'sh.stage', 'o.so_no', 'o.customer_id', 'o.conv_rate', 'ol.style_ref', 'sl.id as line_id', 'sl.qty', 'sl.rate', 'sl.shipped_qty']);
        $invoiced = DB::table('ss_invoice_lines as il')->join('ss_invoices as i', 'i.id', '=', 'il.invoice_id')->whereIn('il.shipment_line_id', $lines->pluck('line_id'))->where('i.state', 'FINAL')
            ->groupBy('il.shipment_line_id')->selectRaw('il.shipment_line_id, sum(il.qty) as q')->pluck('q', 'shipment_line_id');
        $bookings = DB::table('ss_bookings')->whereIn('shipment_id', $lines->pluck('id')->unique())->where('status', '!=', 'CANCELLED')->orderByDesc('booking_date')->get(['shipment_id', 'booking_no', 'vessel_name'])->groupBy('shipment_id');
        $rows = $lines->groupBy('id')->map(function (Collection $g) use ($invoiced, $bookings) {
            $s = $g->first();
            $bk = $bookings->get($s->id)?->first();

            return ['shipment_no' => $s->shipment_no, 'plan_date' => $s->plan_date, 'so_no' => $s->so_no, 'customer' => $this->book()->customerNames()[$s->customer_id] ?? '—',
                'style' => $g->pluck('style_ref')->filter()->unique()->implode(', '), 'shipment_date' => $s->shipment_date, 'ex_factory_date' => $s->ex_factory_date, 'mode' => $s->shipment_mode,
                'destination' => $s->destination_port, 'planned' => (float) $g->sum('qty'), 'invoiced' => (float) $g->sum(fn ($l) => $invoiced[$l->line_id] ?? 0),
                'shipped' => in_array($s->stage, ['CONFIRMED', 'CLOSED'], true) ? (float) $g->sum('shipped_qty') : 0.0, 'value' => round($g->sum(fn ($l) => $l->qty * $l->rate * $l->conv_rate), 2),
                'booking_no' => $bk?->booking_no, 'vessel' => $bk?->vessel_name, 'stage' => __('salesshipment.sr.status.stage.'.$s->stage)];
        })->sortBy('shipment_no');

        return [['shipment_no', 'plan_date:date', 'so_no', 'customer', 'style', 'shipment_date:date', 'ex_factory_date:date', 'mode', 'destination', 'planned:qty', 'invoiced:qty', 'shipped:qty', 'value:money', 'booking_no', 'vessel', 'stage'], $rows->all()];
    }

    /* ── The books ─────────────────────────────────────────────────── */

    /**
     * One row per order size inside the boxes: the order's own fields, the
     * line's, the size's, and its figures — out, returned and net delivered,
     * planned and shipped, the BDT values and the pending balance.
     */
    private function facts(string $dateColumn = 'receive_date', bool $openOnly = false): Collection
    {
        $c = $this->caller()->companyId;
        $rows = DB::table('ss_sales_order_sizes as s')->join('ss_sales_order_lines as ol', 'ol.id', '=', 's.line_id')->join('ss_sales_orders as o', 'o.id', '=', 's.order_id')
            ->where('o.company_id', $c)->tap(fn ($w) => $this->orderFilters($w, 'o', 'ol'))->when($this->q['buyer'] ?? null, fn ($w, $v) => $w->where('o.customer_id', $v))
            ->when($this->q['from'] ?? null, fn ($w, $v) => $w->where("o.{$dateColumn}", '>=', $v))->when($this->q['to'] ?? null, fn ($w, $v) => $w->where("o.{$dateColumn}", '<=', $v))
            ->when($openOnly && ($this->q['status'][0] ?? null) !== 'order', fn ($w) => $w->where('o.state', 'OPEN'))
            ->orderByDesc('o.receive_date')->orderBy('o.so_no')->orderBy('ol.sl_no')->orderBy('s.sort_order')
            ->get(['s.id as size_id', 's.size', 's.qty', 's.rate', 'ol.id as line_id', 'ol.buyer', 'ol.style_ref', 'ol.item_id', 'ol.gmt_color', 'ol.uom', 'ol.section_id',
                'o.id as order_id', 'o.so_no', 'o.receive_date', 'o.customer_id', 'o.conv_rate', 'o.currency_id', 'o.approval_status', 'o.state', 'o.delivery_target_date', 'o.shipment_date']);
        if ($rows->isEmpty()) {
            return $rows;
        }
        $fig = $this->book()->sizeFigures($rows->pluck('order_id')->unique()->values()->all());
        $sizeIds = $rows->pluck('size_id')->all();
        $out = $this->challanTotals($sizeIds);
        $ship = DB::table('ss_shipment_lines as sl')->join('ss_shipments as sh', 'sh.id', '=', 'sl.shipment_id')->whereIn('sl.size_id', $sizeIds)->where('sh.stage', '!=', 'CANCELLED')
            ->groupBy('sl.size_id')->selectRaw("sl.size_id, sum(sl.qty) as planned, sum(case when sh.stage in ('CONFIRMED', 'CLOSED') then sl.shipped_qty else 0 end) as shipped,
                sum(case when sh.stage in ('CONFIRMED', 'CLOSED') then sl.shipped_qty * sl.rate else 0 end) as shipped_raw")->get()->keyBy('size_id');
        $codes = $this->book()->currencyCodes();
        $rows->each(function ($f) use ($fig, $out, $ship, $codes) {
            foreach (['qty', 'rate', 'conv_rate'] as $k) {
                $f->{$k} = (float) $f->{$k};
            }
            $f->conv_rate = $f->conv_rate ?: 1.0;
            $f->out = $fig[$f->size_id]['out'] ?? 0.0;
            $f->returned = $fig[$f->size_id]['returned'] ?? 0.0;
            $f->net = $fig[$f->size_id]['net'] ?? 0.0;
            $f->planned = (float) ($ship[$f->size_id]->planned ?? 0);
            $f->shipped = (float) ($ship[$f->size_id]->shipped ?? 0);
            $f->pending = round(max(0.0, $f->qty - max($f->net, $f->shipped)), 4);
            $f->order_value = $f->qty * $f->rate * $f->conv_rate;
            $f->delivered_value = (float) (($out[$f->size_id]->val ?? 0) - ($out[$f->size_id]->ret_val ?? 0));
            $f->shipped_value = (float) ($ship[$f->size_id]->shipped_raw ?? 0) * $f->conv_rate;
            $f->pending_value = $f->pending * $f->rate * $f->conv_rate;
            $f->currency = $codes[$f->currency_id] ?? '';
        });
        // Order-level statuses, read off the order's sizes together.
        $rows->groupBy('order_id')->each(function (Collection $g) {
            $ordered = $g->sum('qty');
            $net = $g->sum('net');
            $shipped = $g->sum('shipped');
            $delivery = $net <= 0 ? 'WAITING' : ($net + 0.00005 < $ordered ? 'PARTIAL' : 'DONE');
            $shipStatus = $shipped <= 0 ? ($g->sum('planned') > 0 ? 'PLANNED' : 'NOT_PLANNED') : ($shipped + 0.00005 < $ordered ? 'PARTIAL' : 'SHIPPED');
            $g->each(function ($f) use ($delivery, $shipStatus) {
                $f->delivery_status = $delivery;
                $f->ship_status = $shipStatus;
            });
        });
        if ($status = $this->q['status'] ?? null) {
            [$sk, $sv] = $status;
            $rows = $rows->filter(fn ($f) => match ($sk) {
                'approval' => $f->approval_status === $sv,
                'order' => $f->state === $sv,
                'delivery' => $f->delivery_status === $sv,
                'ship' => $f->ship_status === $sv,
                default => true,
            })->values();
        }

        return $rows;
    }

    /** Style, order and item boxes on a query that joins the order (`o`) and its line (`ol`). */
    private function orderFilters($query, string $o, string $ol): void
    {
        $query->when($this->q['order'] ?? null, fn ($w, $v) => $w->where("{$o}.so_no", $v))
            ->when($this->q['style'] ?? null, fn ($w, $v) => $w->where("{$ol}.style_ref", $v))
            ->when($this->q['item'] ?? null, fn ($w, $v) => $w->where("{$ol}.item_id", $v));
    }

    /** Per order size: through the gate (qty, BDT value) and come back on confirmed returns — from one store, or all. */
    private function challanTotals(array $sizeIds, ?string $store = null): Collection
    {
        if (! $sizeIds) {
            return collect();
        }
        $out = DB::table('ss_challan_lines as l')->join('ss_challans as c', 'c.id', '=', 'l.challan_id')->whereIn('l.size_id', $sizeIds)->whereIn('c.state', ['SUBMITTED', 'DELIVERED'])
            ->when($store, fn ($w, $v) => $w->where('c.store_id', $v))->groupBy('l.size_id')->selectRaw('l.size_id, sum(l.qty) as qty, sum(l.qty * l.rate * c.conv_rate) as val')->get()->keyBy('size_id');
        $ret = DB::table('ss_challan_return_lines as rl')->join('ss_challan_returns as r', 'r.id', '=', 'rl.return_id')->join('ss_challan_lines as l', 'l.id', '=', 'rl.challan_line_id')
            ->join('ss_challans as c', 'c.id', '=', 'l.challan_id')->whereIn('l.size_id', $sizeIds)->where('r.state', 'CONFIRMED')->when($store, fn ($w, $v) => $w->where('c.store_id', $v))
            ->groupBy('l.size_id')->selectRaw('l.size_id, sum(rl.qty) as qty, sum(rl.qty * l.rate * c.conv_rate) as val')->get()->keyBy('size_id');

        return collect($sizeIds)->mapWithKeys(fn ($id) => [$id => (object) [
            'qty' => (float) ($out[$id]->qty ?? 0), 'val' => (float) ($out[$id]->val ?? 0), 'ret' => (float) ($ret[$id]->qty ?? 0), 'ret_val' => (float) ($ret[$id]->val ?? 0),
        ]]);
    }

    /** Challan lines inside the boxes (challan date for the window); drafts and cancelled ones only when the status names them. */
    private function challanLines(bool $withStatus = false): Collection
    {
        $c = $this->caller()->companyId;
        [$sk, $sv] = $this->q['status'] ?? [null, null];

        return DB::table('ss_challan_lines as l')->join('ss_challans as c', 'c.id', '=', 'l.challan_id')->join('ss_sales_order_sizes as s', 's.id', '=', 'l.size_id')
            ->join('ss_sales_order_lines as ol', 'ol.id', '=', 's.line_id')->join('ss_sales_orders as o', 'o.id', '=', 'c.order_id')
            ->where('c.company_id', $c)->tap(fn ($w) => $this->orderFilters($w, 'o', 'ol'))->when($this->q['buyer'] ?? null, fn ($w, $v) => $w->where('c.customer_id', $v))
            ->when($this->q['store'] ?? null, fn ($w, $v) => $w->where('c.store_id', $v))
            ->when($this->q['from'] ?? null, fn ($w, $v) => $w->where('c.chalan_date', '>=', $v))->when($this->q['to'] ?? null, fn ($w, $v) => $w->where('c.chalan_date', '<=', $v))
            ->when($withStatus && $sk === 'challan', fn ($w) => $w->where('c.state', $sv), fn ($w) => $w->whereIn('c.state', ['SUBMITTED', 'DELIVERED']))
            ->orderBy('c.chalan_date')->orderBy('c.chalan_no')->orderBy('l.sort_order')
            ->get(['l.id as line_id', 'l.qty', 'l.rate', 'c.id as challan_id', 'c.chalan_no', 'c.gate_pass_no', 'c.chalan_date', 'c.customer_id', 'c.store_id', 'c.currency_id', 'c.conv_rate', 'c.state',
                'o.id as order_id', 'o.so_no', 'ol.style_ref', 'ol.item_id', 'ol.gmt_color', 's.size'])
            ->each(function ($l) {
                $l->qty = (float) $l->qty;
                $l->rate = (float) $l->rate;
                $l->conv_rate = (float) $l->conv_rate ?: 1.0;
            });
    }

    /** S/C delivery lines inside the boxes (chalan date for the window); the status narrows by state, or by reconciled. */
    private function scDeliveryLines(): Collection
    {
        $c = $this->caller()->companyId;
        [$sk, $sv] = $this->q['status'] ?? [null, null];

        return DB::table('ss_sc_delivery_lines as dl')->join('ss_sc_deliveries as d', 'd.id', '=', 'dl.delivery_id')->join('ss_sc_programme_lines as pl', 'pl.id', '=', 'dl.programme_line_id')
            ->join('ss_sc_programmes as p', 'p.id', '=', 'pl.programme_id')->join('ss_sales_order_sizes as s', 's.id', '=', 'pl.size_id')
            ->join('ss_sales_order_lines as ol', 'ol.id', '=', 's.line_id')->join('ss_sales_orders as o', 'o.id', '=', 's.order_id')
            ->where('d.company_id', $c)->tap(fn ($w) => $this->orderFilters($w, 'o', 'ol'))->when($this->q['buyer'] ?? null, fn ($w, $v) => $w->where('d.customer_id', $v))
            ->when($this->q['store'] ?? null, fn ($w, $v) => $w->where('d.store_id', $v))
            ->when($this->q['from'] ?? null, fn ($w, $v) => $w->where('d.chalan_date', '>=', $v))->when($this->q['to'] ?? null, fn ($w, $v) => $w->where('d.chalan_date', '<=', $v))
            ->when($sk === 'sc', fn ($w) => match ($sv) {
                'CLOSED' => $w->where('d.closed', true),
                'RECEIVED' => $w->whereExists(fn ($e) => $e->from('ss_sc_receipts as r')->whereColumn('r.delivery_id', 'd.id')->where('r.state', 'RECEIVED')),
                default => $w->where('d.state', $sv)->where('d.closed', false),
            })
            ->orderBy('d.chalan_date')->orderBy('d.chalan_no')->orderBy('dl.sort_order')
            ->get(['dl.id as line_id', 'dl.qty', 'd.chalan_no', 'd.chalan_date', 'd.state', 'd.closed', 'd.store_id', 'd.customer_id as party_id', 'p.sc_no', 'o.so_no', 'ol.style_ref', 'ol.item_id', 'ol.gmt_color', 's.size']);
    }

    /**
     * Per programme line: sent, received, rejected and still with the party,
     * over its S/C deliveries not cancelled — from one store, or all.
     *
     * @return array<string, array{sent: float, received: float, rejected: float, out: float}>
     */
    private function scFigures(array $programmeLineIds, ?string $store = null): array
    {
        $dl = DB::table('ss_sc_delivery_lines as dl')->join('ss_sc_deliveries as d', 'd.id', '=', 'dl.delivery_id')->whereIn('dl.programme_line_id', $programmeLineIds)->where('d.state', '!=', 'CANCELLED')
            ->when($store, fn ($w, $v) => $w->where('d.store_id', $v))->get(['dl.id', 'dl.programme_line_id']);
        $lines = SubconBook::lines($dl->pluck('id')->all());
        $res = [];
        foreach ($dl as $d) {
            $f = $lines[$d->id] ?? null;
            if (! $f) {
                continue;
            }
            $r = $res[$d->programme_line_id] ?? ['sent' => 0.0, 'received' => 0.0, 'rejected' => 0.0, 'out' => 0.0];
            foreach (['sent', 'received', 'rejected', 'out'] as $k) {
                $r[$k] += $f[$k];
            }
            $res[$d->programme_line_id] = $r;
        }

        return $res;
    }

    /* ── Rows ──────────────────────────────────────────────────────── */

    private function orderRow(Collection $g): array
    {
        $f = $g->first();

        return ['so_no' => $f->so_no, 'so_date' => $f->receive_date, 'customer' => $this->book()->customerNames()[$f->customer_id] ?? '—', 'currency' => $f->currency,
            'order_qty' => $g->sum('qty'), 'order_value' => round($g->sum('order_value'), 2), 'net_delivered' => $g->sum('net'), 'shipped' => $g->sum('shipped'),
            'pending_qty' => round($g->sum('pending'), 4), 'pending_value' => round($g->sum('pending_value'), 2), 'status' => $this->statusLabel($f)];
    }

    private function sizeRow(object $f): array
    {
        return ['so_no' => $f->so_no, 'style' => $f->style_ref, 'item' => $this->book()->items()[$f->item_id] ?? '—', 'color' => $f->gmt_color, 'size' => $f->size, 'order_qty' => $f->qty];
    }

    private function scRow(object $l): array
    {
        return ['sc_no' => $l->sc_no, 'party' => $this->book()->customerNames()[$l->party_id] ?? '—', 'store' => isset($l->store_id) ? ($this->book()->stores()[$l->store_id] ?? '—') : null,
            'so_no' => $l->so_no, 'style' => $l->style_ref, 'item' => $this->book()->items()[$l->item_id] ?? '—', 'color' => $l->gmt_color, 'size' => $l->size];
    }

    /** @return array<string, float|int> */
    private function sums(Collection $g): array
    {
        return ['orders' => $g->pluck('order_id')->unique()->count(), 'order_qty' => $g->sum('qty'), 'order_value' => round($g->sum('order_value'), 2), 'net_delivered' => $g->sum('net'),
            'delivered_value' => round($g->sum('delivered_value'), 2), 'shipped' => $g->sum('shipped'), 'shipped_value' => round($g->sum('shipped_value'), 2),
            'pending_qty' => round($g->sum('pending'), 4), 'pending_value' => round($g->sum('pending_value'), 2)];
    }

    private function statusLabel(object $f): string
    {
        return $f->state === 'CLOSED' ? __('salesshipment.sr.status.order.CLOSED') : __('salesshipment.sr.status.approval.'.$f->approval_status);
    }

    private function book(): SalesBook
    {
        return $this->book ??= new SalesBook($this->caller()->companyId);
    }
}

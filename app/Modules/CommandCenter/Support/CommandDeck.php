<?php

declare(strict_types=1);

namespace App\Modules\CommandCenter\Support;

use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Chief Executive Command Deck (AnnotechERP 27/01; Brickland's "CEO Command
 * Center"): factory-wide performance read from the modules' own records —
 *
 *  - Revenue MTD: commercial invoices dated this month (Trade & Export), in
 *    taka, against the same days of last month;
 *  - Order Book: confirmed orders still to ship (Merchandise), in taka;
 *  - Production Efficiency: sewing output × SMV over the minutes available
 *    this month (Planning's daily plans), against the planned efficiency;
 *  - On-Time Delivery: shipments of the last 90 days that left on or before
 *    their order's ship date (Sales & Shipment).
 *
 * The four panels: revenue, cost (posted expense vouchers) and shipped value
 * over twelve months; the order book by buyer for the next six months;
 * output against plan by floor this week; line efficiency against target
 * this month. Then recent activity (the audit trail's changes) and the top
 * alerts (open order alerts, approvals waiting). Money is converted to taka
 * at the document's own rate, else the currency master's; an amount with no
 * rate at all is left out and counted, never guessed.
 */
final class CommandDeck
{
    /** The audit actions that are work done, not sign-ins or system housekeeping. */
    private const ACTIVITY = ['CREATED', 'UPDATED', 'DELETED', 'STATUS_CHANGED', 'COST_CHANGED'];

    private CarbonImmutable $today;

    /** @var array<string, float> currency code => taka */
    private array $rates;

    private int $unconverted = 0;

    private string $company;

    public function build(): array
    {
        $this->company = app(TenantContext::class)->companyId();
        $this->today = CarbonImmutable::now('Asia/Dhaka')->startOfDay();
        $this->rates = DB::table('currencies')->where('company_id', $this->company)->whereNotNull('exchange_rate')->pluck('exchange_rate', 'code')->map(fn ($r) => (float) $r)->all();
        $this->rates['BDT'] ??= 1.0;

        $out = [
            'kpis' => ['revenue' => $this->revenueKpi(), 'order_book' => $this->orderBookKpi(), 'efficiency' => $this->efficiencyKpi(), 'on_time' => $this->onTimeKpi()],
            'trend' => $this->trend(),
            'buyers' => $this->buyers(),
            'output' => $this->output(),
            'lines' => $this->lines(),
            'activity' => $this->activity(),
            'alerts' => $this->alerts(),
        ];
        $out['unconverted'] = $this->unconverted;

        return $out;
    }

    /** A document's amount in taka: its own rate, else the currency master's; null (and counted) when neither is known. */
    private function taka(float $amount, ?string $currency, ?float $rate): ?float
    {
        $rate = $rate ?: ($this->rates[strtoupper((string) ($currency ?: 'BDT'))] ?? null);
        if ($rate === null) {
            $this->unconverted++;

            return null;
        }

        return $amount * $rate;
    }

    private function invoices(CarbonImmutable $from, CarbonImmutable $to): float
    {
        return (float) DB::table('tx_commercial_invoices')->where('company_id', $this->company)->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])
            ->get(['net_amount', 'invoiced_amount', 'currency', 'loc_exchange_rate'])
            ->sum(fn ($i) => $this->taka((float) ($i->net_amount ?? $i->invoiced_amount ?? 0), $i->currency, $i->loc_exchange_rate !== null ? (float) $i->loc_exchange_rate : null) ?? 0);
    }

    private function revenueKpi(): array
    {
        $start = $this->today->startOfMonth();
        $now = $this->invoices($start, $this->today);
        // The same stretch of last month: the 1st to the same day (or its last day).
        $prevStart = $start->subMonthNoOverflow();
        $prevEnd = $prevStart->addDays(min($this->today->day, $prevStart->daysInMonth) - 1);
        $before = $this->invoices($prevStart, $prevEnd);
        $count = DB::table('tx_commercial_invoices')->where('company_id', $this->company)->whereBetween('invoice_date', [$start->toDateString(), $this->today->toDateString()])->count();

        return ['value' => $count ? round($now, 2) : null, 'previous' => round($before, 2), 'change' => $before > 0 ? round(($now - $before) / $before * 100, 1) : null, 'count' => $count];
    }

    /** Confirmed orders not yet shipped, closed or cancelled, from today on. */
    private function openOrders(?CarbonImmutable $until = null)
    {
        return DB::table('merch_orders')->where('company_id', $this->company)->whereIn('status', ['CONFIRMED', 'IN_PRODUCTION'])->where('projection_only', false)
            ->where('ship_date', '>=', $this->today->toDateString())->when($until, fn ($q) => $q->where('ship_date', '<=', $until->toDateString()))
            ->get(['buyer_id', 'total_qty', 'unit_price', 'currency', 'exchange_rate']);
    }

    private function orderValue(object $o): ?float
    {
        return $this->taka((float) $o->total_qty * (float) ($o->unit_price ?? 0), $o->currency, $o->exchange_rate !== null ? (float) $o->exchange_rate : null);
    }

    private function orderBookKpi(): array
    {
        $orders = $this->openOrders();

        return ['value' => $orders->isEmpty() ? null : round($orders->sum(fn ($o) => $this->orderValue($o) ?? 0), 2), 'count' => $orders->count()];
    }

    /** Planning's daily plans in a window, with the line and floor they belong to. */
    private function plans(CarbonImmutable $from, CarbonImmutable $to)
    {
        return DB::table('planning_daily_plans as p')->join('planning_lines as l', 'l.id', '=', 'p.planning_line_id')->leftJoin('floors as f', 'f.id', '=', 'l.floor_id')
            ->where('p.company_id', $this->company)->whereBetween('p.plan_date', [$from->toDateString(), $to->toDateString()])
            ->get(['l.id as line_id', 'l.name as line', 'f.name as floor', 'p.manpower', 'p.hours', 'p.smv', 'p.efficiency', 'p.daily_target', 'p.typed_output']);
    }

    /**
     * Achieved and planned efficiency of a set of plan days: produced minutes
     * (output × SMV) over available minutes (manpower × hours × 60); the plan's
     * efficiency weighted by the same minutes. Only days with output count.
     *
     * @return array{achieved: ?float, target: ?float}
     */
    private static function efficiency($plans): array
    {
        $worked = $plans->filter(fn ($p) => $p->typed_output !== null && (float) $p->manpower * (float) $p->hours > 0);
        $available = $worked->sum(fn ($p) => (float) $p->manpower * (float) $p->hours * 60);
        if ($available <= 0) {
            return ['achieved' => null, 'target' => null];
        }
        $produced = $worked->sum(fn ($p) => (float) $p->typed_output * (float) $p->smv);
        $target = $worked->sum(fn ($p) => (float) $p->efficiency * (float) $p->manpower * (float) $p->hours * 60);

        return ['achieved' => round($produced / $available * 100, 1), 'target' => round($target / $available, 1)];
    }

    private function efficiencyKpi(): array
    {
        $e = self::efficiency($this->plans($this->today->startOfMonth(), $this->today));

        return ['value' => $e['achieved'], 'target' => $e['target']];
    }

    private function onTimeKpi(): array
    {
        $shipped = DB::table('ss_shipments as s')->join('merch_orders as o', 'o.id', '=', 's.order_id')->where('s.company_id', $this->company)->whereNull('s.cancelled_at')
            ->whereNotNull('s.shipment_date')->whereBetween('s.shipment_date', [$this->today->subDays(89)->toDateString(), $this->today->toDateString()])
            ->get(['s.shipment_date', 'o.ship_date']);
        $onTime = $shipped->filter(fn ($s) => $s->ship_date === null || $s->shipment_date <= $s->ship_date)->count();

        return ['value' => $shipped->isEmpty() ? null : round($onTime / $shipped->count() * 100, 1), 'shipped' => $shipped->count(), 'on_time' => $onTime];
    }

    /** Twelve months to this one: revenue, cost and shipped value, in taka. */
    private function trend(): array
    {
        $from = $this->today->startOfMonth()->subMonthsNoOverflow(11);
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $from->addMonthsNoOverflow($i);
            $months[$m->format('Y-m')] = ['month' => $m->format('Y-m'), 'revenue' => 0.0, 'cost' => 0.0, 'shipment' => 0.0];
        }
        $end = $this->today->endOfMonth()->toDateString();
        foreach (DB::table('tx_commercial_invoices')->where('company_id', $this->company)->whereBetween('invoice_date', [$from->toDateString(), $end])->get(['invoice_date', 'net_amount', 'invoiced_amount', 'currency', 'loc_exchange_rate']) as $i) {
            $months[substr((string) $i->invoice_date, 0, 7)]['revenue'] += $this->taka((float) ($i->net_amount ?? $i->invoiced_amount ?? 0), $i->currency, $i->loc_exchange_rate !== null ? (float) $i->loc_exchange_rate : null) ?? 0;
        }
        // Cost: what posted vouchers put on expense accounts (debit less credit).
        $cost = DB::table('acc_voucher_lines as l')->join('acc_vouchers as v', 'v.id', '=', 'l.voucher_id')->join('acc_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('v.company_id', $this->company)->where('v.posting_status', 'POSTED')->where('a.type', 'EXPENSE')->whereBetween('v.voucher_date', [$from->toDateString(), $end])
            ->get(['v.voucher_date', 'l.debit', 'l.credit']);
        foreach ($cost as $c) {
            $months[substr((string) $c->voucher_date, 0, 7)]['cost'] += (float) $c->debit - (float) $c->credit;
        }
        // Shipped value: shipped quantity at the line's rate, in the order's currency.
        $shipped = DB::table('ss_shipment_lines as l')->join('ss_shipments as s', 's.id', '=', 'l.shipment_id')->join('merch_orders as o', 'o.id', '=', 's.order_id')
            ->where('s.company_id', $this->company)->whereNull('s.cancelled_at')->whereNotNull('s.shipment_date')->whereBetween('s.shipment_date', [$from->toDateString(), $end])
            ->get(['s.shipment_date', 'l.shipped_qty', 'l.qty', 'l.rate', 'o.unit_price', 'o.currency', 'o.exchange_rate']);
        foreach ($shipped as $s) {
            $value = (float) ($s->shipped_qty ?? $s->qty ?? 0) * (float) ($s->rate ?? $s->unit_price ?? 0);
            $months[substr((string) $s->shipment_date, 0, 7)]['shipment'] += $this->taka($value, $s->currency, $s->exchange_rate !== null ? (float) $s->exchange_rate : null) ?? 0;
        }

        return array_values(array_map(fn ($m) => ['month' => $m['month'], 'revenue' => round($m['revenue'], 2), 'cost' => round($m['cost'], 2), 'shipment' => round($m['shipment'], 2)], $months));
    }

    /** Confirmed value to ship in the next six months, by buyer; the eighth buyer on folded into Other. */
    private function buyers(): array
    {
        $names = DB::table('buyers')->where('company_id', $this->company)->get(['id', 'name', 'short_name'])->mapWithKeys(fn ($b) => [$b->id => $b->short_name ?: $b->name])->all();
        $by = [];
        foreach ($this->openOrders($this->today->addMonthsNoOverflow(6)) as $o) {
            $by[$o->buyer_id ?? ''] = ($by[$o->buyer_id ?? ''] ?? 0) + ($this->orderValue($o) ?? 0);
        }
        arsort($by);
        $rows = [];
        $other = 0.0;
        foreach ($by as $id => $value) {
            if (count($rows) < 7) {
                $rows[] = ['buyer' => $names[$id] ?? '—', 'value' => round($value, 2)];
            } else {
                $other += $value;
            }
        }
        if ($other > 0) {
            $rows[] = ['buyer' => null, 'value' => round($other, 2), 'other' => true];
        }

        return $rows;
    }

    /** This week (from Saturday, as the factory's week runs): sewing output against the daily targets, by floor. */
    private function output(): array
    {
        $start = $this->today->subDays(($this->today->dayOfWeek + 1) % 7);
        $by = [];
        foreach ($this->plans($start, $this->today) as $p) {
            $key = $p->floor ?? '—';
            $by[$key] ??= ['floor' => $key, 'plan' => 0, 'actual' => 0];
            $by[$key]['plan'] += (int) $p->daily_target;
            $by[$key]['actual'] += (int) ($p->typed_output ?? 0);
        }
        ksort($by);

        return ['from' => $start->toDateString(), 'rows' => array_values($by)];
    }

    /** This month, by sewing line: efficiency achieved against the plan's. */
    private function lines(): array
    {
        $rows = [];
        foreach ($this->plans($this->today->startOfMonth(), $this->today)->groupBy('line_id') as $plans) {
            $e = self::efficiency($plans);
            if ($e['achieved'] !== null) {
                $rows[] = ['line' => $plans->first()->line, 'achieved' => $e['achieved'], 'target' => $e['target']];
            }
        }
        usort($rows, fn ($a, $b) => strcmp((string) $a['line'], (string) $b['line']));

        return $rows;
    }

    /** The last changes people made anywhere — who did what to which record (the scheduler's own housekeeping is left out). */
    private function activity(): array
    {
        return DB::table('audit_logs as a')->join('users as u', 'u.id', '=', 'a.user_id')->where('a.company_id', $this->company)->whereIn('a.action', self::ACTIVITY)
            ->orderByDesc('a.created_at')->orderByDesc('a.id')->limit(8)
            ->get(['a.action', 'a.entity_type', 'a.entity_label', 'a.created_at', 'u.name as user'])
            ->map(fn ($a) => ['action' => $a->action, 'entity' => $a->entity_type, 'label' => $a->entity_label, 'user' => $a->user, 'at' => CarbonImmutable::parse($a->created_at)->toIso8601String()])->all();
    }

    /** What needs a decision: open order alerts (high first) and approvals waiting (oldest first). */
    private function alerts(): array
    {
        $rank = ['HIGH' => 0, 'MEDIUM' => 1, 'LOW' => 2];
        $orders = DB::table('merch_ai_alerts as a')->leftJoin('merch_orders as o', 'o.id', '=', 'a.order_id')->where('a.company_id', $this->company)->whereIn('a.status', ['OPEN', 'ACKNOWLEDGED'])
            ->get(['a.severity', 'a.title', 'a.detail', 'a.created_at', 'o.order_no'])
            ->sortBy(fn ($a) => [$rank[$a->severity] ?? 3, -strtotime((string) $a->created_at)])->take(5)
            ->map(fn ($a) => ['kind' => 'ORDER_ALERT', 'severity' => $a->severity, 'title' => $a->title, 'detail' => $a->order_no, 'at' => CarbonImmutable::parse($a->created_at)->toIso8601String()])->values();
        $approvals = DB::table('approval_requests')->where('company_id', $this->company)->where('status', 'PENDING')->orderBy('requested_at')->limit(5)
            ->get(['entity_type', 'requested_at', 'due_at'])
            ->map(fn ($r) => ['kind' => 'APPROVAL', 'severity' => $r->due_at && $r->due_at < now() ? 'HIGH' : 'MEDIUM', 'title' => $r->entity_type, 'detail' => null, 'at' => CarbonImmutable::parse($r->requested_at)->toIso8601String()]);

        return $orders->concat($approvals)->sortBy(fn ($a) => $rank[$a['severity']] ?? 3)->take(6)->values()->all();
    }
}

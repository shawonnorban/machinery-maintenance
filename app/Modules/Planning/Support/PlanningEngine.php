<?php

declare(strict_types=1);

namespace App\Modules\Planning\Support;

use App\Modules\HrPayroll\Services\WorkingTimeService;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\StyleItem;
use App\Modules\Planning\Models\PlanningDailyPlan;
use App\Modules\Planning\Models\PlanningLeadTime;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Planning\Models\PlanningMasterPlan;
use App\Modules\Planning\Models\PlanningMasterPlanDay;
use App\Modules\Planning\Models\PlanningPlanEvent;
use App\Modules\Planning\Models\PlanningStyleSmv;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The capacity planning engine (AnnotechERP 12/02), as Brickland plans:
 *
 * - A line's capacity: hourly = manpower × 60 × efficiency ÷ SMV (whole
 *   pieces), daily = hourly × hours.
 * - An order's quantity is shared among the chosen lines by capacity (the
 *   last line takes the rounding), unless the planner set it by hand.
 * - Each line sews on its free working days — the factory calendar's working
 *   days, less the days other orders' locked plans hold — either backward
 *   from the last sewing day (the day before shipment), so the lines finish
 *   together, or forward from a date; a day's target is the line's capacity,
 *   the last day the remainder.
 * - The activities come from the lead times: in-house ends the day before
 *   cutting, booking the day before in-house, cutting ends the day sewing
 *   starts, finishing and packing follow the last sewing day, shipment is the
 *   order's.
 * - The forecast: what has been made (the typed daily output), what the
 *   remaining days can still make, and when the order will now finish.
 */
final class PlanningEngine
{
    /** The activities in Brickland's order, with what each is based on. */
    public const ACTIVITIES = [
        'ACCESSORIES_BOOKING' => 'LEAD_TIME', 'ACCESSORIES_INHOUSE' => 'LEAD_TIME', 'FABRIC_BOOKING' => 'LEAD_TIME', 'FABRIC_INHOUSE' => 'LEAD_TIME',
        'CUTTING' => 'CAPACITY', 'SEWING' => 'SELECTED_LINES', 'FINISHING' => 'PRODUCTION_OUTPUT', 'PACKING' => 'FINISHING', 'SHIPMENT' => 'CONFIRMED_ORDER',
    ];

    /** @return array{0: int, 1: int} hourly and daily capacity */
    public static function capacity(float $manpower, float $hours, float $efficiency, float $smv): array
    {
        if ($smv <= 0) {
            return [0, 0];
        }
        $hourly = (int) floor($manpower * 60 * ($efficiency / 100) / $smv + 1e-9);

        return [$hourly, (int) floor($hourly * $hours + 1e-9)];
    }

    /**
     * The SMV planning reads, and where from: the SMV / SAM master by style,
     * else the style's items (marketing SMV, summed over the set).
     *
     * @return array{smv: ?float, smv_source: ?string, sam: ?float, sam_source: ?string}
     */
    public static function smvOf(Order $order): array
    {
        $master = $order->style_no ? PlanningStyleSmv::query()->where('style_no', $order->style_no)->where('active', true)->first() : null;
        if ($master && ($master->smv !== null || $master->sam !== null)) {
            $smv = (float) ($master->smv ?? $master->sam);

            return ['smv' => $smv, 'smv_source' => 'MASTER', 'sam' => $master->sam !== null ? (float) $master->sam : $smv, 'sam_source' => $master->sam !== null ? 'MASTER' : 'SAME'];
        }
        $items = $order->style_id ? (float) StyleItem::query()->where('style_id', $order->style_id)->sum('smv_marketing') : 0.0;
        if ($items > 0) {
            return ['smv' => round($items, 2), 'smv_source' => 'STYLE', 'sam' => round($items, 2), 'sam_source' => 'SAME'];
        }

        return ['smv' => null, 'smv_source' => null, 'sam' => null, 'sam_source' => null];
    }

    /** The factory a planning line runs in (line → floor → building → factory). */
    public static function factoryOf(string $lineId): ?string
    {
        return DB::table('planning_lines as l')->join('floors as f', 'f.id', '=', 'l.floor_id')->join('buildings as b', 'b.id', '=', 'f.building_id')
            ->where('l.id', $lineId)->value('b.factory_id');
    }

    /**
     * A factory's working days between two dates (both included), on HR &
     * Payroll's calendar (holidays) and the factory's working week (Settings).
     * Without a factory every day counts.
     *
     * @return list<string> dates
     */
    public static function workingDays(?string $factoryId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($to->lt($from)) {
            return [];
        }
        $factory = $factoryId ? Factory::query()->withoutGlobalScopes()->find($factoryId) : null;
        if ($factory === null) {
            $out = [];
            for ($d = $from; $d->lte($to); $d = $d->addDay()) {
                $out[] = $d->toDateString();
            }

            return $out;
        }

        return app(WorkingTimeService::class)->workingDates($factory, $from, $to);
    }

    /**
     * The line days other orders' locked plans hold.
     *
     * @param  list<string>  $lineIds
     * @return array<string, array<string, int>> line id => date => target
     */
    public static function held(array $lineIds, CarbonImmutable $from, CarbonImmutable $to, ?string $exceptOrderId = null): array
    {
        $out = [];
        PlanningMasterPlanDay::query()->whereIn('planning_line_id', $lineIds)->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->whereIn('master_plan_id', PlanningMasterPlan::query()->where('status', 'LOCKED')->when($exceptOrderId, fn ($q) => $q->where('order_id', '!=', $exceptOrderId))->select('id'))
            ->get()->each(function (PlanningMasterPlanDay $d) use (&$out): void {
                $out[$d->planning_line_id][$d->day->toDateString()] = ($out[$d->planning_line_id][$d->day->toDateString()] ?? 0) + $d->target;
            });

        return $out;
    }

    /**
     * A line's free working days in a window: its factory's working days that
     * no other order's locked plan holds.
     *
     * @return list<string>
     */
    public static function freeDays(PlanningLine $line, CarbonImmutable $from, CarbonImmutable $to, ?string $exceptOrderId = null): array
    {
        $held = self::held([$line->id], $from, $to, $exceptOrderId)[$line->id] ?? [];

        return array_values(array_filter(self::workingDays(self::factoryOf($line->id), $from, $to), fn ($d) => ! isset($held[$d])));
    }

    /**
     * Lays out a plan: shares the quantity, places each line's sewing days and
     * targets, and works out the activities.
     *
     * @param  list<array{line_id: string, manpower: float, machines: float, hours: float, efficiency: float, allocated_qty?: ?int}>  $inputs
     * @return array<string, mixed>
     */
    public static function calculate(Order $order, float $smv, array $inputs, string $direction, ?string $forwardFrom, bool $auto, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $ship = CarbonImmutable::parse($order->ship_date);
        $lastSewing = $ship->subDay();
        $lines = PlanningLine::query()->whereIn('id', array_column($inputs, 'line_id'))->get()->keyBy('id');
        $rows = [];
        foreach ($inputs as $in) {
            [$hourly, $daily] = self::capacity((float) $in['manpower'], (float) $in['hours'], (float) $in['efficiency'], $smv);
            $rows[] = $in + ['hourly_capacity' => $hourly, 'daily_capacity' => $daily];
        }
        $total = array_sum(array_column($rows, 'daily_capacity'));
        $qty = (int) $order->total_qty;

        // Share the quantity by capacity; the last line takes what rounding leaves.
        if ($auto || collect($rows)->contains(fn ($r) => ! isset($r['allocated_qty']))) {
            $left = $qty;
            foreach ($rows as $i => &$r) {
                $share = $i === count($rows) - 1 ? $left : ($total > 0 ? (int) floor($qty * $r['daily_capacity'] / $total) : 0);
                $r['allocated_qty'] = max(0, $share);
                $left -= $r['allocated_qty'];
            }
            unset($r);
        }

        $start = $direction === 'FORWARD' && $forwardFrom ? CarbonImmutable::parse($forwardFrom)->max($today->addDay()) : $today->addDay();
        $windowEnd = $direction === 'FORWARD' ? $lastSewing->addDays(120) : $lastSewing;
        $days = [];
        $scheduled = 0;
        foreach ($rows as &$r) {
            $line = $lines[$r['line_id']] ?? null;
            $free = $line ? self::freeDays($line, $start, $windowEnd, $order->id) : [];
            $need = $r['daily_capacity'] > 0 ? (int) ceil($r['allocated_qty'] / $r['daily_capacity']) : 0;
            $chosen = $direction === 'FORWARD' ? array_slice($free, 0, $need) : array_slice($free, max(0, count($free) - $need));
            $left = min($r['allocated_qty'], count($chosen) * $r['daily_capacity']);
            $r['planned_qty'] = $left;
            foreach ($chosen as $day) {
                $target = min($r['daily_capacity'], $left);
                if ($target <= 0) {
                    break;
                }
                $days[] = ['line_id' => $r['line_id'], 'day' => $day, 'capacity' => $r['daily_capacity'], 'target' => $target];
                $left -= $target;
            }
            $r['sewing_start'] = $chosen[0] ?? null;
            $r['sewing_end'] = $chosen ? end($chosen) : null;
            $r['days'] = count($chosen);
            $scheduled += $r['planned_qty'];
        }
        unset($r);

        $starts = array_filter(array_column($rows, 'sewing_start'));
        $ends = array_filter(array_column($rows, 'sewing_end'));
        $sewStart = $starts ? min($starts) : null;
        $sewEnd = $ends ? max($ends) : null;
        $activities = $sewStart && $sewEnd ? self::activities(CarbonImmutable::parse($sewStart), CarbonImmutable::parse($sewEnd), $ship) : [];
        $allocated = array_sum(array_column($rows, 'allocated_qty'));
        usort($days, fn ($a, $b) => [$a['day'], $a['line_id']] <=> [$b['day'], $b['line_id']]);

        return [
            'lines' => $rows, 'days' => $days, 'activities' => $activities,
            'daily_capacity' => $total, 'order_qty' => $qty, 'allocated_qty' => $allocated, 'scheduled_qty' => $scheduled,
            'required_days' => $total > 0 ? round($qty / $total, 2) : 0.0, 'balance' => max(0, $qty - $scheduled),
            'sewing_start' => $sewStart, 'sewing_end' => $sewEnd, 'last_sewing' => $lastSewing->toDateString(), 'ship_date' => $ship->toDateString(),
            'fully_allocated' => $scheduled >= $qty && $qty > 0,
            'late' => $sewEnd !== null && $sewEnd > $lastSewing->toDateString(),
            'lead_passed' => array_values(array_map(fn ($a) => $a['activity'], array_filter($activities, fn ($a) => str_ends_with($a['activity'], 'BOOKING') && $a['start_date'] < $today->toDateString()))),
        ];
    }

    /**
     * The activities, from the lead times and the sewing window.
     *
     * @return list<array{activity: string, duration: int, start_date: string, end_date: string, based_on: string}>
     */
    public static function activities(CarbonImmutable $sewStart, CarbonImmutable $sewEnd, CarbonImmutable $ship): array
    {
        PlanningDefaults::leadTimes(app(TenantContext::class)->companyId());
        $lead = PlanningLeadTime::query()->pluck('lead_days', 'activity')->map(fn ($d) => max(1, (int) $d))->all();
        $span = fn (CarbonImmutable $end, int $days) => [$end->subDays($days - 1), $end];
        $at = [];
        [$cs, $ce] = $span($sewStart, $lead['CUTTING'] ?? 2);
        $at['CUTTING'] = [$cs, $ce];
        foreach (['ACCESSORIES', 'FABRIC'] as $kind) {
            [$is, $ie] = $span($cs->subDay(), $lead["{$kind}_INHOUSE"] ?? 7);
            $at["{$kind}_INHOUSE"] = [$is, $ie];
            $at["{$kind}_BOOKING"] = $span($is->subDay(), $lead["{$kind}_BOOKING"] ?? 5);
        }
        $at['SEWING'] = [$sewStart, $sewEnd];
        $fs = $sewEnd->addDay();
        $at['FINISHING'] = [$fs, $fs->addDays(($lead['FINISHING'] ?? 1) - 1)];
        $at['PACKING'] = [$at['FINISHING'][1], $at['FINISHING'][1]->addDays(($lead['PACKING'] ?? 1) - 1)];
        $at['SHIPMENT'] = [$ship, $ship];

        $out = [];
        foreach (self::ACTIVITIES as $activity => $basedOn) {
            [$s, $e] = $at[$activity];
            $out[] = ['activity' => $activity, 'duration' => (int) $s->diffInDays($e) + 1, 'start_date' => $s->toDateString(), 'end_date' => $e->toDateString(), 'based_on' => $basedOn];
        }

        return $out;
    }

    /**
     * Where a locked plan stands today: made, still to make, what the planned
     * days ahead can still make, and when the order will now finish.
     *
     * @return array<string, mixed>
     */
    public static function forecast(PlanningMasterPlan $plan, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $t = $today->toDateString();
        $produced = (int) PlanningDailyPlan::query()->where('order_id', $plan->order_id)->sum('typed_output');
        $remaining = max(0, $plan->order_qty - $produced);
        $days = PlanningMasterPlanDay::query()->where('master_plan_id', $plan->id)->get();
        $daily = PlanningDailyPlan::query()->where('master_plan_id', $plan->id)->get()->keyBy(fn ($d) => $d->planning_line_id.'|'.$d->plan_date->toDateString());
        $plannedTillDate = (int) $days->filter(fn ($d) => $d->day->toDateString() <= $t)->sum('target');
        $capacityToDeadline = (int) $days->filter(fn ($d) => $d->day->toDateString() >= $t)
            ->sum(fn ($d) => isset($daily[$d->planning_line_id.'|'.$d->day->toDateString()]) ? $daily[$d->planning_line_id.'|'.$d->day->toDateString()]->daily_target : $d->capacity);
        $remainingDays = $days->filter(fn ($d) => $d->day->toDateString() >= $t)->map(fn ($d) => $d->day->toDateString())->unique()->count();
        $original = $plan->sewing_end?->toDateString();
        $lastSewing = $plan->ship_date->subDay()->toDateString();

        // Finish date: the remaining quantity at the plan's daily capacity, on the first line's working days from today (or the plan start).
        if ($remaining <= 0) {
            $forecast = PlanningDailyPlan::query()->where('order_id', $plan->order_id)->whereNotNull('typed_output')->max('plan_date');
            $forecast = $forecast ? CarbonImmutable::parse($forecast)->toDateString() : $original;
        } elseif ($plan->daily_capacity <= 0) {
            $forecast = null;
        } else {
            // Today counts only while its output is still to come.
            $start = PlanningDailyPlan::query()->where('order_id', $plan->order_id)->whereDate('plan_date', $today)->whereNotNull('typed_output')->exists() ? $today->addDay() : $today;
            $from = $plan->sewing_start && $plan->sewing_start->gt($start) ? CarbonImmutable::parse($plan->sewing_start) : $start;
            $need = (int) ceil($remaining / $plan->daily_capacity);
            $line = DB::table('planning_master_plan_lines')->where('master_plan_id', $plan->id)->orderBy('sort_order')->value('planning_line_id');
            $working = self::workingDays($line ? self::factoryOf($line) : null, $from, $from->addDays($need * 3 + 30));
            $forecast = $working[$need - 1] ?? end($working) ?: null;
        }
        $variance = $forecast && $original ? (int) CarbonImmutable::parse($original)->diffInDays(CarbonImmutable::parse($forecast), false) : 0;
        $status = match (true) {
            $remaining <= 0 => 'COMPLETED',
            $forecast !== null && $forecast > $lastSewing => 'AT_RISK',
            $variance > 0 => 'DELAYED',
            $produced === 0 && $plan->sewing_start && $plan->sewing_start->toDateString() > $t => 'NOT_STARTED',
            $variance < 0 => 'AHEAD',
            default => 'ON_TRACK',
        };

        return [
            'order_qty' => $plan->order_qty, 'produced' => $produced, 'remaining' => $remaining, 'percent' => $plan->order_qty > 0 ? (int) floor($produced * 100 / $plan->order_qty) : 0,
            'planned_till_date' => $plannedTillDate, 'vs_plan' => $produced - $plannedTillDate, 'original_completion' => $original, 'current_forecast' => $forecast,
            'ship_date' => $plan->ship_date->toDateString(), 'last_sewing' => $lastSewing, 'variance_days' => $variance, 'remaining_days' => $remainingDays,
            'capacity_to_deadline' => $capacityToDeadline, 'extra_capacity' => max(0, $remaining - $capacityToDeadline), 'status' => $status,
        ];
    }

    /** The next plan number: MP-{shipment year and month}{the company's running number}, under a lock. */
    public static function nextPlanNo(CarbonImmutable $ship): string
    {
        $company = app(TenantContext::class)->companyId();
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ["planning_master_plans:{$company}"]);
        $last = (int) PlanningMasterPlan::query()->pluck('plan_no')->map(fn (string $no): int => (int) substr($no, -4))->max();

        return 'MP-'.$ship->format('Ym').str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    public static function event(PlanningMasterPlan $plan, string $action, string $summary, ?string $userId): void
    {
        PlanningPlanEvent::query()->create(['plan_no' => $plan->plan_no, 'version' => $plan->version, 'action' => $action, 'summary' => $summary, 'user_id' => $userId]);
    }

    /**
     * The plan each order works from now: its latest version (a draft over a
     * locked one shows as the draft), by order id.
     *
     * @return Collection<string, PlanningMasterPlan>
     */
    public static function currentPlans(Collection $orderIds): Collection
    {
        return PlanningMasterPlan::query()->whereIn('order_id', $orderIds->all())->where('status', '!=', 'REVISED')->orderBy('version')->get()->keyBy('order_id');
    }
}

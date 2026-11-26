<?php

declare(strict_types=1);

namespace App\Modules\Planning\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Planning\Models\PlanningDailyPlan;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Planning\Models\PlanningMasterPlan;
use App\Modules\Planning\Models\PlanningMasterPlanActivity;
use App\Modules\Planning\Models\PlanningMasterPlanDay;
use App\Modules\Planning\Support\PlanningEngine;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Capacity Planning → Line Capacity View and Gantt / Timeline (AnnotechERP
 * 12/02), as Brickland keeps them. Line Capacity View: every active line,
 * every day of a month — standard minutes available (manpower × hours × 60 ×
 * efficiency, from the line master) against minutes planned (each order's
 * target × its SMV, the daily plan's where one is saved, else the locked
 * master plan's), with the factory's holidays. Gantt / Timeline: every live
 * master plan (locked or draft) with an activity in the month, booking to
 * shipment.
 */
class PlanningBoardApiController extends ApiController
{
    public function lineCapacity(Request $request): JsonResponse
    {
        $this->allow('planning.plan.view');
        [$from, $to] = $this->month($request);
        $lines = PlanningLine::query()->where('active', true)->orderBy('display_order')->orderBy('code')->get();
        $paths = DB::table('planning_lines as l')->join('floors as f', 'f.id', '=', 'l.floor_id')->join('buildings as b', 'b.id', '=', 'f.building_id')->join('factories as fa', 'fa.id', '=', 'b.factory_id')
            ->whereIn('l.id', $lines->pluck('id'))->get(['l.id', 'b.factory_id', DB::raw("fa.code || ' › ' || f.code as path")])->keyBy('id');

        // Minutes planned per line and day: the saved daily plan's target, else the locked master plan's.
        $plans = PlanningMasterPlan::query()->where('status', 'LOCKED')->get(['id', 'smv'])->keyBy('id');
        $planned = [];
        $cells = [];
        foreach (PlanningMasterPlanDay::query()->whereIn('master_plan_id', $plans->keys())->whereBetween('day', [$from->toDateString(), $to->toDateString()])->get() as $d) {
            $cells[$d->master_plan_id.'|'.$d->planning_line_id.'|'.$d->day->toDateString()] = [$d->planning_line_id, $d->day->toDateString(), $d->target, (float) $plans[$d->master_plan_id]->smv];
        }
        foreach (PlanningDailyPlan::query()->whereIn('master_plan_id', $plans->keys())->whereBetween('plan_date', [$from->toDateString(), $to->toDateString()])->get() as $d) {
            $cells[$d->master_plan_id.'|'.$d->planning_line_id.'|'.$d->plan_date->toDateString()] = [$d->planning_line_id, $d->plan_date->toDateString(), $d->daily_target, (float) $d->smv];
        }
        foreach ($cells as [$line, $day, $pieces, $smv]) {
            $planned[$line][$day]['pieces'] = ($planned[$line][$day]['pieces'] ?? 0) + $pieces;
            $planned[$line][$day]['minutes'] = ($planned[$line][$day]['minutes'] ?? 0) + $pieces * $smv;
        }

        $working = [];
        $totals = ['available' => 0.0, 'planned' => 0.0, 'free' => 0, 'over' => 0];
        $rows = $lines->map(function (PlanningLine $l) use ($from, $to, $paths, $planned, &$working, &$totals): array {
            $factory = $paths[$l->id]->factory_id ?? null;
            $working[$factory] ??= array_flip(PlanningEngine::workingDays($factory, $from, $to));
            $perDay = $l->std_manpower * (float) $l->std_hours * 60 * (float) $l->std_efficiency / 100;
            $days = [];
            $loaded = 0;
            $lineAvailable = 0.0;
            $linePlanned = 0.0;
            for ($d = $from; $d->lte($to); $d = $d->addDay()) {
                $date = $d->toDateString();
                $holiday = ! isset($working[$factory][$date]);
                $minutes = (float) ($planned[$l->id][$date]['minutes'] ?? 0);
                $pieces = (int) ($planned[$l->id][$date]['pieces'] ?? 0);
                if (! $holiday) {
                    $lineAvailable += $perDay;
                    $loaded += $minutes > 0 ? 1 : 0;
                    $totals['free'] += $minutes > 0 ? 0 : 1;
                    $totals['over'] += $minutes > $perDay ? 1 : 0;
                }
                $linePlanned += $minutes;
                $pct = $perDay > 0 ? (int) round($minutes * 100 / $perDay) : 0;
                $days[] = [
                    'date' => $date, 'holiday' => $holiday, 'pieces' => $pieces, 'minutes' => (int) round($minutes), 'available' => (int) round($perDay), 'pct' => $pct,
                    'state' => match (true) {
                        $holiday && $minutes <= 0 => 'HOLIDAY',
                        $minutes <= 0 => 'FREE',
                        $minutes > $perDay => 'OVER',
                        $pct >= 100 => 'FULL',
                        default => 'PART',
                    },
                ];
            }
            $totals['available'] += $lineAvailable;
            $totals['planned'] += $linePlanned;
            $workingDays = count($working[$factory]);

            return [
                'code' => $l->code, 'path' => $paths[$l->id]->path ?? null, 'manpower' => $l->std_manpower, 'hours' => (float) $l->std_hours, 'efficiency' => (float) $l->std_efficiency,
                'days' => $days, 'loaded' => $loaded, 'working' => $workingDays, 'util' => $lineAvailable > 0 ? (int) round($linePlanned * 100 / $lineAvailable) : 0,
            ];
        })->values()->all();

        return ApiResponse::ok([
            'year' => $from->year, 'month' => $from->month, 'rows' => $rows,
            'tiles' => [
                'lines' => count($rows), 'available_h' => (int) round($totals['available'] / 60), 'planned_h' => (int) round($totals['planned'] / 60),
                'util' => $totals['available'] > 0 ? (int) round($totals['planned'] * 100 / $totals['available']) : 0, 'free' => $totals['free'], 'over' => $totals['over'],
            ],
        ]);
    }

    public function timeline(Request $request): JsonResponse
    {
        $this->allow('planning.plan.view');
        [$from, $to] = $this->month($request);
        $q = mb_strtolower(trim((string) $request->query('q', '')));
        $statuses = match ($request->query('plans')) {
            'LOCKED' => ['LOCKED'],
            'DRAFT' => ['DRAFT'],
            default => ['LOCKED', 'DRAFT'],
        };
        $plans = PlanningMasterPlan::query()->whereIn('status', $statuses)
            ->whereIn('id', PlanningMasterPlanActivity::query()->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)->select('master_plan_id'))
            ->orderBy('sewing_start')->orderBy('plan_no')->orderBy('version')->get();
        $orders = Order::query()->whereIn('id', $plans->pluck('order_id'))->get()->keyBy('id');
        $buyers = Buyer::query()->pluck('short_name', 'id');
        $codes = DB::table('planning_master_plan_lines as m')->join('planning_lines as l', 'l.id', '=', 'm.planning_line_id')->whereIn('m.master_plan_id', $plans->pluck('id'))
            ->orderBy('m.sort_order')->get(['m.master_plan_id', 'l.code'])->groupBy('master_plan_id')->map(fn ($g) => $g->pluck('code')->implode(', '));
        $activities = PlanningMasterPlanActivity::query()->whereIn('master_plan_id', $plans->pluck('id'))->orderBy('sort_order')->get()->groupBy('master_plan_id');

        $rows = $plans->map(fn (PlanningMasterPlan $p) => [
            'order_no' => $orders[$p->order_id]->order_no, 'buyer' => $buyers[$orders[$p->order_id]->buyer_id] ?? null, 'style' => $orders[$p->order_id]->style_no,
            'qty' => $p->order_qty, 'lines' => $codes[$p->id] ?? '', 'plan_no' => $p->plan_no, 'version' => $p->version, 'status' => $p->status, 'ship_date' => $p->ship_date?->toDateString(),
            'activities' => ($activities[$p->id] ?? collect())->map(fn (PlanningMasterPlanActivity $a) => [
                'activity' => $a->activity, 'duration' => $a->duration, 'start_date' => $a->start_date->toDateString(), 'end_date' => $a->end_date->toDateString(), 'based_on' => $a->based_on,
            ])->values()->all(),
        ])->filter(fn ($r) => $q === '' || str_contains(mb_strtolower(implode(' ', [$r['order_no'], $r['buyer'], $r['style'], $r['lines'], $r['plan_no']])), $q))->values()->all();

        return ApiResponse::ok(['year' => $from->year, 'month' => $from->month, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'rows' => $rows]);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} the month asked (?year=&month=), else this month */
    private function month(Request $request): array
    {
        $today = CarbonImmutable::today();
        $year = (int) $request->query('year', (string) $today->year);
        $month = (int) $request->query('month', (string) $today->month);
        $from = CarbonImmutable::create($year >= 2000 && $year <= 2100 ? $year : $today->year, $month >= 1 && $month <= 12 ? $month : $today->month, 1)->startOfDay();

        return [$from, $from->endOfMonth()->startOfDay()];
    }
}

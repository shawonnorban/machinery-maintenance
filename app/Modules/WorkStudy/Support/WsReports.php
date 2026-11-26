<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Support;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\WorkStudy\Models\WsChangeover;
use App\Modules\WorkStudy\Models\WsLineProduction;
use App\Modules\WorkStudy\Models\WsNptEntry;
use App\Modules\WorkStudy\Models\WsOtPlan;
use App\Modules\WorkStudy\Models\WsSectionResult;
use App\Modules\WorkStudy\Models\WsSewingTarget;
use App\Modules\WorkStudy\Resources\ChangeoverResource;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Work Study → Reports (AnnotechERP 14/02): Brickland's IE report set — each
 * report's category and the parameters it asks for, as Brickland lists them
 * — and the reports read live from Work Study's own sheets: the target sheet
 * (efficiency, targets, achievement), NPT, the daily line costing (line,
 * floor, order and item profit, the overtime-tier slab), section profit,
 * change-overs, the OT plan and Capacity Planning's day plans.
 *
 * A report whose figures come from a module not built yet (sewing input and
 * output scans, attendance, line layouts, print/embroidery costing) is listed
 * with its parameters and says which module it waits for, rather than
 * inventing numbers.
 */
final class WsReports
{
    /**
     * key => [Brickland's category, its parameters, the module it waits for (null when it runs)].
     * Parameters: office, buyer, order (Work Style/Job#), style (Style/Order#), floors, lines, date
     * (Production Date), range (From/To Date), pluck (Qty Pluck Type), car (Exclude car?), day (Day#).
     */
    public const REPORTS = [
        'efficiency' => ['Efficiency', ['office', 'buyer', 'order', 'floors', 'date'], null],
        // Brickland's NPT also asks Gmt. Section and Fin Teams; an NPT row here carries neither, so they are not offered.
        'npt' => ['Efficiency', ['office', 'buyer', 'order', 'floors', 'lines', 'range'], null],
        'input_output_wip' => ['Production', ['office', 'buyer', 'order', 'style', 'floors', 'date'], 'garments'],
        'line_pl' => ['Costing', ['office', 'buyer', 'order', 'style', 'floors', 'lines', 'range'], null],
        'daily_costing' => ['Costing', ['office', 'date'], null],
        'monthly_costing' => ['Costing', ['office', 'range'], null],
        'manpower_plan' => ['Manpower', ['office', 'floors', 'range'], 'attendance'],
        'line_plan_actual' => ['Production', ['office', 'buyer', 'floors', 'lines', 'range'], 'garments'],
        'finishing_wip' => ['Production', ['office', 'buyer', 'order', 'style', 'floors', 'date'], 'garments'],
        'floor_plan_value' => ['Production', ['office', 'floors', 'range'], 'garments'],
        'date_target' => ['Efficiency', ['date'], null],
        'machine_variance' => ['Engineering', ['buyer', 'order', 'style', 'floors', 'lines', 'range', 'pluck'], 'layout'],
        'target_achievement' => ['Efficiency', ['range'], null],
        'monthly_achievement' => ['Efficiency', ['office', 'floors', 'range', 'car'], null],
        'capacity_booking' => ['Planning', [], 'booking'],
        'plan_target_input' => ['Planning', ['buyer', 'order', 'style', 'floors', 'lines', 'range'], 'garments'],
        'cost_print_emb' => ['Costing', ['date'], 'print'],
        'gmt_item_analysis' => ['Engineering', ['buyer', 'floors', 'range', 'pluck'], 'layout'],
        'target_gen_ot' => ['Efficiency', ['range'], null],
        'manpower_utilization' => ['Manpower', ['floors', 'range'], 'attendance'],
        'pre_post_balancing' => ['Engineering', ['range'], 'layout'],
        'day_pre_post_balancing' => ['Engineering', ['floors', 'range', 'day'], 'layout'],
        'item_pl' => ['Costing', ['buyer', 'order', 'style', 'range'], null],
        'item_pcs' => ['Production', ['buyer', 'order', 'style'], 'garments'],
        'planning_vs_ie' => ['Planning', ['range'], null],
        'daily_manpower_status' => ['Manpower', ['floors', 'date'], 'attendance'],
        'section_pl' => ['Costing', ['office', 'floors', 'range'], null],
        'order_pl' => ['Costing', ['buyer', 'order', 'style', 'range'], null],
        'hourly_status' => ['Production', ['office', 'floors', 'lines', 'date'], 'garments'],
        'line_feeding_sco' => ['Production', ['office', 'floors', 'lines', 'range'], null],
        'ot_plan' => ['Manpower', ['office', 'floors', 'range'], null],
        'ot_plan_actual' => ['Manpower', ['office', 'floors', 'range'], 'attendance'],
        'line_pl_hour_slab' => ['Costing', ['office', 'floors', 'lines', 'range'], null],
        'daily_manpower_utilization' => ['Manpower', ['floors', 'date'], 'attendance'],
        'daily_line_target' => ['Efficiency', ['office', 'floors', 'lines', 'date'], null],
        'cpm_profit' => ['Costing', ['buyer', 'order', 'style', 'range'], 'accounts'],
    ];

    /** The longest From → To a report reads, so a slip of the year does not read a decade. */
    public const MAX_DAYS = 92;

    /** Qty Pluck Type's choices, as Brickland lists them. */
    public const PLUCK = ['ORDER', 'CUT', 'SEWING', 'SHIPMENT'];

    /**
     * A report read for the filters given.
     *
     * @param  array{floors: ?list<string>, lines: ?list<string>, buyer_id: ?string, order: ?string, style: ?string, from: string, to: string, car: bool}  $f
     * @return array{columns: list<array{key: string, type: string}>, rows: list<array<string, mixed>>, totals: ?array<string, mixed>, pending: ?string}
     */
    public static function run(string $key, array $f): array
    {
        $pending = self::REPORTS[$key][2];
        if ($pending) {
            return ['columns' => [], 'rows' => [], 'totals' => null, 'pending' => $pending];
        }
        [$columns, $rows, $sum] = match ($key) {
            'efficiency' => self::efficiency($f),
            'npt' => self::npt($f),
            'line_pl' => self::linePl($f),
            'daily_costing' => self::dailyCosting($f),
            'monthly_costing' => self::monthlyCosting($f),
            'date_target' => self::dateTarget($f),
            'target_achievement' => self::targetAchievement($f),
            'monthly_achievement' => self::monthlyAchievement($f),
            'target_gen_ot' => self::targetGenOt($f),
            'item_pl' => self::groupedPl($f, ['item_name']),
            'order_pl' => self::groupedPl($f, ['buyer', 'order_no', 'item_name']),
            'planning_vs_ie' => self::planningVsIe($f),
            'section_pl' => self::sectionPl($f),
            'line_feeding_sco' => self::lineFeeding($f),
            'ot_plan' => self::otPlan($f),
            'line_pl_hour_slab' => self::hourSlab($f),
            'daily_line_target' => self::dailyLineTarget($f),
        };
        $cols = [];
        foreach ($columns as $k => $type) {
            $cols[] = ['key' => $k, 'type' => $type];
        }

        return ['columns' => $cols, 'rows' => array_values($rows), 'totals' => $rows ? self::totals($columns, $rows, $sum) : null, 'pending' => null];
    }

    /* ---------------- the target sheet ---------------- */

    private static function efficiency(array $f): array
    {
        $rows = self::targets($f)->map(function (WsSewingTarget $t) {
            $prod = (int) $t->prod_gen + (int) $t->prod_ot;
            $produced = round($prod * (float) $t->smv, 2);
            $available = ((int) $t->used_op + (int) $t->used_aso) * (float) $t->wh * 60;

            return self::where($t) + [
                'buyer' => self::buyerName($t->buyer_id), 'order_style' => $t->order_style, 'smv' => (float) $t->smv, 'op' => (int) $t->used_op, 'aso' => (int) $t->used_aso,
                'wh' => (float) $t->wh, 'target' => (int) $t->target_gen + (int) $t->target_ot, 'prod' => $prod, 'produced_min' => $produced,
                'available_min' => round($available, 2), 'effi' => self::pct($produced, $available), 't_effi' => (float) $t->t_effi,
            ];
        })->all();

        return [['date' => 'date', 'floor' => 'text', 'line' => 'text', 'buyer' => 'text', 'order_style' => 'text', 'smv' => 'number', 'op' => 'number', 'aso' => 'number', 'wh' => 'number',
            'target' => 'number', 'prod' => 'number', 'produced_min' => 'number', 'available_min' => 'number', 'effi' => 'pct', 't_effi' => 'pct'],
            $rows, ['target', 'prod', 'produced_min', 'available_min', 'effi' => ['produced_min', 'available_min']]];
    }

    private static function dateTarget(array $f): array
    {
        $rows = self::targets($f)->map(fn (WsSewingTarget $t) => self::where($t) + [
            'buyer' => self::buyerName($t->buyer_id), 'order_style' => $t->order_style, 'smv' => (float) $t->smv, 'op' => (int) $t->used_op, 'aso' => (int) $t->used_aso,
            'wh' => (float) $t->wh, 't_effi' => (float) $t->t_effi, 'target_gen' => (int) $t->target_gen, 'target_ot' => (int) $t->target_ot,
            'ttl_target' => (int) $t->target_gen + (int) $t->target_ot,
        ])->all();

        return [['date' => 'date', 'floor' => 'text', 'line' => 'text', 'buyer' => 'text', 'order_style' => 'text', 'smv' => 'number', 'op' => 'number', 'aso' => 'number', 'wh' => 'number',
            't_effi' => 'pct', 'target_gen' => 'number', 'target_ot' => 'number', 'ttl_target' => 'number'], $rows, ['op', 'aso', 'target_gen', 'target_ot', 'ttl_target']];
    }

    private static function dailyLineTarget(array $f): array
    {
        $rows = self::targets($f)->map(fn (WsSewingTarget $t) => self::where($t) + [
            'order_style' => $t->order_style, 'gmt_item' => $t->gmt_item, 'smv' => (float) $t->smv, 'manpower' => (int) $t->used_op + (int) $t->used_aso, 'wh' => (float) $t->wh,
            'hourly' => (float) $t->wh > 0 ? (int) round((int) $t->target_gen / (float) $t->wh) : 0, 'target_gen' => (int) $t->target_gen, 'target_ot' => (int) $t->target_ot,
        ])->all();

        return [['date' => 'date', 'floor' => 'text', 'line' => 'text', 'order_style' => 'text', 'gmt_item' => 'text', 'smv' => 'number', 'manpower' => 'number', 'wh' => 'number',
            'hourly' => 'number', 'target_gen' => 'number', 'target_ot' => 'number'], $rows, ['manpower', 'hourly', 'target_gen', 'target_ot']];
    }

    private static function targetAchievement(array $f): array
    {
        $rows = self::targets($f)->groupBy(fn (WsSewingTarget $t) => $t->planning_line_id)->map(function (Collection $g) {
            $first = $g->first();
            $target = $g->sum(fn ($t) => (int) $t->target_gen + (int) $t->target_ot);
            $prod = $g->sum(fn ($t) => (int) $t->prod_gen + (int) $t->prod_ot);

            return ['floor' => self::floorCode($first->floor_id), 'line' => self::lineCode($first->planning_line_id), 'days' => $g->pluck('target_date')->map(fn ($d) => $d->toDateString())->unique()->count(),
                'target' => $target, 'prod' => $prod, 'achv' => self::pct($prod, $target), 'variance' => $prod - $target];
        })->sortBy(fn ($r) => $r['floor'].' '.$r['line'])->all();

        return [['floor' => 'text', 'line' => 'text', 'days' => 'number', 'target' => 'number', 'prod' => 'number', 'achv' => 'pct', 'variance' => 'number'], $rows,
            ['target', 'prod', 'variance', 'achv' => ['prod', 'target']]];
    }

    private static function monthlyAchievement(array $f): array
    {
        $rows = self::targets($f)->filter(fn (WsSewingTarget $t) => ! ($f['car'] && $t->is_car))
            ->groupBy(fn (WsSewingTarget $t) => $t->target_date->format('Y-m').'|'.$t->floor_id)->map(function (Collection $g, string $k) {
                $target = $g->sum(fn ($t) => (int) $t->target_gen + (int) $t->target_ot);
                $prod = $g->sum(fn ($t) => (int) $t->prod_gen + (int) $t->prod_ot);

                return ['month' => Carbon::createFromFormat('Y-m-d', substr($k, 0, 7).'-01')->format('M Y'), 'floor' => self::floorCode($g->first()->floor_id),
                    'lines' => $g->pluck('planning_line_id')->unique()->count(), 'target' => $target, 'prod' => $prod, 'achv' => self::pct($prod, $target)];
            })->sortKeys()->all();

        return [['month' => 'text', 'floor' => 'text', 'lines' => 'number', 'target' => 'number', 'prod' => 'number', 'achv' => 'pct'], $rows, ['target', 'prod', 'achv' => ['prod', 'target']]];
    }

    private static function targetGenOt(array $f): array
    {
        $rows = self::targets($f)->groupBy(fn (WsSewingTarget $t) => $t->planning_line_id)->map(function (Collection $g) {
            $first = $g->first();
            $tg = $g->sum(fn ($t) => (int) $t->target_gen);
            $pg = $g->sum(fn ($t) => (int) $t->prod_gen);
            $to = $g->sum(fn ($t) => (int) $t->target_ot);
            $po = $g->sum(fn ($t) => (int) $t->prod_ot);

            return ['floor' => self::floorCode($first->floor_id), 'line' => self::lineCode($first->planning_line_id), 'target_gen' => $tg, 'prod_gen' => $pg, 'achv_gen' => self::pct($pg, $tg),
                'target_ot' => $to, 'prod_ot' => $po, 'achv_ot' => self::pct($po, $to)];
        })->sortBy(fn ($r) => $r['floor'].' '.$r['line'])->all();

        return [['floor' => 'text', 'line' => 'text', 'target_gen' => 'number', 'prod_gen' => 'number', 'achv_gen' => 'pct', 'target_ot' => 'number', 'prod_ot' => 'number', 'achv_ot' => 'pct'],
            $rows, ['target_gen', 'prod_gen', 'target_ot', 'prod_ot', 'achv_gen' => ['prod_gen', 'target_gen'], 'achv_ot' => ['prod_ot', 'target_ot']]];
    }

    /* ---------------- NPT, change-overs, OT plan, Capacity Planning ---------------- */

    private static function npt(array $f): array
    {
        $rows = self::scoped(WsNptEntry::query(), $f, 'npt_date')->when($f['order'], fn ($q) => $q->where('order_style', $f['order']))
            ->orderBy('npt_date')->get()->map(fn (WsNptEntry $n) => [
                'date' => $n->npt_date->toDateString(), 'floor' => self::floorCode($n->floor_id), 'line' => self::lineCode($n->planning_line_id), 'order_style' => $n->order_style,
                'reason_type' => $n->reason_type, 'reason_details' => $n->reason_details, 'responsible_dept' => $n->responsible_dept, 'hour_slots' => implode(', ', $n->hour_slots ?? []),
                'manpower' => (int) $n->manpower, 'minutes' => (int) $n->minutes, 'npt' => (int) $n->npt,
            ])->all();

        return [['date' => 'date', 'floor' => 'text', 'line' => 'text', 'order_style' => 'text', 'reason_type' => 'text', 'reason_details' => 'text', 'responsible_dept' => 'text',
            'hour_slots' => 'text', 'manpower' => 'number', 'minutes' => 'number', 'npt' => 'number'], $rows, ['npt']];
    }

    private static function lineFeeding(array $f): array
    {
        $definition = app(ChangeoverResource::class);
        $rows = self::scoped(WsChangeover::query(), $f, 'changeover_date')->orderBy('changeover_date')->get()->map(function (WsChangeover $c) use ($definition) {
            $p = $definition->present($c);

            return ['date' => $p['changeover_date'], 'floor' => self::floorCode($c->floor_id), 'line' => $p['line'], 'new_buyer' => $p['new_buyer'], 'new_order' => $c->new_order,
                'prev_order' => $c->prev_order, 'sco' => (int) $c->sco_minutes, 'ft' => (float) $c->ft_hours, 'feeding_start' => $p['feeding_start'], 'feeding_end' => $p['feeding_end'],
                'status' => $p['status']];
        })->all();

        return [['date' => 'date', 'floor' => 'text', 'line' => 'text', 'new_buyer' => 'text', 'new_order' => 'text', 'prev_order' => 'text', 'sco' => 'number', 'ft' => 'number',
            'feeding_start' => 'date', 'feeding_end' => 'date', 'status' => 'status'], $rows, ['sco', 'ft']];
    }

    private static function otPlan(array $f): array
    {
        $departments = DB::table('departments')->pluck('name', 'id');
        $designations = DB::table('designations')->pluck('name', 'id');
        $rows = self::scoped(WsOtPlan::query(), $f, 'plan_date')->orderBy('plan_date')->get()->map(function (WsOtPlan $p) use ($departments, $designations) {
            $slots = $p->slots ?? [];
            $op = array_sum(array_map(fn ($s) => (int) ($s['op'] ?? 0), $slots));
            $aso = array_sum(array_map(fn ($s) => (int) ($s['aso'] ?? 0), $slots));

            return ['date' => $p->plan_date->toDateString(), 'floor' => self::floorCode($p->floor_id), 'line' => $p->planning_line_id ? self::lineCode($p->planning_line_id) : null,
                'department' => $departments[$p->department_id] ?? null, 'designation' => $designations[$p->designation_id] ?? null, 'mp_gen' => (int) $p->mp_gen,
                'ot_hours' => count($slots), 'ot_op' => $op, 'ot_aso' => $aso, 'person_hours' => $op + $aso];
        })->all();

        return [['date' => 'date', 'floor' => 'text', 'line' => 'text', 'department' => 'text', 'designation' => 'text', 'mp_gen' => 'number', 'ot_hours' => 'number',
            'ot_op' => 'number', 'ot_aso' => 'number', 'person_hours' => 'number'], $rows, ['mp_gen', 'ot_op', 'ot_aso', 'person_hours']];
    }

    private static function planningVsIe(array $f): array
    {
        $out = [];
        $plans = DB::table('planning_daily_plans')->where('company_id', app(TenantContext::class)->companyId())->whereBetween('plan_date', [$f['from'], $f['to']])
            ->when($f['lines'] !== null, fn ($q) => $q->whereIn('planning_line_id', $f['lines']))->get(['plan_date', 'planning_line_id', 'daily_target']);
        foreach ($plans as $p) {
            $line = PlanningLine::query()->find($p->planning_line_id);
            if (! $line || ($f['floors'] !== null && ! in_array($line->floor_id, $f['floors'], true))) {
                continue;
            }
            $k = substr((string) $p->plan_date, 0, 10).'|'.$p->planning_line_id;
            $out[$k] ??= ['date' => substr((string) $p->plan_date, 0, 10), 'floor' => self::floorCode($line->floor_id), 'line' => $line->code, 'planned' => 0, 'ie_target' => 0, 'prod' => 0];
            $out[$k]['planned'] += (int) $p->daily_target;
        }
        foreach (self::targets($f) as $t) {
            $k = $t->target_date->toDateString().'|'.$t->planning_line_id;
            $out[$k] ??= self::where($t) + ['planned' => 0, 'ie_target' => 0, 'prod' => 0];
            $out[$k]['ie_target'] += (int) $t->target_gen + (int) $t->target_ot;
            $out[$k]['prod'] += (int) $t->prod_gen + (int) $t->prod_ot;
        }
        ksort($out);
        $rows = array_map(fn ($r) => $r + ['plan_achv' => self::pct($r['prod'], $r['planned']), 'ie_achv' => self::pct($r['prod'], $r['ie_target'])], $out);

        return [['date' => 'date', 'floor' => 'text', 'line' => 'text', 'planned' => 'number', 'ie_target' => 'number', 'prod' => 'number', 'plan_achv' => 'pct', 'ie_achv' => 'pct'],
            $rows, ['planned', 'ie_target', 'prod', 'plan_achv' => ['prod', 'planned'], 'ie_achv' => ['prod', 'ie_target']]];
    }

    /* ---------------- section profit ---------------- */

    private static function sectionPl(array $f): array
    {
        $sections = DB::table('sections')->where('company_id', app(TenantContext::class)->companyId())->get(['id', 'name', 'floor_id'])->keyBy('id');
        $rows = WsSectionResult::query()->whereBetween('result_date', [$f['from'], $f['to']])->get()
            ->filter(fn (WsSectionResult $r) => $f['floors'] === null || in_array($sections[$r->section_id]->floor_id ?? null, $f['floors'], true))
            ->groupBy('section_id')->map(function (Collection $g, string $id) use ($sections) {
                $sum = fn (string $k) => round($g->sum(fn ($r) => (float) $r->{$k}), 2);
                $profit = round($g->sum(fn (WsSectionResult $r) => $r->profit()), 2);

                return ['section' => $sections[$id]->name ?? null, 'days' => $g->count(), 'income_target' => $sum('income_target'), 'income' => $sum('income'), 'prod_target' => $sum('prod_target'),
                    'prod_qty' => $sum('prod_qty'), 'oh_cost' => $sum('oh_cost'), 'mat_cost' => $sum('mat_cost'), 'expense' => $sum('expense'), 'profit' => $profit, 'standing' => WsSectionProfit::standing($profit)];
            })->sortBy('section')->all();

        return [['section' => 'text', 'days' => 'number', 'income_target' => 'money', 'income' => 'money', 'prod_target' => 'number', 'prod_qty' => 'number', 'oh_cost' => 'money',
            'mat_cost' => 'money', 'expense' => 'money', 'profit' => 'money', 'standing' => 'status'], $rows, ['income_target', 'income', 'prod_target', 'prod_qty', 'oh_cost', 'mat_cost', 'expense', 'profit']];
    }

    /* ---------------- the daily line costing ---------------- */

    private static function linePl(array $f): array
    {
        $rows = array_map(fn ($r) => array_intersect_key($r, array_flip(['date', 'floor', 'line', 'buyer', 'order_no', 'item_name', 'effi', 'fob_earn', 'cm_earn', 'total_cost', 'profit'])), self::profitRows($f));

        return [['date' => 'date', 'floor' => 'text', 'line' => 'text', 'buyer' => 'text', 'order_no' => 'text', 'item_name' => 'text', 'effi' => 'pct', 'fob_earn' => 'money',
            'cm_earn' => 'money', 'total_cost' => 'money', 'profit' => 'money'], $rows, ['fob_earn', 'cm_earn', 'total_cost', 'profit']];
    }

    private static function dailyCosting(array $f): array
    {
        $rows = collect(self::profitRows($f))->groupBy('floor')->map(fn (Collection $g, string $floor) => [
            'floor' => $floor, 'lines' => $g->pluck('line')->unique()->count(), 'fob_earn' => round($g->sum('fob_earn'), 2), 'cm_earn' => round($g->sum('cm_earn'), 2),
            'total_cost' => round($g->sum('total_cost'), 2), 'profit' => round($g->sum('profit'), 2),
        ])->sortKeys()->all();

        return [['floor' => 'text', 'lines' => 'number', 'fob_earn' => 'money', 'cm_earn' => 'money', 'total_cost' => 'money', 'profit' => 'money'], $rows, ['lines', 'fob_earn', 'cm_earn', 'total_cost', 'profit']];
    }

    private static function monthlyCosting(array $f): array
    {
        $rows = collect(self::profitRows($f))->groupBy(fn ($r) => $r['floor'].'|'.$r['line'])->map(fn (Collection $g) => [
            'floor' => $g->first()['floor'], 'line' => $g->first()['line'], 'days' => $g->pluck('date')->unique()->count(), 'fob_earn' => round($g->sum('fob_earn'), 2),
            'cm_earn' => round($g->sum('cm_earn'), 2), 'total_cost' => round($g->sum('total_cost'), 2), 'profit' => round($g->sum('profit'), 2),
        ])->sortKeys()->all();

        return [['floor' => 'text', 'line' => 'text', 'days' => 'number', 'fob_earn' => 'money', 'cm_earn' => 'money', 'total_cost' => 'money', 'profit' => 'money'], $rows, ['fob_earn', 'cm_earn', 'total_cost', 'profit']];
    }

    private static function hourSlab(array $f): array
    {
        $tiers = ['g', ...WsCosting::TIERS];
        $rows = collect(self::profitRows($f))->groupBy(fn ($r) => $r['floor'].'|'.$r['line'])->map(function (Collection $g) use ($tiers) {
            $row = ['floor' => $g->first()['floor'], 'line' => $g->first()['line']];
            foreach ($tiers as $t) {
                $row["pl_{$t}"] = round($g->sum("pl_{$t}"), 2);
            }

            return $row + ['profit' => round($g->sum('profit'), 2)];
        })->sortKeys()->all();
        $columns = ['floor' => 'text', 'line' => 'text'] + array_fill_keys(array_map(fn ($t) => "pl_{$t}", $tiers), 'money') + ['profit' => 'money'];

        return [$columns, $rows, [...array_map(fn ($t) => "pl_{$t}", $tiers), 'profit']];
    }

    /** Item or order profit: the day's line profit added up by the keys given. */
    private static function groupedPl(array $f, array $by): array
    {
        $rows = collect(self::profitRows($f))->groupBy(fn ($r) => implode('|', array_map(fn ($k) => $r[$k] ?? '', $by)))->map(function (Collection $g) use ($by) {
            $row = array_intersect_key($g->first(), array_flip($by));

            return $row + ['lines' => $g->pluck('line')->unique()->count(), 'days' => $g->pluck('date')->unique()->count(), 'cm_earn' => round($g->sum('cm_earn'), 2),
                'total_cost' => round($g->sum('total_cost'), 2), 'profit' => round($g->sum('profit'), 2)];
        })->sortKeys()->all();
        $columns = array_fill_keys($by, 'text') + ['lines' => 'number', 'days' => 'number', 'cm_earn' => 'money', 'total_cost' => 'money', 'profit' => 'money'];

        return [$columns, $rows, ['cm_earn', 'total_cost', 'profit']];
    }

    /**
     * Every line's day on the daily line costing over the range, as 14/05's
     * Overall Profit Lost works it out, with its date and floor.
     *
     * @return list<array<string, mixed>>
     */
    private static function profitRows(array $f): array
    {
        $days = WsLineProduction::query()->whereBetween('prod_date', [$f['from'], $f['to']])
            ->when($f['floors'] !== null, fn ($q) => $q->whereIn('floor_id', $f['floors']))
            ->select('floor_id', 'prod_date')->distinct()->orderBy('prod_date')->get();
        $lineCodes = $f['lines'] !== null ? PlanningLine::query()->whereIn('id', $f['lines'])->pluck('code')->all() : null;
        $buyer = $f['buyer_id'] ? Buyer::query()->whereKey($f['buyer_id'])->value('short_name') : null;
        $out = [];
        foreach ($days as $d) {
            $date = Carbon::parse($d->prod_date)->toDateString();
            foreach (WsCosting::profit($d->floor_id, $date)['rows'] as $r) {
                if (($lineCodes !== null && ! in_array($r['line'], $lineCodes, true)) || ($buyer && $r['buyer'] !== $buyer) || ($f['order'] && $r['order_no'] !== $f['order'])
                    || ($f['style'] && stripos((string) $r['order_no'], $f['style']) === false)) {
                    continue;
                }
                $out[] = ['date' => $date, 'floor' => self::floorCode($d->floor_id)] + $r;
            }
        }

        return $out;
    }

    /* ---------------- helpers ---------------- */

    /** @return Collection<int, WsSewingTarget> */
    private static function targets(array $f): Collection
    {
        return self::scoped(WsSewingTarget::query(), $f, 'target_date')->where('is_off', false)
            ->when($f['buyer_id'], fn ($q) => $q->where('buyer_id', $f['buyer_id']))
            ->when($f['order'], fn ($q) => $q->where('order_style', $f['order']))
            ->when($f['style'], fn ($q) => $q->where('order_style', 'ilike', '%'.addcslashes($f['style'], '%_\\').'%'))
            ->orderBy('target_date')->orderBy('floor_id')->orderBy('sort_order')->get()
            ->sortBy(fn (WsSewingTarget $t) => $t->target_date->toDateString().' '.self::floorCode($t->floor_id).' '.self::lineCode($t->planning_line_id))->values();
    }

    private static function scoped(Builder $q, array $f, string $dateColumn): Builder
    {
        return $q->whereBetween($dateColumn, [$f['from'], $f['to']])
            ->when($f['floors'] !== null, fn ($q) => $q->whereIn('floor_id', $f['floors']))
            ->when($f['lines'] !== null, fn ($q) => $q->whereIn('planning_line_id', $f['lines']));
    }

    /** @return array{date: string, floor: ?string, line: ?string} */
    private static function where(WsSewingTarget $t): array
    {
        return ['date' => $t->target_date->toDateString(), 'floor' => self::floorCode($t->floor_id), 'line' => self::lineCode($t->planning_line_id)];
    }

    /**
     * The total row: the columns named added up; a percentage worked out again from its two totals (never averaged).
     *
     * @param  array<string, string>  $columns
     * @param  array<int|string, string|array{0: string, 1: string}>  $sum
     */
    private static function totals(array $columns, array $rows, array $sum): array
    {
        $out = array_fill_keys(array_keys($columns), null);
        foreach ($sum as $k => $v) {
            if (is_int($k)) {
                $out[$v] = round(array_sum(array_map(fn ($r) => (float) ($r[$v] ?? 0), $rows)), 2);
            }
        }
        foreach ($sum as $k => $v) {
            if (is_string($k)) {
                $out[$k] = self::pct((float) $out[$v[0]], (float) $out[$v[1]]);
            }
        }

        return $out;
    }

    private static function pct(float $part, float $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 2) : 0.0;
    }

    private static array $floors = [];

    private static array $lines = [];

    private static array $buyers = [];

    private static function floorCode(?string $id): ?string
    {
        return $id ? (self::$floors[$id] ??= DB::table('floors')->where('id', $id)->value('code')) : null;
    }

    private static function lineCode(?string $id): ?string
    {
        return $id ? (self::$lines[$id] ??= DB::table('planning_lines')->where('id', $id)->value('code')) : null;
    }

    private static function buyerName(?string $id): ?string
    {
        return $id ? (self::$buyers[$id] ??= Buyer::query()->whereKey($id)->value('short_name')) : null;
    }
}

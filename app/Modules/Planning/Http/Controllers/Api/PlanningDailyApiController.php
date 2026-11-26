<?php

declare(strict_types=1);

namespace App\Modules\Planning\Http\Controllers\Api;

use App\Modules\HrPayroll\Models\HrShift;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Planning\Models\PlanningDailyPlan;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Planning\Models\PlanningMasterPlan;
use App\Modules\Planning\Models\PlanningMasterPlanDay;
use App\Modules\Planning\Support\PlanningEngine;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Support\Clock;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Capacity Planning → Daily Plan, Daily Plan List and Plan vs Actual &
 * Forecast (AnnotechERP 12/02), as Brickland keeps them. Only an order with a
 * locked master plan is planned by day: its plan lines for the day load with
 * the line master's manpower, machines, hours and efficiency (an HR shift
 * sets the hours), the day's capacity and target follow, and the output made is
 * typed once the day has come. Confirm Day closes the day's resources. Plan
 * vs Actual reads every locked plan's original commitment, the days recorded
 * and the forecast. The master plan itself is never changed here.
 */
class PlanningDailyApiController extends ApiController
{
    /** The orders a day can be planned for: those with a locked master plan. */
    public function orders(): JsonResponse
    {
        $this->allow('planning.plan.view');
        $plans = PlanningMasterPlan::query()->where('status', 'LOCKED')->orderBy('sewing_start')->get();
        $orders = Order::query()->whereIn('id', $plans->pluck('order_id'))->get()->keyBy('id');
        $buyers = Buyer::query()->pluck('short_name', 'id');

        return ApiResponse::ok($plans->map(fn (PlanningMasterPlan $p) => [
            'value' => $orders[$p->order_id]->order_no,
            'label' => implode(' · ', array_filter([$orders[$p->order_id]->order_no, $buyers[$orders[$p->order_id]->buyer_id] ?? null, 'sewing '.$p->sewing_start?->toDateString().' → '.$p->sewing_end?->toDateString()])),
        ])->values()->all());
    }

    /** One order's day: its plan lines with what is saved, the forecast, and every order planned that day. */
    public function day(Request $request): JsonResponse
    {
        $this->allow('planning.plan.view');
        $date = $this->date($request->query('date'));
        $orderNo = trim((string) $request->query('order', ''));

        return ApiResponse::ok([
            'date' => $date->toDateString(),
            'entry' => $orderNo !== '' ? $this->entry($date, $this->lockedPlan($orderNo)) : null,
            'dashboard' => $this->dashboard($date),
        ]);
    }

    /** Save Daily Plan: the day's lines for one order, replacing what was saved; actual output only once the day has come. */
    public function save(Request $request): JsonResponse
    {
        $this->allow('planning.plan.manage');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'date' => ['required', 'date'], 'order_no' => ['required', 'string'], 'rows' => ['required', 'array', 'min:1'],
            'rows.*.line_id' => ['required', 'distinct', Rule::exists('planning_lines', 'id')->where('company_id', $company)],
            'rows.*.shift_id' => ['nullable', Rule::exists('hr_shifts', 'id')->where('company_id', $company)],
            'rows.*.manpower' => ['required', 'numeric', 'min:1'], 'rows.*.machines' => ['required', 'numeric', 'min:0'],
            'rows.*.hours' => ['required', 'numeric', 'gt:0', 'max:24'], 'rows.*.efficiency' => ['required', 'numeric', 'gt:0', 'max:150'],
            'rows.*.daily_target' => ['required', 'integer', 'min:0'], 'rows.*.typed_output' => ['nullable', 'integer', 'min:0'], 'rows.*.remarks' => ['nullable', 'string', 'max:500'],
        ], ['rows.required' => __('planning.plan.daily.v.rows'), 'rows.min' => __('planning.plan.daily.v.rows')]);
        $date = $this->date($data['date']);
        $plan = $this->lockedPlan($data['order_no']);
        ksort($data['rows']);
        $rows = array_values($data['rows']);
        $future = $date->gt(CarbonImmutable::today());
        $errors = [];
        foreach ($rows as $i => $r) {
            if ($future && ($r['typed_output'] ?? null) !== null) {
                $errors["rows.{$i}.typed_output"] = __('planning.plan.daily.v.future_output');
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $existing = PlanningDailyPlan::query()->where('order_id', $plan->order_id)->whereDate('plan_date', $date)->get()->keyBy('planning_line_id');
        $master = PlanningMasterPlanDay::query()->where('master_plan_id', $plan->id)->whereDate('day', $date)->pluck('target', 'planning_line_id');
        $user = $this->caller()->auditUserId();
        DB::transaction(function () use ($rows, $existing, $master, $plan, $date, $user): void {
            $kept = [];
            foreach ($rows as $r) {
                $current = $existing->get($r['line_id']);
                // A confirmed day keeps its resources; only the output and remarks change.
                if ($current?->status === 'CONFIRMED') {
                    $current->update(['typed_output' => $r['typed_output'] ?? null, 'remarks' => $r['remarks'] ?? null]);
                    $kept[] = $current->id;

                    continue;
                }
                [, $daily] = PlanningEngine::capacity((float) $r['manpower'], (float) $r['hours'], (float) $r['efficiency'], (float) $plan->smv);
                $values = [
                    'master_plan_id' => $plan->id, 'shift_id' => $r['shift_id'] ?? null, 'manpower' => (int) $r['manpower'], 'machines' => (int) $r['machines'],
                    'hours' => $r['hours'], 'efficiency' => $r['efficiency'], 'smv' => $plan->smv, 'daily_capacity' => $daily, 'master_target' => (int) ($master[$r['line_id']] ?? 0),
                    'daily_target' => (int) $r['daily_target'], 'typed_output' => $r['typed_output'] ?? null, 'remarks' => $r['remarks'] ?? null,
                ];
                $row = $current
                    ? tap($current)->update($values)
                    : PlanningDailyPlan::query()->create($values + ['plan_date' => $date->toDateString(), 'order_id' => $plan->order_id, 'planning_line_id' => $r['line_id'], 'status' => 'PLANNED', 'created_by' => $user]);
                $kept[] = $row->id;
            }
            PlanningDailyPlan::query()->where('order_id', $plan->order_id)->whereDate('plan_date', $date)->where('status', 'PLANNED')->whereNotIn('id', $kept)->delete();
        });

        return ApiResponse::ok(['date' => $date->toDateString(), 'entry' => $this->entry($date, $plan), 'dashboard' => $this->dashboard($date)]);
    }

    /** Confirm Day: the day's saved lines for the order are confirmed — resources closed, output still typed. */
    public function confirm(Request $request): JsonResponse
    {
        $this->allow('planning.plan.manage');
        $data = $request->validate(['date' => ['required', 'date'], 'order_no' => ['required', 'string']]);
        $date = $this->date($data['date']);
        $plan = $this->lockedPlan($data['order_no']);
        $n = PlanningDailyPlan::query()->where('order_id', $plan->order_id)->whereDate('plan_date', $date)->update(['status' => 'CONFIRMED']);
        if ($n === 0) {
            throw ValidationException::withMessages(['date' => __('planning.plan.daily.v.nothing_saved')]);
        }

        return ApiResponse::ok(['date' => $date->toDateString(), 'entry' => $this->entry($date, $plan), 'dashboard' => $this->dashboard($date)]);
    }

    /** Daily Plan List: saved days by date window, line, status and search. */
    public function index(Request $request): JsonResponse
    {
        $this->allow('planning.plan.view');

        return ApiResponse::ok(['rows' => $this->listRows($request), 'lines' => PlanningLine::query()->orderBy('code')->get()->map(fn ($l) => ['value' => $l->id, 'label' => $l->code])->all()]);
    }

    /** Plan vs Actual & Forecast: every locked plan's commitment, progress and forecast; then line by line, day by day. */
    public function planVsActual(Request $request): JsonResponse
    {
        $this->allow('planning.plan.view');
        $plans = PlanningMasterPlan::query()->where('status', 'LOCKED')->orderBy('sewing_start')->get();
        $orders = Order::query()->whereIn('id', $plans->pluck('order_id'))->get()->keyBy('id');
        $rows = $this->pvaRows($request, $plans, $orders);
        $counts = $rows->countBy('status');

        // Day by day: the master days and saved daily plans of the locked plans, in the window and on the line asked.
        $from = $request->query('from');
        $to = $request->query('to');
        $line = $request->query('line');
        $inWindow = fn ($w, string $col) => $w->whereIn('master_plan_id', $plans->pluck('id'))
            ->when($from, fn ($x) => $x->whereDate($col, '>=', $from))->when($to, fn ($x) => $x->whereDate($col, '<=', $to))->when($line, fn ($x) => $x->where('planning_line_id', $line));
        $cells = [];
        foreach ($inWindow(PlanningMasterPlanDay::query(), 'day')->get() as $d) {
            $cells[$d->master_plan_id.'|'.$d->planning_line_id.'|'.$d->day->toDateString()] = ['plan' => $d->master_plan_id, 'line' => $d->planning_line_id, 'date' => $d->day->toDateString(), 'target' => $d->target, 'daily' => null];
        }
        foreach ($inWindow(PlanningDailyPlan::query(), 'plan_date')->get() as $d) {
            $key = $d->master_plan_id.'|'.$d->planning_line_id.'|'.$d->plan_date->toDateString();
            $cells[$key] ??= ['plan' => $d->master_plan_id, 'line' => $d->planning_line_id, 'date' => $d->plan_date->toDateString(), 'target' => 0];
            $cells[$key]['daily'] = $d;
        }
        uasort($cells, fn ($a, $b) => [$a['date'], $a['plan'], $a['line']] <=> [$b['date'], $b['plan'], $b['line']]);
        // The balance runs on from what each order made before the window.
        $made = $from ? PlanningDailyPlan::query()->whereIn('master_plan_id', $plans->pluck('id'))->whereDate('plan_date', '<', $from)->groupBy('master_plan_id')
            ->selectRaw('master_plan_id, coalesce(sum(typed_output), 0) as made')->pluck('made', 'master_plan_id')->map(fn ($v) => (int) $v)->all() : [];
        $lineCodes = PlanningLine::query()->pluck('code', 'id');
        $planOf = $plans->keyBy('id');
        $progress = [];
        foreach ($cells as $c) {
            $p = $planOf[$c['plan']];
            $dp = $c['daily'];
            $made[$p->id] = ($made[$p->id] ?? 0) + (int) ($dp?->typed_output ?? 0);
            $progress[] = [
                'date' => $c['date'], 'line' => $lineCodes[$c['line']] ?? '—', 'order_no' => $orders[$p->order_id]->order_no,
                'plan_target' => $dp?->daily_target ?? $c['target'], 'actual' => $dp?->typed_output, 'variance' => $dp?->typed_output !== null ? $dp->typed_output - $dp->daily_target : null,
                'balance' => max(0, $p->order_qty - $made[$p->id]), 'remarks' => $dp?->remarks,
            ];
        }

        return ApiResponse::ok([
            'as_of' => CarbonImmutable::today()->toDateString(),
            // A plan not started yet counts as on track, as Brickland counts it.
            'tiles' => [
                'locked' => $rows->count(), 'AT_RISK' => (int) ($counts['AT_RISK'] ?? 0), 'DELAYED' => (int) ($counts['DELAYED'] ?? 0),
                'ON_TRACK' => (int) ($counts['ON_TRACK'] ?? 0) + (int) ($counts['NOT_STARTED'] ?? 0), 'AHEAD' => (int) ($counts['AHEAD'] ?? 0), 'COMPLETED' => (int) ($counts['COMPLETED'] ?? 0),
            ],
            'rows' => $rows->all(), 'progress' => $progress, 'lines' => PlanningLine::query()->orderBy('code')->get()->map(fn ($l) => ['value' => $l->id, 'label' => $l->code])->all(),
        ]);
    }

    /** Plan vs Actual's Export: the orders table as on screen, with its search. */
    public function planVsActualExcel(Request $request): StreamedResponse
    {
        $this->allow('planning.plan.view');
        $plans = PlanningMasterPlan::query()->where('status', 'LOCKED')->orderBy('sewing_start')->get();
        $rows = $this->pvaRows($request, $plans, Order::query()->whereIn('id', $plans->pluck('order_id'))->get()->keyBy('id'));
        $head = ['order_no', 'buyer', 'plan_no', 'lines', 'order_qty', 'produced', 'remaining', 'original_completion', 'current_forecast', 'ship_date', 'variance_days', 'extra_capacity', 'status'];

        return response()->streamDownload(function () use ($rows, $head): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(array_map(fn ($k) => __("planning.plan.pvx.col.{$k}"), $head)));
            foreach ($rows as $r) {
                $writer->addRow(Row::fromValues(array_map(fn ($k) => match ($k) {
                    'plan_no' => $r['plan_no'].' V'.$r['version'],
                    'status' => __("planning.plan.fc.statuses_title.{$r['status']}"),
                    default => $r[$k],
                }, $head)));
            }
            $writer->close();
        }, 'plan-vs-actual-'.now()->format('Ymd-His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Plan vs Actual's orders: each locked plan's commitment, progress and forecast, matching the search.
     *
     * @param  Collection<int, PlanningMasterPlan>  $plans
     * @param  Collection<string, Order>  $orders
     * @return Collection<int, array<string, mixed>>
     */
    private function pvaRows(Request $request, Collection $plans, Collection $orders): Collection
    {
        $q = mb_strtolower(trim((string) $request->query('q', '')));
        $buyers = Buyer::query()->pluck('short_name', 'id');
        $codes = DB::table('planning_master_plan_lines as m')->join('planning_lines as l', 'l.id', '=', 'm.planning_line_id')->whereIn('m.master_plan_id', $plans->pluck('id'))
            ->orderBy('m.sort_order')->get(['m.master_plan_id', 'l.code'])->groupBy('master_plan_id')->map(fn ($g) => $g->pluck('code')->implode(', '));

        return $plans->map(function (PlanningMasterPlan $p) use ($orders, $buyers, $codes): array {
            $f = PlanningEngine::forecast($p);
            $o = $orders[$p->order_id];

            return [
                'order_no' => $o->order_no, 'buyer' => $buyers[$o->buyer_id] ?? null, 'plan_no' => $p->plan_no, 'version' => $p->version, 'lines' => $codes[$p->id] ?? '',
                'order_qty' => $p->order_qty, 'produced' => $f['produced'], 'percent' => $f['percent'], 'remaining' => $f['remaining'], 'original_completion' => $f['original_completion'],
                'current_forecast' => $f['current_forecast'], 'ship_date' => $f['ship_date'], 'variance_days' => $f['variance_days'], 'extra_capacity' => $f['extra_capacity'], 'status' => $f['status'],
            ];
        })->filter(fn ($r) => $q === '' || str_contains(mb_strtolower(implode(' ', [$r['order_no'], $r['buyer'], $r['plan_no'], $r['lines']])), $q))->values();
    }

    /** @return array<string, mixed> */
    private function entry(CarbonImmutable $date, PlanningMasterPlan $plan): array
    {
        $o = Order::query()->findOrFail($plan->order_id);
        $saved = PlanningDailyPlan::query()->where('order_id', $plan->order_id)->whereDate('plan_date', $date)->get()->keyBy('planning_line_id');
        $master = PlanningMasterPlanDay::query()->where('master_plan_id', $plan->id)->whereDate('day', $date)->get()->keyBy('planning_line_id');
        $planLines = DB::table('planning_master_plan_lines')->where('master_plan_id', $plan->id)->orderBy('sort_order')->get()->keyBy('planning_line_id');
        // The day's lines: what is saved, else the plan lines that hold the day.
        $lineIds = $saved->isNotEmpty() ? $saved->keys() : $master->keys();
        $lines = PlanningLine::query()->whereIn('id', $lineIds->merge($planLines->keys())->unique())->get()->keyBy('id');
        $floors = DB::table('floors')->pluck('code', 'id');
        $row = function (string $lineId) use ($saved, $master, $planLines, $lines, $floors, $plan): array {
            $s = $saved->get($lineId);
            $l = $lines[$lineId];
            $pl = $planLines->get($lineId);
            $manpower = $s?->manpower ?? $pl->manpower ?? $l->std_manpower;
            $hours = $s?->hours ?? (float) ($pl->hours ?? $l->std_hours);
            $efficiency = $s?->efficiency ?? (float) ($pl->efficiency ?? $l->std_efficiency);
            [$hourly, $daily] = PlanningEngine::capacity((float) $manpower, (float) $hours, (float) $efficiency, (float) $plan->smv);

            return [
                'line_id' => $lineId, 'code' => $l->code, 'floor' => $floors[$l->floor_id] ?? null, 'shift_id' => $s?->shift_id,
                'manpower' => $manpower, 'machines' => $s?->machines ?? $pl->machines ?? $l->std_machines, 'hours' => $hours, 'efficiency' => $efficiency,
                'master' => ['manpower' => $pl->manpower ?? $l->std_manpower, 'machines' => $pl->machines ?? $l->std_machines, 'hours' => (float) ($pl->hours ?? $l->std_hours), 'efficiency' => (float) ($pl->efficiency ?? $l->std_efficiency)],
                'smv' => $plan->smv, 'hourly_capacity' => $hourly, 'daily_capacity' => $daily, 'master_target' => (int) ($master[$lineId]->target ?? 0),
                'daily_target' => $s?->daily_target ?? (int) ($master[$lineId]->target ?? $daily), 'typed_output' => $s?->typed_output, 'remarks' => $s?->remarks,
                'status' => $s ? $s->status : 'NOT_SAVED',
            ];
        };

        return [
            'order_no' => $o->order_no, 'buyer' => Buyer::query()->whereKey($o->buyer_id)->value('short_name'), 'qty' => $plan->order_qty, 'smv' => $plan->smv,
            'plan_no' => $plan->plan_no, 'version' => $plan->version, 'sewing_start' => $plan->sewing_start?->toDateString(), 'sewing_end' => $plan->sewing_end?->toDateString(),
            'can_type' => $date->lte(CarbonImmutable::today()), 'confirmed' => $saved->isNotEmpty() && $saved->every(fn ($d) => $d->status === 'CONFIRMED'), 'saved' => $saved->isNotEmpty(),
            'rows' => $lineIds->map($row)->values()->all(),
            'shifts' => HrShift::query()->where('active', true)->orderBy('code')->get()->map(fn (HrShift $x) => ['value' => $x->id, 'label' => "{$x->code} — {$x->name} (".Clock::range($x->start_time, $x->end_time).')', 'hours' => self::shiftHours($x)])->values()->all(),
            'add_lines' => PlanningLine::query()->where('active', true)->whereNotIn('id', $lineIds->all())->orderBy('code')->get()->map(fn ($l) => ['value' => $l->id, 'label' => $l->code, 'row' => (function () use ($l, $plan, $floors): array {
                [$hourly, $daily] = PlanningEngine::capacity((float) $l->std_manpower, (float) $l->std_hours, (float) $l->std_efficiency, (float) $plan->smv);

                return ['line_id' => $l->id, 'code' => $l->code, 'floor' => $floors[$l->floor_id] ?? null, 'shift_id' => null, 'manpower' => $l->std_manpower, 'machines' => $l->std_machines,
                    'hours' => (float) $l->std_hours, 'efficiency' => (float) $l->std_efficiency, 'master' => ['manpower' => $l->std_manpower, 'machines' => $l->std_machines, 'hours' => (float) $l->std_hours, 'efficiency' => (float) $l->std_efficiency],
                    'smv' => $plan->smv, 'hourly_capacity' => $hourly, 'daily_capacity' => $daily, 'master_target' => 0, 'daily_target' => $daily, 'typed_output' => null, 'remarks' => null, 'status' => 'NOT_SAVED'];
            })()])->values()->all(),
            'forecast' => PlanningEngine::forecast($plan),
        ];
    }

    /** @return list<array<string, mixed>> every order planned on the day, line by line */
    private function dashboard(CarbonImmutable $date): array
    {
        $rows = PlanningDailyPlan::query()->whereDate('plan_date', $date)->orderBy('order_id')->get();
        $orders = Order::query()->whereIn('id', $rows->pluck('order_id'))->pluck('order_no', 'id');
        $codes = PlanningLine::query()->pluck('code', 'id');

        return $rows->map(fn (PlanningDailyPlan $d) => [
            'order_no' => $orders[$d->order_id] ?? null, 'line' => $codes[$d->planning_line_id] ?? null, 'manpower' => $d->manpower, 'machines' => $d->machines,
            'hours' => $d->hours, 'efficiency' => $d->efficiency, 'capacity' => $d->daily_capacity, 'target' => $d->daily_target, 'actual' => $d->typed_output, 'status' => $d->status,
        ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function listRows(Request $request): array
    {
        $q = mb_strtolower(trim((string) $request->query('q', '')));
        $rows = PlanningDailyPlan::query()
            ->when($request->query('from'), fn ($w, $v) => $w->whereDate('plan_date', '>=', $v))->when($request->query('to'), fn ($w, $v) => $w->whereDate('plan_date', '<=', $v))
            ->when($request->query('line'), fn ($w, $v) => $w->where('planning_line_id', $v))->when($request->query('status'), fn ($w, $v) => $w->where('status', $v))
            ->orderByDesc('plan_date')->limit(1000)->get();
        $orders = Order::query()->whereIn('id', $rows->pluck('order_id')->unique())->pluck('order_no', 'id');
        $plans = PlanningMasterPlan::query()->whereIn('id', $rows->pluck('master_plan_id')->unique())->get()->keyBy('id');
        $codes = PlanningLine::query()->pluck('code', 'id');

        return $rows->map(fn (PlanningDailyPlan $d) => [
            'date' => $d->plan_date->toDateString(), 'order_no' => $orders[$d->order_id] ?? null, 'master_plan' => isset($plans[$d->master_plan_id]) ? $plans[$d->master_plan_id]->plan_no.' V'.$plans[$d->master_plan_id]->version : null,
            'line' => $codes[$d->planning_line_id] ?? null, 'manpower' => $d->manpower, 'machines' => $d->machines, 'hours' => $d->hours, 'efficiency' => $d->efficiency, 'smv' => $d->smv,
            'daily_capacity' => $d->daily_capacity, 'master_target' => $d->master_target, 'daily_target' => $d->daily_target, 'typed_output' => $d->typed_output, 'status' => $d->status, 'remarks' => $d->remarks,
        ])->filter(fn ($r) => $q === '' || str_contains(mb_strtolower(implode(' ', [$r['order_no'], $r['master_plan'], $r['line'], $r['remarks']])), $q))
            ->sortBy([['date', 'desc'], ['order_no', 'asc'], ['line', 'asc']])->values()->all();
    }

    /** A shift's working hours as the HR shift master keeps them, else end less start (across midnight) less the break. */
    public static function shiftHours(HrShift $shift): float
    {
        if ($shift->working_hours !== null) {
            return (float) $shift->working_hours;
        }
        $minutes = CarbonImmutable::parse($shift->start_time)->diffInMinutes(CarbonImmutable::parse($shift->end_time), false);
        if ($minutes <= 0) {
            $minutes += 1440;
        }

        return round(max(0, $minutes - (int) $shift->break_min) / 60, 2);
    }

    private function lockedPlan(string $orderNo): PlanningMasterPlan
    {
        $order = Order::query()->where('order_no', strtoupper(trim($orderNo)))->first();

        return ($order ? PlanningMasterPlan::query()->where('order_id', $order->id)->where('status', 'LOCKED')->first() : null)
            ?? throw ValidationException::withMessages(['order_no' => __('planning.plan.daily.v.no_locked', ['order' => strtoupper(trim($orderNo))])]);
    }

    private function date(mixed $value): CarbonImmutable
    {
        try {
            return $value ? CarbonImmutable::parse((string) $value)->startOfDay() : CarbonImmutable::today();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date' => __('planning.plan.daily.v.date')]);
        }
    }
}

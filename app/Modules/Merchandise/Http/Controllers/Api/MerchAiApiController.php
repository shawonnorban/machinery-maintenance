<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\AiAlert;
use App\Modules\Merchandise\Models\AiOrderScore;
use App\Modules\Merchandise\Models\AiRun;
use App\Modules\Merchandise\Models\AiTaskScore;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OrderTna;
use App\Modules\Merchandise\Models\TnaTask;
use App\Modules\Merchandise\Support\MerchAi\ClaudeWriter;
use App\Modules\Merchandise\Support\MerchAi\RuleEngine;
use App\Modules\Merchandise\Support\TrimOrders;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Merchandise Desk → the AI groups (AnnotechERP 06/02–06/04): the last run's
 * snapshot — every scored order, every ranked task, the alerts, the
 * deadlines ahead and the book's aggregates (by buyer, department and task
 * owner) — which each of the twenty-eight screens reads its own view of;
 * Run analysis; and an alert acknowledged, resolved or dismissed.
 */
class MerchAiApiController extends ApiController
{
    public function snapshot(): JsonResponse
    {
        $this->allow('merchandise.order.view');

        return ApiResponse::ok($this->present(AiRun::query()->orderByDesc('run_at')->first()));
    }

    /** Run analysis: the rule engine over the live book, and Claude's written analysis on top when a key is set. */
    public function run(RuleEngine $engine, ClaudeWriter $claude): JsonResponse
    {
        $this->allow('merchandise.order.manage');
        $run = $engine->run($this->caller()->auditUserId());
        $claude->write($run);

        return ApiResponse::ok($this->present($run->refresh()));
    }

    public function alert(AiAlert $alert, string $action): JsonResponse
    {
        $this->allow('merchandise.order.manage');
        $status = ['acknowledge' => 'ACKNOWLEDGED', 'resolve' => 'RESOLVED', 'dismiss' => 'DISMISSED', 'reopen' => 'OPEN'][$action] ?? null;
        if ($status === null) {
            throw ValidationException::withMessages(['action' => __('merchandise.ai.bad_action')]);
        }
        $alert->update(['status' => $status, 'status_at' => now(), 'status_by' => $this->caller()->auditUserId()]);

        return ApiResponse::ok($this->alertRow($alert->refresh(), Order::query()->whereKey($alert->order_id)->value('order_no')));
    }

    /** @return array<string, mixed> */
    private function present(?AiRun $run): array
    {
        $claude = app(ClaudeWriter::class)->enabled();
        if ($run === null) {
            return ['run' => null, 'claude' => $claude, 'orders' => [], 'tasks' => [], 'alerts' => [], 'deadlines' => [], 'buyers' => [], 'departments' => [], 'owners' => [], 'weights' => RuleEngine::WEIGHTS, 'totals' => null];
        }
        $today = CarbonImmutable::today();
        $scores = AiOrderScore::query()->where('run_id', $run->id)->orderByDesc('risk')->get();
        $orders = Order::query()->whereIn('id', $scores->pluck('order_id'))->get()->keyBy('id');
        $rows = TrimOrders::rows($orders->values());
        $orderRows = $scores->map(function (AiOrderScore $s) use ($orders, $rows, $today): array {
            $o = $orders[$s->order_id] ?? null;
            $r = $rows[$s->order_id] ?? [];

            return [
                'order_no' => $o?->order_no, 'buyer' => $r['buyer'] ?? null, 'style_no' => $r['style_no'] ?? null, 'style_desc' => $r['style_desc'] ?? null, 'qty' => (int) ($o?->total_qty ?? 0),
                'value' => $o && $o->unit_price !== null ? round($o->total_qty * $o->unit_price, 2) : null, 'currency' => $o?->currency, 'conf_date' => $o?->conf_date?->toDateString(),
                'ship_date' => $o?->ship_date?->toDateString(), 'days_left' => $o?->ship_date ? (int) $today->diffInDays($o->ship_date, false) : null,
            ] + $s->only(['risk', 'health', 'band', 'delay_probability', 'expected_delay', 'confidence', 'material_risk', 'production_risk', 'approval_risk', 'tna_risk', 'shipment_risk',
                'production_progress', 'task_completion', 'tasks_total', 'tasks_done', 'next_milestone', 'factors', 'action', 'action_source', 'summary']) + ['next_milestone_date' => $s->next_milestone_date?->toDateString()];
        })->values();
        $byOrder = $orderRows->keyBy('order_no');

        // Tasks: the run's ranking, with the calendar line, its task, department and owner.
        $taskScores = AiTaskScore::query()->where('run_id', $run->id)->orderBy('rank')->get();
        $lines = OrderTna::query()->whereIn('id', $taskScores->pluck('order_tna_id'))->get()->keyBy('id');
        $tasks = TnaTask::query()->whereIn('id', $lines->pluck('tna_task_id')->unique())->get()->keyBy('id');
        $owners = RuleEngine::owners($tasks->keys());
        $departments = RuleEngine::departments($tasks->values());
        $taskRows = $taskScores->map(function (AiTaskScore $t) use ($lines, $tasks, $owners, $departments, $orders): array {
            $line = $lines[$t->order_tna_id] ?? null;
            $task = $line ? ($tasks[$line->tna_task_id] ?? null) : null;

            return [
                'id' => $t->id, 'rank' => $t->rank, 'task' => $task?->name ?? '—', 'department' => $task ? ($departments[$task->id] ?? null) : null, 'owner' => $task ? ($owners[$task->id] ?? null) : null,
                'order_no' => $orders[$t->order_id]?->order_no ?? null, 'plan_date' => $line?->plan_date?->toDateString(), 'days_late' => $t->days_late, 'slip_days' => $t->slip_days,
                'slip_risk' => $t->slip_risk, 'priority' => $t->priority, 'recommendation' => $t->recommendation, 'milestone' => (bool) ($task?->is_start || $task?->is_end),
            ];
        })->values();

        // Every calendar line of a live order (closed ones too), for completion by department and by owner.
        $all = OrderTna::query()->whereIn('order_id', $scores->pluck('order_id'))->where('required', true)->get();
        $allTasks = TnaTask::query()->whereIn('id', $all->pluck('tna_task_id')->unique())->get()->keyBy('id');
        $allDepts = RuleEngine::departments($allTasks->values());
        $allOwners = RuleEngine::owners($allTasks->keys());
        $tally = fn ($group) => $group->map(fn ($lines, $name): array => [
            'name' => $name, 'tasks' => $lines->count(), 'done' => $lines->whereNotNull('actual_date')->count(), 'pending' => $lines->whereNull('actual_date')->count(),
            'overdue' => $lines->whereNull('actual_date')->filter(fn ($l) => $l->plan_date && $l->plan_date->lt($today))->count(),
            'on_time' => $lines->whereNotNull('actual_date')->filter(fn ($l) => $l->plan_date && $l->actual_date->lte($l->plan_date))->count(),
            'completion' => $lines->count() ? round($lines->whereNotNull('actual_date')->count() / $lines->count() * 100, 1) : 0,
        ])->sortByDesc('overdue')->values();

        // What lands ahead: milestones (a calendar's opening and closing events) and shipments, six weeks out.
        $deadlines = $all->toBase()->whereNull('actual_date')->filter(fn ($l) => $l->plan_date && $l->plan_date->betweenIncluded($today, $today->addDays(42)) && (($allTasks[$l->tna_task_id] ?? null)?->is_start || ($allTasks[$l->tna_task_id] ?? null)?->is_end))
            ->map(fn ($l) => ['date' => $l->plan_date->toDateString(), 'kind' => 'MILESTONE', 'what' => $allTasks[$l->tna_task_id]->name, 'order_no' => $orders[$l->order_id]?->order_no])
            ->merge($orders->filter(fn ($o) => $o->ship_date && $o->ship_date->betweenIncluded($today, $today->addDays(42)))->map(fn ($o) => ['date' => $o->ship_date->toDateString(), 'kind' => 'SHIPMENT', 'what' => __('merchandise.ai.shipment_of', ['qty' => number_format($o->total_qty)]), 'order_no' => $o->order_no]))
            ->map(fn (array $d) => $d + ['risk' => $byOrder[$d['order_no']]['risk'] ?? null, 'band' => $byOrder[$d['order_no']]['band'] ?? null, 'days' => (int) $today->diffInDays(CarbonImmutable::parse($d['date']), false)])
            ->sortBy('date')->values();

        $buyers = $orderRows->groupBy(fn ($o) => $o['buyer'] ?? '—')->map(fn ($g, $buyer): array => [
            'buyer' => $buyer, 'orders' => $g->count(), 'qty' => $g->sum('qty'), 'value' => round((float) $g->sum('value'), 2),
            'at_risk' => $g->whereIn('band', ['CRITICAL', 'HIGH'])->count(), 'avg_health' => round((float) $g->avg('health'), 1),
            'avg_progress' => round((float) $g->avg('production_progress'), 1), 'on_time' => $g->where('expected_delay', 0)->count(),
        ])->sortByDesc('at_risk')->values();

        $alerts = AiAlert::query()->orderByRaw("case severity when 'CRITICAL' then 0 when 'HIGH' then 1 when 'MEDIUM' then 2 else 3 end")->orderByDesc('updated_at')->get();
        $alertOrders = Order::query()->whereIn('id', $alerts->pluck('order_id')->unique())->pluck('order_no', 'id');
        $live = $orderRows->count();
        $pending = $taskRows->count();

        return [
            'run' => ['run_at' => $run->run_at->toIso8601String(), 'engine' => $run->engine, 'orders' => $run->orders, 'tasks' => $run->tasks, 'alerts' => $run->alerts, 'narrative' => $run->narrative,
                'run_by' => $run->run_by ? User::query()->whereKey($run->run_by)->value('name') : null],
            'claude' => $claude,
            'orders' => $orderRows->all(), 'tasks' => $taskRows->all(),
            'alerts' => $alerts->map(fn (AiAlert $a) => $this->alertRow($a, $alertOrders[$a->order_id] ?? null))->values()->all(),
            'deadlines' => $deadlines->all(), 'buyers' => $buyers->all(),
            'departments' => $tally($all->groupBy(fn ($l) => $allDepts[$l->tna_task_id] ?? __('merchandise.ai.no_department')))->all(),
            'owners' => $tally($all->filter(fn ($l) => isset($allOwners[$l->tna_task_id]))->groupBy(fn ($l) => $allOwners[$l->tna_task_id]))->all(),
            'weights' => RuleEngine::WEIGHTS,
            'totals' => [
                'live' => $live, 'completion' => $live ? round((float) $orderRows->avg('production_progress'), 1) : 0, 'at_risk' => $orderRows->whereIn('band', ['CRITICAL', 'HIGH'])->count(),
                'delayed' => $orderRows->where('expected_delay', '>', 0)->count(), 'pending' => $pending, 'overdue' => $taskRows->where('days_late', '>', 0)->count(),
                'due_week' => $taskRows->filter(fn ($t) => $t['days_late'] <= 0 && $t['days_late'] >= -7)->count(),
                'value' => round((float) $orderRows->where('currency', 'USD')->sum('value'), 2), 'avg_health' => $live ? round((float) $orderRows->avg('health'), 1) : 0,
                'avg_risk' => $live ? round((float) $orderRows->avg('risk'), 1) : 0, 'avg_confidence' => $live ? (int) round((float) $orderRows->avg('confidence')) : 0,
                'avg_probability' => $live ? round((float) $orderRows->avg('delay_probability'), 1) : 0, 'expected_delay' => (int) $orderRows->sum('expected_delay'),
                'open_alerts' => $alerts->where('status', 'OPEN')->count(), 'task_completion' => $all->count() ? round($all->whereNotNull('actual_date')->count() / $all->count() * 100, 1) : 0,
                'tasks_total' => $all->count(), 'tasks_done' => $all->whereNotNull('actual_date')->count(),
                'factor_hot' => collect(['material', 'production', 'tna', 'approval', 'shipment'])->mapWithKeys(fn ($f) => [$f => $orderRows->where($f.'_risk', '>=', 60)->count()])->all(),
                'factor_avg' => collect(['material', 'production', 'tna', 'approval', 'shipment'])->mapWithKeys(fn ($f) => [$f => $live ? round((float) $orderRows->avg($f.'_risk'), 1) : 0])->all(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function alertRow(AiAlert $a, ?string $orderNo): array
    {
        return $a->only(['id', 'kind', 'severity', 'title', 'detail', 'advice', 'status']) + ['order_no' => $orderNo, 'status_at' => $a->status_at?->toIso8601String(), 'raised_at' => $a->created_at?->toIso8601String()];
    }
}

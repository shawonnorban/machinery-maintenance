<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Support\MerchAi;

use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\AccBooking;
use App\Modules\Merchandise\Models\AccBookingLine;
use App\Modules\Merchandise\Models\AccPurchaseOrderLine;
use App\Modules\Merchandise\Models\AccReceipt;
use App\Modules\Merchandise\Models\AccReceiptLine;
use App\Modules\Merchandise\Models\AccRequisition;
use App\Modules\Merchandise\Models\AiAlert;
use App\Modules\Merchandise\Models\AiOrderScore;
use App\Modules\Merchandise\Models\AiRun;
use App\Modules\Merchandise\Models\AiTaskScore;
use App\Modules\Merchandise\Models\FabricBooking;
use App\Modules\Merchandise\Models\LabdipShade;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OrderTna;
use App\Modules\Merchandise\Models\PurchasedFabricIndent;
use App\Modules\Merchandise\Models\PurchasedFabricMrr;
use App\Modules\Merchandise\Models\PurchasedFabricMrrLine;
use App\Modules\Merchandise\Models\PurchasedFabricPoLine;
use App\Modules\Merchandise\Models\SampleFeedback;
use App\Modules\Merchandise\Models\TnaTask;
use App\Modules\Merchandise\Models\TnaTaskGroup;
use App\Modules\Merchandise\Models\TnaTaskRight;
use App\Modules\Merchandise\Support\TrimOrders;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The rule engine behind the Merchandise Desk AI groups (AnnotechERP
 * 06/02–06/04), as Brickland's: every live order is scored from four factors
 * — shipment (schedule pressure: how much of the order window is gone against
 * how much is made), material (fabric and trims booked, ordered, received),
 * production (floor output against the window) and approval (lab dips,
 * samples and approval milestones still out) — into a composite risk, a
 * health score, a delay probability, an expected slip and a confidence; every
 * open T&A task is ranked; and alerts are raised that outlive the run.
 *
 * Cutting, sewing, finishing and shipment are not built yet, so production
 * and shipped quantities read zero, as they do on Brickland's own book.
 */
final class RuleEngine
{
    /** Composite weights, as Brickland's Risk Driver Analysis shows them: delivery, material, production pace, time & action, approvals. */
    public const WEIGHTS = ['shipment' => 0.22, 'material' => 0.22, 'production' => 0.24, 'tna' => 0.18, 'approval' => 0.14];

    private CarbonImmutable $today;

    public function run(?string $userId): AiRun
    {
        $this->today = CarbonImmutable::today();
        $orders = TrimOrders::open()->filter(fn (Order $o) => $o->ship_date !== null)->values();
        $rows = TrimOrders::rows($orders);
        $ids = $orders->pluck('id');
        $tna = OrderTna::query()->whereIn('order_id', $ids)->where('required', true)->orderBy('plan_date')->get()->groupBy('order_id');
        $tasks = TnaTask::query()->whereIn('id', $tna->flatten()->pluck('tna_task_id')->unique())->get()->keyBy('id');
        $material = $this->materialChain($ids);
        $approvals = $this->approvals($ids);

        return DB::transaction(function () use ($orders, $rows, $tna, $tasks, $material, $approvals, $userId): AiRun {
            $run = AiRun::query()->create(['run_at' => now(), 'engine' => 'RULE', 'run_by' => $userId]);
            $scores = [];
            foreach ($orders as $order) {
                $scores[$order->id] = $this->score($order, $rows[$order->id], $tna[$order->id] ?? collect(), $tasks, $material[$order->id], $approvals[$order->id]);
                AiOrderScore::query()->create(['run_id' => $run->id, 'order_id' => $order->id] + collect($scores[$order->id])->except(['alerts'])->all());
            }
            $ranked = $this->rankTasks($tna, $tasks, $rows, $scores);
            foreach ($ranked as $t) {
                AiTaskScore::query()->create(['run_id' => $run->id] + $t);
            }
            $raised = $this->alerts($run, $scores);
            $run->update(['orders' => count($scores), 'tasks' => count($ranked), 'alerts' => $raised]);

            return $run->refresh();
        });
    }

    /**
     * One order: the four sub-scores, the composite and what follows from it.
     *
     * @return array<string, mixed>
     */
    private function score(Order $order, array $row, Collection $calendar, Collection $tasks, array $material, array $approval): array
    {
        $conf = CarbonImmutable::parse($order->conf_date ?? $order->order_date ?? $order->created_at)->startOfDay();
        $ship = CarbonImmutable::parse($order->ship_date)->startOfDay();
        $window = max(1, (int) $conf->diffInDays($ship, false));
        $elapsed = min(1.0, max(0.0, $conf->diffInDays($this->today, false) / $window));
        $daysLeft = (int) $this->today->diffInDays($ship, false);
        // No cutting, sewing or finishing module yet: nothing is produced or shipped.
        $produced = 0.0;
        $shipped = 0.0;

        $total = $calendar->count();
        $done = $calendar->whereNotNull('actual_date')->count();
        $overdue = $calendar->whereNull('actual_date')->filter(fn (OrderTna $t) => $t->plan_date && $t->plan_date->lt($this->today))->count();
        $taskCompletion = $total ? round($done / $total * 100, 1) : 0.0;

        $coverage = 25 * count(array_filter($material['have']));
        $materialRisk = $this->clamp((100 - $coverage) * (0.6 + 0.4 * $elapsed));
        $productionRisk = $this->clamp((100 - $produced) * min(1.0, $elapsed * 1.05));
        // No approval milestone or no calendar at all is a gap in itself, not a clean sheet.
        $approvalRisk = $approval['total'] === 0 ? 30.0 : $this->clamp($approval['outstanding'] / $approval['total'] * 100 * (0.5 + 0.5 * $elapsed) + ($approval['overdue'] > 0 ? 20 : 0));
        $shipmentRisk = $daysLeft < 0 && $shipped < 100 ? 100.0 : $this->clamp($elapsed * 100 - $produced);
        $tnaRisk = $total === 0 ? 40.0 : $this->clamp($overdue / $total * 100 * 0.7 + max(0, $elapsed * 100 - $taskCompletion) * 0.3);
        $risk = round(self::WEIGHTS['shipment'] * $shipmentRisk + self::WEIGHTS['material'] * $materialRisk + self::WEIGHTS['production'] * $productionRisk
            + self::WEIGHTS['tna'] * $tnaRisk + self::WEIGHTS['approval'] * $approvalRisk, 1);
        $band = $risk >= 75 ? 'CRITICAL' : ($risk >= 60 ? 'HIGH' : ($risk >= 40 ? 'MEDIUM' : 'LOW'));
        // Health is the other side of risk, lifted by what has actually been done.
        $health = round($this->clamp(0.6 * (100 - $risk) + 0.4 * (($produced + $taskCompletion) / 2)), 1);
        $probability = round($daysLeft < 0 && $produced < 100 ? max(95.0, 100 / (1 + exp(-($risk - 55) / 12))) : 100 / (1 + exp(-($risk - 55) / 12)), 1);
        // The slip: the floor's share of the window still to run, plus the weeks a missing material chain costs, past the days left.
        $needed = (1 - $produced / 100) * $window * 0.6 + 21 * (1 - $coverage / 100);
        $expected = $produced >= 100 ? 0 : (int) min(365, max(0, round($needed - max(0, $daysLeft)) + max(0, -$daysLeft)));
        $confidence = (int) min(95, 40 + ($total > 0 ? 15 : 0) + ($material['any'] ? 15 : 0) + ($approval['total'] > 0 ? 10 : 0) + ($order->conf_date ? 10 : 0) + ($order->unit_price ? 5 : 0));

        $next = $calendar->whereNull('actual_date')->sortBy(fn (OrderTna $t) => $t->plan_date?->toDateString() ?? '9999')
            ->first(fn (OrderTna $t) => ($tasks[$t->tna_task_id] ?? null)?->is_start || ($tasks[$t->tna_task_id] ?? null)?->is_end)
            ?? $calendar->whereNull('actual_date')->sortBy(fn (OrderTna $t) => $t->plan_date?->toDateString() ?? '9999')->first();

        $no = $order->order_no;
        $when = $daysLeft >= 0 ? trans_choice('merchandise.ai.days_to_ship', $daysLeft, ['n' => $daysLeft]) : trans_choice('merchandise.ai.days_past_ship', -$daysLeft, ['n' => -$daysLeft]);
        $missing = $material['missing'] === [] ? null : implode(', ', array_map(fn (string $k) => __("merchandise.ai.missing.{$k}"), $material['missing']));
        $factors = [
            'shipment' => __('merchandise.ai.factor.shipment', ['when' => $when, 'shipped' => $this->pct($shipped)]),
            'material' => $missing ?? __('merchandise.ai.factor.material_ok'),
            'production' => __('merchandise.ai.factor.production', ['produced' => $this->pct($produced), 'window' => $this->pct($elapsed * 100)]),
            'tna' => $total === 0 ? __('merchandise.ai.factor.no_tna') : __('merchandise.ai.factor.tna', ['overdue' => $overdue, 'done' => $done, 'total' => $total]),
            'approval' => $approval['total'] === 0 ? __('merchandise.ai.factor.no_approval') : __('merchandise.ai.factor.approval', ['out' => $approval['outstanding'], 'total' => $approval['total']]),
        ];
        $action = match (true) {
            $daysLeft >= 0 && $daysLeft <= 14 && $produced < 50 => __('merchandise.ai.action.exposed', ['no' => $no, 'days' => $daysLeft, 'produced' => $this->pct($produced), 'window' => $this->pct($elapsed * 100)]),
            $missing !== null => __('merchandise.ai.action.material', ['no' => $no, 'missing' => $missing, 'when' => $when]),
            $overdue > 0 => trans_choice('merchandise.ai.action.tasks', $overdue, ['no' => $no, 'n' => $overdue]),
            $approvalRisk >= 60 => __('merchandise.ai.action.approval', ['no' => $no, 'n' => $approval['outstanding']]),
            default => __('merchandise.ai.action.on_track', ['no' => $no]),
        };

        return [
            'risk' => $risk, 'health' => $health, 'band' => $band, 'delay_probability' => $probability, 'expected_delay' => $expected, 'confidence' => $confidence,
            'material_risk' => round($materialRisk, 1), 'production_risk' => round($productionRisk, 1), 'approval_risk' => round($approvalRisk, 1), 'tna_risk' => round($tnaRisk, 1), 'shipment_risk' => round($shipmentRisk, 1),
            'production_progress' => $produced, 'task_completion' => $taskCompletion, 'tasks_total' => $total, 'tasks_done' => $done,
            'next_milestone' => $next ? (($tasks[$next->tna_task_id] ?? null)?->name) : null, 'next_milestone_date' => $next?->plan_date?->toDateString(),
            'factors' => $factors, 'action' => $action, 'action_source' => 'RULE',
            'summary' => __('merchandise.ai.summary', ['buyer' => $row['buyer'] ?? '—', 'qty' => number_format($row['qty']), 'produced' => $this->pct($produced), 'done' => $done, 'total' => $total, 'band' => __('merchandise.ai.band.'.$band), 'days' => $expected]),
            // Kept for the alerts, not stored on the score.
            'alerts' => compact('daysLeft', 'produced', 'missing', 'overdue', 'approval', 'no', 'when') + ['qty' => $row['qty'], 'buyer' => $row['buyer'], 'ship' => $ship->toDateString(), 'band' => $band, 'overdue_tasks' => $calendar->whereNull('actual_date')->filter(fn (OrderTna $t) => $t->plan_date && $t->plan_date->lt($this->today))->take(3)->map(fn (OrderTna $t) => ($tasks[$t->tna_task_id] ?? null)?->name)->filter()->values()->all()],
        ];
    }

    /**
     * Each order's material chain: fabric booked and approved, accessories booked (or an approved indent), a purchase order placed, anything received into store.
     *
     * @return array<string, array{have: array<string, bool>, missing: list<string>, any: bool}>
     */
    private function materialChain(Collection $ids): array
    {
        $fabric = FabricBooking::query()->whereIn('order_id', $ids)->where('status', 'APPROVED')->pluck('order_id')->flip();
        $requisitions = AccRequisition::query()->whereIn('order_id', $ids)->where('dummy', false)->get(['id', 'order_id', 'status']);
        $bookedAcc = AccBooking::query()->whereIn('order_id', $ids)->whereIn('id', AccBookingLine::query()->select('booking_id'))->pluck('order_id')->flip();
        $approvedAcc = $requisitions->where('status', 'APPROVED')->pluck('order_id')->flip();
        $accPoLines = AccPurchaseOrderLine::query()->whereIn('requisition_id', $requisitions->pluck('id'))->get(['id', 'requisition_id']);
        $reqOrder = $requisitions->pluck('order_id', 'id');
        $indents = PurchasedFabricIndent::query()->whereIn('order_id', $ids)->pluck('order_id', 'id');
        $fabPoLines = PurchasedFabricPoLine::query()->whereIn('indent_id', $indents->keys())->get(['id', 'indent_id']);
        $poOrders = $accPoLines->map(fn ($l) => $reqOrder[$l->requisition_id] ?? null)->merge($fabPoLines->map(fn ($l) => $indents[$l->indent_id] ?? null))->filter()->flip();
        $accReceived = AccReceiptLine::query()->whereIn('po_line_id', $accPoLines->pluck('id'))->whereIn('mrr_id', AccReceipt::query()->where('status', 'FINALIZED')->select('id'))->pluck('po_line_id');
        $fabReceived = PurchasedFabricMrrLine::query()->whereIn('po_line_id', $fabPoLines->pluck('id'))->whereIn('mrr_id', PurchasedFabricMrr::query()->where('status', 'FINALIZED')->select('id'))->pluck('po_line_id');
        $receivedOrders = $accPoLines->whereIn('id', $accReceived)->map(fn ($l) => $reqOrder[$l->requisition_id] ?? null)
            ->merge($fabPoLines->whereIn('id', $fabReceived)->map(fn ($l) => $indents[$l->indent_id] ?? null))->filter()->flip();

        $out = [];
        foreach ($ids as $id) {
            $have = ['fabric' => isset($fabric[$id]), 'accessories' => isset($bookedAcc[$id]) || isset($approvedAcc[$id]), 'po' => isset($poOrders[$id]), 'received' => isset($receivedOrders[$id])];
            $out[$id] = ['have' => $have, 'missing' => array_keys(array_filter($have, fn (bool $v) => ! $v)), 'any' => in_array(true, $have, true) || $requisitions->contains('order_id', $id)];
        }

        return $out;
    }

    /**
     * Each order's approvals: lab dip shades and sample feedback not yet approved, and approval milestones on its calendar.
     *
     * @return array<string, array{total: int, outstanding: int, overdue: int}>
     */
    private function approvals(Collection $ids): array
    {
        $shades = LabdipShade::query()->whereIn('order_id', $ids)->get(['order_id', 'status', 'target_date'])->groupBy('order_id');
        $samples = SampleFeedback::query()->whereIn('order_id', $ids)->get(['order_id', 'status'])->groupBy('order_id');
        $milestones = OrderTna::query()->whereIn('order_id', $ids)->where('required', true)
            ->whereIn('tna_task_id', TnaTask::query()->where(fn ($q) => $q->where('is_sample', true)->orWhere('name', 'ilike', '%approv%'))->select('id'))
            ->get(['order_id', 'plan_date', 'actual_date'])->groupBy('order_id');
        $out = [];
        foreach ($ids as $id) {
            $s = $shades[$id] ?? collect();
            $f = $samples[$id] ?? collect();
            $m = $milestones[$id] ?? collect();
            $out[$id] = [
                'total' => $s->count() + $f->count() + $m->count(),
                'outstanding' => $s->where('status', '!=', 'APPROVED')->count() + $f->where('status', '!=', 'APPROVED')->count() + $m->whereNull('actual_date')->count(),
                'overdue' => $s->where('status', '!=', 'APPROVED')->filter(fn ($x) => $x->target_date && $x->target_date->lt($this->today))->count()
                    + $m->whereNull('actual_date')->filter(fn ($x) => $x->plan_date && $x->plan_date->lt($this->today))->count(),
            ];
        }

        return $out;
    }

    /**
     * Every open T&A task on a live order, ranked: lateness first, then the risk of the order it sits on, a milestone ahead of the rest.
     *
     * @return list<array<string, mixed>>
     */
    private function rankTasks(Collection $tna, Collection $tasks, array $rows, array $scores): array
    {
        $open = [];
        foreach ($tna as $orderId => $calendar) {
            foreach ($calendar->whereNull('actual_date') as $t) {
                $task = $tasks[$t->tna_task_id] ?? null;
                $late = $t->plan_date ? (int) $t->plan_date->diffInDays($this->today, false) : 0;
                // Priority 0–100: half the order's risk, lateness up to a month, a task due this week, and a milestone once it is close (a week out) or late.
                $milestone = ($task?->is_start || $task?->is_end) && $late >= -7;
                $score = round(min(100, 0.5 * $scores[$orderId]['risk'] + min(30, max(0, $late)) + ($late <= 0 && $late >= -7 ? 7 + $late : 0) + ($milestone ? 10 : 0)), 1);
                $slip = $late > 0 ? $late + 3 : 0;
                // How likely the task is to cost the order its date: the order's own delay probability once late, fading over six weeks ahead of plan.
                $slipRisk = round($scores[$orderId]['delay_probability'] * ($late > 0 ? 1 : max(0, 1 + $late / 42)), 1);
                $no = $rows[$orderId]['order_no'];
                $open[] = [
                    'order_tna_id' => $t->id, 'order_id' => $orderId, 'days_late' => $late, 'slip_days' => $slip, 'slip_risk' => $slipRisk, 'priority' => $score,
                    'recommendation' => match (true) {
                        $late > 0 => trans_choice('merchandise.ai.task.late', $late, ['days' => $late, 'no' => $no, 'slip' => $slip]),
                        $late >= -7 => trans_choice('merchandise.ai.task.soon', -$late, ['days' => -$late, 'no' => $no, 'task' => $task?->name ?? '—']),
                        default => __('merchandise.ai.task.on_plan', ['date' => $t->plan_date?->format('d M Y') ?? '—', 'no' => $no]),
                    },
                ];
            }
        }
        usort($open, fn (array $a, array $b) => [$b['priority'], $b['days_late']] <=> [$a['priority'], $a['days_late']]);

        return array_map(fn (array $t, int $i) => ['rank' => $i + 1] + $t, $open, array_keys($open));
    }

    /** The run's findings: raised, refreshed, or (once their reason is gone) resolved by the engine; a dismissed one stays dismissed. */
    private function alerts(AiRun $run, array $scores): int
    {
        $found = [];
        foreach ($scores as $orderId => $s) {
            $a = $s['alerts'];
            $severity = $a['band'];
            if ($a['daysLeft'] >= 0 && $a['daysLeft'] <= 14 && $a['produced'] < 50) {
                $found[] = [$orderId, 'SHIP_SOON', $severity, trans_choice('merchandise.ai.alert.ship_soon.title', $a['daysLeft'], ['no' => $a['no'], 'days' => $a['daysLeft'], 'produced' => $this->pct($a['produced'])]),
                    __('merchandise.ai.alert.ship_soon.detail', ['qty' => number_format($a['qty']), 'buyer' => $a['buyer'] ?? '—', 'date' => $a['ship'], 'left' => $this->pct(100 - $a['produced'])]), __('merchandise.ai.alert.ship_soon.advice')];
            }
            if ($a['daysLeft'] < 0) {
                $found[] = [$orderId, 'PAST_SHIP', 'CRITICAL', trans_choice('merchandise.ai.alert.past_ship.title', -$a['daysLeft'], ['no' => $a['no'], 'days' => -$a['daysLeft'], 'produced' => $this->pct($a['produced'])]),
                    __('merchandise.ai.alert.past_ship.detail', ['qty' => number_format($a['qty']), 'buyer' => $a['buyer'] ?? '—', 'date' => $a['ship']]), __('merchandise.ai.alert.past_ship.advice')];
            }
            if ($a['missing'] !== null && $a['daysLeft'] <= 45) {
                $found[] = [$orderId, 'MATERIAL', $severity, __('merchandise.ai.alert.material.title', ['no' => $a['no']]), $a['missing'], __('merchandise.ai.alert.material.advice', ['when' => $a['when']])];
            }
            if ($a['overdue'] > 0) {
                $found[] = [$orderId, 'TASKS', $a['overdue'] >= 3 ? 'HIGH' : 'MEDIUM', trans_choice('merchandise.ai.alert.tasks.title', $a['overdue'], ['n' => $a['overdue'], 'no' => $a['no']]),
                    implode(', ', $a['overdue_tasks']), __('merchandise.ai.alert.tasks.advice')];
            }
            if ($a['approval']['outstanding'] > 0 && $a['daysLeft'] <= 30) {
                $found[] = [$orderId, 'APPROVAL', 'MEDIUM', trans_choice('merchandise.ai.alert.approval.title', $a['approval']['outstanding'], ['n' => $a['approval']['outstanding'], 'no' => $a['no']]),
                    __('merchandise.ai.alert.approval.detail', ['out' => $a['approval']['outstanding'], 'total' => $a['approval']['total']]), __('merchandise.ai.alert.approval.advice')];
            }
        }
        $keys = [];
        foreach ($found as [$orderId, $kind, $severity, $title, $detail, $advice]) {
            $keys[] = $orderId.'|'.$kind;
            $alert = AiAlert::query()->firstOrNew(['order_id' => $orderId, 'kind' => $kind]);
            $reopen = $alert->exists && $alert->status === 'RESOLVED';
            $alert->fill(['severity' => $severity, 'title' => $title, 'detail' => $detail ?: '—', 'advice' => $advice, 'last_run_id' => $run->id, 'first_run_id' => $alert->first_run_id ?? $run->id]);
            if (! $alert->exists || $reopen) {
                $alert->status = 'OPEN';
                $alert->status_at = null;
                $alert->status_by = null;
            }
            $alert->save();
        }
        // A reason the run no longer finds is resolved by the engine.
        AiAlert::query()->whereIn('status', ['OPEN', 'ACKNOWLEDGED'])->get()->reject(fn (AiAlert $a) => in_array($a->order_id.'|'.$a->kind, $keys, true))
            ->each(fn (AiAlert $a) => $a->update(['status' => 'RESOLVED', 'status_at' => now(), 'status_by' => null]));

        return count($found);
    }

    private function clamp(float $v): float
    {
        return max(0.0, min(100.0, $v));
    }

    private function pct(float $v): string
    {
        return rtrim(rtrim(number_format($v, 0, '.', ''), '0'), '.') ?: '0';
    }

    /** @return array<string, string> each task's owner: the first user who may update it, or none */
    public static function owners(Collection $taskIds): array
    {
        $rights = TnaTaskRight::query()->whereIn('tna_task_id', $taskIds)->where('can_update', true)->get(['tna_task_id', 'user_id']);
        $users = User::query()->whereIn('id', $rights->pluck('user_id')->unique())->pluck('name', 'id');

        return $rights->groupBy('tna_task_id')->map(fn ($r) => $users[$r->first()->user_id] ?? null)->filter()->all();
    }

    /** @return array<string, string> each task's department: its T&A task group */
    public static function departments(Collection $tasks): array
    {
        $groups = TnaTaskGroup::query()->whereIn('id', $tasks->pluck('tna_task_group_id')->filter()->unique())->pluck('name', 'id');

        return $tasks->mapWithKeys(fn (TnaTask $t) => [$t->id => $groups[$t->tna_task_group_id] ?? null])->filter()->all();
    }
}

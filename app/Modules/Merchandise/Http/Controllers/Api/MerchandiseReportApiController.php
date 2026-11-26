<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Budget;
use App\Modules\Merchandise\Models\BudgetLine;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\FabricBooking;
use App\Modules\Merchandise\Models\FabricBookingLine;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OrderSize;
use App\Modules\Merchandise\Models\OrderTna;
use App\Modules\Merchandise\Models\SampleBatch;
use App\Modules\Merchandise\Models\SampleLine;
use App\Modules\Merchandise\Models\SampleProgram;
use App\Modules\Merchandise\Models\SampleType;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\TnaTask;
use App\Modules\Merchandise\Models\TnaTaskGroup;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Merchandise Desk → Reports (AnnotechERP 06/13): Order Merchandising Reports,
 * as Brickland works it — one of the module's reports picked, a From Date,
 * and Preview reads every figure from the module's own tables at that
 * moment (nothing is cached). Each report answers its columns and rows (and
 * a chart where it is one); a report whose figures come from a module not
 * yet built — cutting, sewing, shipment, stores, accessories — reads zero
 * there, as Brickland's own does, and says so.
 */
class MerchandiseReportApiController extends ApiController
{
    /** Brickland's report set, in its order. */
    public const REPORTS = [
        'sample-production', 'bulk-fabric-booking', 'budget-sheet', 'task-tna-fail', 'dept-tna-fail', 'style-dept-tna-fail', 'buyer-dept-tna-fail',
        'order-accessories-status', 'tna-pass-fail-summary', 'buyer-costing-margin', 'buyer-cm-earning', 'order-budget-costing', 'budget-vs-actual',
        'budget-vs-actual-chart', 'order-confirmation-list', 'buyer-accessories-pr', 'order-tracking', 'textile-budget-vs-actual', 'import-fabric-receive',
        'po-actual-shipment', 'purchase-fabric-stock', 'finish-fabric-stock', 'trims-stock', 'order-size-breakdown',
    ];

    /** Reports whose figures belong to modules not built yet: they read zero (or nothing) there. */
    private const PENDING_MODULES = [
        'order-accessories-status' => 'accessories', 'buyer-accessories-pr' => 'accessories', 'order-tracking' => 'production',
        'import-fabric-receive' => 'stores', 'po-actual-shipment' => 'shipment', 'purchase-fabric-stock' => 'stores', 'finish-fabric-stock' => 'stores', 'trims-stock' => 'stores',
    ];

    public function show(Request $request, string $report): JsonResponse
    {
        $this->allow('merchandise.report.view');
        $request->merge(['report' => $report]);
        $data = $request->validate(['report' => ['required', Rule::in(self::REPORTS)], 'from' => ['nullable', 'date']]);
        $from = CarbonImmutable::parse($data['from'] ?? CarbonImmutable::now()->startOfYear())->startOfDay();
        $method = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $report))));
        [$columns, $rows, $chart] = $this->{$method}($from) + [2 => null];

        return ApiResponse::ok([
            'report' => $report, 'from' => $from->toDateString(), 'columns' => $columns, 'rows' => array_values($rows), 'chart' => $chart,
            'pending_module' => self::PENDING_MODULES[$report] ?? null,
        ]);
    }

    // ── Sample, booking and budget ──────────────────────────────────────────

    private function sampleProduction(CarbonImmutable $from): array
    {
        $programs = SampleProgram::query()->where('program_date', '>=', $from)->get()->keyBy('id');
        $lines = SampleLine::query()->whereIn('program_id', $programs->keys())->orderBy('program_id')->orderBy('sort_order')->get();
        $batches = SampleBatch::query()->whereIn('id', $lines->pluck('batch_id')->filter())->get()->keyBy('id');
        $types = SampleType::query()->whereIn('id', $lines->pluck('sample_type_id')->filter())->pluck('name', 'id');
        $orders = Order::query()->whereIn('id', $programs->pluck('order_id')->filter())->pluck('order_no', 'id');

        return [$this->cols(['program_no', 'program_date:date', 'batch_no', 'order_no', 'sample_type', 'item', 'colour', 'size', 'req_qty:number', 'sewn:number', 'delivered:number', 'rejected:number', 'status:status']),
            $lines->map(function (SampleLine $l) use ($programs, $batches, $types, $orders): array {
                $p = $programs[$l->program_id];

                return ['program_no' => $p->program_no, 'program_date' => $p->program_date?->toDateString(), 'batch_no' => $l->batch_id ? ($batches[$l->batch_id]->batch_no ?? null) : null,
                    'order_no' => $p->order_id ? ($orders[$p->order_id] ?? null) : null, 'sample_type' => $types[$l->sample_type_id] ?? null, 'item' => $l->item, 'colour' => $l->colour, 'size' => $l->size,
                    'req_qty' => (float) $l->req_qty, 'sewn' => (float) $l->sewn_qty, 'delivered' => (float) $l->delivered_qty, 'rejected' => (float) $l->rejected_qty, 'status' => $l->status];
            })->all()];
    }

    private function bulkFabricBooking(CarbonImmutable $from): array
    {
        $bookings = FabricBooking::query()->where('booking_date', '>=', $from)->orderByDesc('booking_date')->orderByDesc('id')->get();
        $orders = Order::query()->whereIn('id', $bookings->pluck('order_id')->filter())->pluck('order_no', 'id');
        $qty = FabricBookingLine::query()->whereIn('booking_id', $bookings->pluck('id'))->get(['booking_id', 'finish_qty', 'cons_unit', 'uom'])->groupBy('booking_id');

        return [$this->cols(['booking_no', 'booking_type:badge', 'order_no', 'booking_date:date', 'status:status', 'qty_label']),
            $bookings->map(fn (FabricBooking $b): array => ['booking_no' => $b->booking_no, 'booking_type' => $b->booking_type, 'order_no' => $b->order_id ? ($orders[$b->order_id] ?? null) : null,
                'booking_date' => $b->booking_date->toDateString(), 'status' => $b->status, 'qty_label' => FabricBookingLine::unitTotals($qty[$b->id] ?? collect())['qty_label']])->all()];
    }

    private function budgetSheet(CarbonImmutable $from): array
    {
        return [$this->cols(['budget_no', 'order_no', 'style', 'currency', 'order_value:number', 'budget_cost:number', 'margin:number', 'approved:yesno']),
            $this->budgets($from)->map(fn (array $b): array => ['budget_no' => $b['budget_no'], 'order_no' => $b['order_no'], 'style' => $b['style'], 'currency' => $b['currency'],
                'order_value' => $b['value'], 'budget_cost' => $b['cost'], 'margin' => round($b['value'] - $b['cost'], 2), 'approved' => $b['approved']])->all()];
    }

    private function orderBudgetCosting(CarbonImmutable $from): array
    {
        return [$this->cols(['order_no', 'style', 'qty:number', 'order_value:number', 'budget_cost:number', 'margin:number', 'margin_pct:number']),
            $this->budgets($from)->map(fn (array $b): array => ['order_no' => $b['order_no'], 'style' => $b['style'], 'qty' => $b['qty'], 'order_value' => $b['value'], 'budget_cost' => $b['cost'],
                'margin' => round($b['value'] - $b['cost'], 2), 'margin_pct' => $b['value'] > 0 ? round(100 * ($b['value'] - $b['cost']) / $b['value'], 2) : null])->all()];
    }

    private function budgetVsActual(CarbonImmutable $from): array
    {
        $rows = [];
        foreach ($this->budgets($from) as $b) {
            foreach ($b['heads'] as $head => [$budget, $actual]) {
                if ($budget > 0 || $actual > 0) {
                    $rows[] = ['budget_no' => $b['budget_no'], 'order_no' => $b['order_no'], 'head' => $head, 'budget' => $budget, 'actual' => $actual, 'variance' => round($budget - $actual, 2)];
                }
            }
        }

        return [$this->cols(['budget_no', 'order_no', 'head:head', 'budget:number', 'actual:number', 'variance:number']), $rows];
    }

    private function budgetVsActualChart(CarbonImmutable $from): array
    {
        $rows = $this->budgets($from)->map(function (array $b): array {
            $budget = round(array_sum(array_column($b['heads'], 0)), 2);
            $actual = round(array_sum(array_column($b['heads'], 1)), 2);

            return ['order_no' => $b['order_no'] ?? $b['budget_no'], 'budget' => $budget, 'actual' => $actual, 'variance' => round($budget - $actual, 2)];
        })->values();

        return [$this->cols(['order_no', 'budget:number', 'actual:number', 'variance:number']), $rows->all(),
            ['label' => 'order_no', 'series' => ['budget', 'actual']]];
    }

    private function textileBudgetVsActual(CarbonImmutable $from): array
    {
        $rows = [];
        foreach ($this->budgets($from) as $b) {
            foreach (Budget::ACTUAL_GROUPS['fabric_process'] as $head) {
                [$budget, $actual] = $b['heads'][$head] ?? [0.0, 0.0];
                if ($budget > 0 || $actual > 0) {
                    $rows[] = ['order_no' => $b['order_no'], 'head' => $head, 'budget' => $budget, 'actual' => $actual, 'variance' => round($budget - $actual, 2),
                        'variance_pct' => $budget > 0 ? round(100 * ($budget - $actual) / $budget, 2) : null];
                }
            }
        }

        return [$this->cols(['order_no', 'head:head', 'budget:number', 'actual:number', 'variance:number', 'variance_pct:number']), $rows];
    }

    // ── Buyers and orders ───────────────────────────────────────────────────

    private function buyerCostingMargin(CarbonImmutable $from): array
    {
        $orders = $this->orders($from);
        $costs = $this->budgets(CarbonImmutable::create(2000))->keyBy('order_id');

        return [$this->cols(['buyer', 'orders:number', 'qty:number', 'order_value:number', 'avg_fob:number', 'budget_cost:number', 'net_margin:number']),
            $orders->groupBy('buyer')->map(function (Collection $g, string $buyer) use ($costs): array {
                $value = round($g->sum('value'), 2);
                $qty = (float) $g->sum('qty');
                $cost = round($g->sum(fn ($o) => $costs[$o['id']]['cost'] ?? 0), 2);

                return ['buyer' => $buyer ?: null, 'orders' => $g->count(), 'qty' => $qty, 'order_value' => $value, 'avg_fob' => $qty > 0 ? round($value / $qty, 4) : null,
                    'budget_cost' => $cost, 'net_margin' => round($value - $cost, 2)];
            })->sortByDesc('order_value')->values()->all()];
    }

    private function buyerCmEarning(CarbonImmutable $from): array
    {
        $orders = $this->orders($from);
        $costs = $this->budgets(CarbonImmutable::create(2000))->keyBy('order_id');

        return [$this->cols(['buyer', 'month', 'orders:number', 'qty:number', 'cm:number', 'cm_per_dz:number']),
            $orders->groupBy(fn ($o) => $o['buyer'].'|'.($o['ship_date'] ? substr($o['ship_date'], 0, 7) : ''))->map(function (Collection $g) use ($costs): array {
                $qty = (float) $g->sum('qty');
                $cm = round($g->sum(fn ($o) => $costs[$o['id']]['heads']['cm'][0] ?? 0), 2);

                return ['buyer' => $g->first()['buyer'], 'month' => $g->first()['ship_date'] ? substr($g->first()['ship_date'], 0, 7) : null, 'orders' => $g->count(), 'qty' => $qty,
                    'cm' => $cm, 'cm_per_dz' => $qty > 0 ? round($cm / $qty * 12, 4) : null];
            })->sortBy([['buyer', 'asc'], ['month', 'asc']])->values()->all()];
    }

    private function orderConfirmationList(CarbonImmutable $from): array
    {
        return [$this->cols(['buyer', 'order_no', 'style', 'conf_date:date', 'ship_date:date', 'qty:number', 'value:money']),
            $this->orders($from)->sortByDesc('conf_date')->map(fn ($o): array => ['buyer' => $o['buyer'], 'order_no' => $o['order_no'], 'style' => $o['style'], 'conf_date' => $o['conf_date'],
                'ship_date' => $o['ship_date'], 'qty' => $o['qty'], 'value' => round($o['value'], 2), 'currency' => $o['currency']])->values()->all()];
    }

    private function orderAccessoriesStatus(CarbonImmutable $from): array
    {
        return [$this->cols(['order_no', 'prs:number', 'bookings:number', 'sourced:number']),
            $this->orders($from)->sortBy('ship_date')->map(fn ($o): array => ['order_no' => $o['order_no'], 'prs' => 0, 'bookings' => 0, 'sourced' => 0])->values()->all()];
    }

    private function buyerAccessoriesPr(CarbonImmutable $from): array
    {
        return [$this->cols(['buyer', 'pr_no', 'pr_date:date', 'item', 'qty:number', 'status:status']), []];
    }

    private function orderTracking(CarbonImmutable $from): array
    {
        return [$this->cols(['order_no', 'ship_date:date', 'order_qty:number', 'cut:number', 'sewn:number', 'finished:number', 'shipped:number', 'shipped_pct:number']),
            $this->orders($from)->sortBy('ship_date')->map(fn ($o): array => ['order_no' => $o['order_no'], 'ship_date' => $o['ship_date'], 'order_qty' => $o['qty'],
                'cut' => 0, 'sewn' => 0, 'finished' => 0, 'shipped' => 0, 'shipped_pct' => 0])->values()->all()];
    }

    private function poActualShipment(CarbonImmutable $from): array
    {
        return [$this->cols(['po_no', 'order_no', 'ship_date:date', 'order_qty:number', 'shipped:number', 'balance:number', 'status:status']),
            $this->orders($from)->sortBy('ship_date')->map(fn ($o): array => ['po_no' => $o['po_no'], 'order_no' => $o['order_no'], 'ship_date' => $o['ship_date'], 'order_qty' => $o['qty'],
                'shipped' => 0, 'balance' => $o['qty'], 'status' => $o['status']])->values()->all()];
    }

    private function orderSizeBreakdown(CarbonImmutable $from): array
    {
        $orders = $this->orders($from)->keyBy('id');
        $sizes = OrderSize::query()->whereIn('order_id', $orders->keys())->orderBy('order_id')->orderBy('sort_order')->get(['order_id', 'colour', 'size', 'qty']);

        return [$this->cols(['order_no', 'colour', 'size', 'qty:number']),
            $sizes->map(fn ($s): array => ['order_no' => $orders[$s->order_id]['order_no'], 'colour' => $s->colour, 'size' => $s->size, 'qty' => (float) $s->qty])
                ->sortBy([['order_no', 'asc'], ['colour', 'asc'], ['size', 'asc']])->values()->all()];
    }

    // ── Fabric in hand ──────────────────────────────────────────────────────

    private function importFabricReceive(CarbonImmutable $from): array
    {
        return [$this->cols(['booking_no', 'order_no', 'booking_date:date', 'fabric', 'colour', 'qty:number', 'uom', 'received:number', 'balance:number']),
            $this->fabricLines($from, ['IMPORT'])];
    }

    private function purchaseFabricStock(CarbonImmutable $from): array
    {
        return [$this->cols(['booking_no', 'booking_type:badge', 'order_no', 'fabric', 'colour', 'qty:number', 'uom', 'received:number', 'balance:number']),
            $this->fabricLines($from, ['ADDITIONAL', 'BUFFER', 'IMPORT'])];
    }

    private function finishFabricStock(CarbonImmutable $from): array
    {
        return [$this->cols(['booking_no', 'order_no', 'fabric', 'colour', 'qty:number', 'uom', 'received:number', 'balance:number']),
            $this->fabricLines($from, ['BULK'])];
    }

    private function trimsStock(CarbonImmutable $from): array
    {
        return [$this->cols(['item', 'description', 'uom', 'received:number', 'issued:number', 'stock:number']), []];
    }

    // ── T&A ─────────────────────────────────────────────────────────────────

    private function taskTnaFail(CarbonImmutable $from): array
    {
        return [$this->cols(['order_no', 'task', 'plan_date:date', 'actual_date:date', 'days_late:number']),
            $this->milestones($from)->filter(fn ($m) => $m['failed'])->sortByDesc('days_late')->map(fn ($m): array => [
                'order_no' => $m['order_no'], 'task' => $m['task'], 'plan_date' => $m['plan_date'], 'actual_date' => $m['actual_date'], 'days_late' => $m['days_late'],
            ])->values()->all()];
    }

    private function deptTnaFail(CarbonImmutable $from): array
    {
        return $this->failBy($from, ['department']);
    }

    private function styleDeptTnaFail(CarbonImmutable $from): array
    {
        return $this->failBy($from, ['style', 'department']);
    }

    private function buyerDeptTnaFail(CarbonImmutable $from): array
    {
        return $this->failBy($from, ['buyer', 'department']);
    }

    private function tnaPassFailSummary(CarbonImmutable $from): array
    {
        return [$this->cols(['task', 'instances:number', 'done:number', 'open:number', 'failed:number']),
            $this->milestones($from)->groupBy('task')->map(fn (Collection $g, string $task): array => [
                'task' => $task, 'instances' => $g->count(), 'done' => $g->whereNotNull('actual_date')->count(), 'open' => $g->whereNull('actual_date')->count(), 'failed' => $g->where('failed', true)->count(),
            ])->sortBy(fn ($r) => $r['task'])->values()->all()];
    }

    /** Milestones counted by the given dimensions: how many there are, how many failed, and the share. */
    private function failBy(CarbonImmutable $from, array $by): array
    {
        return [$this->cols([...$by, 'tasks:number', 'failed:number', 'fail_pct:number']),
            $this->milestones($from)->groupBy(fn ($m) => implode('|', array_map(fn ($k) => $m[$k] ?? '', $by)))->map(function (Collection $g) use ($by): array {
                $failed = $g->where('failed', true)->count();

                return collect($by)->mapWithKeys(fn ($k) => [$k => $g->first()[$k]])->all() + ['tasks' => $g->count(), 'failed' => $failed, 'fail_pct' => round(100 * $failed / $g->count(), 1)];
            })->sortByDesc('failed')->values()->all()];
    }

    /**
     * Every planned milestone from the date on, with whether it failed: completed after its plan date, or still
     * open with the plan date past. Days late run to the actual date, or to today while open.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function milestones(CarbonImmutable $from): Collection
    {
        $today = CarbonImmutable::now()->startOfDay();
        $lines = OrderTna::query()->whereNotNull('plan_date')->where('plan_date', '>=', $from)->orderBy('plan_date')->get();
        $tasks = TnaTask::query()->whereIn('id', $lines->pluck('tna_task_id'))->get()->keyBy('id');
        $groups = TnaTaskGroup::query()->whereIn('id', $tasks->pluck('tna_task_group_id')->filter())->pluck('name', 'id');
        $orders = $this->orders(CarbonImmutable::create(2000), false)->keyBy('id');

        return $lines->filter(fn (OrderTna $l) => isset($orders[$l->order_id]))->map(function (OrderTna $l) use ($tasks, $groups, $orders, $today): array {
            $task = $tasks[$l->tna_task_id];
            $plan = CarbonImmutable::parse($l->plan_date);
            $end = $l->actual_date ? CarbonImmutable::parse($l->actual_date) : $today;
            $late = (int) $plan->diffInDays($end, false);
            $o = $orders[$l->order_id];

            return ['order_no' => $o['order_no'], 'buyer' => $o['buyer'], 'style' => $o['style'], 'task' => $task->name,
                'department' => $task->tna_task_group_id ? ($groups[$task->tna_task_group_id] ?? null) : null,
                'plan_date' => $plan->toDateString(), 'actual_date' => $l->actual_date?->toDateString(), 'days_late' => max(0, $late), 'failed' => $late > 0];
        })->values();
    }

    // ── Shared readers ──────────────────────────────────────────────────────

    /**
     * The orders confirmed (or, for the tracking reports, raised) from the date on, with buyer, style and value.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function orders(CarbonImmutable $from, bool $confirmedOnly = true): Collection
    {
        $orders = Order::query()->where('status', '!=', 'CANCELLED')
            ->when($confirmedOnly, fn ($q) => $q->whereIn('status', ['CONFIRMED', 'IN_PRODUCTION', 'SHIPPED', 'CLOSED'])->where(fn ($w) => $w->where('conf_date', '>=', $from)->orWhere(fn ($n) => $n->whereNull('conf_date')->where('created_at', '>=', $from))))
            ->get();
        $styles = Style::query()->whereIn('id', $orders->pluck('style_id')->filter())->get()->keyBy('id');
        $buyers = Buyer::query()->whereIn('id', $orders->pluck('buyer_id')->merge($styles->pluck('buyer_id'))->filter())->get(['id', 'name', 'short_name'])->keyBy('id');

        return $orders->map(function (Order $o) use ($styles, $buyers): array {
            $s = $o->style_id ? ($styles[$o->style_id] ?? null) : null;
            $b = $buyers[$o->buyer_id ?? $s?->buyer_id] ?? null;

            return ['id' => $o->id, 'order_no' => $o->order_no, 'po_no' => $o->po_no, 'buyer' => $b ? ($b->short_name ?: $b->name) : null, 'style' => $s?->styleNo() ?? $o->style_no,
                'conf_date' => $o->conf_date?->toDateString(), 'ship_date' => $o->ship_date?->toDateString(), 'qty' => (float) $o->total_qty, 'status' => $o->status,
                'value' => (float) $o->total_qty * (float) $o->unit_price, 'currency' => strtoupper((string) ($o->currency ?: 'USD'))];
        })->values();
    }

    /**
     * The budgets raised from the date on: their order, value, total cost (every head but FOB) and each head's
     * budget against its actual, read the way Budget vs Actual Costing reads them.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function budgets(CarbonImmutable $from): Collection
    {
        $budgets = Budget::query()->where('created_at', '>=', $from)->orderBy('budget_no')->get();
        $lines = BudgetLine::query()->whereIn('budget_id', $budgets->pluck('id'))->get(['budget_id', 'section', 'qty', 'rate'])->groupBy('budget_id');
        $orders = Order::query()->whereIn('id', $budgets->pluck('order_id')->filter())->get()->keyBy('id');
        $styles = Style::query()->whereIn('id', $budgets->pluck('style_id')->merge($orders->pluck('style_id'))->filter())->get()->keyBy('id');
        $sectionHead = ['YARN' => 'yarn', 'PURCHASED_FABRIC' => 'purchased_fabric', 'SEWING_ACC' => 'sewing_acc', 'FINISHING_ACC' => 'finishing_acc', 'GENERAL_ACC' => 'general_acc', 'SPECIAL_ACC' => 'special_acc'];

        return $budgets->map(function (Budget $b) use ($lines, $orders, $styles, $sectionHead): array {
            $mine = $lines[$b->id] ?? collect();
            $budgeted = collect($sectionHead)->mapWithKeys(fn ($head, $section) => [$head => round((float) $mine->where('section', $section)->sum(fn ($l) => $l->qty * $l->rate), 4)])->all()
                + array_map('floatval', $b->process_costs ?? []) + array_map('floatval', $b->overall_costs ?? []);
            $actual = $b->actual_costs ?? [];
            $heads = [];
            foreach (array_merge(...array_values(Budget::ACTUAL_GROUPS)) as $head) {
                $heads[$head] = [round((float) ($budgeted[$head] ?? 0), 2), round((float) ($actual[$head] ?? 0), 2)];
            }
            $o = $b->order_id ? ($orders[$b->order_id] ?? null) : null;
            $s = $styles[$b->style_id ?? $o?->style_id] ?? null;
            $value = $o ? (float) $o->total_qty * (float) $o->unit_price : (float) ($budgeted['fob'] ?? 0);

            return ['budget_no' => $b->budget_no, 'order_id' => $b->order_id, 'order_no' => $o?->order_no, 'style' => $s?->styleNo(), 'currency' => $b->currency,
                'qty' => $o ? (float) $o->total_qty : null, 'value' => round($value, 2), 'cost' => round(array_sum(array_column($heads, 0)), 2), 'heads' => $heads, 'approved' => $b->approved_at !== null];
        })->values();
    }

    /** @return list<array<string, mixed>> the booked fabric of the types from the date on, received nothing yet */
    private function fabricLines(CarbonImmutable $from, array $types): array
    {
        $bookings = FabricBooking::query()->whereIn('booking_type', $types)->where('booking_date', '>=', $from)->get()->keyBy('id');
        $lines = FabricBookingLine::query()->whereIn('booking_id', $bookings->keys())->orderBy('booking_id')->orderBy('disp_order')->get();
        $fabrics = FabricType::query()->whereIn('id', $lines->pluck('fabric_type_id'))->pluck('name', 'id');
        $colours = Colour::query()->whereIn('id', $lines->pluck('fab_colour_id')->filter())->pluck('name', 'id');
        $orders = Order::query()->whereIn('id', $bookings->pluck('order_id')->filter())->pluck('order_no', 'id');

        return $lines->map(function (FabricBookingLine $l) use ($bookings, $fabrics, $colours, $orders): array {
            $b = $bookings[$l->booking_id];
            $qty = round((float) $l->finish_qty, 3);

            return ['booking_no' => $b->booking_no, 'booking_type' => $b->booking_type, 'order_no' => $b->order_id ? ($orders[$b->order_id] ?? null) : null,
                'booking_date' => $b->booking_date->toDateString(), 'fabric' => $fabrics[$l->fabric_type_id] ?? null,
                'colour' => $l->colour_spec ?: ($l->fab_colour_id ? ($colours[$l->fab_colour_id] ?? null) : null), 'qty' => $qty, 'uom' => $l->uom ?: ucfirst(strtolower((string) $l->cons_unit)),
                'received' => 0, 'balance' => $qty];
        })->all();
    }

    /** "qty:number" → ['key' => 'qty', 'type' => 'number'] */
    private function cols(array $spec): array
    {
        return array_map(function (string $c): array {
            [$key, $type] = array_pad(explode(':', $c, 2), 2, 'text');

            return ['key' => $key, 'type' => $type];
        }, $spec);
    }
}

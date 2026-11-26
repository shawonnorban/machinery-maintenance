<?php

declare(strict_types=1);

namespace App\Modules\Planning\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Planning\Models\PlanningDailyPlan;
use App\Modules\Planning\Models\PlanningLeadTime;
use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Planning\Models\PlanningMasterPlan;
use App\Modules\Planning\Models\PlanningMasterPlanActivity;
use App\Modules\Planning\Models\PlanningMasterPlanDay;
use App\Modules\Planning\Models\PlanningMasterPlanLine;
use App\Modules\Planning\Models\PlanningPlanEvent;
use App\Modules\Planning\Models\PlanningRevisionRequest;
use App\Modules\Planning\Support\PlanningDefaults;
use App\Modules\Planning\Support\PlanningEngine;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
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
 * Capacity Planning → Projections, Master Plan, Master Plan List and Revision
 * Request (AnnotechERP 12/02), as Brickland keeps them. Confirmed orders by
 * shipment month; an order's capacity plan — lines chosen, resources
 * adjusted, laid out and saved as a draft, then confirmed and locked as the
 * original commitment; a locked plan is changed only through a revision
 * request, whose approval opens the next version as a draft (the locked one
 * stays in force until that is locked, then it is Revised). A plan is
 * addressed by its number and version (MP-2026110004, V1), an order by its
 * number, never their keys.
 */
class PlanningPlanApiController extends ApiController
{
    /** The orders planning works from: confirmed, not yet shipped. */
    public const PLANNABLE = ['CONFIRMED', 'IN_PRODUCTION'];

    /** Projections: one year's confirmed orders by shipment month, the month's orders with where their plans stand. */
    public function projections(Request $request): JsonResponse
    {
        $this->allow('planning.plan.view');
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        $orders = Order::query()->whereIn('status', self::PLANNABLE)->whereYear('ship_date', $year)->orderBy('ship_date')->orderBy('order_no')->get();
        $counts = $orders->countBy(fn (Order $o) => (int) $o->ship_date->format('n'));
        $inMonth = $orders->filter(fn (Order $o) => (int) $o->ship_date->format('n') === $month)->values();
        $plans = PlanningEngine::currentPlans($inMonth->pluck('id'));
        // The locked version stays in force while a revision is drafted over it.
        $locked = PlanningMasterPlan::query()->whereIn('order_id', $inMonth->pluck('id'))->where('status', 'LOCKED')->get()->keyBy('order_id');
        $buyers = Buyer::query()->pluck('short_name', 'id');
        $rows = $inMonth->map(function (Order $o) use ($plans, $locked, $buyers): array {
            $p = $locked[$o->id] ?? $plans[$o->id] ?? null;

            return [
                'order_no' => $o->order_no, 'buyer' => $buyers[$o->buyer_id] ?? null, 'po' => $o->po_no, 'style' => $o->style_no, 'qty' => (int) $o->total_qty,
                'ship_date' => $o->ship_date->toDateString(), 'status' => $p?->status ?? 'PENDING', 'plan_no' => $p?->plan_no, 'version' => $p?->version,
            ];
        })->all();

        return ApiResponse::ok([
            'year' => $year, 'month' => $month, 'counts' => (object) $counts->all(), 'rows' => $rows,
            'tiles' => [
                'orders' => count($rows), 'qty' => array_sum(array_column($rows, 'qty')),
                'pending' => count(array_filter($rows, fn ($r) => $r['status'] !== 'LOCKED')), 'locked' => count(array_filter($rows, fn ($r) => $r['status'] === 'LOCKED')),
            ],
        ]);
    }

    /** Master Plan's order picker: the month's confirmed orders, those with a plan marked. */
    public function orders(Request $request): JsonResponse
    {
        $this->allow('planning.plan.view');
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        $orders = Order::query()->whereIn('status', self::PLANNABLE)->whereYear('ship_date', $year)->whereMonth('ship_date', $month)->orderBy('ship_date')->orderBy('order_no')->get();
        $planned = PlanningMasterPlan::query()->whereIn('order_id', $orders->pluck('id'))->pluck('order_id')->flip();
        $buyers = Buyer::query()->pluck('short_name', 'id');

        return ApiResponse::ok($orders->map(fn (Order $o) => [
            'value' => $o->order_no, 'has_plan' => isset($planned[$o->id]),
            'label' => implode(' · ', array_filter([$o->order_no, $buyers[$o->buyer_id] ?? null, number_format((int) $o->total_qty).' PCS', $o->ship_date->toDateString(), isset($planned[$o->id]) ? 'has plan' : null])),
        ])->values()->all());
    }

    /** An order opened on Master Plan: its information, the lines it may use, and its current plan if it has one. */
    public function order(string $order): JsonResponse
    {
        $this->allow('planning.plan.view');
        $o = $this->findOrder($order);
        $current = PlanningEngine::currentPlans(collect([$o->id]))->get($o->id);

        return ApiResponse::ok([
            'order' => $this->orderInfo($o),
            'lines' => $o->ship_date ? $this->lineChoices($o) : [],
            'plan' => $current ? $this->payload($current) : null,
            'lead_times' => $this->leadTimes(),
        ]);
    }

    /** Calculate Plan: the layout, not saved. */
    public function calculate(Request $request): JsonResponse
    {
        $this->allow('planning.plan.view');
        [$o, $data, $smv] = $this->validated($request);

        return ApiResponse::ok($this->result($o, $data, $smv));
    }

    /** Save as Draft: a new plan (V1), or the order's open draft rewritten. */
    public function save(Request $request): JsonResponse
    {
        $this->allow('planning.plan.manage');
        [$o, $data, $smv] = $this->validated($request);
        $draft = PlanningMasterPlan::query()->where('order_id', $o->id)->where('status', 'DRAFT')->first();
        if (! $draft && PlanningMasterPlan::query()->where('order_id', $o->id)->exists()) {
            throw ValidationException::withMessages(['order_no' => __('planning.plan.v.locked_order', ['order' => $o->order_no])]);
        }
        $result = $this->result($o, $data, $smv);
        if ($result['days'] === []) {
            throw ValidationException::withMessages(['lines' => __('planning.plan.v.nothing_scheduled')]);
        }
        $user = $this->caller()->auditUserId();
        $info = PlanningEngine::smvOf($o);
        $plan = DB::transaction(function () use ($o, $data, $result, $draft, $user, $info, $smv): PlanningMasterPlan {
            $values = [
                'smv' => $smv, 'smv_source' => $info['smv_source'] ?? 'MASTER', 'sam' => $info['sam'], 'sam_source' => $info['sam_source'], 'direction' => $data['direction'],
                'forward_from' => $data['direction'] === 'FORWARD' ? $data['forward_from'] : null, 'scenario' => $data['scenario'] ?? 'Scenario A', 'order_qty' => (int) $o->total_qty,
                'allocated_qty' => $result['scheduled_qty'], 'daily_capacity' => $result['daily_capacity'], 'required_days' => $result['required_days'],
                'sewing_start' => $result['sewing_start'], 'sewing_end' => $result['sewing_end'], 'ship_date' => $o->ship_date->toDateString(), 'remarks' => $data['remarks'] ?? null,
            ];
            if ($draft) {
                $draft->update($values);
                foreach ([PlanningMasterPlanLine::class, PlanningMasterPlanDay::class, PlanningMasterPlanActivity::class] as $model) {
                    $model::query()->where('master_plan_id', $draft->id)->delete();
                }
                $plan = $draft;
            } else {
                $plan = PlanningMasterPlan::query()->create($values + ['plan_no' => PlanningEngine::nextPlanNo(CarbonImmutable::parse($o->ship_date)), 'version' => 1, 'order_id' => $o->id, 'status' => 'DRAFT', 'created_by' => $user]);
            }
            $this->writeLayout($plan, $result);
            PlanningEngine::event($plan, $draft ? 'UPDATED' : 'CREATED', __($draft ? 'planning.plan.audit.updated' : 'planning.plan.audit.created', [
                'v' => $plan->version, 'order' => $o->order_no, 'lines' => $this->lineCodes($plan), 'cap' => number_format($result['daily_capacity']),
                'from' => $result['sewing_start'], 'to' => $result['sewing_end'],
            ], 'en'), $user);

            return $plan;
        });

        return ApiResponse::ok($this->payload($plan->fresh()));
    }

    public function show(Request $request, string $plan): JsonResponse
    {
        $this->allow('planning.plan.view');

        return ApiResponse::ok($this->payload($this->find($plan, $request->query('v'))));
    }

    /** Delete a draft; a locked version is never deleted. */
    public function deleteDraft(string $plan): JsonResponse
    {
        $this->allow('planning.plan.manage');
        $p = PlanningMasterPlan::query()->where('plan_no', strtoupper($plan))->where('status', 'DRAFT')->first()
            ?? throw ValidationException::withMessages(['plan' => __('planning.plan.v.no_draft', ['no' => strtoupper($plan)])]);
        DB::transaction(function () use ($p): void {
            PlanningEngine::event($p, 'DELETED', __('planning.plan.audit.deleted', ['v' => $p->version], 'en'), $this->caller()->auditUserId());
            PlanningRevisionRequest::query()->where('revised_plan_id', $p->id)->update(['revised_plan_id' => null]);
            $p->delete();
        });

        return ApiResponse::ok(['deleted' => true]);
    }

    /** Confirm & Lock: the draft becomes the original commitment; the version it revises is Revised. */
    public function confirm(string $plan): JsonResponse
    {
        $this->allow('planning.plan.approve');
        $p = PlanningMasterPlan::query()->where('plan_no', strtoupper($plan))->where('status', 'DRAFT')->first()
            ?? throw ValidationException::withMessages(['plan' => __('planning.plan.v.no_draft', ['no' => strtoupper($plan)])]);
        if (! PlanningMasterPlanDay::query()->where('master_plan_id', $p->id)->exists()) {
            throw ValidationException::withMessages(['plan' => __('planning.plan.v.nothing_scheduled')]);
        }
        $user = $this->caller()->auditUserId();
        DB::transaction(function () use ($p, $user): void {
            PlanningMasterPlan::query()->where('plan_no', $p->plan_no)->where('status', 'LOCKED')->update(['status' => 'REVISED']);
            $p->update(['status' => 'LOCKED', 'confirmed_by' => $user, 'confirmed_at' => now()]);
            PlanningEngine::event($p, 'LOCKED', __('planning.plan.audit.locked', [
                'v' => $p->version, 'order' => Order::query()->whereKey($p->order_id)->value('order_no'), 'lines' => $this->lineCodes($p),
                'from' => $p->sewing_start?->toDateString(), 'to' => $p->sewing_end?->toDateString(), 'qty' => number_format($p->allocated_qty),
            ], 'en'), $user);
        });

        return ApiResponse::ok($this->payload($p->fresh()));
    }

    /** Request a revision of the locked version: the plan stays in force until a revision is approved and locked. */
    public function requestRevision(Request $request, string $plan): JsonResponse
    {
        $this->allow('planning.plan.manage');
        $p = PlanningMasterPlan::query()->where('plan_no', strtoupper($plan))->where('status', 'LOCKED')->first()
            ?? throw ValidationException::withMessages(['plan' => __('planning.plan.v.not_locked', ['no' => strtoupper($plan)])]);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']], ['reason.required' => __('planning.plan.v.reason')]);
        if (PlanningMasterPlan::query()->where('plan_no', $p->plan_no)->where('status', 'DRAFT')->exists()
            || PlanningRevisionRequest::query()->where('master_plan_id', $p->id)->where('status', 'PENDING')->exists()) {
            throw ValidationException::withMessages(['reason' => __('planning.plan.v.revision_open', ['no' => $p->plan_no])]);
        }
        $user = $this->caller()->auditUserId();
        PlanningRevisionRequest::query()->create(['master_plan_id' => $p->id, 'reason' => $data['reason'], 'status' => 'PENDING', 'requested_by' => $user]);
        PlanningEngine::event($p, 'REVISION', __('planning.plan.audit.revision', ['v' => $p->version, 'reason' => $data['reason']], 'en'), $user);

        return ApiResponse::ok($this->payload($p->fresh()));
    }

    /** Revision Request: every request, newest first. */
    public function revisions(Request $request): JsonResponse
    {
        $this->allow('planning.plan.view');
        $status = (string) $request->query('status', '');
        $rows = PlanningRevisionRequest::query()->when($status !== '', fn ($q) => $q->where('status', $status))->orderByDesc('created_at')->get();

        return ApiResponse::ok($this->revisionRows($rows));
    }

    /** Approve: the next version opens as a draft copied from the locked one. */
    public function approveRevision(Request $request, string $revision): JsonResponse
    {
        $this->allow('planning.plan.approve');
        $r = $this->pendingRevision($revision);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $user = $this->caller()->auditUserId();
        DB::transaction(function () use ($r, $data, $user): void {
            $locked = PlanningMasterPlan::query()->findOrFail($r->master_plan_id);
            $next = $locked->replicate(['status', 'confirmed_by', 'confirmed_at', 'version']);
            $next->fill(['version' => $locked->version + 1, 'status' => 'DRAFT', 'created_by' => $user])->save();
            foreach ([PlanningMasterPlanLine::class, PlanningMasterPlanDay::class, PlanningMasterPlanActivity::class] as $model) {
                $model::query()->where('master_plan_id', $locked->id)->get()->each(fn ($row) => $row->replicate()->fill(['master_plan_id' => $next->id])->save());
            }
            $r->update(['status' => 'APPROVED', 'decided_by' => $user, 'decided_at' => now(), 'decision_note' => $data['note'] ?? null, 'revised_plan_id' => $next->id]);
            PlanningEngine::event($locked, 'APPROVED', __('planning.plan.audit.approved', ['v' => $locked->version, 'next' => $next->version], 'en'), $user);
            PlanningEngine::event($next, 'CREATED', __('planning.plan.audit.opened', ['v' => $next->version, 'from' => $locked->version], 'en'), $user);
        });

        return ApiResponse::ok($this->revisionRows(collect([$r->fresh()]))[0]);
    }

    public function rejectRevision(Request $request, string $revision): JsonResponse
    {
        $this->allow('planning.plan.approve');
        $r = $this->pendingRevision($revision);
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']], ['note.required' => __('planning.plan.v.note')]);
        $user = $this->caller()->auditUserId();
        $r->update(['status' => 'REJECTED', 'decided_by' => $user, 'decided_at' => now(), 'decision_note' => $data['note']]);
        PlanningEngine::event(PlanningMasterPlan::query()->findOrFail($r->master_plan_id), 'REJECTED', __('planning.plan.audit.rejected', ['note' => $data['note']], 'en'), $user);

        return ApiResponse::ok($this->revisionRows(collect([$r->fresh()]))[0]);
    }

    /** Master Plan List: every version of every plan, by status (Awaiting = locked with a revision waiting), shipment window and search. */
    public function index(Request $request): JsonResponse
    {
        $this->allow('planning.plan.view');

        return ApiResponse::ok($this->listRows($request));
    }

    public function excel(Request $request): StreamedResponse
    {
        $this->allow('planning.plan.view');
        $rows = $this->listRows($request);
        $head = ['plan_no', 'version', 'order_no', 'buyer', 'style', 'qty', 'lines', 'daily_capacity', 'sewing_start', 'sewing_end', 'ship_date', 'status'];

        return response()->streamDownload(function () use ($rows, $head): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(array_map(fn ($k) => __("planning.plan.col.{$k}"), $head)));
            foreach ($rows as $r) {
                $writer->addRow(Row::fromValues(array_map(fn ($k) => $k === 'status' ? __("planning.plan.status.{$r['status']}") : ($k === 'version' ? 'V'.$r['version'] : $r[$k]), $head)));
            }
            $writer->close();
        }, 'master-plans-'.now()->format('Ymd-His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @return list<array<string, mixed>> */
    private function listRows(Request $request): array
    {
        $status = strtoupper((string) $request->query('status', ''));
        $q = trim((string) $request->query('q', ''));
        $plans = PlanningMasterPlan::query()
            ->when($request->query('from'), fn ($w, $v) => $w->whereDate('ship_date', '>=', $v))
            ->when($request->query('to'), fn ($w, $v) => $w->whereDate('ship_date', '<=', $v))
            ->orderByDesc('plan_no')->orderByDesc('version')->get();
        $awaiting = PlanningRevisionRequest::query()->where('status', 'PENDING')->pluck('master_plan_id')->flip();
        $orders = Order::query()->whereIn('id', $plans->pluck('order_id')->unique())->get()->keyBy('id');
        $buyers = Buyer::query()->pluck('short_name', 'id');
        $codes = $this->lineCodesFor($plans->pluck('id'));

        return $plans->map(function (PlanningMasterPlan $p) use ($awaiting, $orders, $buyers, $codes): array {
            $o = $orders[$p->order_id];
            $state = $p->status === 'LOCKED' && isset($awaiting[$p->id]) ? 'AWAITING' : $p->status;

            return [
                'plan_no' => $p->plan_no, 'version' => $p->version, 'order_no' => $o->order_no, 'buyer' => $buyers[$o->buyer_id] ?? null, 'style' => $o->style_no,
                'qty' => $p->order_qty, 'lines' => $codes[$p->id] ?? '', 'daily_capacity' => $p->daily_capacity,
                'sewing_start' => $p->sewing_start?->toDateString(), 'sewing_end' => $p->sewing_end?->toDateString(), 'ship_date' => $p->ship_date->toDateString(), 'status' => $state,
            ];
        })->filter(fn ($r) => ($status === '' || $r['status'] === $status)
            && ($q === '' || str_contains(mb_strtolower(implode(' ', [$r['plan_no'], $r['order_no'], $r['buyer'], $r['style'], $r['lines']])), mb_strtolower($q))))->values()->all();
    }

    /**
     * @return array{0: Order, 1: array<string, mixed>, 2: float}
     */
    private function validated(Request $request): array
    {
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'order_no' => ['required', 'string'], 'direction' => ['required', Rule::in(['BACKWARD', 'FORWARD'])], 'forward_from' => ['nullable', 'required_if:direction,FORWARD', 'date'],
            'auto' => ['boolean'], 'scenario' => ['nullable', 'string', 'max:32'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'], 'lines.*.line_id' => ['required', 'distinct', Rule::exists('planning_lines', 'id')->where('company_id', $company)->where('active', true)],
            'lines.*.manpower' => ['required', 'numeric', 'min:1'], 'lines.*.machines' => ['required', 'numeric', 'min:0'], 'lines.*.hours' => ['required', 'numeric', 'gt:0', 'max:24'],
            'lines.*.efficiency' => ['required', 'numeric', 'gt:0', 'max:150'], 'lines.*.allocated_qty' => ['nullable', 'integer', 'min:0'],
        ], [
            'lines.required' => __('planning.plan.v.pick_line'), 'lines.min' => __('planning.plan.v.pick_line'), 'forward_from.required_if' => __('planning.plan.v.forward_from'),
        ]);
        $o = $this->findOrder($data['order_no']);
        $smv = PlanningEngine::smvOf($o)['smv'];
        if (! $o->ship_date || ! $smv) {
            throw ValidationException::withMessages(['order_no' => __('planning.plan.v.not_plannable')]);
        }
        ksort($data['lines']);
        $data['lines'] = array_values($data['lines']);

        return [$o, $data, (float) $smv];
    }

    /** @param array<string, mixed> $data */
    private function result(Order $o, array $data, float $smv): array
    {
        $auto = (bool) ($data['auto'] ?? true);
        $inputs = array_map(fn ($l) => [
            'line_id' => $l['line_id'], 'manpower' => (float) $l['manpower'], 'machines' => (float) $l['machines'], 'hours' => (float) $l['hours'], 'efficiency' => (float) $l['efficiency'],
        ] + (! $auto && isset($l['allocated_qty']) ? ['allocated_qty' => (int) $l['allocated_qty']] : []), $data['lines']);

        return PlanningEngine::calculate($o, $smv, $inputs, $data['direction'], $data['forward_from'] ?? null, $auto);
    }

    private function writeLayout(PlanningMasterPlan $plan, array $result): void
    {
        foreach ($result['lines'] as $i => $l) {
            PlanningMasterPlanLine::query()->create([
                'master_plan_id' => $plan->id, 'planning_line_id' => $l['line_id'], 'manpower' => (int) $l['manpower'], 'machines' => (int) $l['machines'], 'hours' => $l['hours'],
                'efficiency' => $l['efficiency'], 'hourly_capacity' => $l['hourly_capacity'], 'daily_capacity' => $l['daily_capacity'], 'allocated_qty' => $l['planned_qty'],
                'sewing_start' => $l['sewing_start'], 'sewing_end' => $l['sewing_end'], 'days' => $l['days'], 'sort_order' => $i + 1,
            ]);
        }
        foreach ($result['days'] as $d) {
            PlanningMasterPlanDay::query()->create(['master_plan_id' => $plan->id, 'planning_line_id' => $d['line_id'], 'day' => $d['day'], 'capacity' => $d['capacity'], 'target' => $d['target']]);
        }
        foreach ($result['activities'] as $i => $a) {
            PlanningMasterPlanActivity::query()->create($a + ['master_plan_id' => $plan->id, 'sort_order' => $i + 1]);
        }
    }

    /** @return array<string, mixed> */
    private function orderInfo(Order $o): array
    {
        $smv = PlanningEngine::smvOf($o);
        $sizes = DB::table('merch_order_sizes')->where('order_id', $o->id)->orderBy('sort_order')->get(['colour', 'size']);
        $tna = DB::table('merch_order_tna')->where('order_id', $o->id)->count();
        $problems = array_values(array_filter([! $o->ship_date ? 'no_ship_date' : null, ! $smv['smv'] ? 'no_smv' : null]));

        return [
            'order_no' => $o->order_no, 'buyer' => Buyer::query()->whereKey($o->buyer_id)->value('short_name'), 'po' => $o->po_no, 'style' => $o->style_no,
            'qty' => (int) $o->total_qty, 'unit' => $o->unit, 'ship_date' => $o->ship_date?->toDateString(), 'last_sewing' => $o->ship_date?->subDay()->toDateString(),
            'colors' => $sizes->pluck('colour')->unique()->values()->all(), 'sizes' => $sizes->pluck('size')->unique()->values()->all(), 'tna' => $tna,
            'problems' => $problems, 'can_plan' => $problems === [],
        ] + $smv;
    }

    /** @return list<array<string, mixed>> the lines an order may sew on, with their free days up to the last sewing day */
    private function lineChoices(Order $o): array
    {
        $from = CarbonImmutable::tomorrow();
        $to = CarbonImmutable::parse($o->ship_date)->subDay();
        $paths = DB::table('planning_lines as l')->join('floors as f', 'f.id', '=', 'l.floor_id')->join('buildings as b', 'b.id', '=', 'f.building_id')->join('factories as fa', 'fa.id', '=', 'b.factory_id')
            ->select(['l.id', DB::raw("fa.code || ' › ' || f.code as path")])->pluck('path', 'id');

        return PlanningLine::query()->where('active', true)->orderBy('display_order')->orderBy('code')->get()->map(fn (PlanningLine $l) => [
            'id' => $l->id, 'code' => $l->code, 'name' => $l->name, 'path' => $paths[$l->id] ?? null, 'status' => $l->under_maintenance ? 'MAINTENANCE' : 'AVAILABLE',
            'free_days' => count(PlanningEngine::freeDays($l, $from, $to, $o->id)), 'manpower' => $l->std_manpower, 'machines' => $l->std_machines,
            'hours' => (float) $l->std_hours, 'efficiency' => (float) $l->std_efficiency,
        ])->values()->all();
    }

    /** @return list<array{activity: string, lead_days: int, counted_from: string}> the lead times the activities are laid out from (Planning Setup) */
    private function leadTimes(): array
    {
        PlanningDefaults::leadTimes(app(TenantContext::class)->companyId());

        return PlanningLeadTime::query()->orderBy('sort_order')->get()->map(fn ($l) => ['activity' => $l->activity, 'lead_days' => (int) $l->lead_days, 'counted_from' => $l->counted_from])->all();
    }

    /** @return array<string, mixed> */
    private function payload(PlanningMasterPlan $p): array
    {
        $o = Order::query()->findOrFail($p->order_id);
        $lines = PlanningMasterPlanLine::query()->where('master_plan_id', $p->id)->orderBy('sort_order')->get();
        $lineInfo = PlanningLine::query()->whereIn('id', $lines->pluck('planning_line_id'))->get()->keyBy('id');
        $floors = DB::table('floors')->whereIn('id', $lineInfo->pluck('floor_id'))->pluck('code', 'id');
        $days = PlanningMasterPlanDay::query()->where('master_plan_id', $p->id)->orderBy('day')->get();
        $daily = PlanningDailyPlan::query()->where('master_plan_id', $p->id)->get()->keyBy(fn ($d) => $d->planning_line_id.'|'.$d->plan_date->toDateString());
        $versions = PlanningMasterPlan::query()->where('plan_no', $p->plan_no)->orderBy('version')->get(['version', 'status']);
        $revisions = PlanningRevisionRequest::query()->whereIn('master_plan_id', PlanningMasterPlan::query()->where('plan_no', $p->plan_no)->select('id'))->orderByDesc('created_at')->get();
        $events = PlanningPlanEvent::query()->where('plan_no', $p->plan_no)->orderByDesc('created_at')->orderByDesc('id')->get();
        $users = DB::table('users')->whereIn('id', $events->pluck('user_id')->merge([$p->confirmed_by, $p->created_by])->filter()->unique())->pluck('name', 'id');
        $code = fn ($id) => $lineInfo[$id]->code ?? '—';
        $cumulative = 0;

        return [
            'plan_no' => $p->plan_no, 'version' => $p->version, 'status' => $p->status,
            'awaiting' => $revisions->contains(fn ($r) => $r->status === 'PENDING' && $r->master_plan_id === $p->id),
            'current' => $versions->last()?->version === $p->version, 'order' => array_merge($this->orderInfo($o), ['smv' => $p->smv, 'smv_source' => $p->smv_source, 'sam' => $p->sam, 'sam_source' => $p->sam_source]),
            'direction' => $p->direction, 'forward_from' => $p->forward_from?->toDateString(), 'scenario' => $p->scenario, 'remarks' => $p->remarks,
            'order_qty' => $p->order_qty, 'allocated_qty' => $p->allocated_qty, 'daily_capacity' => $p->daily_capacity, 'required_days' => $p->required_days,
            'sewing_start' => $p->sewing_start?->toDateString(), 'sewing_end' => $p->sewing_end?->toDateString(), 'ship_date' => $p->ship_date->toDateString(),
            'confirmed_by' => $users[$p->confirmed_by] ?? null, 'confirmed_at' => $p->confirmed_at?->toIso8601String(),
            'lines' => $lines->map(fn (PlanningMasterPlanLine $l) => [
                'line_id' => $l->planning_line_id, 'code' => $code($l->planning_line_id), 'floor' => $floors[$lineInfo[$l->planning_line_id]->floor_id ?? ''] ?? null,
                'manpower' => $l->manpower, 'machines' => $l->machines, 'hours' => $l->hours, 'efficiency' => $l->efficiency, 'hourly_capacity' => $l->hourly_capacity,
                'daily_capacity' => $l->daily_capacity, 'allocated_qty' => $l->allocated_qty, 'sewing_start' => $l->sewing_start?->toDateString(), 'sewing_end' => $l->sewing_end?->toDateString(), 'days' => $l->days,
            ])->values()->all(),
            'days' => $days->map(function (PlanningMasterPlanDay $d) use ($code, $daily, $lines, $p, &$cumulative): array {
                $dp = $daily[$d->planning_line_id.'|'.$d->day->toDateString()] ?? null;
                $line = $lines->firstWhere('planning_line_id', $d->planning_line_id);
                $cumulative += (int) ($dp?->typed_output ?? 0);

                return [
                    'day' => $d->day->toDateString(), 'line_id' => $d->planning_line_id, 'line' => $code($d->planning_line_id), 'capacity' => $d->capacity, 'target' => $d->target,
                    'manpower' => $dp?->manpower ?? $line?->manpower, 'machines' => $dp?->machines ?? $line?->machines, 'hours' => $dp?->hours ?? $line?->hours,
                    'daily_capacity' => $dp?->daily_capacity, 'plan_target' => $dp?->daily_target ?? $d->target, 'actual' => $dp?->typed_output,
                    'variance' => $dp?->typed_output !== null ? $dp->typed_output - $dp->daily_target : null, 'balance' => $p->order_qty - $cumulative,
                    'source' => $dp ? 'DAILY' : 'PLANNED', 'status' => $dp?->status ?? 'PLANNED', 'remarks' => $dp?->remarks,
                ];
            })->values()->all(),
            'activities' => PlanningMasterPlanActivity::query()->where('master_plan_id', $p->id)->orderBy('sort_order')->get()
                ->map(fn ($a) => ['activity' => $a->activity, 'duration' => $a->duration, 'start_date' => $a->start_date->toDateString(), 'end_date' => $a->end_date->toDateString(), 'based_on' => $a->based_on])->all(),
            'forecast' => $p->status === 'DRAFT' ? null : PlanningEngine::forecast($p),
            'versions' => $versions->map(fn ($v) => ['version' => $v->version, 'status' => $v->status])->values()->all(),
            'revisions' => $this->revisionRows($revisions),
            'events' => $events->map(fn ($e) => ['action' => $e->action, 'version' => $e->version, 'summary' => $e->summary, 'by' => $users[$e->user_id] ?? null, 'at' => $e->created_at?->toIso8601String()])->values()->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function revisionRows(Collection $rows): array
    {
        $plans = PlanningMasterPlan::query()->whereIn('id', $rows->pluck('master_plan_id')->merge($rows->pluck('revised_plan_id'))->filter()->unique())->get()->keyBy('id');
        $orders = Order::query()->whereIn('id', $plans->pluck('order_id')->unique())->pluck('order_no', 'id');
        $users = DB::table('users')->whereIn('id', $rows->pluck('requested_by')->merge($rows->pluck('decided_by'))->filter()->unique())->pluck('name', 'id');

        return $rows->map(function (PlanningRevisionRequest $r) use ($plans, $orders, $users): array {
            $p = $plans[$r->master_plan_id] ?? null;
            $next = $r->revised_plan_id ? ($plans[$r->revised_plan_id] ?? null) : null;

            return [
                'id' => $r->id, 'requested_at' => $r->created_at?->toIso8601String(), 'plan_no' => $p?->plan_no, 'version' => $p?->version, 'order_no' => $orders[$p?->order_id] ?? null,
                'reason' => $r->reason, 'requested_by' => $users[$r->requested_by] ?? null, 'status' => $r->status, 'decided_at' => $r->decided_at?->toIso8601String(),
                'decided_by' => $users[$r->decided_by] ?? null, 'decision_note' => $r->decision_note, 'revised_version' => $next?->version, 'revised_status' => $next?->status,
            ];
        })->values()->all();
    }

    private function pendingRevision(string $id): PlanningRevisionRequest
    {
        $r = PlanningRevisionRequest::query()->find($id) ?? abort(404);
        if ($r->status !== 'PENDING') {
            throw ValidationException::withMessages(['revision' => __('planning.plan.v.decided')]);
        }

        return $r;
    }

    private function lineCodes(PlanningMasterPlan $p): string
    {
        return $this->lineCodesFor(collect([$p->id]))[$p->id] ?? '';
    }

    /** @return array<string, string> plan id => "13, LINE01" */
    private function lineCodesFor(Collection $planIds): array
    {
        return DB::table('planning_master_plan_lines as m')->join('planning_lines as l', 'l.id', '=', 'm.planning_line_id')->whereIn('m.master_plan_id', $planIds->all())
            ->orderBy('m.sort_order')->get(['m.master_plan_id', 'l.code'])->groupBy('master_plan_id')->map(fn ($g) => $g->pluck('code')->implode(', '))->all();
    }

    private function findOrder(string $no): Order
    {
        return Order::query()->where('order_no', strtoupper(trim($no)))->first()
            ?? throw ValidationException::withMessages(['order_no' => __('planning.plan.v.no_order', ['order' => strtoupper(trim($no))])]);
    }

    private function find(string $no, mixed $version): PlanningMasterPlan
    {
        $q = PlanningMasterPlan::query()->where('plan_no', strtoupper(trim($no)));
        $plan = $version !== null && $version !== '' ? $q->where('version', (int) ltrim((string) $version, 'vV'))->first() : $q->where('status', '!=', 'REVISED')->orderByDesc('version')->first();

        return $plan ?? throw ValidationException::withMessages(['plan' => __('planning.plan.v.missing', ['no' => strtoupper(trim($no))])]);
    }
}

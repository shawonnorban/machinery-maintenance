<?php

declare(strict_types=1);

namespace App\Modules\ProductionTracking\Http\Controllers\Api;

use App\Modules\ProductionTracking\Models\PtDailyPlan;
use App\Modules\ProductionTracking\Models\PtDailyPlanEvent;
use App\Modules\ProductionTracking\Support\PtBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Production Tracking → Daily Production Plan (AnnotechERP 30/06): a line's
 * target for a day, generated from its capacity on that day by method A–D,
 * saved as a draft, submitted, and approved (never by the one who prepared
 * it) or rejected. With the approval workflow off, submitting approves.
 * Only an approved target reaches the dashboards and TVs.
 *
 * An approved plan is revised by a new revision of the same plan number (the
 * old one stays in the history, no longer current); a plan is cancelled with
 * a reason. One current, uncancelled plan per line and day.
 */
class DailyPlanApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->allow('productiontracking.view');
        $q = $request->validate(['date' => ['nullable', 'date'], 'section' => ['nullable', Rule::in(PtBook::SECTIONS)], 'status' => ['nullable', Rule::in(PtBook::STATUSES)]]);
        $c = $this->caller()->companyId;
        $date = CarbonImmutable::parse($q['date'] ?? PtBook::today())->toDateString();
        $rows = PtDailyPlan::query()->where('current', true)->whereDate('plan_date', $date)
            ->when($q['section'] ?? null, fn ($w, $s) => $w->where('section', $s))->when($q['status'] ?? null, fn ($w, $s) => $w->where('status', $s))
            ->orderBy('plan_no')->get();

        return ApiResponse::ok([
            'date' => $date,
            'approval_workflow' => PtBook::settings($c)->approval_workflow,
            'rows' => $rows->map(fn (PtDailyPlan $p) => $this->row($p, $c))->all(),
            'proposed' => (int) $rows->whereNotIn('status', ['CANCELLED', 'REJECTED'])->sum('proposed_target'),
            'approved' => (int) $rows->where('status', 'APPROVED')->sum('approved_target'),
        ]);
    }

    /** The calculated target for a line, day, method and hours — what the form shows before saving. */
    public function preview(Request $request): JsonResponse
    {
        $this->allow('productiontracking.view');
        $d = $this->validated($request, true);

        return ApiResponse::ok($this->calculation($d));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('productiontracking.plan.prepare');
        $d = $this->validated($request);
        $c = $this->caller()->companyId;
        $this->assertFree($d['section'], $d['line_id'], $d['plan_date']);
        $calc = $this->calculation($d);

        $plan = DB::transaction(function () use ($d, $c, $calc) {
            $plan = PtDailyPlan::query()->create($this->values($d, $calc) + [
                'plan_no' => $this->nextNo($c, $d['plan_date']), 'revision' => 1, 'status' => 'DRAFT', 'current' => true, 'created_by' => $this->caller()->auditUserId(),
            ]);
            $this->event($plan, 'CREATE', null, 'DRAFT');

            return $plan;
        });

        return ApiResponse::created($this->row($plan, $c));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->allow('productiontracking.plan.prepare');
        $plan = PtDailyPlan::query()->findOrFail($id);
        if (! in_array($plan->status, ['DRAFT', 'REJECTED'], true)) {
            throw ValidationException::withMessages(['status' => __('productiontracking.v.plan_locked')]);
        }
        $d = $this->validated($request);
        $this->assertFree($d['section'], $d['line_id'], $d['plan_date'], $plan->plan_no);
        $calc = $this->calculation($d);
        $from = $plan->status;
        $plan->update($this->values($d, $calc) + ['status' => 'DRAFT', 'approved_target' => null, 'decision_note' => null]);
        $this->event($plan, 'EDIT', $from, 'DRAFT');

        return ApiResponse::ok($this->row($plan->fresh(), $this->caller()->companyId));
    }

    /** submit | approve | reject | cancel | revise */
    public function act(Request $request, string $id, string $action): JsonResponse
    {
        $plan = PtDailyPlan::query()->findOrFail($id);
        $c = $this->caller()->companyId;
        $me = $this->caller()->auditUserId();
        $note = $request->validate(['note' => [Rule::requiredIf(in_array($action, ['reject', 'cancel'], true)), 'nullable', 'string', 'max:1000'],
            'approved_target' => ['nullable', 'integer', 'min:0', 'max:9999999']])['note'] ?? null;
        $settings = PtBook::settings($c);
        $from = $plan->status;

        $result = DB::transaction(function () use ($plan, $action, $me, $note, $settings, $from, $request) {
            switch ($action) {
                case 'submit':
                    $this->allow('productiontracking.plan.prepare');
                    $this->expect($plan, ['DRAFT', 'REJECTED']);
                    if ($settings->approval_workflow) {
                        $plan->update(['status' => 'PENDING_APPROVAL']);
                        $this->event($plan, 'SUBMIT', $from, 'PENDING_APPROVAL', $note);
                    } else {
                        $plan->update(['status' => 'APPROVED', 'approved_target' => $plan->proposed_target, 'approved_by' => $me, 'approved_at' => now()]);
                        $this->event($plan, 'APPROVE', $from, 'APPROVED', $note);
                    }

                    return $plan;
                case 'approve':
                case 'reject':
                    $this->allow('productiontracking.plan.approve');
                    $this->expect($plan, ['PENDING_APPROVAL']);
                    if ($plan->created_by !== null && $plan->created_by === $me) {
                        throw ValidationException::withMessages(['status' => __('productiontracking.v.own_plan')]);
                    }
                    $to = $action === 'approve' ? 'APPROVED' : 'REJECTED';
                    $approved = $action === 'approve' ? (int) ($request->input('approved_target') ?? $plan->proposed_target) : null;
                    $plan->update(['status' => $to, 'approved_target' => $approved, 'approved_by' => $me, 'approved_at' => now(), 'decision_note' => $note]);
                    $this->event($plan, strtoupper($action), $from, $to, $note);

                    return $plan;
                case 'cancel':
                    $this->allowEither('productiontracking.plan.prepare', 'productiontracking.plan.approve');
                    $this->expect($plan, ['DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'REJECTED']);
                    $plan->update(['status' => 'CANCELLED', 'decision_note' => $note]);
                    $this->event($plan, 'CANCEL', $from, 'CANCELLED', $note);

                    return $plan;
                case 'revise':
                    $this->allow('productiontracking.plan.prepare');
                    $this->expect($plan, ['APPROVED']);
                    $plan->update(['current' => false]);
                    $this->event($plan, 'REVISE', $from, $from, $note);
                    $next = $plan->replicate(['id']);
                    $next->fill(['revision' => $plan->revision + 1, 'status' => 'DRAFT', 'current' => true, 'approved_target' => null, 'approved_by' => null, 'approved_at' => null,
                        'decision_note' => null, 'created_by' => $me]);
                    $next->save();
                    $this->event($next, 'CREATE', null, 'DRAFT', __('productiontracking.v.revision_of', ['rev' => $plan->revision]));

                    return $next;
                default:
                    throw ValidationException::withMessages(['action' => __('productiontracking.v.action')]);
            }
        });

        return ApiResponse::ok($this->row($result->fresh(), $c));
    }

    public function history(string $id): JsonResponse
    {
        $this->allow('productiontracking.view');
        $plan = PtDailyPlan::query()->findOrFail($id);
        $ids = PtDailyPlan::query()->where('plan_no', $plan->plan_no)->pluck('revision', 'id');
        $events = PtDailyPlanEvent::query()->whereIn('plan_id', $ids->keys())->orderBy('created_at')->orderBy('id')->get();
        $users = DB::table('users')->whereIn('id', $events->pluck('user_id')->filter()->unique())->pluck('name', 'id');

        return ApiResponse::ok(['plan_no' => $plan->plan_no, 'events' => $events->map(fn ($e) => [
            'revision' => $ids[$e->plan_id] ?? null, 'action' => $e->action, 'from' => $e->from_status, 'to' => $e->to_status, 'note' => $e->note,
            'by' => $users[$e->user_id] ?? null, 'at' => $e->created_at?->toIso8601String(),
        ])->all()]);
    }

    /* ── Helpers ────────────────────────────────────────────────────── */

    private function validated(Request $request, bool $preview = false): array
    {
        $c = $this->caller()->companyId;
        $d = $request->validate([
            'plan_date' => ['required', 'date'],
            'section' => ['required', Rule::in(PtBook::SECTIONS)],
            'line_id' => ['required', 'string', 'size:26'],
            'order_id' => ['nullable', 'string', 'size:26', Rule::exists('merch_orders', 'id')->where('company_id', $c)],
            'po_no' => ['nullable', 'string', 'max:64'], 'buyer_name' => ['nullable', 'string', 'max:255'], 'style' => ['nullable', 'string', 'max:120'],
            'method' => ['required', Rule::in(PtBook::METHODS)],
            'planned_hours' => [$preview ? 'nullable' : 'required', 'numeric', 'gt:0', 'max:24'],
            'proposed_target' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'override_reason' => ['nullable', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'operators' => ['nullable', 'integer', 'min:1', 'max:9999'], 'smv' => ['nullable', 'numeric', 'gt:0', 'max:999'], 'efficiency' => ['nullable', 'numeric', 'gt:0', 'max:200'],
        ], ['line_id.required' => __('productiontracking.v.line')], __('productiontracking.attrs'));
        if (! PtBook::lines($c, $d['section'])->has($d['line_id'])) {
            throw ValidationException::withMessages(['line_id' => __('productiontracking.v.line')]);
        }
        // Buyer, style and PO come from the ERP order when one is picked.
        if ($d['order_id'] ?? null) {
            $o = DB::table('merch_orders as o')->leftJoin('buyers as b', 'b.id', '=', 'o.buyer_id')->where('o.id', $d['order_id'])->first(['o.po_no', 'o.order_no', 'o.style_no', 'b.name as buyer']);
            $d = array_merge($d, ['po_no' => $o->po_no ?: $o->order_no, 'buyer_name' => $o->buyer, 'style' => $o->style_no]);
        }

        return $d;
    }

    /** @return array{capacity: ?array, calculated: ?int, formula: ?string, error: ?string} */
    private function calculation(array $d): array
    {
        $c = $this->caller()->companyId;
        $date = CarbonImmutable::parse($d['plan_date'])->toDateString();
        $cap = PtBook::capacityOn($d['section'], $d['line_id'], $date);
        if ($cap === null) {
            throw ValidationException::withMessages(['line_id' => __('productiontracking.v.no_capacity', ['date' => $date])]);
        }
        $hours = (float) ($d['planned_hours'] ?? 0);
        $calc = $hours > 0 || $d['method'] === 'B'
            ? PtBook::calculate($cap, $d['method'], max($hours, 0.0), PtBook::settings($c), ['operators' => $d['operators'] ?? null, 'smv' => $d['smv'] ?? null, 'efficiency' => $d['efficiency'] ?? null])
            : ['target' => null, 'formula' => null];

        return ['capacity' => ['type' => $cap->capacity_type, 'standard' => (float) $cap->standard_capacity, 'efficiency' => $cap->default_efficiency === null ? null : (float) $cap->default_efficiency,
            'includes_efficiency' => $cap->includes_efficiency, 'from' => $cap->effective_from->toDateString(), 'to' => $cap->effective_to?->toDateString()],
            'calculated' => $calc['target'], 'formula' => $calc['formula']];
    }

    private function values(array $d, array $calc): array
    {
        $proposed = $d['proposed_target'] ?? null;
        if ($proposed !== null && (int) $proposed !== (int) $calc['calculated'] && blank($d['override_reason'] ?? null)) {
            throw ValidationException::withMessages(['override_reason' => __('productiontracking.v.override_reason')]);
        }

        return [
            'plan_date' => $d['plan_date'], 'section' => $d['section'], 'line_id' => $d['line_id'], 'order_id' => $d['order_id'] ?? null,
            'po_no' => $d['po_no'] ?? null, 'buyer_name' => $d['buyer_name'] ?? null, 'style' => $d['style'] ?? null, 'method' => $d['method'],
            'planned_hours' => $d['planned_hours'], 'capacity' => $calc['capacity'] + ['formula' => $calc['formula']],
            'calculated_target' => (int) $calc['calculated'], 'proposed_target' => (int) ($proposed ?? $calc['calculated']),
            'override_reason' => $proposed !== null && (int) $proposed !== (int) $calc['calculated'] ? $d['override_reason'] : null, 'remarks' => $d['remarks'] ?? null,
        ];
    }

    private function assertFree(string $section, string $lineId, string $date, ?string $planNo = null): void
    {
        $taken = PtDailyPlan::query()->where('current', true)->where('section', $section)->where('line_id', $lineId)->whereDate('plan_date', $date)
            ->whereNotIn('status', ['CANCELLED'])->when($planNo, fn ($q) => $q->where('plan_no', '!=', $planNo))->value('plan_no');
        if ($taken) {
            throw ValidationException::withMessages(['line_id' => __('productiontracking.v.plan_exists', ['no' => $taken])]);
        }
    }

    private function expect(PtDailyPlan $plan, array $statuses): void
    {
        if (! $plan->current || ! in_array($plan->status, $statuses, true)) {
            throw ValidationException::withMessages(['status' => __('productiontracking.v.plan_status', ['status' => $plan->status])]);
        }
    }

    private function event(PtDailyPlan $plan, string $action, ?string $from, ?string $to, ?string $note = null): void
    {
        PtDailyPlanEvent::query()->create(['company_id' => $plan->company_id, 'plan_id' => $plan->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to,
            'note' => $note, 'user_id' => $this->caller()->auditUserId(), 'created_at' => now()]);
    }

    /** DPP-YYYYMMDD-001, per company and day. */
    private function nextNo(string $c, string $date): string
    {
        $base = 'DPP-'.CarbonImmutable::parse($date)->format('Ymd').'-';
        $last = DB::table('pt_daily_plans')->where('company_id', $c)->where('plan_no', 'like', $base.'%')->orderByDesc('plan_no')->value('plan_no');

        return $base.str_pad((string) ((int) substr((string) $last, strlen($base)) + 1), 3, '0', STR_PAD_LEFT);
    }

    private function row(PtDailyPlan $p, string $c): array
    {
        static $lines = null, $users = [];
        $lines ??= PtBook::lines($c, 'SEWING')->merge(PtBook::lines($c, 'FINISHING'));
        $name = fn (?string $id) => $id ? ($users[$id] ??= DB::table('users')->where('id', $id)->value('name')) : null;
        $line = $lines->get($p->line_id);

        return [
            'id' => $p->id, 'plan_no' => $p->plan_no, 'revision' => $p->revision, 'plan_date' => $p->plan_date->toDateString(), 'section' => $p->section,
            'line_id' => $p->line_id, 'line_code' => $line?->code, 'line' => PtBook::label($line), 'order_id' => $p->order_id, 'po_no' => $p->po_no, 'buyer_name' => $p->buyer_name, 'style' => $p->style,
            'method' => $p->method, 'planned_hours' => (float) $p->planned_hours, 'capacity' => $p->capacity, 'calculated_target' => $p->calculated_target,
            'proposed_target' => $p->proposed_target, 'approved_target' => $p->approved_target, 'override_reason' => $p->override_reason, 'remarks' => $p->remarks,
            'status' => $p->status, 'decision_note' => $p->decision_note, 'created_by' => $name($p->created_by), 'approved_by' => $name($p->approved_by),
            'approved_at' => $p->approved_at?->toIso8601String(), 'mine' => $p->created_by !== null && $p->created_by === $this->caller()->auditUserId(),
        ];
    }
}

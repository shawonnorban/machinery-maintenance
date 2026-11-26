<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Http\Controllers\Api;

use App\Modules\HrPayroll\Support\SalaryBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HR & Payroll → Salary Processing → Payroll Adjustment (AnnotechERP 24/06),
 * as Brickland lays it out: a finalized month is corrected without
 * rewriting it. Its finalized slips are set against the month's OT as
 * attendance and OT corrections stand today; a slip that differs (or has an
 * adjustment pending or approved but unpaid) is flagged. The difference —
 * revised OT hours × rate, or an amount to pay (+) or recover (−) — is
 * raised for approval, approved or rejected, and an approved one is added
 * once to the employee's next monthly salary (applied when that run is
 * finalized).
 */
class HrPayrollAdjustmentApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.salary.view', 'hrpayroll.adjustment.manage', 'hrpayroll.adjustment.approve', 'hrpayroll.salary.calculate');
        $d = $request->validate(['period' => ['nullable', 'string'], 'department' => ['nullable', 'string'], 'q' => ['nullable', 'string', 'max:80'], 'show' => ['nullable', Rule::in(['flagged', 'all'])]]);

        return ApiResponse::ok($this->view($d['period'] ?? '', $d['department'] ?? '', trim($d['q'] ?? ''), $d['show'] ?? 'flagged'));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->caller()->can('hrpayroll.salary.calculate') || $this->caller()->can('hrpayroll.adjustment.manage'), 403);
        $d = $request->validate([
            'period' => ['required', 'string'], 'employee' => ['required', 'string'], 'kind' => ['required', Rule::in(['OT', 'OTHER'])],
            'hours' => ['required_if:kind,OT', 'nullable', 'numeric', 'min:0', 'max:999'], 'rate' => ['required_if:kind,OT', 'nullable', 'numeric', 'min:0', 'max:99999'],
            'amount' => ['required_if:kind,OTHER', 'nullable', 'numeric', 'between:-9999999,9999999'], 'reason' => ['required', 'string', 'max:1000'],
        ], ['hours.required_if' => __('hrpayroll.sp.v.hours_rate'), 'rate.required_if' => __('hrpayroll.sp.v.hours_rate'), 'amount.required_if' => __('hrpayroll.sp.v.amount'), 'reason.required' => __('hrpayroll.sp.v.reason')]);
        $c = $this->caller()->companyId;
        $period = $this->period($d['period']);
        $slip = DB::table('hr_salary_slips as s')->join('hr_employees as e', 'e.id', '=', 's.employee_id')->join('hr_salary_runs as r', 'r.id', '=', 's.run_id')
            ->where('s.pay_period_id', $period->id)->where('e.company_id', $c)->where('e.employee_code', $d['employee'])->first(['s.*', 'r.status as run_status', 'e.employee_code']);
        if (! $slip || ! in_array($slip->run_status, ['FINALIZED', 'PAID'], true)) {
            throw ValidationException::withMessages(['employee' => __('hrpayroll.sp.v.not_finalized')]);
        }
        $adjustedOt = (float) DB::table('hr_payroll_adjustments')->where('slip_id', $slip->id)->where('kind', 'OT')->where('status', '!=', 'REJECTED')->sum('amount');
        if ($d['kind'] === 'OT') {
            $original = round((float) $slip->ot_amount + $adjustedOt, 2);
            $revised = round((float) $d['hours'] * (float) $d['rate'], 2);
        } else {
            $original = (float) $slip->net;
            $revised = round($original + (float) $d['amount'], 2);
        }
        $amount = round($revised - $original, 2);
        if (abs($amount) < 0.01) {
            throw ValidationException::withMessages(['amount' => __('hrpayroll.sp.v.no_difference')]);
        }
        $ref = $this->nextRef($c);
        DB::table('hr_payroll_adjustments')->insert([
            'id' => strtolower((string) Str::ulid()), 'company_id' => $c, 'ref_no' => $ref, 'slip_id' => $slip->id, 'pay_period_id' => $period->id, 'employee_id' => $slip->employee_id,
            'kind' => $d['kind'], 'hours' => $d['kind'] === 'OT' ? $d['hours'] : null, 'rate' => $d['kind'] === 'OT' ? $d['rate'] : null,
            'original' => $original, 'revised' => $revised, 'amount' => $amount, 'reason' => trim($d['reason']), 'status' => 'PENDING', 'requested_by' => $this->caller()->auditUserId(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ApiResponse::created(['message' => __('hrpayroll.sp.done.adj_raised', ['ref' => $ref, 'amount' => number_format($amount, 2)]), 'view' => $this->view($period->period_no, ...$this->filters($request))]);
    }

    public function decide(Request $request, string $ref): JsonResponse
    {
        $this->allow('hrpayroll.adjustment.approve');
        $row = DB::table('hr_payroll_adjustments')->where('company_id', $this->caller()->companyId)->where('ref_no', $ref)->first();
        abort_unless($row, 404);
        $d = $request->validate(['decision' => ['required', Rule::in(['APPROVE', 'REJECT'])], 'note' => [Rule::requiredIf(fn () => $request->input('decision') === 'REJECT'), 'nullable', 'string', 'max:1000']], ['note.required' => __('hrpayroll.sp.v.note')]);
        if ($row->status !== 'PENDING') {
            throw ValidationException::withMessages(['decision' => __('hrpayroll.sp.v.decided')]);
        }
        $status = $d['decision'] === 'APPROVE' ? 'APPROVED' : 'REJECTED';
        DB::table('hr_payroll_adjustments')->where('id', $row->id)->update(['status' => $status, 'decision_note' => trim((string) ($d['note'] ?? '')) ?: null, 'decided_by' => $this->caller()->auditUserId(), 'decided_at' => now(), 'updated_at' => now()]);
        $period = DB::table('hr_pay_periods')->where('id', $row->pay_period_id)->value('period_no');

        return ApiResponse::ok(['message' => __("hrpayroll.sp.done.adj_{$status}", ['ref' => $ref]), 'view' => $this->view((string) $request->input('period', $period), ...$this->filters($request))]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    /** The screen's department, search and show filters, kept across a raise or a decision. @return array{0: string, 1: string, 2: string} */
    private function filters(Request $request): array
    {
        return [(string) $request->input('department', ''), trim((string) $request->input('q', '')), $request->input('show') === 'all' ? 'all' : 'flagged'];
    }

    /** @return array<string, mixed> */
    private function view(string $periodNo, string $department, string $q, string $show): array
    {
        $c = $this->caller()->companyId;
        $periods = DB::table('hr_pay_periods as p')->join('hr_salary_runs as r', 'r.pay_period_id', '=', 'p.id')->where('p.company_id', $c)->whereIn('r.status', ['FINALIZED', 'PAID'])
            ->orderByDesc('p.start_date')->get(['p.*']);
        $period = $periodNo !== '' ? $periods->firstWhere('period_no', $periodNo) : $periods->first();
        $book = new SalaryBook($c);
        $slips = collect();
        $states = [];
        if ($period) {
            $all = DB::table('hr_salary_slips as s')->join('hr_employees as e', 'e.id', '=', 's.employee_id')->leftJoin('departments as dp', 'dp.id', '=', 's.department_id')->leftJoin('grades as g', 'g.id', '=', 's.grade_id')
                ->where('s.pay_period_id', $period->id)->orderBy('e.employee_code')
                ->get(['s.id', 's.employee_id', 's.ot_hours', 's.ot_amount', 's.net', 's.status', 's.department_id', 'e.employee_code', 'e.name', 'e.card_no', 'dp.name as department', 'g.code as grade']);
            $states = $book->slipStates($period, $all);
            $rates = $book->rates($all->pluck('employee_id')->all());
            $slips = $all->filter(fn ($s) => ($department === '' || $s->department_id === $department)
                && ($q === '' || Str::contains(Str::lower("{$s->employee_code} {$s->name} {$s->card_no}"), Str::lower($q))))
                ->map(fn ($s) => [
                    'code' => $s->employee_code, 'name' => $s->name, 'department' => $s->department, 'grade' => $s->grade, 'status' => $s->status,
                    'slip_ot_hours' => (float) $s->ot_hours, 'slip_ot_amount' => (float) $s->ot_amount, 'net' => (float) $s->net, 'rate' => $rates[$s->employee_id] ?? 0.0,
                    'current_hours' => $states[$s->id]['current_hours'], 'current_amount' => $states[$s->id]['current_amount'], 'adjusted_ot' => $states[$s->id]['adjusted_ot'],
                    'already' => $states[$s->id]['already'], 'flagged' => $states[$s->id]['flagged'], 'why' => $states[$s->id]['why'],
                ])->values();
            $slipsShown = $show === 'flagged' ? $slips->where('flagged', true)->values() : $slips;
        }
        $adjustments = $period ? $this->adjustments(fn ($q) => $q->where('a.pay_period_id', $period->id)) : [];
        $pending = $this->adjustments(fn ($q) => $q->where('a.status', 'PENDING'));
        $approved = DB::table('hr_payroll_adjustments')->where('company_id', $c)->where('status', 'APPROVED');

        return [
            'periods' => $periods->map(fn ($p) => ['value' => $p->period_no, 'label' => "{$p->period_no} · ".CarbonImmutable::parse($p->start_date)->format('F Y')])->values()->all(),
            'period' => $period ? ['no' => $period->period_no, 'label' => CarbonImmutable::parse($period->start_date)->format('F Y')] : null,
            'filters' => ['period' => $period->period_no ?? '', 'department' => $department, 'q' => $q, 'show' => $show],
            'slips' => ($slipsShown ?? collect())->all(),
            'adjustments' => $adjustments,
            'pending' => $pending,
            'summary' => [
                'finalized' => $slips->count(), 'flagged' => $slips->where('flagged', true)->count(), 'pending' => count($pending),
                'approved' => (clone $approved)->count(), 'approved_amount' => round((float) (clone $approved)->sum('amount'), 2),
                'applied' => DB::table('hr_payroll_adjustments')->where('company_id', $c)->where('status', 'APPLIED')->count(),
            ],
            'departments' => DB::table('departments')->where('company_id', $c)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name'])->map(fn ($x) => ['value' => $x->id, 'label' => $x->name])->all(),
            'can' => ['raise' => $this->caller()->can('hrpayroll.salary.calculate') || $this->caller()->can('hrpayroll.adjustment.manage'), 'decide' => $this->caller()->can('hrpayroll.adjustment.approve')],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function adjustments(callable $where): array
    {
        $q = DB::table('hr_payroll_adjustments as a')->join('hr_employees as e', 'e.id', '=', 'a.employee_id')->join('hr_pay_periods as p', 'p.id', '=', 'a.pay_period_id')
            ->leftJoin('hr_pay_periods as pi', 'pi.id', '=', 'a.paid_in_period_id')->leftJoin('users as r', 'r.id', '=', 'a.requested_by')->leftJoin('users as d', 'd.id', '=', 'a.decided_by')
            ->where('a.company_id', $this->caller()->companyId)->orderByDesc('a.ref_no');
        $where($q);

        return $q->get(['a.*', 'e.employee_code', 'e.name', 'p.period_no', 'p.start_date as p_start', 'pi.period_no as paid_in', 'r.name as requester', 'd.name as decider'])->map(fn ($a) => [
            'ref' => $a->ref_no, 'code' => $a->employee_code, 'name' => $a->name, 'period' => $a->period_no, 'period_label' => CarbonImmutable::parse($a->p_start)->format('F Y'),
            'kind' => $a->kind, 'hours' => $a->hours === null ? null : (float) $a->hours, 'rate' => $a->rate === null ? null : (float) $a->rate,
            'original' => (float) $a->original, 'revised' => (float) $a->revised, 'amount' => (float) $a->amount, 'status' => $a->status, 'reason' => $a->reason,
            'requested_by' => $a->requester, 'requested_at' => (string) $a->created_at, 'decided_by' => $a->decider, 'decided_at' => $a->decided_at ? (string) $a->decided_at : null,
            'decision_note' => $a->decision_note, 'paid_in' => $a->paid_in,
        ])->all();
    }

    private function period(string $no): object
    {
        $p = DB::table('hr_pay_periods')->where('company_id', $this->caller()->companyId)->where('period_no', $no)->first();
        abort_unless($p, 404);

        return $p;
    }

    private function nextRef(string $c): string
    {
        $last = DB::table('hr_payroll_adjustments')->where('company_id', $c)->orderByDesc('ref_no')->value('ref_no');

        return 'PADJ-'.str_pad((string) ((int) substr((string) $last, 5) + 1), 6, '0', STR_PAD_LEFT);
    }
}

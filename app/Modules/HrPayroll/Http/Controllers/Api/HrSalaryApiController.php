<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Http\Controllers\Api;

use App\Modules\HrPayroll\Support\AttendanceBook;
use App\Modules\HrPayroll\Support\SalaryBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HR & Payroll → Salary Processing (AnnotechERP 24/06): Daily Salary, OT
 * Salary and Monthly Salary, as Brickland lays them out.
 *
 * - **Daily Salary:** load a day for a scope (bulk, one employee, a
 *   department, section or line), calculate it from attendance, mark it
 *   reviewed, approve it (or un-approve it to calculate again) — all rows or
 *   the ticked ones.
 * - **OT Salary:** the OT paid from attendance over a range (up to 92 days),
 *   with corrections applied; Recalculate works the range's attendance again.
 * - **Monthly Salary:** a pay period's run — calculate (any scope; a
 *   recalculation goes back to calculated), review, approve, finalize (locked:
 *   slips processed, approved adjustments paid), mark paid, or re-open a
 *   finalized run; the slips, Gross A & Gross B, the bank and cash lists.
 */
class HrSalaryApiController extends ApiController
{
    private const TZ = SalaryBook::TZ;

    private const OT_MAX_DAYS = 92;

    private const OT_MAX_ROWS = 5000;

    private const RUN_FLOW = ['review' => ['CALCULATED', 'REVIEWED', 'hrpayroll.salary.calculate'], 'approve' => ['REVIEWED', 'APPROVED', 'hrpayroll.salary.approve'], 'finalize' => ['APPROVED', 'FINALIZED', 'hrpayroll.salary.finalize'], 'pay' => ['FINALIZED', 'PAID', 'hrpayroll.salary.finalize'], 'reopen' => ['FINALIZED', 'APPROVED', 'hrpayroll.salary.finalize']];

    public function options(): JsonResponse
    {
        $this->allowEither('hrpayroll.salary.view', 'hrpayroll.salary.calculate', 'hrpayroll.salary.approve', 'hrpayroll.salary.finalize');
        $c = $this->caller()->companyId;

        return ApiResponse::ok([
            'today' => CarbonImmutable::now(self::TZ)->toDateString(),
            'departments' => DB::table('departments')->where('company_id', $c)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name'])->map(fn ($x) => ['value' => $x->id, 'label' => $x->name])->all(),
            'sections' => DB::table('sections')->where('company_id', $c)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'department_id'])->map(fn ($x) => ['value' => $x->id, 'label' => $x->name, 'department_id' => $x->department_id])->all(),
            'lines' => DB::table('production_lines')->where('company_id', $c)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name', 'section_id', 'department_id'])->map(fn ($x) => ['value' => $x->id, 'label' => $x->name, 'section_id' => $x->section_id, 'department_id' => $x->department_id])->all(),
            'employees' => DB::table('hr_employees')->where('company_id', $c)->whereIn('status', ['ACTIVE', 'SUSPENDED'])->orderBy('employee_code')->get(['employee_code', 'name'])->map(fn ($e) => ['value' => $e->employee_code, 'label' => "{$e->employee_code} — {$e->name}"])->all(),
            'periods' => $this->periods()->map(fn ($p) => ['value' => $p->period_no, 'label' => $this->periodLabel($p), 'closed' => (bool) $p->closed])->values()->all(),
            'can' => ['calculate' => $this->caller()->can('hrpayroll.salary.calculate'), 'approve' => $this->caller()->can('hrpayroll.salary.approve'), 'finalize' => $this->caller()->can('hrpayroll.salary.finalize')],
        ]);
    }

    /* ── Daily Salary ──────────────────────────────────────────────── */

    public function daily(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.salary.view', 'hrpayroll.salary.calculate', 'hrpayroll.salary.approve');
        $date = $this->validDate($request);

        return ApiResponse::ok($this->dailyView($date, SalaryBook::scope($request->all())));
    }

    public function calculateDaily(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.salary.calculate');
        $date = $this->validDate($request);
        $scope = SalaryBook::scope($request->all());
        $book = $this->book();
        $people = $this->picked($book->employees($scope)->get(), $request)->filter(fn ($e) => $this->employedOn($e, $date, $date))->values();
        $ids = $people->pluck('id')->all();
        $stored = DB::table('hr_daily_salaries')->whereIn('employee_id', $ids)->where('date', $date)->get()->keyBy('employee_id');
        $att = DB::table('hr_attendance_days')->whereIn('employee_id', $ids)->where('date', $date)->get()->keyBy('employee_id');
        $paid = DB::table('hr_leave_types')->where('company_id', $this->caller()->companyId)->pluck('paid', 'id');
        $lines = $book->payLines($ids);
        $ot = $book->otDays($date, $date, $ids, $people);
        $done = 0;
        $skipped = 0;
        $now = now();
        DB::transaction(function () use ($people, $stored, $att, $paid, $lines, $ot, $book, $date, $now, &$done, &$skipped): void {
            foreach ($people as $e) {
                $old = $stored[$e->id] ?? null;
                if ($old && $old->status === 'APPROVED') {
                    $skipped++;

                    continue;
                }
                $a = $att[$e->id] ?? null;
                $v = $book->daily($e, $date, $a, $a && $a->leave_type_id ? (bool) ($paid[$a->leave_type_id] ?? true) : null, $lines[$e->id] ?? [], $ot["{$e->id}|{$date}"] ?? null);
                $v['flags'] = json_encode($v['flags'], JSON_UNESCAPED_UNICODE);
                $v += ['status' => 'CALCULATED', 'calculated_by' => $this->caller()->auditUserId(), 'reviewed_by' => null, 'reviewed_at' => null, 'approved_by' => null, 'approved_at' => null, 'updated_at' => $now];
                if ($old) {
                    DB::table('hr_daily_salaries')->where('id', $old->id)->update($v);
                } else {
                    DB::table('hr_daily_salaries')->insert($v + ['id' => $this->ulid(), 'company_id' => $this->caller()->companyId, 'employee_id' => $e->id, 'date' => $date, 'created_at' => $now]);
                }
                $done++;
            }
        });
        $message = __('hrpayroll.sp.done.daily_calculated', ['n' => $done, 'date' => $date]).($skipped ? ' '.__('hrpayroll.sp.done.approved_kept', ['n' => $skipped]) : '');

        return ApiResponse::ok(['message' => $message, 'view' => $this->dailyView($date, $scope)]);
    }

    public function moveDaily(Request $request): JsonResponse
    {
        $d = $request->validate(['action' => ['required', Rule::in(['review', 'approve', 'unapprove'])]]);
        $flow = ['review' => ['CALCULATED', 'REVIEWED', 'hrpayroll.salary.calculate', 'reviewed'], 'approve' => ['REVIEWED', 'APPROVED', 'hrpayroll.salary.approve', 'approved'], 'unapprove' => ['APPROVED', 'REVIEWED', 'hrpayroll.salary.approve', 'unapproved']][$d['action']];
        $this->allow($flow[2]);
        $date = $this->validDate($request);
        $scope = SalaryBook::scope($request->all());
        $people = $this->picked($this->book()->employees($scope)->get(), $request);
        $q = DB::table('hr_daily_salaries')->whereIn('employee_id', $people->pluck('id'))->where('date', $date)->where('status', $flow[0]);
        $n = (clone $q)->count();
        if ($n === 0) {
            throw ValidationException::withMessages(['action' => __("hrpayroll.sp.v.none_to_{$d['action']}")]);
        }
        $by = $this->caller()->auditUserId();
        $q->update(match ($d['action']) {
            'review' => ['status' => 'REVIEWED', 'reviewed_by' => $by, 'reviewed_at' => now(), 'updated_at' => now()],
            'approve' => ['status' => 'APPROVED', 'approved_by' => $by, 'approved_at' => now(), 'updated_at' => now()],
            default => ['status' => 'REVIEWED', 'approved_by' => null, 'approved_at' => null, 'updated_at' => now()],
        });

        return ApiResponse::ok(['message' => __("hrpayroll.sp.done.daily_{$flow[3]}", ['n' => $n]), 'view' => $this->dailyView($date, $scope)]);
    }

    /* ── OT Salary ─────────────────────────────────────────────────── */

    public function otSalary(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.salary.view', 'hrpayroll.salary.calculate');
        [$from, $to] = $this->otRange($request);
        $scope = SalaryBook::scope(['mode' => 'BULK'] + $request->only(['department', 'section', 'line', 'employee']));
        $book = $this->book();
        $people = $this->employedIn($book->employees($scope)->get(), $from, $to);
        $ids = $people->pluck('id')->all();
        $ot = $book->otDays($from, $to, $ids, $people);
        $byId = $people->keyBy('id');
        $att = DB::table('hr_attendance_days as a')->leftJoin('hr_shifts as s', 's.id', '=', 'a.shift_id')->whereIn('a.employee_id', $ids)->whereBetween('a.date', [$from, $to])
            ->get(['a.employee_id', 'a.date', 'a.out_at', 's.name as shift', 's.start_time', 's.end_time', 's.working_hours', 's.break_min'])->keyBy(fn ($a) => $a->employee_id.'|'.substr((string) $a->date, 0, 10));
        uksort($ot, function (string $a, string $b) use ($byId): int {
            [$ea, $da] = explode('|', $a);
            [$eb, $db] = explode('|', $b);

            return [$da, $byId[$ea]->employee_code] <=> [$db, $byId[$eb]->employee_code];
        });
        $rows = [];
        foreach ($ot as $key => $o) {
            if ($o['hours'] <= 0 && $o['original_hours'] <= 0) {
                continue;
            }
            [$id, $date] = explode('|', $key);
            $e = $byId[$id];
            $a = $att[$key] ?? null;
            $rows[] = [
                'key' => "{$e->employee_code}|{$date}", 'date' => $date, 'code' => $e->employee_code, 'name' => $e->name, 'department' => $e->department, 'section' => $e->section, 'line' => $e->line,
                'shift' => $a->shift ?? null, 'shift_end' => AttendanceBook::regularEnd($a), 'out_time' => $a && $a->out_at ? CarbonImmutable::parse($a->out_at, 'UTC')->timezone(self::TZ)->format('H:i') : null,
                'original_hours' => $o['original_hours'], 'hours' => $o['hours'], 'rate' => $o['rate'], 'amount' => $o['amount'], 'corrected' => $o['corrected'],
            ];
        }
        $paid = array_filter($rows, fn ($r) => $r['hours'] > 0);
        $waiting = DB::table('hr_attendance_days')->whereIn('employee_id', $ids)->whereBetween('date', [$from, $to])->where('status', 'MISSING_PUNCH')->count();

        return ApiResponse::ok([
            'filters' => ['from' => $from, 'to' => $to] + $request->only(['department', 'section', 'line', 'employee']),
            'employees' => count(array_unique(array_column($paid, 'code'))),
            'total_hours' => round(array_sum(array_column($rows, 'hours')), 2),
            'total_amount' => round(array_sum(array_column($rows, 'amount')), 2),
            'waiting' => $waiting,
            'rows' => array_slice($rows, 0, self::OT_MAX_ROWS),
            'truncated' => count($rows) > self::OT_MAX_ROWS,
            'can_recalculate' => $this->caller()->can('hrpayroll.salary.calculate'),
        ]);
    }

    public function recalculateOt(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.salary.calculate');
        [$from, $to] = $this->otRange($request);
        $scope = SalaryBook::scope(['mode' => 'BULK'] + $request->only(['department', 'section', 'line', 'employee']));
        $ids = $this->book()->employees($scope)->pluck('e.id')->all();
        $n = $ids ? (new AttendanceBook($this->caller()->companyId))->process($from, $to, $ids) : 0;

        return ApiResponse::ok(['message' => __('hrpayroll.sp.done.ot_recalculated', ['n' => $n])]);
    }

    /* ── Monthly Salary ────────────────────────────────────────────── */

    public function monthly(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.salary.view', 'hrpayroll.salary.calculate', 'hrpayroll.salary.approve', 'hrpayroll.salary.finalize');
        $period = $this->period((string) $request->query('period', ''));

        return ApiResponse::ok($this->monthlyView($period, SalaryBook::scope($request->all())));
    }

    public function calculateMonthly(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.salary.calculate');
        $period = $this->period((string) $request->input('period', ''));
        $scope = SalaryBook::scope($request->all());
        if ($period->closed) {
            throw ValidationException::withMessages(['period' => __('hrpayroll.sp.v.period_closed')]);
        }
        $run = DB::table('hr_salary_runs')->where('pay_period_id', $period->id)->first();
        if ($run && in_array($run->status, ['FINALIZED', 'PAID'], true)) {
            throw ValidationException::withMessages(['period' => __('hrpayroll.sp.v.locked')]);
        }
        $start = substr((string) $period->start_date, 0, 10);
        $end = substr((string) $period->end_date, 0, 10);
        $book = $this->book();
        $people = $this->employedIn($book->employees($scope)->get(), $start, $end);
        if ($people->isEmpty()) {
            throw ValidationException::withMessages(['period' => __('hrpayroll.sp.v.nobody')]);
        }
        $ids = $people->pluck('id')->all();
        $att = DB::table('hr_attendance_days')->whereIn('employee_id', $ids)->whereBetween('date', [$start, $end])->get(['employee_id', 'date', 'status', 'leave_type_id', 'is_late', 'late_minutes'])->groupBy('employee_id');
        $paidLeave = DB::table('hr_leave_types')->where('company_id', $this->caller()->companyId)->pluck('paid', 'id')->map(fn ($v) => (bool) $v)->all();
        $lines = $book->payLines($ids);
        $periodLines = $book->periodLines($period->id, $people);
        $ot = $book->otDays($start, $end, $ids, $people);
        $otHours = [];
        $otAmount = [];
        foreach ($ot as $key => $o) {
            $id = explode('|', $key)[0];
            $otHours[$id] = ($otHours[$id] ?? 0) + $o['hours'];
            $otAmount[$id] = ($otAmount[$id] ?? 0) + $o['amount'];
        }
        $bonus = DB::table('hr_bonus_lines as l')->join('hr_bonus_runs as r', 'r.id', '=', 'l.run_id')->where('r.pay_period_id', $period->id)->whereIn('r.status', ['FINALIZED', 'PAID'])
            ->where('l.eligible', true)->whereIn('l.employee_id', $ids)->groupBy('l.employee_id')->selectRaw('l.employee_id, sum(l.amount) as amount')->pluck('amount', 'employee_id');
        // Approved adjustments of earlier periods, paid once with the next salary calculated.
        $adjustments = DB::table('hr_payroll_adjustments as a')->join('hr_pay_periods as p', 'p.id', '=', 'a.pay_period_id')
            ->where('a.company_id', $this->caller()->companyId)->where('a.status', 'APPROVED')->whereIn('a.employee_id', $ids)->where('p.start_date', '<', $start)
            ->where(fn ($q) => $q->whereNull('a.paid_in_period_id')->orWhere('a.paid_in_period_id', $period->id))->get(['a.id', 'a.employee_id', 'a.amount']);
        $adjSum = $adjustments->groupBy('employee_id')->map(fn ($g) => (float) $g->sum('amount'));
        $now = now();
        $by = $this->caller()->auditUserId();
        DB::transaction(function () use (&$run, $period, $people, $att, $paidLeave, $lines, $periodLines, $otHours, $otAmount, $bonus, $adjSum, $adjustments, $book, $now, $by): void {
            if ($run) {
                DB::table('hr_salary_runs')->where('id', $run->id)->update(['status' => 'CALCULATED', 'run_at' => $now, 'run_by' => $by, 'reviewed_by' => null, 'reviewed_at' => null, 'approved_by' => null, 'approved_at' => null, 'updated_at' => $now]);
            } else {
                $run = (object) ['id' => $this->ulid()];
                DB::table('hr_salary_runs')->insert(['id' => $run->id, 'company_id' => $this->caller()->companyId, 'pay_period_id' => $period->id, 'status' => 'CALCULATED', 'run_at' => $now, 'run_by' => $by, 'created_at' => $now, 'updated_at' => $now]);
            }
            $existing = DB::table('hr_salary_slips')->where('pay_period_id', $period->id)->whereIn('employee_id', $people->pluck('id'))->pluck('id', 'employee_id');
            foreach ($people as $e) {
                $v = $book->slip($period, $e, $att[$e->id] ?? collect(), $paidLeave, $lines[$e->id] ?? [], $periodLines[$e->id] ?? [], $otHours[$e->id] ?? 0.0, $otAmount[$e->id] ?? 0.0, (float) ($bonus[$e->id] ?? 0), $adjSum[$e->id] ?? 0.0);
                $v += ['run_id' => $run->id, 'status' => 'DRAFT', 'updated_at' => $now];
                if (isset($existing[$e->id])) {
                    DB::table('hr_salary_slips')->where('id', $existing[$e->id])->update($v);
                } else {
                    DB::table('hr_salary_slips')->insert($v + ['id' => $this->ulid(), 'company_id' => $this->caller()->companyId, 'pay_period_id' => $period->id, 'employee_id' => $e->id, 'created_at' => $now]);
                }
            }
            DB::table('hr_payroll_adjustments')->whereIn('id', $adjustments->pluck('id'))->update(['paid_in_period_id' => $period->id, 'updated_at' => $now]);
        });

        return ApiResponse::ok(['message' => __('hrpayroll.sp.done.monthly_calculated', ['n' => $people->count(), 'period' => $period->period_no]), 'view' => $this->monthlyView($period, $scope)]);
    }

    public function moveMonthly(Request $request): JsonResponse
    {
        $d = $request->validate(['action' => ['required', Rule::in(array_keys(self::RUN_FLOW))]]);
        [$fromStatus, $toStatus, $permission] = self::RUN_FLOW[$d['action']];
        $this->allow($permission);
        $period = $this->period((string) $request->input('period', ''));
        $run = DB::table('hr_salary_runs')->where('pay_period_id', $period->id)->first();
        if (! $run || $run->status !== $fromStatus) {
            throw ValidationException::withMessages(['action' => __('hrpayroll.sp.v.run_not', ['status' => __("hrpayroll.sp.run_status.{$fromStatus}")])]);
        }
        $by = $this->caller()->auditUserId();
        $now = now();
        DB::transaction(function () use ($d, $run, $period, $toStatus, $by, $now): void {
            $stamp = match ($d['action']) {
                'review' => ['reviewed_by' => $by, 'reviewed_at' => $now],
                'approve' => ['approved_by' => $by, 'approved_at' => $now],
                'finalize' => ['finalized_by' => $by, 'finalized_at' => $now],
                'pay' => ['paid_by' => $by, 'paid_at' => $now],
                default => ['finalized_by' => null, 'finalized_at' => null],
            };
            DB::table('hr_salary_runs')->where('id', $run->id)->update(['status' => $toStatus, 'updated_at' => $now] + $stamp);
            $slip = ['finalize' => 'PROCESSED', 'pay' => 'PAID', 'reopen' => 'DRAFT'][$d['action']] ?? null;
            if ($slip) {
                DB::table('hr_salary_slips')->where('run_id', $run->id)->update(['status' => $slip, 'updated_at' => $now]);
            }
            if ($d['action'] === 'finalize') {
                DB::table('hr_payroll_adjustments')->where('paid_in_period_id', $period->id)->where('status', 'APPROVED')->update(['status' => 'APPLIED', 'updated_at' => $now]);
            } elseif ($d['action'] === 'reopen') {
                DB::table('hr_payroll_adjustments')->where('paid_in_period_id', $period->id)->where('status', 'APPLIED')->update(['status' => 'APPROVED', 'updated_at' => $now]);
            }
        });

        return ApiResponse::ok(['message' => __("hrpayroll.sp.done.run_{$d['action']}", ['period' => $period->period_no]), 'view' => $this->monthlyView($period, SalaryBook::scope($request->all()))]);
    }

    /* ── Views ─────────────────────────────────────────────────────── */

    /** @return array<string, mixed> */
    private function dailyView(string $date, array $scope): array
    {
        $book = $this->book();
        $people = $book->employees($scope)->get()->filter(fn ($e) => $this->employedOn($e, $date, $date))->values();
        $ids = $people->pluck('id')->all();
        $stored = DB::table('hr_daily_salaries as s')->leftJoin('users as u', 'u.id', '=', 's.approved_by')->whereIn('s.employee_id', $ids)->where('s.date', $date)->get(['s.*', 'u.name as approver'])->keyBy('employee_id');
        $att = DB::table('hr_attendance_days')->whereIn('employee_id', $ids)->where('date', $date)->get()->keyBy('employee_id');
        $paid = DB::table('hr_leave_types')->where('company_id', $this->caller()->companyId)->pluck('paid', 'id');
        $rows = $people->map(function ($e) use ($stored, $att, $paid, $book, $date) {
            $s = $stored[$e->id] ?? null;
            $base = ['code' => $e->employee_code, 'name' => $e->name, 'department' => $e->department, 'section' => $e->section, 'line' => $e->line, 'designation' => $e->designation];
            if ($s) {
                return $base + [
                    'attendance' => $s->attendance_status, 'in_time' => $s->in_time, 'out_time' => $s->out_time, 'late_minutes' => (int) $s->late_minutes,
                    'daily_salary' => (float) $s->daily_salary, 'allowance' => (float) $s->allowance, 'ot_hours' => (float) $s->ot_hours, 'ot_rate' => (float) $s->ot_rate, 'ot_amount' => (float) $s->ot_amount,
                    'late_deduction' => (float) $s->late_deduction, 'other_deduction' => (float) $s->other_deduction, 'net' => (float) $s->net,
                    'status' => $s->status, 'approved_by' => $s->approver, 'approved_at' => $s->approved_at ? (string) $s->approved_at : null, 'flags' => json_decode((string) $s->flags, true) ?: [],
                ];
            }
            $a = $att[$e->id] ?? null;
            $v = $book->daily($e, $date, $a, $a && $a->leave_type_id ? (bool) ($paid[$a->leave_type_id] ?? true) : null, [], null);

            return $base + [
                'attendance' => $v['attendance_status'], 'in_time' => $v['in_time'], 'out_time' => $v['out_time'], 'late_minutes' => $v['late_minutes'],
                'daily_salary' => 0.0, 'allowance' => 0.0, 'ot_hours' => 0.0, 'ot_rate' => 0.0, 'ot_amount' => 0.0, 'late_deduction' => 0.0, 'other_deduction' => 0.0, 'net' => 0.0,
                'status' => 'NOT_CALCULATED', 'approved_by' => null, 'approved_at' => null, 'flags' => $v['flags'],
            ];
        })->values();

        return [
            'date' => $date, 'scope' => $scope, 'scope_label' => $book->scopeLabel($scope), 'days_in_month' => CarbonImmutable::parse($date)->daysInMonth,
            'rows' => $rows->all(),
            'summary' => [
                'employees' => $rows->count(), 'calculated' => $rows->where('status', '!=', 'NOT_CALCULATED')->count(), 'reviewed' => $rows->where('status', 'REVIEWED')->count(), 'approved' => $rows->where('status', 'APPROVED')->count(),
                'daily_salary' => round($rows->sum('daily_salary') + $rows->sum('allowance'), 2), 'allowance' => round($rows->sum('allowance'), 2), 'ot' => round($rows->sum('ot_amount'), 2),
                'deductions' => round($rows->sum('late_deduction') + $rows->sum('other_deduction'), 2), 'net' => round($rows->sum('net'), 2), 'flagged' => $rows->filter(fn ($r) => $r['flags'] !== [])->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function monthlyView(object $period, array $scope): array
    {
        $book = $this->book();
        $start = substr((string) $period->start_date, 0, 10);
        $end = substr((string) $period->end_date, 0, 10);
        $people = $this->employedIn($book->employees($scope)->get(), $start, $end)->keyBy('id');
        $run = DB::table('hr_salary_runs as r')->leftJoin('users as a', 'a.id', '=', 'r.reviewed_by')->leftJoin('users as b', 'b.id', '=', 'r.approved_by')->leftJoin('users as f', 'f.id', '=', 'r.finalized_by')->leftJoin('users as p', 'p.id', '=', 'r.paid_by')
            ->where('r.pay_period_id', $period->id)->first(['r.*', 'a.name as reviewer', 'b.name as approver', 'f.name as finalizer', 'p.name as payer']);
        $slips = DB::table('hr_salary_slips as s')->join('hr_employees as e', 'e.id', '=', 's.employee_id')
            ->leftJoin('departments as dp', 'dp.id', '=', 's.department_id')->leftJoin('sections as sc', 'sc.id', '=', 's.section_id')->leftJoin('production_lines as ln', 'ln.id', '=', 's.line_id')
            ->leftJoin('designations as d', 'd.id', '=', 's.designation_id')->leftJoin('grades as g', 'g.id', '=', 's.grade_id')
            ->where('s.pay_period_id', $period->id)->whereIn('s.employee_id', $people->keys())->orderBy('e.employee_code')
            ->get(['s.*', 'e.employee_code', 'e.name', 'dp.name as department', 'sc.name as section', 'ln.name as line', 'd.name as designation', 'g.code as grade']);
        $locked = $run && in_array($run->status, ['FINALIZED', 'PAID'], true);
        $states = $locked ? $book->slipStates($period, $slips) : [];
        $rows = $slips->map(fn ($s) => $this->slipRow($s, $states[$s->id] ?? null))->values();
        $flagged = $rows->where('adjustment_required', true)->count();
        $payable = $rows->where('held', false);

        return [
            'period' => ['no' => $period->period_no, 'label' => $this->monthName($start), 'start' => $start, 'end' => $end, 'pay_date' => substr((string) $period->pay_date, 0, 10), 'closed' => (bool) $period->closed],
            'scope' => $scope, 'scope_label' => $book->scopeLabel($scope),
            'run' => $run ? [
                'status' => $run->status, 'run_at' => (string) $run->run_at, 'reviewed_by' => $run->reviewer, 'approved_by' => $run->approver, 'approved_at' => $run->approved_at ? (string) $run->approved_at : null,
                'finalized_by' => $run->finalizer, 'finalized_at' => $run->finalized_at ? (string) $run->finalized_at : null, 'paid_by' => $run->payer, 'paid_at' => $run->paid_at ? (string) $run->paid_at : null,
                'slips' => DB::table('hr_salary_slips')->where('run_id', $run->id)->count(), 'total_net' => round((float) DB::table('hr_salary_slips')->where('run_id', $run->id)->sum('net'), 2),
            ] : null,
            'rows' => $rows->all(),
            'summary' => [
                'employees' => $rows->count(), 'held' => $rows->where('held', true)->count(), 'gross' => round($rows->sum('earned_gross'), 2), 'allowances' => round($rows->sum('allowances'), 2),
                'ot' => round($rows->sum('ot_amount'), 2), 'bonus' => round($rows->sum('bonus'), 2), 'late' => round($rows->sum('late_deduction'), 2), 'other' => round($rows->sum('other_deduction'), 2),
                'loan' => round($rows->sum('loan_advance'), 2), 'net' => round($rows->sum('net'), 2),
                'bank_count' => $payable->where('payment_method', 'BANK')->count(), 'cash_count' => $payable->where('payment_method', 'CASH')->count(),
                'bank_net' => round($payable->where('payment_method', 'BANK')->sum('net'), 2), 'cash_net' => round($payable->where('payment_method', 'CASH')->sum('net'), 2),
                'gross_b' => round($rows->sum('gross_b'), 2),
            ],
            'not_calculated' => $locked ? 0 : $people->count() - $slips->count(),
            'compliance_warnings' => $rows->where('below_minimum', true)->count(),
            'adjustment_required' => $flagged,
            'late_note' => $book->lateNote(),
        ];
    }

    /** @return array<string, mixed> */
    private function slipRow(object $s, ?array $state): array
    {
        $lines = json_decode((string) $s->lines, true) ?: ['allowances' => [], 'deductions' => []];

        return [
            'code' => $s->employee_code, 'name' => $s->name, 'department' => $s->department, 'section' => $s->section, 'line' => $s->line, 'designation' => $s->designation, 'grade' => $s->grade,
            'gross_a' => (float) $s->gross_a, 'gross_b' => (float) $s->gross_b, 'basic' => (float) $s->basic,
            'period_days' => (int) $s->period_days, 'present_days' => (int) $s->present_days, 'absent_days' => (int) $s->absent_days, 'unpaid_days' => (int) $s->unpaid_days, 'payable_days' => (int) $s->payable_days,
            'leave_deduction' => (float) $s->leave_deduction, 'earned_gross' => (float) $s->earned_gross, 'allowances' => (float) $s->allowances,
            'ot_hours' => (float) $s->ot_hours, 'ot_amount' => (float) $s->ot_amount, 'bonus' => (float) $s->bonus, 'late_days' => (int) $s->late_days, 'late_deduction' => (float) $s->late_deduction,
            'stamp_charge' => (float) $s->stamp_charge, 'other_deduction' => (float) $s->other_deduction, 'loan_advance' => (float) $s->loan_advance, 'adjustment' => (float) $s->adjustment, 'net' => (float) $s->net,
            'allowance_lines' => $lines['allowances'] ?? [], 'deduction_lines' => $lines['deductions'] ?? [],
            'slip_status' => $s->status, 'adjustment_required' => (bool) ($state['flagged'] ?? false), 'held' => (bool) $s->held,
            'payment_method' => $s->payment_method, 'structure' => $s->structure, 'minimum_wage' => $s->minimum_wage === null ? null : (float) $s->minimum_wage, 'minimum_basis' => $s->minimum_basis, 'below_minimum' => (bool) $s->below_minimum,
        ];
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    private function book(): SalaryBook
    {
        return new SalaryBook($this->caller()->companyId);
    }

    private function ulid(): string
    {
        return strtolower((string) Str::ulid());
    }

    private function validDate(Request $request): string
    {
        $today = CarbonImmutable::now(self::TZ)->toDateString();
        $d = $request->validate(['date' => ['nullable', 'date_format:Y-m-d', "before_or_equal:{$today}"]], ['date.before_or_equal' => __('hrpayroll.sp.v.future')]);

        return $d['date'] ?? $today;
    }

    /** @return array{0: string, 1: string} */
    private function otRange(Request $request): array
    {
        $today = CarbonImmutable::now(self::TZ)->toDateString();
        $d = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $from = $d['from'] ?? $today;
        $to = $d['to'] ?? $from;
        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1 > self::OT_MAX_DAYS) {
            throw ValidationException::withMessages(['to' => __('hrpayroll.sp.v.ot_range', ['n' => self::OT_MAX_DAYS])]);
        }

        return [$from, $to];
    }

    /** The ticked employees (by code) when some were ticked, else everyone. */
    private function picked(Collection $people, Request $request): Collection
    {
        $codes = $request->input('employees');

        return is_array($codes) && $codes !== [] ? $people->whereIn('employee_code', $codes)->values() : $people;
    }

    private function employedOn(object $e, string $from, string $to): bool
    {
        return (! $e->joining_date || substr((string) $e->joining_date, 0, 10) <= $to) && (! $e->separation_date || substr((string) $e->separation_date, 0, 10) >= $from);
    }

    private function employedIn(Collection $people, string $from, string $to): Collection
    {
        return $people->filter(fn ($e) => $this->employedOn($e, $from, $to))->values();
    }

    /** The pay periods to pick from: open ones and any with a run, newest first. */
    private function periods(): Collection
    {
        $withRun = DB::table('hr_salary_runs')->where('company_id', $this->caller()->companyId)->pluck('pay_period_id');

        return DB::table('hr_pay_periods')->where('company_id', $this->caller()->companyId)->where('period_type', 'MONTHLY')
            ->where(fn ($q) => $q->where('closed', false)->orWhereIn('id', $withRun))->orderByDesc('start_date')->orderByDesc('period_no')->get();
    }

    /** A period by its number, or the one holding today (else the latest). */
    private function period(string $no): object
    {
        $c = $this->caller()->companyId;
        if ($no !== '') {
            $p = DB::table('hr_pay_periods')->where('company_id', $c)->where('period_no', $no)->first();
            abort_unless($p, 404);

            return $p;
        }
        $today = CarbonImmutable::now(self::TZ)->toDateString();
        $periods = $this->periods();
        $p = $periods->first(fn ($p) => substr((string) $p->start_date, 0, 10) <= $today && substr((string) $p->end_date, 0, 10) >= $today) ?? $periods->first();
        if (! $p) {
            throw ValidationException::withMessages(['period' => __('hrpayroll.sp.v.no_period')]);
        }

        return $p;
    }

    private function periodLabel(object $p): string
    {
        return "{$p->period_no} · ".$this->monthName(substr((string) $p->start_date, 0, 10));
    }

    private function monthName(string $date): string
    {
        return CarbonImmutable::parse($date)->locale(app()->getLocale())->translatedFormat('F Y');
    }
}

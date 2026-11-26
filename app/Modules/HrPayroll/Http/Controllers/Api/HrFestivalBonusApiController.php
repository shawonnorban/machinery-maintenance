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
 * HR & Payroll → Salary Processing → Festival Bonus (AnnotechERP 24/06), as
 * Brickland lays it out: pick the payroll month; the festivals whose bonus
 * is paid with it (Festival Calendar) are listed; a festival's sheet —
 * every active employee, eligible with the rule and bonus, or not with why —
 * is a preview until calculated, then reviewed, approved, finalized (locked;
 * Monthly Salary carries it on the payment month's slips) and paid. A
 * reviewed or approved sheet can go back to draft.
 */
class HrFestivalBonusApiController extends ApiController
{
    private const FLOW = ['review' => ['DRAFT', 'REVIEWED', 'hrpayroll.salary.calculate'], 'approve' => ['REVIEWED', 'APPROVED', 'hrpayroll.salary.approve'], 'finalize' => ['APPROVED', 'FINALIZED', 'hrpayroll.salary.finalize'], 'pay' => ['FINALIZED', 'PAID', 'hrpayroll.salary.finalize']];

    public function show(Request $request): JsonResponse
    {
        $this->allowEither('hrpayroll.salary.view', 'hrpayroll.salary.calculate', 'hrpayroll.salary.approve', 'hrpayroll.salary.finalize');
        $d = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'festival' => ['nullable', 'string']]);

        return ApiResponse::ok($this->view($d['month'] ?? CarbonImmutable::now(SalaryBook::TZ)->format('Y-m'), $d['festival'] ?? null));
    }

    public function calculate(Request $request): JsonResponse
    {
        $this->allow('hrpayroll.salary.calculate');
        $festival = $this->festival((string) $request->input('festival'));
        $run = DB::table('hr_bonus_runs')->where('festival_id', $festival->id)->first();
        if ($run && ! in_array($run->status, ['DRAFT', 'REVIEWED'], true)) {
            throw ValidationException::withMessages(['festival' => __('hrpayroll.sp.v.bonus_locked')]);
        }
        $blockers = $this->blockers($festival);
        if ($blockers) {
            throw ValidationException::withMessages(['festival' => $blockers[0]]);
        }
        $book = new SalaryBook($this->caller()->companyId);
        $lines = $book->bonus($festival, $this->staff());
        $period = $book->periodFor($festival->payment_month);
        $total = round(array_sum(array_column(array_filter($lines, fn ($l) => $l['eligible']), 'amount')), 2);
        $now = now();
        DB::transaction(function () use (&$run, $festival, $lines, $period, $total, $now): void {
            $values = ['pay_period_id' => $period->id, 'status' => 'DRAFT', 'total' => $total, 'calculated_at' => $now, 'calculated_by' => $this->caller()->auditUserId(), 'reviewed_by' => null, 'reviewed_at' => null, 'approved_by' => null, 'approved_at' => null, 'updated_at' => $now];
            if ($run) {
                DB::table('hr_bonus_runs')->where('id', $run->id)->update($values);
                DB::table('hr_bonus_lines')->where('run_id', $run->id)->delete();
            } else {
                $run = (object) ['id' => strtolower((string) Str::ulid())];
                DB::table('hr_bonus_runs')->insert($values + ['id' => $run->id, 'company_id' => $this->caller()->companyId, 'festival_id' => $festival->id, 'created_at' => $now]);
            }
            foreach (array_chunk($lines, 200) as $chunk) {
                DB::table('hr_bonus_lines')->insert(array_map(fn ($l) => $l + ['id' => strtolower((string) Str::ulid()), 'run_id' => $run->id, 'created_at' => $now, 'updated_at' => $now], $chunk));
            }
        });
        $eligible = count(array_filter($lines, fn ($l) => $l['eligible']));

        return ApiResponse::ok(['message' => __('hrpayroll.sp.done.bonus_calculated', ['n' => $eligible, 'total' => number_format($total, 2)]), 'view' => $this->view($festival->payment_month, $festival->id)]);
    }

    public function move(Request $request): JsonResponse
    {
        $d = $request->validate(['action' => ['required', Rule::in(['review', 'approve', 'finalize', 'pay', 'reopen'])]]);
        $festival = $this->festival((string) $request->input('festival'));
        $run = DB::table('hr_bonus_runs')->where('festival_id', $festival->id)->first();
        if ($d['action'] === 'reopen') {
            $this->allow('hrpayroll.salary.approve');
            $ok = $run && in_array($run->status, ['REVIEWED', 'APPROVED'], true);
            $to = 'DRAFT';
        } else {
            [$from, $to, $permission] = self::FLOW[$d['action']];
            $this->allow($permission);
            $ok = $run && $run->status === $from;
        }
        if (! $ok) {
            throw ValidationException::withMessages(['action' => __('hrpayroll.sp.v.bonus_not_ready')]);
        }
        if ($d['action'] === 'finalize') {
            $salary = DB::table('hr_salary_runs')->where('pay_period_id', $run->pay_period_id)->value('status');
            if (in_array($salary, ['FINALIZED', 'PAID'], true)) {
                throw ValidationException::withMessages(['action' => __('hrpayroll.sp.v.salary_finalized')]);
            }
        }
        $by = $this->caller()->auditUserId();
        $now = now();
        $stamp = match ($d['action']) {
            'review' => ['reviewed_by' => $by, 'reviewed_at' => $now],
            'approve' => ['approved_by' => $by, 'approved_at' => $now],
            'finalize' => ['finalized_by' => $by, 'finalized_at' => $now],
            'pay' => ['paid_by' => $by, 'paid_at' => $now],
            default => ['reviewed_by' => null, 'reviewed_at' => null, 'approved_by' => null, 'approved_at' => null],
        };
        DB::table('hr_bonus_runs')->where('id', $run->id)->update(['status' => $to, 'updated_at' => $now] + $stamp);

        return ApiResponse::ok(['message' => __("hrpayroll.sp.done.bonus_{$d['action']}", ['festival' => "{$festival->name} {$festival->year}"]), 'view' => $this->view($festival->payment_month, $festival->id)]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    /** @return array<string, mixed> */
    private function view(string $month, ?string $festivalId): array
    {
        $c = $this->caller()->companyId;
        $festivals = DB::table('hr_festivals as f')->leftJoin('hr_bonus_rule_sets as s', 's.id', '=', 'f.bonus_rule_set_id')->leftJoin('hr_bonus_runs as r', 'r.festival_id', '=', 'f.id')
            ->where('f.company_id', $c)->where('f.active', true)->where('f.payment_month', $month)->orderBy('f.festival_date')
            ->get(['f.*', 's.name as rule_set', 'r.status as run_status']);
        $chosen = $festivalId ? $festivals->firstWhere('id', $festivalId) : ($festivals->count() === 1 ? $festivals->first() : null);

        return [
            'month' => $month,
            'festivals' => $festivals->map(fn ($f) => [
                'id' => $f->id, 'name' => $f->name, 'year' => (int) $f->year, 'festival_date' => substr((string) $f->festival_date, 0, 10), 'payment_month' => $f->payment_month,
                'rule_set' => $f->rule_set, 'bonus_type' => $f->bonus_type, 'min_service' => (int) ($f->min_service ?? 0), 'run_status' => $f->run_status,
            ])->values()->all(),
            'sheet' => $chosen ? $this->sheet($chosen) : null,
            'can' => ['calculate' => $this->caller()->can('hrpayroll.salary.calculate'), 'approve' => $this->caller()->can('hrpayroll.salary.approve'), 'finalize' => $this->caller()->can('hrpayroll.salary.finalize')],
        ];
    }

    /** @return array<string, mixed> */
    private function sheet(object $f): array
    {
        $run = DB::table('hr_bonus_runs as r')->leftJoin('hr_pay_periods as p', 'p.id', '=', 'r.pay_period_id')->leftJoin('users as a', 'a.id', '=', 'r.approved_by')
            ->where('r.festival_id', $f->id)->first(['r.*', 'p.period_no', 'a.name as approver']);
        $people = $this->staff()->keyBy('id');
        if ($run) {
            $lines = DB::table('hr_bonus_lines as l')->join('hr_employees as e', 'e.id', '=', 'l.employee_id')->where('l.run_id', $run->id)->orderBy('e.employee_code')->get(['l.*'])
                ->map(fn ($l) => (array) $l)->all();
            $people = DB::table('hr_employees as e')->leftJoin('departments as dp', 'dp.id', '=', 'e.department_id')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')
                ->whereIn('e.id', array_column($lines, 'employee_id'))->get(['e.*', 'dp.name as department', 'd.name as designation'])->keyBy('id');
        } else {
            $lines = (new SalaryBook($this->caller()->companyId))->bonus($f, $people->values());
        }
        $row = function (array $l) use ($people): array {
            $e = $people[$l['employee_id']];

            return [
                'code' => $e->employee_code, 'name' => $e->name, 'department' => $e->department, 'designation' => $e->designation, 'joining_date' => $e->joining_date ? substr((string) $e->joining_date, 0, 10) : null,
                'service_days' => $l['service_days'] === null ? null : (int) $l['service_days'], 'basis' => $l['basis'], 'basis_amount' => (float) $l['basis_amount'], 'pct' => $l['pct'] === null ? null : (float) $l['pct'],
                'fixed' => (float) $l['fixed'], 'amount' => (float) $l['amount'], 'rule' => $l['rule'], 'reason' => $l['reason'],
            ];
        };
        $eligible = array_values(array_map($row, array_filter($lines, fn ($l) => (bool) $l['eligible'])));
        $not = array_values(array_map($row, array_filter($lines, fn ($l) => ! $l['eligible'])));

        return [
            'festival' => ['id' => $f->id, 'name' => $f->name, 'year' => (int) $f->year, 'festival_date' => substr((string) $f->festival_date, 0, 10), 'payment_month' => $f->payment_month, 'bonus_type' => $f->bonus_type, 'rule_set' => $f->rule_set ?? null],
            'run' => $run ? ['status' => $run->status, 'period_no' => $run->period_no, 'calculated_at' => (string) $run->calculated_at, 'approved_by' => $run->approver, 'approved_at' => $run->approved_at ? (string) $run->approved_at : null, 'finalized_at' => $run->finalized_at ? (string) $run->finalized_at : null] : null,
            'eligible' => $eligible, 'not_eligible' => $not, 'total' => round(array_sum(array_column($eligible, 'amount')), 2),
            'blockers' => $run && ! in_array($run->status, ['DRAFT', 'REVIEWED'], true) ? [] : $this->blockers($f),
        ];
    }

    /** Why a festival's bonus cannot be calculated yet. @return list<string> */
    private function blockers(object $f): array
    {
        $out = [];
        if (! $f->bonus_rule_set_id) {
            $out[] = __('hrpayroll.sp.block.no_rule_set');
        }
        $period = (new SalaryBook($this->caller()->companyId))->periodFor($f->payment_month);
        if (! $period) {
            $out[] = __('hrpayroll.sp.block.no_period', ['month' => CarbonImmutable::parse("{$f->payment_month}-01")->format('F Y')]);
        } elseif (in_array(DB::table('hr_salary_runs')->where('pay_period_id', $period->id)->value('status'), ['FINALIZED', 'PAID'], true)) {
            $out[] = __('hrpayroll.sp.block.salary_finalized', ['period' => $period->period_no]);
        }

        return $out;
    }

    private function festival(string $id): object
    {
        $f = DB::table('hr_festivals as f')->leftJoin('hr_bonus_rule_sets as s', 's.id', '=', 'f.bonus_rule_set_id')->where('f.company_id', $this->caller()->companyId)->where('f.id', $id)->first(['f.*', 's.name as rule_set']);
        abort_unless($f, 404);

        return $f;
    }

    /** Every active employee with their department and designation. */
    private function staff()
    {
        return DB::table('hr_employees as e')->leftJoin('departments as dp', 'dp.id', '=', 'e.department_id')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')
            ->where('e.company_id', $this->caller()->companyId)->whereIn('e.status', ['ACTIVE', 'SUSPENDED'])->orderBy('e.employee_code')
            ->get(['e.*', 'dp.name as department', 'd.name as designation']);
    }
}

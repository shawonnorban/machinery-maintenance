<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Leaving the company (AnnotechERP 24/08):
 *
 * - **Off the roster:** an approved separation takes the employee off the
 *   active roster once the last working day has passed (status Separated,
 *   leaving date = last working day); a confirmed lefty at once from the
 *   last working date. A reinstated lefty is Active again.
 * - **Final settlement:** worked out from the records, as Brickland writes it
 *   in the statement's notes —
 *   - salary due: Gross A ÷ the days of each month for every day from the
 *     day after the last finalized salary (else the first of the leaving
 *     month, or the joining date) to the last working day, less absent and
 *     unpaid-leave days;
 *   - OT due: the OT on attendance (with corrections) in the same span;
 *   - bonus due: festival bonus approved for the employee but not yet paid
 *     on a finalized salary;
 *   - leave encashment: the balance of encashable leave types × Gross A ÷ 30;
 *   - other adjustment: approved payroll adjustments not yet paid;
 *   - loan recovery: nothing outstanding (no loan register yet).
 */
final class SeparationBook
{
    public const TZ = 'Asia/Dhaka';

    public function __construct(private readonly string $companyId) {}

    public static function today(): string
    {
        return CarbonImmutable::now(self::TZ)->toDateString();
    }

    /** Take employees whose approved separation's last day has passed off the roster. Answers how many. */
    public function applyDue(): int
    {
        $rows = DB::table('hr_separations')->where('company_id', $this->companyId)->where('status', 'APPROVED')->whereNull('applied_at')->where('last_working_date', '<', self::today())->get();
        foreach ($rows as $s) {
            DB::transaction(function () use ($s): void {
                $this->leave($s->employee_id, substr((string) $s->last_working_date, 0, 10));
                DB::table('hr_separations')->where('id', $s->id)->update(['applied_at' => now(), 'updated_at' => now()]);
            });
        }

        return $rows->count();
    }

    /** The employee leaves the roster: Separated from the day after the last working day. */
    public function leave(string $employeeId, string $lastDay): void
    {
        DB::table('hr_employees')->where('id', $employeeId)->update(['status' => 'SEPARATED', 'active' => false, 'separation_date' => $lastDay, 'updated_at' => now()]);
        DB::table('hr_employee_assignments')->where('employee_id', $employeeId)->whereNull('effective_to')->update(['effective_to' => $lastDay, 'updated_at' => now()]);
    }

    /** "1y 2m 19d" from the joining date to a date. */
    public static function serviceLength(?string $joined, string $to): string
    {
        if (! $joined || $joined > $to) {
            return '—';
        }
        $d = CarbonImmutable::parse($joined)->diff(CarbonImmutable::parse($to));

        return "{$d->y}y {$d->m}m {$d->d}d";
    }

    /**
     * The settlement's figures and how they were worked out.
     *
     * @return array{salary_due: float, ot_due: float, bonus_due: float, leave_encashment: float, deduction: float, loan_recovery: float, other_adjustment: float, notes: string}
     */
    public function calculate(object $e, string $actionDate): array
    {
        $gross = (float) $e->gross_a;
        $lastPaid = DB::table('hr_salary_slips as s')->join('hr_salary_runs as r', 'r.id', '=', 's.run_id')->join('hr_pay_periods as p', 'p.id', '=', 's.pay_period_id')
            ->where('s.employee_id', $e->id)->whereIn('r.status', ['FINALIZED', 'PAID'])->max('p.end_date');
        $monthStart = substr($actionDate, 0, 8).'01';
        $from = $lastPaid ? CarbonImmutable::parse(substr((string) $lastPaid, 0, 10))->addDay()->toDateString() : $monthStart;
        $joined = $e->joining_date ? substr((string) $e->joining_date, 0, 10) : null;
        if ($joined && $joined > $from) {
            $from = $joined;
        }
        $salary = 0.0;
        $days = 0;
        $absent = 0;
        $ot = 0.0;
        $otHours = 0.0;
        if ($from <= $actionDate) {
            $paidLeave = DB::table('hr_leave_types')->where('company_id', $this->companyId)->pluck('paid', 'id');
            $off = DB::table('hr_attendance_days')->where('employee_id', $e->id)->whereBetween('date', [$from, $actionDate])
                ->where(fn ($q) => $q->where('status', 'ABSENT')->orWhere(fn ($w) => $w->where('status', 'LEAVE')->whereNotNull('leave_type_id')))->get(['date', 'status', 'leave_type_id'])
                ->filter(fn ($a) => $a->status === 'ABSENT' || ! ($paidLeave[$a->leave_type_id] ?? true))->map(fn ($a) => substr((string) $a->date, 0, 10))->flip();
            for ($d = CarbonImmutable::parse($from); $d->toDateString() <= $actionDate; $d = $d->addDay()) {
                $days++;
                if (isset($off[$d->toDateString()])) {
                    $absent++;

                    continue;
                }
                $salary += $gross / $d->daysInMonth;
            }
            foreach ((new SalaryBook($this->companyId))->otDays($from, $actionDate, [$e->id]) as $o) {
                $ot += $o['amount'];
                $otHours += $o['hours'];
            }
        }
        // Bonus approved for the employee whose payment month's salary has not been finalized for them.
        $bonusLines = DB::table('hr_bonus_lines as l')->join('hr_bonus_runs as r', 'r.id', '=', 'l.run_id')->where('l.employee_id', $e->id)->where('l.eligible', true)
            ->whereIn('r.status', ['APPROVED', 'FINALIZED', 'PAID'])->get(['l.amount', 'r.pay_period_id']);
        $bonus = 0.0;
        $bonusCount = 0;
        foreach ($bonusLines as $b) {
            $paid = DB::table('hr_salary_slips as s')->join('hr_salary_runs as r', 'r.id', '=', 's.run_id')->where('s.employee_id', $e->id)->where('s.pay_period_id', $b->pay_period_id)->whereIn('r.status', ['FINALIZED', 'PAID'])->exists();
            if (! $paid) {
                $bonus += (float) $b->amount;
                $bonusCount++;
            }
        }
        // Encashable leave left this year.
        $leave = 0.0;
        $leaveDays = 0.0;
        $book = new LeaveBook($this->companyId);
        $year = $book->yearOf($actionDate);
        if ($year) {
            // Only leave earned so far is paid out: one day per 18 days present this year (Labour Act s.117),
            // plus what was carried forward, less what was taken — never more than the balance.
            $yearFrom = max(substr((string) $year->cal_start, 0, 10), $joined ?? '');
            $present = DB::table('hr_attendance_days')->where('employee_id', $e->id)->whereBetween('date', [$yearFrom, $actionDate])->whereIn('status', ['PRESENT', 'MISSING_PUNCH'])->count();
            $encashable = DB::table('hr_leave_types')->where('company_id', $this->companyId)->where('encashable', true)->pluck('id')->flip();
            foreach ($book->balances($e, $year) as $b) {
                if (isset($encashable[$b['leave_type_id']]) && $b['balance_day'] !== null && $b['balance_day'] > 0) {
                    $earned = intdiv($present, 18) + (float) $b['adjusted_day'] - (float) $b['taken_day'] - (float) $b['taken_hour'] / 8;
                    $leaveDays += max(0.0, min((float) $b['balance_day'], $earned));
                }
            }
            $leave = $leaveDays * $gross / 30;
        }
        $adjust = (float) DB::table('hr_payroll_adjustments')->where('employee_id', $e->id)->where('status', 'APPROVED')->whereNull('paid_in_period_id')->sum('amount');

        $notes = [
            $from <= $actionDate
                ? __('hrpayroll.st.note.salary', ['from' => self::dmy($from), 'to' => self::dmy($actionDate), 'days' => $days, 'absent' => $absent, 'rate' => number_format($gross / CarbonImmutable::parse($actionDate)->daysInMonth, 2), 'month_days' => CarbonImmutable::parse($actionDate)->daysInMonth])
                  .' '.($lastPaid ? __('hrpayroll.st.note.after_paid', ['date' => self::dmy(substr((string) $lastPaid, 0, 10))]) : __('hrpayroll.st.note.no_paid'))
                : __('hrpayroll.st.note.salary_none'),
            __('hrpayroll.st.note.ot', ['hours' => rtrim(rtrim(number_format($otHours, 2), '0'), '.'), 'amount' => number_format($ot, 2)]),
            __('hrpayroll.st.note.bonus', ['n' => $bonusCount]),
            $leaveDays > 0 ? __('hrpayroll.st.note.leave', ['days' => rtrim(rtrim(number_format($leaveDays, 2), '0'), '.')]) : __('hrpayroll.st.note.leave_none'),
            __('hrpayroll.st.note.loan'),
        ];
        if (abs($adjust) >= 0.01) {
            $notes[] = __('hrpayroll.st.note.adjustment', ['amount' => number_format($adjust, 2)]);
        }

        return [
            'salary_due' => round($salary, 2), 'ot_due' => round($ot, 2), 'bonus_due' => round($bonus, 2), 'leave_encashment' => round($leave, 2),
            'deduction' => 0.0, 'loan_recovery' => 0.0, 'other_adjustment' => round($adjust, 2), 'notes' => implode(' ', $notes),
        ];
    }

    /** @param array<string, mixed> $v */
    public static function payable(array $v): float
    {
        return round((float) $v['salary_due'] + (float) $v['ot_due'] + (float) $v['bonus_due'] + (float) $v['leave_encashment'] - (float) $v['deduction'] - (float) $v['loan_recovery'] + (float) $v['other_adjustment'], 2);
    }

    public static function dmy(string $date): string
    {
        return CarbonImmutable::parse($date)->format('d-m-Y');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * How pay is worked out (AnnotechERP 24/06), as Brickland's Salary
 * Processing screens show it:
 *
 * - **Scope:** Bulk (any of department, section, line, employee), or one
 *   employee, a department, a section or a line.
 * - **OT:** a day's OT from attendance (hours × the OT rule rate), or its
 *   OT Correction where one was saved.
 * - **Daily salary:** a day = Gross A ÷ the days in its month; a present,
 *   leave (paid type), holiday or weekly-off day is paid, an absent, unpaid
 *   leave or unrecorded day is not; the day's share of the employee's other
 *   allowances and deductions, and the day's OT. Late is decided at month end.
 * - **Monthly slip:** Gross A less the unpaid days (absent, unpaid leave,
 *   not yet joined or already left) at Gross A ÷ the period's days; other
 *   allowances; OT; the finalized festival bonus paid with the month; late
 *   by the payroll settings; the stamp charge and other deductions;
 *   loan/advance; approved payroll adjustments. Net is rounded by the
 *   payroll settings.
 * - **Festival bonus:** by the festival's bonus rule set — the rule whose
 *   service (months) and salary band hold the employee, for their employee
 *   type (and department where the rule names one): the basis (Basic or
 *   Gross A) × the percentage, plus any fixed amount.
 */
final class SalaryBook
{
    public const TZ = 'Asia/Dhaka';

    /** Revenue stamp on every monthly slip that pays anything. */
    public const STAMP_CHARGE = 10.0;

    public const MODES = ['BULK', 'INDIVIDUAL', 'DEPARTMENT', 'SECTION', 'LINE'];

    /** Which filters each mode keeps. */
    private const MODE_KEYS = ['BULK' => ['department', 'section', 'line', 'employee'], 'INDIVIDUAL' => ['employee'], 'DEPARTMENT' => ['department'], 'SECTION' => ['department', 'section'], 'LINE' => ['department', 'section', 'line']];

    private ?OtBook $ot = null;

    /** @var array<string, float> */
    private array $basics = [];

    public function __construct(private readonly string $companyId) {}

    public function otBook(): OtBook
    {
        return $this->ot ??= new OtBook($this->companyId);
    }

    /* ── Scope ─────────────────────────────────────────────────────── */

    /**
     * The scope a request asks for, with the filters its mode does not use dropped.
     *
     * @param  array<string, mixed>  $in
     * @return array{mode: string, department: string, section: string, line: string, employee: string}
     */
    public static function scope(array $in): array
    {
        $mode = in_array($in['mode'] ?? 'BULK', self::MODES, true) ? $in['mode'] ?? 'BULK' : 'BULK';
        $out = ['mode' => $mode];
        foreach (['department', 'section', 'line', 'employee'] as $k) {
            $out[$k] = in_array($k, self::MODE_KEYS[$mode], true) ? (string) ($in[$k] ?? '') : '';
        }

        return $out;
    }

    /** The employees on the payroll in a scope, with their place by name. */
    public function employees(array $scope): Builder
    {
        return DB::table('hr_employees as e')
            ->leftJoin('departments as dp', 'dp.id', '=', 'e.department_id')->leftJoin('sections as sc', 'sc.id', '=', 'e.section_id')
            ->leftJoin('production_lines as ln', 'ln.id', '=', 'e.line_id')->leftJoin('designations as d', 'd.id', '=', 'e.designation_id')
            ->leftJoin('grades as g', 'g.id', '=', 'e.grade_id')
            ->where('e.company_id', $this->companyId)->whereIn('e.status', ['ACTIVE', 'SUSPENDED'])
            ->when($scope['department'] !== '', fn ($q) => $q->where('e.department_id', $scope['department']))
            ->when($scope['section'] !== '', fn ($q) => $q->where('e.section_id', $scope['section']))
            ->when($scope['line'] !== '', fn ($q) => $q->where('e.line_id', $scope['line']))
            ->when($scope['employee'] !== '', fn ($q) => $q->where('e.employee_code', $scope['employee']))
            ->orderBy('e.employee_code')
            ->select(['e.*', 'dp.name as department', 'sc.name as section', 'ln.name as line', 'd.name as designation', 'g.code as grade']);
    }

    /** "All employees", "Sewing › Line Production", "EMP-000001 — Mohammad Sayem" */
    public function scopeLabel(array $scope): string
    {
        if ($scope['employee'] !== '') {
            $e = DB::table('hr_employees')->where('company_id', $this->companyId)->where('employee_code', $scope['employee'])->first(['employee_code', 'name']);

            return $e ? "{$e->employee_code} — {$e->name}" : $scope['employee'];
        }
        $names = array_filter([
            $scope['department'] !== '' ? DB::table('departments')->where('id', $scope['department'])->value('name') : null,
            $scope['section'] !== '' ? DB::table('sections')->where('id', $scope['section'])->value('name') : null,
            $scope['line'] !== '' ? DB::table('production_lines')->where('id', $scope['line'])->value('name') : null,
        ]);

        return $names ? implode(' › ', $names) : __('hrpayroll.sp.all_employees');
    }

    /* ── OT ────────────────────────────────────────────────────────── */

    /**
     * Each day's OT from attendance in a range: "employee|date" => the
     * attendance hours, the hours and rate paid (the correction's where one
     * was saved) and the amount.
     *
     * @param  list<string>  $ids
     * @return array<string, array{original_hours: float, hours: float, rate: float, amount: float, corrected: bool}>
     */
    public function otDays(string $from, string $to, array $ids, ?Collection $employees = null): array
    {
        if (! $ids) {
            return [];
        }
        $days = DB::table('hr_attendance_days as a')->leftJoin('hr_ot_corrections as k', fn ($j) => $j->on('k.employee_id', '=', 'a.employee_id')->on('k.date', '=', 'a.date'))
            ->where('a.company_id', $this->companyId)->whereIn('a.employee_id', $ids)->whereBetween('a.date', [$from, $to])
            ->where(fn ($q) => $q->where('a.ot_minutes', '>', 0)->orWhereNotNull('k.id'))
            ->get(['a.employee_id', 'a.date', 'a.ot_minutes', 'k.id as k_id', 'k.hours as k_hours', 'k.rate as k_rate']);
        $rates = $this->rates($days->pluck('employee_id')->unique()->values()->all(), $employees);
        $out = [];
        foreach ($days as $d) {
            $original = round(((int) $d->ot_minutes) / 60, 2);
            $hours = $d->k_id ? (float) $d->k_hours : $original;
            $rate = $d->k_id ? (float) $d->k_rate : ($rates[$d->employee_id] ?? 0.0);
            $out[$d->employee_id.'|'.substr((string) $d->date, 0, 10)] = ['original_hours' => $original, 'hours' => $hours, 'rate' => $rate, 'amount' => round($hours * $rate, 2), 'corrected' => (bool) $d->k_id];
        }

        return $out;
    }

    /**
     * Each employee's OT rate by their OT standing.
     *
     * @param  list<string>  $ids
     * @return array<string, float>
     */
    public function rates(array $ids, ?Collection $employees = null): array
    {
        if (! $ids) {
            return [];
        }
        $people = $employees ? $employees->whereIn('id', $ids)->values() : DB::table('hr_employees')->whereIn('id', $ids)->get();
        $standing = $this->otBook()->eligibility($people);
        $out = [];
        foreach ($people as $e) {
            $out[$e->id] = $this->otBook()->rate($e, $standing[$e->id]['rule'] ?? null);
        }

        return $out;
    }

    /** An employee's Basic (their Basic pay line, or the Basic Gross A works out to). */
    public function basic(object $e): float
    {
        return $this->basics[$e->id] ??= $this->otBook()->basic($e);
    }

    /* ── Allowances and deductions ─────────────────────────────────── */

    /**
     * The employees' standing allowances and deductions — their pay lines
     * outside the core salary that affect pay.
     *
     * @param  list<string>  $ids
     * @return array<string, array{ALLOWANCE: list<array{0: string, 1: float}>, DEDUCTION: list<array{0: string, 1: float}>}>
     */
    public function payLines(array $ids): array
    {
        $out = [];
        if (! $ids) {
            return $out;
        }
        $rows = DB::table('hr_employee_pay_lines as l')->join('hr_pay_elements as p', 'p.id', '=', 'l.pay_element_id')
            ->whereIn('l.employee_id', $ids)->where('l.active', true)->where('l.effect_salary', true)->where('l.core_salary', false)->where('l.amount', '>', 0)
            ->orderBy('l.sort_order')->orderBy('p.name')->get(['l.employee_id', 'l.type', 'p.name', 'l.amount']);
        foreach ($rows as $r) {
            $out[$r->employee_id][$r->type][] = [$r->name, (float) $r->amount];
        }

        return $out;
    }

    /**
     * The period's one-off allowances and deductions (Payroll Settings →
     * Allowance / Deduction), filed against the employee's login by
     * employee code.
     *
     * @param  Collection<int, object>  $employees
     * @return array<string, array{ALLOWANCE: list<array{0: string, 1: float}>, DEDUCTION: list<array{0: string, 1: float}>}>
     */
    public function periodLines(string $periodId, Collection $employees): array
    {
        $byCode = $employees->pluck('id', 'employee_code');
        $users = DB::table('users')->whereIn('employee_code', $byCode->keys()->all())->pluck('employee_code', 'id');
        $out = [];
        if ($users->isEmpty()) {
            return $out;
        }
        foreach (['hr_allowances' => 'ALLOWANCE', 'hr_deductions' => 'DEDUCTION'] as $table => $type) {
            $rows = DB::table("{$table} as x")->join('hr_pay_elements as p', 'p.id', '=', 'x.pay_element_id')
                ->where('x.pay_period_id', $periodId)->whereIn('x.user_id', $users->keys()->all())->get(['x.user_id', 'p.name', 'x.amount']);
            foreach ($rows as $r) {
                $out[$byCode[$users[$r->user_id]]][$type][] = [$r->name, (float) $r->amount];
            }
        }

        return $out;
    }

    /* ── Daily salary ──────────────────────────────────────────────── */

    /**
     * A day's pay for an employee.
     *
     * @param  array{ALLOWANCE?: list<array{0: string, 1: float}>, DEDUCTION?: list<array{0: string, 1: float}>}  $lines
     * @param  array{hours: float, rate: float, amount: float}|null  $ot
     * @return array<string, mixed>
     */
    public function daily(object $e, string $date, ?object $att, ?bool $leavePaid, array $lines, ?array $ot): array
    {
        $days = CarbonImmutable::parse($date)->daysInMonth;
        $gross = (float) $e->gross_a;
        $status = $att->status ?? 'NO_RECORD';
        $paid = match ($status) {
            'PRESENT', 'HOLIDAY', 'WEEKLY_OFF', 'MISSING_PUNCH' => true,
            'LEAVE' => $leavePaid !== false,
            default => false,
        };
        $flags = [];
        if ($status === 'NO_RECORD') {
            $flags[] = __('hrpayroll.sp.flag.no_record');
        } elseif ($status === 'ABSENT') {
            $flags[] = __('hrpayroll.sp.flag.absent');
        } elseif ($status === 'MISSING_PUNCH') {
            $flags[] = __('hrpayroll.sp.flag.missing_punch');
        } elseif ($status === 'LEAVE' && ! $paid) {
            $flags[] = __('hrpayroll.sp.flag.unpaid_leave');
        }
        if ($gross <= 0) {
            $flags[] = __('hrpayroll.sp.flag.no_gross');
        }
        $sum = fn (string $type) => array_sum(array_map(fn ($l) => $l[1], $lines[$type] ?? []));
        $dailySalary = $paid ? round($gross / $days, 2) : 0.0;
        $allowance = $paid ? round($sum('ALLOWANCE') / $days, 2) : 0.0;
        $other = $paid ? round($sum('DEDUCTION') / $days, 2) : 0.0;
        $otAmount = (float) ($ot['amount'] ?? 0);

        return [
            'attendance_status' => $status,
            'in_time' => $att && $att->in_at ? CarbonImmutable::parse($att->in_at, 'UTC')->timezone(self::TZ)->format('H:i') : null,
            'out_time' => $att && $att->out_at ? CarbonImmutable::parse($att->out_at, 'UTC')->timezone(self::TZ)->format('H:i') : null,
            'late_minutes' => (int) ($att->late_minutes ?? 0),
            'gross_a' => $gross, 'daily_salary' => $dailySalary, 'allowance' => $allowance,
            'ot_hours' => (float) ($ot['hours'] ?? 0), 'ot_rate' => (float) ($ot['rate'] ?? 0), 'ot_amount' => $otAmount,
            'late_deduction' => 0.0, 'other_deduction' => $other,
            'net' => round($dailySalary + $allowance + $otAmount - $other, 2),
            'flags' => $flags,
        ];
    }

    /* ── Monthly slip ──────────────────────────────────────────────── */

    /**
     * An employee's slip for a pay period.
     *
     * @param  Collection<int, object>  $attendance  the employee's attendance days in the period
     * @param  array<string, bool>  $paidLeave  leave type id => paid
     * @param  array{ALLOWANCE?: list<array{0: string, 1: float}>, DEDUCTION?: list<array{0: string, 1: float}>}  $lines
     * @param  array{ALLOWANCE?: list<array{0: string, 1: float}>, DEDUCTION?: list<array{0: string, 1: float}>}  $periodLines
     * @return array<string, mixed>
     */
    public function slip(object $period, object $e, Collection $attendance, array $paidLeave, array $lines, array $periodLines, float $otHours, float $otAmount, float $bonus, float $adjustment): array
    {
        $start = substr((string) $period->start_date, 0, 10);
        $end = substr((string) $period->end_date, 0, 10);
        $periodDays = (int) CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) + 1;
        $from = $e->joining_date && substr((string) $e->joining_date, 0, 10) > $start ? substr((string) $e->joining_date, 0, 10) : $start;
        $to = $e->separation_date && substr((string) $e->separation_date, 0, 10) < $end ? substr((string) $e->separation_date, 0, 10) : $end;
        $employed = $from > $to ? 0 : (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1;
        $mine = $attendance->filter(fn ($a) => ($d = substr((string) $a->date, 0, 10)) >= $from && $d <= $to);
        $present = $mine->whereIn('status', ['PRESENT', 'MISSING_PUNCH'])->count();
        $absent = $mine->where('status', 'ABSENT')->count();
        $unpaidLeave = $mine->filter(fn ($a) => $a->status === 'LEAVE' && $a->leave_type_id && ($paidLeave[$a->leave_type_id] ?? true) === false)->count();
        $unpaid = ($periodDays - $employed) + $absent + $unpaidLeave;
        $gross = (float) $e->gross_a;
        $leaveDeduction = round($gross / $periodDays * $unpaid, 2);
        $earned = round($gross - $leaveDeduction, 2);
        $basic = $this->basic($e);
        $lateDays = $mine->filter(fn ($a) => (bool) $a->is_late)->count();
        $lateMinutes = (int) $mine->sum('late_minutes');
        $lateBasis = $this->setting('LATE_DEDUCTION_BASIS') === 'GROSS' ? $gross : $basic;
        $late = match ($this->setting('LATE_DEDUCTION_MODE')) {
            'LATE_DAYS' => round(intdiv($lateDays, max(1, (int) $this->setting('LATE_DAYS_PER_DEDUCTION'))) * $lateBasis / $periodDays, 2),
            'PER_MINUTE' => round($lateMinutes * $lateBasis / $periodDays / 480, 2),
            default => 0.0,
        };
        $allowLines = array_merge($lines['ALLOWANCE'] ?? [], $periodLines['ALLOWANCE'] ?? []);
        $dedLines = array_merge($lines['DEDUCTION'] ?? [], $periodLines['DEDUCTION'] ?? []);
        $allowances = round(array_sum(array_map(fn ($l) => $l[1], $allowLines)), 2);
        $otherOnly = round(array_sum(array_map(fn ($l) => $l[1], $dedLines)), 2);
        $before = $earned + $allowances + $otAmount + $bonus + $adjustment - $late - $otherOnly;
        $stamp = $before > 0 ? self::STAMP_CHARGE : 0.0;
        $step = max(1, (int) $this->setting('ROUND_NET_TO'));
        $net = round(round(($before - $stamp) / $step) * $step, 2);
        $min = $this->minimum($e);

        return [
            'department_id' => $e->department_id, 'section_id' => $e->section_id, 'line_id' => $e->line_id, 'designation_id' => $e->designation_id, 'grade_id' => $e->grade_id,
            'gross_a' => $gross, 'gross_b' => (float) $e->gross_b, 'basic' => $basic,
            'period_days' => $periodDays, 'present_days' => $present, 'absent_days' => $absent, 'unpaid_days' => $unpaid, 'payable_days' => $periodDays - $unpaid,
            'leave_deduction' => $leaveDeduction, 'earned_gross' => $earned, 'allowances' => $allowances,
            'ot_hours' => round($otHours, 2), 'ot_amount' => round($otAmount, 2), 'bonus' => round($bonus, 2),
            'late_days' => $lateDays, 'late_deduction' => $late, 'stamp_charge' => $stamp, 'other_deduction' => round($otherOnly + $stamp, 2),
            'loan_advance' => 0.0, 'adjustment' => round($adjustment, 2), 'net' => $net,
            'lines' => json_encode(['allowances' => $allowLines, 'deductions' => $dedLines], JSON_UNESCAPED_UNICODE),
            'payment_method' => 'CASH', 'held' => $e->status === 'SUSPENDED',
            'structure' => $min['structure'], 'minimum_wage' => $min['minimum'], 'minimum_basis' => $min['basis'], 'below_minimum' => $min['minimum'] !== null && $gross > 0 && $gross < $min['minimum'],
        ];
    }

    /**
     * The wage an employee's salary structure asks for at least: its grade's
     * minimum gross where the gazette sets one, else the fixed allowances.
     *
     * @return array{structure: ?string, minimum: ?float, basis: ?string}
     */
    public function minimum(object $e): array
    {
        $structure = $e->gazette_id ? SalaryStructures::find($e->gazette_id, $e->employment_type_id) : null;
        $label = $structure['label'] ?? null;
        $wage = $e->grade_id && $e->gazette_id ? DB::table('hr_gazette_grade_wages')->where('gazette_id', $e->gazette_id)->where('employment_type_id', $e->employment_type_id)->where('grade_id', $e->grade_id)->where('active', true)->first() : null;
        if ($wage) {
            $basic = (float) $wage->basic;

            return ['structure' => $label, 'minimum' => round($basic + $basic * (float) $wage->house_rent_pct / 100 + (float) $wage->medical + (float) $wage->conveyance + (float) $wage->food + (float) ($wage->other ?? 0), 2), 'basis' => 'GRADE'];
        }
        if ($structure) {
            return ['structure' => $label, 'minimum' => round(($structure['heads']['MED'] ?? 0) + ($structure['heads']['FOOD'] ?? 0) + ($structure['heads']['CONV'] ?? 0), 2), 'basis' => 'FIXED'];
        }

        return ['structure' => null, 'minimum' => null, 'basis' => null];
    }

    /** "Late: every 3 late days in the month cost one day's basic." by the payroll settings */
    public function lateNote(): string
    {
        return match ($this->setting('LATE_DEDUCTION_MODE')) {
            'LATE_DAYS' => __('hrpayroll.sp.late_note.days', ['n' => $this->setting('LATE_DAYS_PER_DEDUCTION'), 'basis' => __('hrpayroll.sp.basis_word.'.$this->setting('LATE_DEDUCTION_BASIS'))]),
            'PER_MINUTE' => __('hrpayroll.sp.late_note.minute', ['basis' => __('hrpayroll.sp.basis_word.'.$this->setting('LATE_DEDUCTION_BASIS'))]),
            default => __('hrpayroll.sp.late_note.none'),
        };
    }

    public function setting(string $key): string
    {
        return (string) (DB::table('hr_payroll_settings')->where('company_id', $this->companyId)->where('key', $key)->value('value') ?? HrDefaults::SETTINGS[$key][0]);
    }

    /* ── Festival bonus ────────────────────────────────────────────── */

    /**
     * A festival's bonus for each employee: eligible with the rule and amount, or not with why.
     *
     * @param  Collection<int, object>  $employees
     * @return list<array<string, mixed>>
     */
    public function bonus(object $festival, Collection $employees): array
    {
        $date = substr((string) $festival->festival_date, 0, 10);
        $rules = DB::table('hr_bonus_rules')->where('company_id', $this->companyId)->where('rule_set_id', $festival->bonus_rule_set_id)->where('active', true)
            ->orderByRaw('department_id is null')->orderBy('service_low')->get();
        $types = DB::table('hr_employment_types')->where('company_id', $this->companyId)->pluck('parent_id', 'id');
        $chain = function (?string $id) use ($types): array {
            $out = [];
            while ($id && $types->has($id) && ! in_array($id, $out, true)) {
                $out[] = $id;
                $id = $types[$id];
            }

            return $out;
        };
        $min = (int) ($festival->min_service ?? 0);
        $out = [];
        foreach ($employees as $e) {
            $joined = $e->joining_date ? substr((string) $e->joining_date, 0, 10) : null;
            $service = $joined && $joined <= $date ? (int) CarbonImmutable::parse($joined)->diffInDays(CarbonImmutable::parse($date)) : null;
            $row = ['employee_id' => $e->id, 'eligible' => false, 'service_days' => $service, 'gross_a' => (float) $e->gross_a, 'basis' => null, 'basis_amount' => 0.0, 'pct' => null, 'fixed' => 0.0, 'amount' => 0.0, 'rule' => null, 'reason' => null];
            if (! $joined) {
                $out[] = ['reason' => __('hrpayroll.sp.why.no_joining')] + $row;

                continue;
            }
            if ($joined > $date) {
                $out[] = ['reason' => __('hrpayroll.sp.why.joins_after')] + $row;

                continue;
            }
            if ($service < $min) {
                $out[] = ['reason' => __('hrpayroll.sp.why.min_service', ['days' => $service, 'min' => $min])] + $row;

                continue;
            }
            if ((float) $e->gross_a <= 0) {
                $out[] = ['reason' => __('hrpayroll.sp.why.no_gross')] + $row;

                continue;
            }
            $months = (int) floor(CarbonImmutable::parse($joined)->diffInMonths(CarbonImmutable::parse($date)));
            $tree = $chain($e->employment_type_id);
            $gross = (float) $e->gross_a;
            $rule = $rules->first(fn ($r) => $months >= (int) $r->service_low && $months <= (int) $r->service_high && $gross >= (float) $r->salary_low && $gross <= (float) $r->salary_high
                && in_array($r->employment_type_id, $tree, true) && ($r->department_id === null || $r->department_id === $e->department_id)
                && $r->designation_group_id === null && $r->shift_team_id === null);
            if (! $rule) {
                $out[] = ['reason' => __('hrpayroll.sp.why.no_rule', ['months' => $months, 'gross' => number_format($gross, 2)])] + $row;

                continue;
            }
            $basisAmount = $rule->basis === 'GROSS' ? $gross : $this->basic($e);
            $pct = $rule->pct === null ? null : (float) $rule->pct;
            $factor = $pct === null ? 0.0 : ($pct > 1 ? $pct / 100 : $pct);
            $fixed = (float) ($rule->fixed ?? 0);
            $out[] = [
                'eligible' => true, 'basis' => $rule->basis, 'basis_amount' => round($basisAmount, 2), 'pct' => $pct, 'fixed' => $fixed,
                'amount' => round($basisAmount * $factor + $fixed, 2),
                'rule' => __('hrpayroll.sp.rule_text', ['low' => $rule->service_low, 'high' => $rule->service_high >= 9999 ? '∞' : $rule->service_high, 'basis' => __('hrpayroll.sp.basis_word.'.$rule->basis)]),
            ] + $row;
        }

        return $out;
    }

    /* ── Finalized slips against today's figures ───────────────────── */

    /**
     * Each finalized slip against the month's OT as attendance and OT
     * corrections stand today: flagged when it differs from what the slip
     * paid plus the OT adjustments already raised, or while an adjustment on
     * it is pending or approved but not yet paid.
     *
     * @param  Collection<int, object>  $slips  slips of one period (id, employee_id, ot_hours, ot_amount)
     * @return array<string, array{current_hours: float, current_amount: float, adjusted_ot: float, already: float, open: int, flagged: bool, why: string}>
     */
    public function slipStates(object $period, Collection $slips): array
    {
        if ($slips->isEmpty()) {
            return [];
        }
        $ot = $this->otDays(substr((string) $period->start_date, 0, 10), substr((string) $period->end_date, 0, 10), $slips->pluck('employee_id')->all());
        $hours = [];
        $amounts = [];
        foreach ($ot as $key => $d) {
            $emp = explode('|', $key)[0];
            $hours[$emp] = ($hours[$emp] ?? 0) + $d['hours'];
            $amounts[$emp] = ($amounts[$emp] ?? 0) + $d['amount'];
        }
        $adj = DB::table('hr_payroll_adjustments')->whereIn('slip_id', $slips->pluck('id'))->where('status', '!=', 'REJECTED')->get(['slip_id', 'kind', 'amount', 'status', 'ref_no'])->groupBy('slip_id');
        $out = [];
        foreach ($slips as $s) {
            $mine = $adj[$s->id] ?? collect();
            $adjustedOt = round((float) $mine->where('kind', 'OT')->sum('amount'), 2);
            $current = round($amounts[$s->employee_id] ?? 0, 2);
            $open = $mine->whereIn('status', ['PENDING', 'APPROVED']);
            $differs = abs($current - ((float) $s->ot_amount + $adjustedOt)) >= 0.01;
            $why = $differs ? __('hrpayroll.sp.why_adj.ot', ['slip' => self::num((float) $s->ot_hours), 'now' => self::num($hours[$s->employee_id] ?? 0), 'diff' => number_format($current - (float) $s->ot_amount - $adjustedOt, 2)])
                : ($open->isNotEmpty() ? __('hrpayroll.sp.why_adj.open', ['refs' => $open->pluck('ref_no')->implode(', ')]) : '');
            $out[$s->id] = ['current_hours' => round($hours[$s->employee_id] ?? 0, 2), 'current_amount' => $current, 'adjusted_ot' => $adjustedOt, 'already' => round((float) $mine->sum('amount'), 2), 'open' => $open->count(), 'flagged' => $differs || $open->isNotEmpty(), 'why' => $why];
        }

        return $out;
    }

    private static function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    /** The pay period that pays a month ("2027-03"), if there is one. */
    public function periodFor(string $month): ?object
    {
        return DB::table('hr_pay_periods')->where('company_id', $this->companyId)->where('period_type', 'MONTHLY')
            ->where('start_date', '<=', "{$month}-01")->where('end_date', '>=', "{$month}-01")->orderByDesc('start_date')->first();
    }
}

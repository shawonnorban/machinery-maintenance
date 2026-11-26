<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who earns OT, at what rate, and how a day's OT is counted (AnnotechERP 24/05).
 *
 * - **General OT:** a worker (the employee type's tree is not Staff) with a
 *   grade whose General OT is on (Grade-wise General OT; on unless switched
 *   off) and an OT rule — the grade's, else the employee type's, else the
 *   company's general one.
 * - **Worker OT:** a staff member switched on in OT Eligibility, by an OT
 *   rule written for the employee's type (or a type above it).
 * - **Rate:** the rule's wage (Basic — the employee's Basic pay line, or the
 *   Basic their gross works out to — or the gross) ÷ divisor × multiplier.
 * - **Counting:** OT starts the limits' minutes after the shift end; past the
 *   break-after hours the OT break comes off; it is rounded down to the
 *   rounding step (up when within the grace of the next step); shorter than
 *   the minimum pays nothing; above the daily cap it is capped.
 */
final class OtBook
{
    public const LIMIT_KEYS = ['start_after_min', 'grace_min', 'general_rounding_min', 'worker_rounding_min', 'break_min', 'break_after_hours', 'min_minutes', 'max_hours_day'];

    private ?object $limits = null;

    public function __construct(private readonly string $companyId) {}

    /** The company's OT limits (Brickland's defaults where none are saved). */
    public function limits(): object
    {
        return $this->limits ??= DB::table('hr_ot_limits')->where('company_id', $this->companyId)->first()
            ?? (object) ['start_after_min' => 0, 'grace_min' => 0, 'general_rounding_min' => 60, 'worker_rounding_min' => 60, 'break_min' => 0, 'break_after_hours' => 0, 'min_minutes' => 0, 'max_hours_day' => 0, 'updated_by' => null, 'updated_at' => null];
    }

    /**
     * Each employee's OT standing: category, General / Worker OT, the rule
     * and why.
     *
     * @return array<string, array{category: ?string, type: ?string, rule: ?object, general: bool, general_reason: string, worker_allowed: bool, worker_ot: bool, worker_rule: ?object, worker_rule_text: string, note: ?string}>
     */
    public function eligibility(Collection $employees): array
    {
        $types = DB::table('hr_employment_types')->where('company_id', $this->companyId)->get(['id', 'name', 'parent_id'])->keyBy('id');
        $chain = function (?string $id) use ($types): array {
            $out = [];
            while ($id && isset($types[$id]) && ! in_array($id, $out, true)) {
                $out[] = $id;
                $id = $types[$id]->parent_id;
            }

            return $out;
        };
        $rules = DB::table('hr_ot_rules')->where('company_id', $this->companyId)->where('active', true)
            ->where('effective_from', '<=', now()->toDateString())->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()->toDateString()))
            ->orderByDesc('effective_from')->get();
        $grades = DB::table('grades')->where('company_id', $this->companyId)->pluck('code', 'id');
        $gradeOff = DB::table('hr_grade_ot')->where('company_id', $this->companyId)->where('general_ot', false)->pluck('grade_id')->flip();
        $switched = DB::table('hr_employee_ot')->where('company_id', $this->companyId)->whereIn('employee_id', $employees->pluck('id'))->get()->keyBy('employee_id');
        $out = [];
        foreach ($employees as $e) {
            $tree = $chain($e->employment_type_id);
            $root = $tree ? $types[end($tree)]->name : null;
            $category = $root === null ? null : (stripos($root, 'staff') !== false ? 'Staff' : 'Worker');
            $sw = $switched[$e->id] ?? null;
            $row = ['category' => $category, 'type' => null, 'rule' => null, 'general' => false, 'general_reason' => '', 'worker_allowed' => $category === 'Staff', 'worker_ot' => (bool) ($sw->worker_ot ?? false), 'worker_rule' => null, 'worker_rule_text' => '—', 'note' => $sw->note ?? null];
            if ($category === 'Staff') {
                $row['general_reason'] = __('hrpayroll.lo.why.staff');
                $row['worker_rule'] = $rules->first(fn ($r) => $r->employment_type_id && in_array($r->employment_type_id, $tree, true));
                $row['worker_rule_text'] = $row['worker_rule'] ? self::ruleText($row['worker_rule']) : __('hrpayroll.lo.why.no_worker_rule');
                if ($row['worker_ot'] && $row['worker_rule']) {
                    $row['type'] = 'WORKER';
                    $row['rule'] = $row['worker_rule'];
                }
            } elseif ($category === 'Worker') {
                $rule = $rules->first(fn ($r) => $e->grade_id && $r->grade_id === $e->grade_id)
                    ?? $rules->first(fn ($r) => ! $r->grade_id && $r->employment_type_id && in_array($r->employment_type_id, $tree, true))
                    ?? $rules->first(fn ($r) => ! $r->grade_id && ! $r->employment_type_id);
                if (! $e->grade_id) {
                    $row['general_reason'] = __('hrpayroll.lo.why.no_grade');
                } elseif (isset($gradeOff[$e->grade_id])) {
                    $row['general_reason'] = __('hrpayroll.lo.why.grade_off', ['grade' => $grades[$e->grade_id] ?? '']);
                } elseif (! $rule) {
                    $row['general_reason'] = __('hrpayroll.lo.why.no_rule');
                } else {
                    $row['general'] = true;
                    $row['type'] = 'GENERAL';
                    $row['rule'] = $rule;
                    $row['general_reason'] = __('hrpayroll.lo.why.graded', ['rule' => self::ruleText($rule)]);
                }
            } else {
                $row['general_reason'] = __('hrpayroll.lo.why.no_type');
            }
            $out[$e->id] = $row;
        }

        return $out;
    }

    /** "OT-STD · Basic ÷ 208 × 2" */
    public static function ruleText(object $rule): string
    {
        $basis = $rule->wage_basis === 'GROSS' ? __('hrpayroll.lo.basis.GROSS') : __('hrpayroll.lo.basis.BASIC');

        return "{$rule->code} · {$basis} ÷ ".self::n($rule->divisor).' × '.self::n($rule->multiplier);
    }

    /**
     * An employee's OT rate by a rule: the rule's wage ÷ divisor × multiplier.
     */
    public function rate(object $employee, ?object $rule): float
    {
        if (! $rule || (float) $rule->divisor <= 0) {
            return 0.0;
        }
        $wage = $rule->wage_basis === 'GROSS' ? (float) $employee->gross_a : $this->basic($employee);

        return round($wage / (float) $rule->divisor * (float) $rule->multiplier, 2);
    }

    /** The employee's Basic: their Basic pay line, else the Basic their gross works out to. */
    public function basic(object $employee): float
    {
        $line = DB::table('hr_employee_pay_lines')->join('hr_pay_elements', 'hr_pay_elements.id', '=', 'hr_employee_pay_lines.pay_element_id')
            ->where('hr_employee_pay_lines.employee_id', $employee->id)->where('hr_pay_elements.code', 'BASIC')->where('hr_employee_pay_lines.active', true)->value('hr_employee_pay_lines.amount');
        if ($line !== null && (float) $line > 0) {
            return (float) $line;
        }
        $structure = $employee->gazette_id ? SalaryStructures::find($employee->gazette_id, $employee->employment_type_id) : null;

        return $structure ? (float) SalaryStructures::split($structure['heads'], (float) $employee->gross_a)['basic'] : 0.0;
    }

    /**
     * A day's OT minutes from the minutes stayed past the OT start, by the
     * limits: the OT break, the rounding (with its grace), the minimum, the cap.
     */
    public function count(int $raw, string $type): int
    {
        $l = $this->limits();
        if ($raw <= 0) {
            return 0;
        }
        if ((int) $l->break_min > 0 && $raw > (float) $l->break_after_hours * 60) {
            $raw = max(0, $raw - (int) $l->break_min);
        }
        $step = max(60, (int) ($type === 'WORKER' ? $l->worker_rounding_min : $l->general_rounding_min));
        $units = intdiv($raw, $step);
        if ((int) $l->grace_min > 0 && $raw - $units * $step >= $step - (int) $l->grace_min) {
            $units++;
        }
        $ot = $units * $step;
        if ((int) $l->min_minutes > 0 && $raw < (int) $l->min_minutes) {
            return 0;
        }
        if ((float) $l->max_hours_day > 0) {
            $ot = min($ot, (int) round((float) $l->max_hours_day * 60));
        }

        return $ot;
    }

    /**
     * Approved OT windows by employee and date: "employee|date" =>
     * [start, end] as Dhaka "Y-m-d H:i" (the end on the next day when it
     * runs past midnight).
     *
     * @param  list<string>  $employeeIds
     * @return array<string, array{0: string, 1: string}>
     */
    public function windows(string $from, string $to, array $employeeIds): array
    {
        $rows = DB::table('hr_ot_requests')->join('hr_ot_request_employees', 'hr_ot_request_employees.request_id', '=', 'hr_ot_requests.id')
            ->leftJoin('hr_shifts', 'hr_shifts.id', '=', 'hr_ot_requests.shift_id')
            ->where('hr_ot_requests.company_id', $this->companyId)->where('hr_ot_requests.status', 'APPROVED')->where('hr_ot_request_employees.removed', false)
            ->whereIn('hr_ot_request_employees.employee_id', $employeeIds)->whereBetween('hr_ot_requests.date', [$from, $to])
            ->get(['hr_ot_request_employees.employee_id', 'hr_ot_requests.date', 'hr_ot_requests.ot_start', 'hr_ot_requests.ot_end', 'hr_shifts.start_time', 'hr_shifts.cross_day']);
        $out = [];
        foreach ($rows as $r) {
            $d = substr((string) $r->date, 0, 10);
            // A night shift's OT from 02:00 is the next morning's, though the day is the one the shift started.
            $start = $r->cross_day && $r->ot_start < $r->start_time ? date('Y-m-d', strtotime($d.' +1 day')) : $d;
            $end = $r->ot_end <= $r->ot_start ? date('Y-m-d', strtotime($start.' +1 day')) : $start;
            $key = "{$r->employee_id}|{$d}";
            $w = ["{$start} {$r->ot_start}", "{$end} {$r->ot_end}"];
            // Two approved requests for one day: the widest window counts.
            $out[$key] = isset($out[$key]) ? [min($out[$key][0], $w[0]), max($out[$key][1], $w[1])] : $w;
        }

        return $out;
    }

    private static function n(mixed $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Leave balances (AnnotechERP 24/05): what an employee has left of each leave
 * type in a leave year — the fiscal year's calendar span.
 *
 * - **Entitled:** the leave type's days for the year; none until the minimum
 *   employment is served; an accruing type earns a day for every 18 days
 *   present, up to its days. A type without days has no limit.
 * - **Adjusted:** carried forward from the year before, up to the type's
 *   carry-forward days, when the type carries days forward.
 * - **Taken:** summed from the approved applications — days, and the hours
 *   of early departures.
 * - **Balance:** entitled plus adjusted, less taken (an hour is an eighth of
 *   a day).
 */
final class LeaveBook
{
    public const HOURS_PER_DAY = 8;

    private const ACCRUAL_DAYS = 18;

    public function __construct(private readonly string $companyId) {}

    /** The leave types open to an employee (a type for one gender only to that gender). */
    public function types(?object $employee = null): Collection
    {
        $gender = strtoupper((string) ($employee->gender ?? ''));

        return DB::table('hr_leave_types')->where('company_id', $this->companyId)->where('active', true)->orderBy('name')->get()
            ->filter(fn ($t) => $t->applicable_for === 'COMMON' || $employee === null || $gender === '' || $t->applicable_for === $gender)->values();
    }

    /** The fiscal year whose calendar span holds a date. */
    public function yearOf(string $date): ?object
    {
        return DB::table('acc_fiscal_years')->where('company_id', $this->companyId)->where('cal_start', '<=', $date)->where('cal_end', '>=', $date)->first()
            ?? DB::table('acc_fiscal_years')->where('company_id', $this->companyId)->orderByDesc('cal_start')->first();
    }

    /**
     * Each open leave type's balance for an employee in a year.
     *
     * @return list<array<string, mixed>>
     */
    public function balances(object $employee, object $year, ?string $except = null): array
    {
        $out = [];
        $previous = DB::table('acc_fiscal_years')->where('company_id', $this->companyId)->where('cal_end', '<', $year->cal_start)->orderByDesc('cal_end')->first();
        foreach ($this->types($employee) as $t) {
            [$takenDay, $takenHour] = $this->taken($employee->id, $t->id, $year->id, $except);
            $entitled = $this->entitled($employee, $t, $year);
            $adjusted = 0.0;
            if ($t->day_forward && $previous && $entitled !== null) {
                $before = $this->entitled($employee, $t, $previous);
                [$d, $h] = $this->taken($employee->id, $t->id, $previous->id);
                $left = max(0.0, (float) $before - $d - $h / self::HOURS_PER_DAY);
                $adjusted = $t->forward_days !== null ? min($left, (float) $t->forward_days) : $left;
            }
            $balance = $entitled === null ? null : round($entitled + $adjusted - $takenDay - $takenHour / self::HOURS_PER_DAY, 2);
            $out[] = [
                'leave_type_id' => $t->id, 'code' => $t->code, 'leave_type' => "{$t->name} ({$t->code})", 'balance_check' => (bool) $t->balance_check,
                'entitled_day' => $entitled, 'entitled_hour' => $entitled === null ? null : $entitled * self::HOURS_PER_DAY,
                'taken_day' => $takenDay, 'taken_hour' => $takenHour,
                'adjusted_day' => round($adjusted, 2), 'adjusted_hour' => round($adjusted * self::HOURS_PER_DAY, 2),
                'balance_day' => $balance, 'balance_hour' => $balance === null ? null : round($balance * self::HOURS_PER_DAY, 2),
            ];
        }

        return $out;
    }

    private function entitled(object $employee, object $type, object $year): ?float
    {
        if ($type->max_days === null) {
            return null;
        }
        if ($type->min_employment && $employee->joining_date) {
            $unit = ['DAY' => 'days', 'MONTH' => 'months', 'YEAR' => 'years'][$type->min_unit ?? 'MONTH'] ?? 'months';
            if (CarbonImmutable::parse($employee->joining_date)->add((int) $type->min_employment, $unit)->toDateString() > substr((string) $year->cal_end, 0, 10)) {
                return 0.0;
            }
        }
        if ($type->accrual) {
            $present = DB::table('hr_attendance_days')->where('employee_id', $employee->id)->where('status', 'PRESENT')
                ->whereBetween('date', [substr((string) $year->cal_start, 0, 10), substr((string) $year->cal_end, 0, 10)])->count();

            return (float) min((int) $type->max_days, intdiv($present, self::ACCRUAL_DAYS));
        }

        return (float) $type->max_days;
    }

    /** @return array{0: float, 1: float} days and hours taken by approved applications */
    private function taken(string $employeeId, string $typeId, string $yearId, ?string $except = null): array
    {
        $rows = DB::table('hr_leave_applications')->where('employee_id', $employeeId)->where('leave_type_id', $typeId)->where('fiscal_year_id', $yearId)->where('status', 'APPROVED')
            ->when($except, fn ($q, $id) => $q->where('id', '!=', $id))->get(['mode', 'days', 'hours']);

        return [(float) $rows->where('mode', 'DAILY')->sum('days'), (float) $rows->where('mode', 'EARLY_DEPARTURE')->sum('hours')];
    }
}

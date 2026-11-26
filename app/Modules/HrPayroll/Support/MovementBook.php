<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * How an employee moves (AnnotechERP 24/07):
 *
 * - **Posting:** a transfer or promotion closes the open posting in the
 *   history on the effective date and opens the new one from it; the
 *   employee's current posting follows, so manpower counts them where they
 *   now are.
 * - **Salary:** a new Gross A is kept in the salary history from its date,
 *   and the core pay lines (Basic, House Rent, Medical, Conveyance, Food)
 *   are split again by the employee's salary structure. Gross B is never
 *   touched.
 * - **Approved and due:** an approved promotion or increment takes effect
 *   once its effective date has come (today or earlier at approval; later
 *   ones on their day, by `hr:apply-movements` or when a screen loads).
 */
final class MovementBook
{
    public const TZ = 'Asia/Dhaka';

    private const PLACEMENT = ['factory_id', 'building_id', 'floor_id', 'department_id', 'section_id', 'line_id', 'designation_id', 'grade_id', 'shift_id'];

    /** Pay-line codes for the parts a gross splits into. */
    private const HEADS = ['basic' => 'BASIC', 'house_rent' => 'HRENT', 'medical' => 'MED', 'conveyance' => 'CONV', 'food' => 'FOOD'];

    public function __construct(private readonly string $companyId) {}

    public static function today(): string
    {
        return CarbonImmutable::now(self::TZ)->toDateString();
    }

    /**
     * Move an employee to a new posting from a date.
     *
     * @param  array<string, ?string>  $changes  placement column => id
     */
    public function move(object $employee, array $changes, string $from, ?string $reason, ?string $by): void
    {
        $placement = [];
        foreach (self::PLACEMENT as $k) {
            $placement[$k] = array_key_exists($k, $changes) ? $changes[$k] : $employee->{$k};
        }
        $now = now();
        DB::table('hr_employee_assignments')->where('employee_id', $employee->id)->whereNull('effective_to')->update(['effective_to' => $from, 'updated_at' => $now]);
        // The history needs a factory, a department and a designation; a posting without them is only kept on the employee.
        if ($placement['factory_id'] && $placement['department_id'] && $placement['designation_id']) {
            DB::table('hr_employee_assignments')->insert($placement + [
                'id' => strtolower((string) Str::ulid()), 'company_id' => $this->companyId, 'employee_id' => $employee->id, 'reason' => $reason,
                'effective_from' => $from, 'created_by' => $by, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        DB::table('hr_employees')->where('id', $employee->id)->update($placement + ['updated_at' => $now]);
    }

    /** A new Gross A from a date: the salary history, and the core pay lines split again. */
    public function setGross(object $employee, float $gross, string $from, ?string $by): void
    {
        $now = now();
        DB::table('hr_employees')->where('id', $employee->id)->update(['gross_a' => $gross, 'updated_at' => $now]);
        DB::table('hr_employee_salaries')->insert([
            'id' => strtolower((string) Str::ulid()), 'company_id' => $this->companyId, 'employee_id' => $employee->id, 'gazette_id' => $employee->gazette_id,
            'employment_type_id' => $employee->employment_type_id, 'gross_a' => $gross, 'gross_b' => $employee->gross_b, 'actual_salary' => $employee->actual_salary,
            'effective_from' => $from, 'created_by' => $by, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $structure = $employee->gazette_id ? SalaryStructures::find($employee->gazette_id, $employee->employment_type_id) : null;
        if (! $structure) {
            return;
        }
        $parts = SalaryStructures::split($structure['heads'], $gross);
        $elements = DB::table('hr_pay_elements')->where('company_id', $this->companyId)->pluck('id', 'code');
        foreach (self::HEADS as $part => $code) {
            if (isset($elements[$code])) {
                DB::table('hr_employee_pay_lines')->where('employee_id', $employee->id)->where('pay_element_id', $elements[$code])->where('core_salary', true)
                    ->update(['amount' => $parts[$part], 'updated_at' => $now]);
            }
        }
    }

    /** The Basic a gross works out to under the employee's structure (0 when it does not cover the fixed heads). */
    public static function basicOf(object $employee, float $gross): ?int
    {
        $structure = $employee->gazette_id ? SalaryStructures::find($employee->gazette_id, $employee->employment_type_id) : null;

        return $structure ? SalaryStructures::split($structure['heads'], $gross)['basic'] : null;
    }

    /** Apply every approved promotion and increment whose day has come. Answers how many. */
    public function applyDue(?string $by = null): int
    {
        $today = self::today();
        $n = 0;
        $promotions = DB::table('hr_promotions')->where('company_id', $this->companyId)->where('status', 'APPROVED')->whereNull('applied_at')->where('effective_date', '<=', $today)->orderBy('effective_date')->orderBy('ref_no')->get();
        foreach ($promotions as $p) {
            DB::transaction(function () use ($p, $by): void {
                $e = DB::table('hr_employees')->where('id', $p->employee_id)->first();
                $date = substr((string) $p->effective_date, 0, 10);
                $this->move($e, ['designation_id' => $p->new_designation_id, 'grade_id' => $p->new_grade_id ?? $e->grade_id], $date, __('hrpayroll.mv.history.promotion', ['ref' => $p->ref_no]), $by ?? $p->decided_by);
                if ($p->new_gross !== null) {
                    $this->setGross(DB::table('hr_employees')->where('id', $p->employee_id)->first(), (float) $p->new_gross, $date, $by ?? $p->decided_by);
                }
                DB::table('hr_promotions')->where('id', $p->id)->update(['applied_at' => now(), 'updated_at' => now()]);
            });
            $n++;
        }
        $increments = DB::table('hr_increments')->where('company_id', $this->companyId)->where('status', 'APPROVED')->whereNull('applied_at')->where('effective_date', '<=', $today)->orderBy('effective_date')->orderBy('ref_no')->get();
        foreach ($increments as $i) {
            DB::transaction(function () use ($i, $by): void {
                $e = DB::table('hr_employees')->where('id', $i->employee_id)->first();
                $this->setGross($e, (float) $i->new_gross, substr((string) $i->effective_date, 0, 10), $by ?? $i->decided_by);
                DB::table('hr_increments')->where('id', $i->id)->update(['applied_at' => now(), 'updated_at' => now()]);
            });
            $n++;
        }

        return $n;
    }
}

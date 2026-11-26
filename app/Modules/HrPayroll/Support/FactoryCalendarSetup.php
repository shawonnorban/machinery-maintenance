<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Puts a factory calendar together the way the product reads it (the user's
 * decision of 2026-11-15: HR & Payroll is the one calendar):
 * shifts on HR & Payroll → Shift Master, dated holidays on HR & Payroll's
 * Holidays, and the factory's operating mode and working week in Settings.
 *
 * For seeders, data moves and tests; screens save through their own APIs.
 * Written straight to the tables so it needs no tenant in context.
 */
final class FactoryCalendarSetup
{
    /** A shift on HR & Payroll → Shift Master, found by code or added; its id. */
    public static function shift(string $companyId, string $code, string $name, string $start, string $end, int $breakMin = 0, bool $active = true, ?string $breakStart = null): string
    {
        $start = substr($start, 0, 5);
        $end = substr($end, 0, 5);
        [$sh, $sm] = array_map('intval', explode(':', $start));
        [$eh, $em] = array_map('intval', explode(':', $end));
        $span = ($eh * 60 + $em) - ($sh * 60 + $sm);
        $cross = $span <= 0;
        $span = $cross ? $span + 1440 : $span;

        $values = [
            'name' => $name, 'start_time' => $start, 'end_time' => $end, 'break_start' => $breakStart, 'break_min' => $breakMin,
            'working_hours' => round(max(0, $span - $breakMin) / 60, 2), 'cross_day' => $cross, 'active' => $active,
            'updated_at' => now(),
        ];

        $id = DB::table('hr_shifts')->where('company_id', $companyId)->where('code', $code)->value('id');
        if ($id !== null) {
            DB::table('hr_shifts')->where('id', $id)->update($values);

            return (string) $id;
        }

        $id = (string) Str::ulid();
        DB::table('hr_shifts')->insert($values + ['id' => $id, 'company_id' => $companyId, 'code' => $code, 'created_at' => now()]);

        return $id;
    }

    /** A dated holiday on HR & Payroll's calendar, found by date and name or added; its id. */
    public static function holiday(string $companyId, string $date, string $name, int $days = 1, bool $recurring = false, string $type = 'GOVT'): string
    {
        $id = DB::table('hr_holidays')->where('company_id', $companyId)->whereDate('start_date', $date)->where('name', $name)->value('id');
        if ($id !== null) {
            return (string) $id;
        }

        $code = 'HOL-'.str_replace('-', '', $date).'-'.strtoupper(Str::random(3));
        $fiscalYear = DB::table('acc_fiscal_years')->where('company_id', $companyId)
            ->whereDate('fy_start', '<=', $date)->whereDate('fy_end', '>=', $date)->value('id');

        $id = (string) Str::ulid();
        DB::table('hr_holidays')->insert([
            'id' => $id, 'company_id' => $companyId, 'fiscal_year_id' => $fiscalYear, 'code' => $code, 'name' => $name,
            'type' => $type, 'start_date' => $date, 'days' => max(1, $days), 'recurring' => $recurring,
            'calendar_days' => max(1, $days), 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * A factory's operating mode and, when given, its working week
     * (`general.working_days`, e.g. SAT…THU), as factory-level settings.
     *
     * @param  list<string>|null  $workingDays
     */
    public static function factory(string $companyId, string $factoryId, string $mode = 'SHIFT_BASED', ?array $workingDays = null): void
    {
        self::setting($companyId, $factoryId, 'factory.operating_mode', 'ENUM', $mode);
        if ($workingDays !== null) {
            self::setting($companyId, $factoryId, 'general.working_days', 'LIST', array_values($workingDays));
        }
    }

    /**
     * A floor line's shift plan: the Capacity Planning line laid over it
     * (found, or added on the line's floor) runs an HR shift.
     */
    public static function linePlan(string $companyId, string $productionLineId, string $hrShiftId): void
    {
        $line = DB::table('production_lines')->where('id', $productionLineId)->first(['id', 'code', 'name', 'floor_id']);
        $planning = DB::table('planning_lines')->where('company_id', $companyId)->where('floor_line_id', $productionLineId)->value('id');
        if ($planning === null) {
            $planning = (string) Str::ulid();
            DB::table('planning_lines')->insert([
                'id' => $planning, 'company_id' => $companyId, 'floor_id' => $line->floor_id, 'floor_line_id' => $line->id,
                'code' => $line->code, 'name' => $line->name, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $shift = DB::table('hr_shifts')->where('id', $hrShiftId)->first(['name', 'start_time', 'end_time', 'break_min']);
        $values = ['shift_name' => $shift->name, 'start_time' => $shift->start_time, 'end_time' => $shift->end_time,
            'break_minutes' => (int) ($shift->break_min ?? 0), 'active' => true, 'updated_at' => now()];
        $match = ['planning_line_id' => $planning, 'hr_shift_id' => $hrShiftId];
        if (DB::table('planning_line_shifts')->where($match)->exists()) {
            DB::table('planning_line_shifts')->where($match)->update($values);

            return;
        }
        DB::table('planning_line_shifts')->insert($match + $values + ['id' => (string) Str::ulid(), 'company_id' => $companyId, 'created_at' => now()]);
    }

    /** One setting row at company (factory null) or factory level. */
    public static function setting(string $companyId, ?string $factoryId, string $key, string $type, mixed $value): void
    {
        $match = ['company_id' => $companyId, 'factory_id' => $factoryId, 'production_line_id' => null, 'key' => $key];
        $row = DB::table('settings')->where($match)->value('id');
        $values = ['value' => json_encode(['v' => $value]), 'value_type' => $type, 'updated_at' => now()];

        if ($row !== null) {
            DB::table('settings')->where('id', $row)->update($values);

            return;
        }

        DB::table('settings')->insert($match + $values + ['id' => (string) Str::ulid(), 'created_at' => now()]);
    }
}

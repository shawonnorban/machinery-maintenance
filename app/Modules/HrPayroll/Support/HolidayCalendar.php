<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Support;

use App\Modules\HrPayroll\Models\HrHoliday;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The company's holidays, as HR & Payroll keeps them (Festival Calendar →
 * Holidays, AnnotechERP 24/01) — the one factory calendar (the user's
 * decision of 2026-11-15). Attendance, Machinery's maintenance schedule and
 * downtime, and Capacity Planning all read their days off from here.
 *
 * A dated holiday covers `days` days from its start; a recurring one falls on
 * the same day every year from its first. Weekly offs are not dated holidays:
 * they are Settings → General Preferences → Working days.
 */
final class HolidayCalendar
{
    /**
     * Holiday dates (`Y-m-d` => name) of a company between two dates, inclusive.
     *
     * @return array<string, string>
     */
    public static function dates(string $companyId, string $from, string $to): array
    {
        $first = CarbonImmutable::parse($from)->startOfDay();
        $last = CarbonImmutable::parse($to)->startOfDay();
        if ($last->lessThan($first)) {
            return [];
        }

        $rows = DB::table('hr_holidays')
            ->where('company_id', $companyId)
            ->where('active', true)
            ->where('type', '!=', 'WEEKLY_OFF')
            ->whereNotNull('start_date')
            ->where(fn ($q) => $q->where('recurring', true)->orWhere('start_date', '<=', $last->toDateString()))
            ->get(['name', 'start_date', 'days', 'recurring']);

        $out = [];
        foreach ($rows as $row) {
            $start = CarbonImmutable::parse($row->start_date)->startOfDay();
            $span = max(1, (int) $row->days);
            $years = $row->recurring ? range(max($start->year, $first->year - 1), $last->year) : [$start->year];

            foreach ($years as $year) {
                $begin = $row->recurring ? $start->setYear($year) : $start;
                for ($i = 0; $i < $span; $i++) {
                    $day = $begin->addDays($i);
                    if ($day->betweenIncluded($first, $last)) {
                        $out[$day->toDateString()] ??= (string) $row->name;
                    }
                }
            }
        }
        ksort($out);

        return $out;
    }

    /** Whether a company is off for a holiday on a date. */
    public static function isHoliday(string $companyId, string $date): bool
    {
        return self::dates($companyId, $date, $date) !== [];
    }

    /**
     * Records how many days a saved holiday puts on the calendar (the
     * Holidays grid's "Calendar days"): every day it spans, once active.
     */
    public static function file(HrHoliday $holiday): void
    {
        $days = $holiday->getAttribute('start_date') && $holiday->getAttribute('active') && $holiday->getAttribute('type') !== 'WEEKLY_OFF'
            ? max(1, (int) $holiday->getAttribute('days'))
            : 0;
        $holiday->forceFill(['calendar_days' => $days])->saveQuietly();
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Services;

use App\Modules\HrPayroll\Models\HrShift;
use App\Modules\HrPayroll\Support\HolidayCalendar;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Converts a wall-clock window into working minutes for a factory.
 *
 * This is the service every availability, MTTR, downtime and escalation figure
 * in the product resolves against (ADR-048). Getting it wrong does not throw;
 * it quietly produces numbers a factory manager will not recognise, which is
 * why it is isolated here and tested directly.
 *
 * HR & Payroll is the one factory calendar (the user's decision of
 * 2026-11-15), read from:
 * - **shifts:** HR & Payroll → Shift Master (`hr_shifts`) — the shifts the
 *   factory's active employees are on, else every active shift of the company;
 *   overlapping shifts are counted once. A shift's `break_min` is taken out at
 *   its `break_start`, or of the middle of the shift when no clock time is set;
 * - **holidays:** HR & Payroll → Festival Calendar → Holidays (`hr_holidays`);
 * - **weekly off-days:** Settings → General Preferences → Working days
 *   (`general.working_days`, company or factory level);
 * - **operating mode:** `factory.operating_mode` (company or factory level).
 *   A factory that never chose one runs continuously and the caller is told
 *   so (CONTINUOUS_FALLBACK), as before.
 *
 * All arithmetic happens in the factory timezone, then results are minutes.
 * Callers pass UTC instants and receive minutes; they never see local times.
 */
class WorkingTimeService
{
    private const DAYS = ['MON' => 1, 'TUE' => 2, 'WED' => 3, 'THU' => 4, 'FRI' => 5, 'SAT' => 6, 'SUN' => 7];

    private const DEFAULT_WORKING_DAYS = ['SAT', 'SUN', 'MON', 'TUE', 'WED', 'THU'];

    /**
     * Working minutes between two instants.
     *
     * @return array{minutes: int, basis: string}
     */
    public function workingMinutesBetween(
        Factory $factory,
        CarbonInterface $fromUtc,
        CarbonInterface $toUtc,
    ): array {
        if ($toUtc->lessThanOrEqualTo($fromUtc)) {
            return ['minutes' => 0, 'basis' => 'EMPTY_WINDOW'];
        }

        $mode = $this->setting($factory, 'factory.operating_mode');

        if ($mode === null) {
            return [
                'minutes' => (int) round($fromUtc->diffInMinutes($toUtc, absolute: true)),
                'basis' => 'CONTINUOUS_FALLBACK',
            ];
        }

        if ($mode === 'CONTINUOUS') {
            return [
                'minutes' => (int) round($fromUtc->diffInMinutes($toUtc, absolute: true)),
                'basis' => 'CONTINUOUS',
            ];
        }

        $tz = $factory->timezone;
        $from = CarbonImmutable::parse($fromUtc)->setTimezone($tz);
        $to = CarbonImmutable::parse($toUtc)->setTimezone($tz);

        $shifts = $this->shiftsFor($factory, $to);

        if ($shifts->isEmpty()) {
            return ['minutes' => 0, 'basis' => 'NO_SHIFTS_DEFINED'];
        }

        $holidays = HolidayCalendar::dates($factory->company_id, $from->subDay()->toDateString(), $to->addDay()->toDateString());
        $offDays = $this->offDays($factory);

        // Every shift interval in the window, merged so overlapping shifts
        // (General over Morning and Evening) are counted once.
        $intervals = [];

        // Start one day early so a shift that began yesterday and crosses
        // midnight into the window is still counted.
        $cursor = $from->subDay()->startOfDay();
        $limit = $to->startOfDay();

        while ($cursor->lessThanOrEqualTo($limit)) {
            if (! isset($holidays[$cursor->toDateString()]) && ! in_array($cursor->dayOfWeekIso, $offDays, true)) {
                foreach ($shifts as $shift) {
                    array_push($intervals, ...$this->shiftIntervals($cursor, $shift));
                }
            }

            $cursor = $cursor->addDay();
        }

        $minutes = 0;
        foreach ($this->merge($intervals) as [$start, $end]) {
            $minutes += $this->overlapMinutes($start, $end, $from, $to);
        }

        return ['minutes' => $minutes, 'basis' => 'SHIFT_CALENDAR'];
    }

    /**
     * Scheduled operating minutes for a period, used as the denominator of
     * availability (SRS 31.1).
     *
     * @return array{minutes: int, basis: string}
     */
    public function scheduledOperatingMinutes(
        Factory $factory,
        CarbonInterface $fromUtc,
        CarbonInterface $toUtc,
    ): array {
        return $this->workingMinutesBetween($factory, $fromUtc, $toUtc);
    }

    public function isWorkingDay(Factory $factory, CarbonInterface $dateUtc): bool
    {
        $mode = $this->setting($factory, 'factory.operating_mode');

        if ($mode === null || $mode === 'CONTINUOUS') {
            return true;
        }

        $local = CarbonImmutable::parse($dateUtc)->setTimezone($factory->timezone)->startOfDay();

        if (HolidayCalendar::dates($factory->company_id, $local->toDateString(), $local->toDateString()) !== []) {
            return false;
        }

        return ! in_array($local->dayOfWeekIso, $this->offDays($factory), true);
    }

    /**
     * Moves a date off a non-working day per the configured policy
     * (SRS 47.3). Used when generating maintenance due dates.
     */
    public function applyNonWorkingDayPolicy(
        Factory $factory,
        CarbonInterface $dateUtc,
        string $policy,
    ): CarbonImmutable {
        $date = CarbonImmutable::parse($dateUtc);

        if ($policy === 'NONE') {
            return $date;
        }

        $step = $policy === 'PREVIOUS_WORKING_DAY' ? -1 : 1;

        // Bounded: a factory with every day off would otherwise loop forever.
        for ($i = 0; $i < 14; $i++) {
            if ($this->isWorkingDay($factory, $date)) {
                return $date;
            }

            $date = $date->addDays($step);
        }

        return CarbonImmutable::parse($dateUtc);
    }

    /**
     * The working days of one factory (local dates, `Y-m-d`) between two
     * dates, inclusive: not a weekly off-day and not an HR holiday. Capacity
     * Planning counts its days on this.
     *
     * @return list<string>
     */
    public function workingDates(Factory $factory, CarbonInterface $from, CarbonInterface $to): array
    {
        $first = CarbonImmutable::parse($from->toDateString());
        $last = CarbonImmutable::parse($to->toDateString());
        $holidays = HolidayCalendar::dates($factory->company_id, $first->toDateString(), $last->toDateString());
        $offDays = $this->offDays($factory);

        $out = [];
        for ($day = $first; $day->lessThanOrEqualTo($last); $day = $day->addDay()) {
            if (! isset($holidays[$day->toDateString()]) && ! in_array($day->dayOfWeekIso, $offDays, true)) {
                $out[] = $day->toDateString();
            }
        }

        return $out;
    }

    /**
     * ISO weekdays (1 = Monday … 7 = Sunday) the factory does not work.
     *
     * @return list<int>
     */
    public function offDays(Factory $factory): array
    {
        $working = $this->setting($factory, 'general.working_days') ?? self::DEFAULT_WORKING_DAYS;
        $working = array_map(fn ($d) => strtoupper(trim((string) $d)), (array) $working);

        return array_values(array_map(fn (string $d) => self::DAYS[$d], array_diff(array_keys(self::DAYS), $working)));
    }

    /**
     * A setting's value for this factory: its own, else its company's; null
     * when neither chose one (the catalog default is not a choice). Read
     * straight from the rows, because schedules and snapshots run outside any
     * request's company.
     */
    private function setting(Factory $factory, string $key): mixed
    {
        $rows = DB::table('settings')
            ->where('company_id', $factory->company_id)
            ->where('key', $key)
            ->whereNull('production_line_id')
            ->where(fn ($q) => $q->whereNull('factory_id')->orWhere('factory_id', $factory->id))
            ->get(['factory_id', 'value']);

        $row = $rows->firstWhere('factory_id', $factory->id) ?? $rows->firstWhere('factory_id', null);

        return $row ? (json_decode((string) $row->value, true)['v'] ?? null) : null;
    }

    /**
     * The shifts this factory runs on a date: those its active employees are
     * on, else every active shift of the company.
     *
     * @return Collection<int, HrShift>
     */
    private function shiftsFor(Factory $factory, CarbonImmutable $at): Collection
    {
        $base = HrShift::query()
            ->withoutGlobalScopes()
            ->where('company_id', $factory->company_id)
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('effective_date')->orWhereDate('effective_date', '<=', $at->toDateString()));

        $staffed = DB::table('hr_employees')
            ->where('company_id', $factory->company_id)
            ->where('factory_id', $factory->id)
            ->where('status', 'ACTIVE')
            ->whereNotNull('shift_id')
            ->distinct()
            ->pluck('shift_id');

        if ($staffed->isNotEmpty()) {
            $own = (clone $base)->whereIn('id', $staffed)->get();
            if ($own->isNotEmpty()) {
                return $own;
            }
        }

        return $base->get();
    }

    /**
     * One shift's working intervals starting on a local day, its break taken
     * out at its clock time (the middle when none is set).
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function shiftIntervals(CarbonImmutable $day, HrShift $shift): array
    {
        $start = $this->applyTime($day, (string) $shift->start_time);
        $end = $this->applyTime($day, (string) $shift->end_time);

        // A shift ending at or before its start time runs past midnight.
        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        $break = (int) ($shift->break_min ?? 0);
        $length = (int) round($start->diffInMinutes($end, absolute: true));

        if ($break <= 0 || $break >= $length) {
            return $break >= $length ? [] : [[$start, $end]];
        }

        $breakStart = $start->addMinutes(intdiv($length - $break, 2));
        if ((string) $shift->break_start !== '') {
            // A night shift's 01:00 break is the next morning's.
            $at = $this->applyTime($day, (string) $shift->break_start);
            $at = $at->lessThan($start) ? $at->addDay() : $at;
            $breakStart = $at->addMinutes($break)->lessThanOrEqualTo($end) ? $at : $breakStart;
        }

        return [[$start, $breakStart], [$breakStart->addMinutes($break), $end]];
    }

    /**
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $intervals
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function merge(array $intervals): array
    {
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);

        $merged = [];
        foreach ($intervals as [$start, $end]) {
            $last = count($merged) - 1;
            if ($last >= 0 && $start->lessThanOrEqualTo($merged[$last][1])) {
                if ($end->greaterThan($merged[$last][1])) {
                    $merged[$last][1] = $end;
                }

                continue;
            }
            $merged[] = [$start, $end];
        }

        return $merged;
    }

    private function applyTime(CarbonImmutable $day, string $time): CarbonImmutable
    {
        [$h, $m, $s] = array_pad(explode(':', $time), 3, '0');

        return $day->startOfDay()->setTime((int) $h, (int) $m, (int) $s);
    }

    private function overlapMinutes(
        CarbonImmutable $aStart,
        CarbonImmutable $aEnd,
        CarbonImmutable $bStart,
        CarbonImmutable $bEnd,
    ): int {
        $start = $aStart->greaterThan($bStart) ? $aStart : $bStart;
        $end = $aEnd->lessThan($bEnd) ? $aEnd : $bEnd;

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        return (int) round($start->diffInMinutes($end, absolute: true));
    }
}

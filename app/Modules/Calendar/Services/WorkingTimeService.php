<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Services;

use App\Modules\Calendar\Models\FactoryCalendar;
use App\Modules\Calendar\Models\FactoryHoliday;
use App\Modules\Calendar\Models\Shift;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Converts a wall-clock window into working minutes for a factory.
 *
 * This is the service every availability, MTTR, downtime and escalation figure
 * in the product resolves against (ADR-048). Getting it wrong does not throw;
 * it quietly produces numbers a factory manager will not recognise, which is
 * why it is isolated here and tested directly.
 *
 * All arithmetic happens in the factory timezone, then results are minutes.
 * Callers pass UTC instants and receive minutes; they never see local times.
 */
class WorkingTimeService
{
    /**
     * Working minutes between two instants.
     *
     * A factory with no calendar falls back to continuous operation and the
     * caller is told so, because a report that silently changes basis is worse
     * than one that says which basis it used (SRS 47.2 rule 4).
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

        $calendar = $this->calendarFor($factory, $fromUtc);

        if ($calendar === null) {
            return [
                'minutes' => (int) round($fromUtc->diffInMinutes($toUtc, absolute: true)),
                'basis' => 'CONTINUOUS_FALLBACK',
            ];
        }

        if ($calendar->isContinuous()) {
            return [
                'minutes' => (int) round($fromUtc->diffInMinutes($toUtc, absolute: true)),
                'basis' => 'CONTINUOUS',
            ];
        }

        $tz = $factory->timezone;
        $from = CarbonImmutable::parse($fromUtc)->setTimezone($tz);
        $to = CarbonImmutable::parse($toUtc)->setTimezone($tz);

        $shifts = $this->shiftsFor($factory, $from, $to);

        if ($shifts->isEmpty()) {
            return ['minutes' => 0, 'basis' => 'NO_SHIFTS_DEFINED'];
        }

        $holidays = $this->holidaysFor($factory, $from, $to);

        $minutes = 0;

        // Start one day early so a shift that began yesterday and crosses
        // midnight into the window is still counted.
        $cursor = $from->subDay()->startOfDay();
        $limit = $to->startOfDay();

        while ($cursor->lessThanOrEqualTo($limit)) {
            foreach ($this->shiftIntervalsOn($cursor, $shifts, $holidays, $calendar) as [$start, $end]) {
                $minutes += $this->overlapMinutes($start, $end, $from, $to);
            }

            $cursor = $cursor->addDay();
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
        $calendar = $this->calendarFor($factory, $dateUtc);

        if ($calendar === null || $calendar->isContinuous()) {
            return true;
        }

        $local = CarbonImmutable::parse($dateUtc)->setTimezone($factory->timezone)->startOfDay();

        $holiday = $this->holidaysFor($factory, $local, $local->endOfDay())
            ->get($local->toDateString());

        if ($holiday !== null) {
            return $holiday->is_working_day;
        }

        return ! in_array($local->dayOfWeekIso, $calendar->weekly_off_days, true);
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

    private function calendarFor(Factory $factory, CarbonInterface $atUtc): ?FactoryCalendar
    {
        $date = CarbonImmutable::parse($atUtc)->setTimezone($factory->timezone)->toDateString();

        return FactoryCalendar::query()
            ->where('factory_id', $factory->id)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($q) use ($date): void {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * @return Collection<int, Shift>
     */
    private function shiftsFor(Factory $factory, CarbonImmutable $from, CarbonImmutable $to)
    {
        return Shift::query()
            ->with('breaks')
            ->where('factory_id', $factory->id)
            ->where('status', 'ACTIVE')
            ->whereDate('effective_from', '<=', $to->toDateString())
            ->where(function ($q) use ($from): void {
                $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $from->toDateString());
            })
            ->get();
    }

    /**
     * @return Collection<string, FactoryHoliday>
     */
    private function holidaysFor(Factory $factory, CarbonImmutable $from, CarbonImmutable $to)
    {
        return FactoryHoliday::query()
            ->where('factory_id', $factory->id)
            ->whereBetween('date', [
                $from->subDay()->toDateString(),
                $to->addDay()->toDateString(),
            ])
            ->get()
            ->keyBy(fn (FactoryHoliday $h) => $h->date->toDateString());
    }

    /**
     * Concrete start and end instants for every shift running on one local day,
     * with non-operating breaks already removed.
     *
     * @param  Collection<int, Shift>  $shifts
     * @param  Collection<string, FactoryHoliday>  $holidays
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function shiftIntervalsOn(
        CarbonImmutable $day,
        $shifts,
        $holidays,
        FactoryCalendar $calendar,
    ): array {
        $dateKey = $day->toDateString();
        $holiday = $holidays->get($dateKey);
        $isWorkingDayOverride = false;

        if ($holiday !== null) {
            if (! $holiday->is_working_day) {
                return [];
            }

            // An explicit working-day override beats the weekly off-day rule.
            $isWorkingDayOverride = true;
        } elseif (in_array($day->dayOfWeekIso, $calendar->weekly_off_days, true)) {
            return [];
        }

        $intervals = [];

        foreach ($shifts as $shift) {
            // On an override the day-of-week pattern is bypassed: a factory
            // declaring "we are working this Friday" means its normal shifts
            // run, and those shifts are by definition not scheduled on Friday.
            if (! $isWorkingDayOverride && ! $shift->runsOn($day->dayOfWeekIso)) {
                continue;
            }

            if ($day->lt($shift->effective_from->startOfDay())) {
                continue;
            }

            if ($shift->effective_to !== null && $day->gt($shift->effective_to->endOfDay())) {
                continue;
            }

            $start = $this->applyTime($day, $shift->start_time);
            $end = $this->applyTime($day, $shift->end_time);

            // A shift ending at or before its start time runs past midnight.
            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->addDay();
            }

            foreach ($this->subtractBreaks($start, $end, $shift) as $segment) {
                $intervals[] = $segment;
            }
        }

        return $intervals;
    }

    /**
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function subtractBreaks(CarbonImmutable $start, CarbonImmutable $end, Shift $shift): array
    {
        $segments = [[$start, $end]];

        foreach ($shift->breaks as $break) {
            if ($break->counts_as_operating_time) {
                continue;
            }

            $breakStart = $this->applyTime($start, $break->start_time);
            $breakEnd = $this->applyTime($start, $break->end_time);

            if ($breakEnd->lessThanOrEqualTo($breakStart)) {
                $breakEnd = $breakEnd->addDay();
            }

            // A break before the shift start belongs to the overnight portion.
            if ($breakStart->lessThan($start)) {
                $breakStart = $breakStart->addDay();
                $breakEnd = $breakEnd->addDay();
            }

            $next = [];

            foreach ($segments as [$segStart, $segEnd]) {
                if ($breakEnd->lessThanOrEqualTo($segStart) || $breakStart->greaterThanOrEqualTo($segEnd)) {
                    $next[] = [$segStart, $segEnd];

                    continue;
                }

                if ($breakStart->greaterThan($segStart)) {
                    $next[] = [$segStart, $breakStart];
                }

                if ($breakEnd->lessThan($segEnd)) {
                    $next[] = [$breakEnd, $segEnd];
                }
            }

            $segments = $next;
        }

        return $segments;
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

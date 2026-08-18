<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Services;

use App\Modules\Calendar\Services\WorkingTimeService;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;

/**
 * Dry-runs a plan's rules to show the next few due dates before it is saved
 * (Frontend 5.4).
 *
 * A combined OR rule, on a rolling schedule, with a non-working-day policy, is
 * genuinely hard to reason about. Showing the dates turns a guess into a check,
 * and it is cheaper to correct a plan here than to explain six months of wrong
 * PM compliance later.
 *
 * Writes nothing. It shares the interval arithmetic with the generator but
 * never touches the schedule table.
 */
class DueDatePreview
{
    private const COUNT = 5;

    public function __construct(private readonly WorkingTimeService $workingTime) {}

    /**
     * @param  array{
     *     schedule_mode?: string,
     *     start_date?: string|null,
     *     interval_value?: int|string|null,
     *     interval_unit?: string|null,
     *     non_working_day_policy?: string|null,
     *     factory_id?: string|null
     * }  $input
     * @return array{dates: list<array{date: string, moved: bool}>, note: string|null}
     */
    public function forInput(array $input, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        $value = (int) ($input['interval_value'] ?? 0);
        $unit = $input['interval_unit'] ?? 'DAY';
        $mode = $input['schedule_mode'] ?? 'ROLLING';
        $policy = $input['non_working_day_policy'] ?? 'NONE';

        if ($value <= 0) {
            return ['dates' => [], 'note' => __('maintenance.preview_needs_interval')];
        }

        $start = $input['start_date'] ?? null;
        $anchor = filled($start)
            ? CarbonImmutable::parse($start)->startOfDay()
            : $now->startOfDay();

        $factory = filled($input['factory_id'] ?? null)
            ? Factory::find($input['factory_id'])
            : null;

        $dates = [];
        $cursor = $anchor->lessThan($now->startOfDay()) ? $now->startOfDay() : $anchor;

        for ($i = 0; $i < self::COUNT; $i++) {
            $moved = false;
            $shown = $cursor;

            if ($factory !== null && $policy !== 'NONE') {
                $adjusted = $this->workingTime->applyNonWorkingDayPolicy($factory, $cursor, $policy);
                $moved = ! $adjusted->isSameDay($cursor);
                $shown = $adjusted;
            }

            $dates[] = ['date' => $shown->toDateString(), 'moved' => $moved];

            $cursor = $this->add($cursor, $value, $unit);
        }

        // A rolling plan only ever has one occurrence outstanding: the rest of
        // the preview assumes each is completed exactly on its due date, which
        // it will not be. Say so rather than implying a fixed grid.
        $note = $mode === 'ROLLING'
            ? __('maintenance.preview_rolling_note')
            : null;

        return ['dates' => $dates, 'note' => $note];
    }

    private function add(CarbonImmutable $from, int $value, string $unit): CarbonImmutable
    {
        return match ($unit) {
            'HOUR' => $from->addHours($value),
            'DAY' => $from->addDays($value),
            'WEEK' => $from->addWeeks($value),
            'MONTH' => $from->addMonthsNoOverflow($value),
            'QUARTER' => $from->addMonthsNoOverflow($value * 3),
            'YEAR' => $from->addYearsNoOverflow($value),
            default => $from->addDays($value),
        };
    }
}

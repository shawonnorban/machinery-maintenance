<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Actions;

use App\Modules\Calendar\Models\FactoryCalendar;
use App\Modules\Calendar\Models\FactoryHoliday;
use App\Modules\Calendar\Models\Shift;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * When a factory is running (SRS 7).
 *
 * Two numbers in this product are computed from nothing else: availability
 * divides downtime by scheduled operating time, and a maintenance date that
 * lands on a rest day is moved to the next working one. Both read this
 * calendar, so until a factory sets its own, every figure it sees is derived
 * from somebody else's week.
 *
 * A calendar is effective from a date and superseded rather than edited, for
 * the same reason a labour rate was: last quarter's availability was computed
 * against last quarter's working week, and rewriting the week would restate a
 * number somebody already reported.
 */
class ManageFactoryCalendar
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setCalendar(string $factoryId, array $data): FactoryCalendar
    {
        $this->assertFactory($factoryId);

        $from = CarbonImmutable::parse($data['effective_from'])->startOfDay();

        $current = FactoryCalendar::where('factory_id', $factoryId)
            ->whereNull('effective_to')
            ->orderByDesc('effective_from')
            ->first();

        if ($current !== null && $from->lessThanOrEqualTo($current->effective_from)) {
            throw ValidationException::withMessages([
                'effective_from' => __('calendar.must_start_later', [
                    'date' => $current->effective_from->toDateString(),
                ]),
            ])->status(422);
        }

        // Closed the day before the new one opens, so no day is claimed by two
        // calendars and none by neither.
        $current?->forceFill(['effective_to' => $from->subDay()->toDateString()])->save();

        return FactoryCalendar::create([
            'company_id' => $this->context->companyId(),
            'factory_id' => $factoryId,
            'operating_mode' => $data['operating_mode'],
            'weekly_off_days' => $this->weekdays($data['weekly_off_days'] ?? [], $data['operating_mode']),
            'effective_from' => $from->toDateString(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveShift(string $factoryId, array $data, ?Shift $shift = null): Shift
    {
        $this->assertFactory($factoryId);

        $start = $data['start_time'];
        $end = $data['end_time'];

        $values = [
            'factory_id' => $factoryId,
            'name' => $data['name'],
            'code' => strtoupper(trim((string) $data['code'])),
            'start_time' => $start,
            'end_time' => $end,
            // A night shift ends before it starts on the clock. Stored rather
            // than derived at read time, because every duration calculation
            // downstream needs it and none of them should re-derive it.
            'crosses_midnight' => $end <= $start,
            'days_of_week' => $this->weekdays($data['days_of_week'] ?? [], 'SHIFT_BASED'),
            'is_overtime' => (bool) ($data['is_overtime'] ?? false),
            'effective_from' => $data['effective_from'] ?? CarbonImmutable::now()->toDateString(),
            'status' => $data['status'] ?? 'ACTIVE',
        ];

        if ($shift !== null) {
            $shift->update($values);

            return $shift->fresh();
        }

        return Shift::create($values + ['company_id' => $this->context->companyId()]);
    }

    public function deleteShift(Shift $shift): void
    {
        // Nothing points at a shift by foreign key, but the hours it describes
        // are what past availability was measured against. Ending it leaves
        // that readable; deleting it does not.
        $shift->forceFill([
            'status' => 'INACTIVE',
            'effective_to' => CarbonImmutable::now()->toDateString(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addHoliday(string $factoryId, array $data): FactoryHoliday
    {
        $this->assertFactory($factoryId);

        $date = CarbonImmutable::parse($data['date'])->toDateString();

        $existing = FactoryHoliday::where('factory_id', $factoryId)
            ->whereDate('date', $date)
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'date' => __('calendar.holiday_exists'),
            ])->status(422);
        }

        return FactoryHoliday::create([
            'company_id' => $this->context->companyId(),
            'factory_id' => $factoryId,
            'date' => $date,
            'name' => $data['name'],
            // Eid and the two national days are closures; a working Friday
            // during a shipment week is the same table saying the opposite.
            'is_working_day' => (bool) ($data['is_working_day'] ?? false),
        ]);
    }

    public function removeHoliday(FactoryHoliday $holiday): void
    {
        $holiday->delete();
    }

    /**
     * @param  mixed  $days
     * @return list<int>
     */
    private function weekdays($days, string $mode): array
    {
        if ($mode === 'CONTINUOUS') {
            // A plant that never stops has no weekly off day, whatever the
            // form happened to send.
            return [];
        }

        $days = array_values(array_unique(array_map('intval', (array) $days)));

        foreach ($days as $day) {
            if ($day < 1 || $day > 7) {
                throw ValidationException::withMessages([
                    'weekly_off_days' => __('calendar.invalid_weekday'),
                ]);
            }
        }

        sort($days);

        return $days;
    }

    private function assertFactory(string $factoryId): void
    {
        if (! $this->context->canAccessFactory($factoryId)) {
            throw ValidationException::withMessages([
                'factory_id' => __('calendar.factory_unavailable'),
            ]);
        }
    }
}

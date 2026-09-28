<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Modules\Calendar\Models\FactoryCalendar;
use App\Modules\Calendar\Models\FactoryHoliday;
use App\Modules\Calendar\Models\Shift;
use App\Modules\Calendar\Models\ShiftBreak;
use App\Modules\Calendar\Services\WorkingTimeService;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * SRS 47 and ADR-048.
 *
 * Every availability, MTTR and downtime figure resolves through this service,
 * so its edge cases are tested directly rather than through the modules that
 * consume it.
 */
class WorkingTimeServiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Factory $factory;

    private WorkingTimeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->company, 'Dhaka Unit 1', 'DHK');

        TenantFixture::actingAsTenant($this->company);

        $this->service = app(WorkingTimeService::class);
    }

    /** One 08:00-22:00 shift, Saturday to Thursday, Friday off. */
    private function singleShiftCalendar(): void
    {
        FactoryCalendar::create([
            'factory_id' => $this->factory->id,
            'operating_mode' => 'SHIFT_BASED',
            'weekly_off_days' => [5], // Friday
            'effective_from' => '2026-01-01',
        ]);

        Shift::create([
            'factory_id' => $this->factory->id,
            'name' => 'Day Shift',
            'code' => 'DAY',
            'start_time' => '08:00:00',
            'end_time' => '22:00:00',
            'days_of_week' => [1, 2, 3, 4, 6, 7],
            'effective_from' => '2026-01-01',
        ]);
    }

    /** UTC instant for a local Asia/Dhaka wall-clock time. */
    private function dhaka(string $local): CarbonImmutable
    {
        return CarbonImmutable::parse($local, 'Asia/Dhaka')->utc();
    }

    public function test_the_overnight_case_from_adr_048(): void
    {
        $this->singleShiftCalendar();

        // Breakdown at 21:50, production resumes 06:10 next morning.
        // Wall clock says 8h20m. The factory was only running for 10 minutes
        // of that, because the shift ended at 22:00.
        $result = $this->service->workingMinutesBetween(
            $this->factory,
            $this->dhaka('2026-08-17 21:50'),
            $this->dhaka('2026-08-18 06:10'),
        );

        $this->assertSame(10, $result['minutes']);
        $this->assertSame('SHIFT_CALENDAR', $result['basis']);
    }

    public function test_a_window_inside_one_shift_is_wall_clock(): void
    {
        $this->singleShiftCalendar();

        $result = $this->service->workingMinutesBetween(
            $this->factory,
            $this->dhaka('2026-08-17 10:00'),
            $this->dhaka('2026-08-17 12:30'),
        );

        $this->assertSame(150, $result['minutes']);
    }

    public function test_a_full_working_day_counts_the_shift_length(): void
    {
        $this->singleShiftCalendar();

        $result = $this->service->workingMinutesBetween(
            $this->factory,
            $this->dhaka('2026-08-17 00:00'),
            $this->dhaka('2026-08-18 00:00'),
        );

        $this->assertSame(14 * 60, $result['minutes']);
    }

    public function test_the_weekly_off_day_contributes_nothing(): void
    {
        $this->singleShiftCalendar();

        // 2026-08-21 is a Friday.
        $this->assertSame(5, CarbonImmutable::parse('2026-08-21')->dayOfWeekIso);

        $result = $this->service->workingMinutesBetween(
            $this->factory,
            $this->dhaka('2026-08-21 00:00'),
            $this->dhaka('2026-08-22 00:00'),
        );

        $this->assertSame(0, $result['minutes']);
    }

    public function test_a_holiday_contributes_nothing(): void
    {
        $this->singleShiftCalendar();

        FactoryHoliday::create([
            'factory_id' => $this->factory->id,
            'date' => '2026-08-17',
            'name' => 'National Mourning Day',
        ]);

        $result = $this->service->workingMinutesBetween(
            $this->factory,
            $this->dhaka('2026-08-17 00:00'),
            $this->dhaka('2026-08-18 00:00'),
        );

        $this->assertSame(0, $result['minutes']);
    }

    public function test_a_working_day_override_turns_an_off_day_back_on(): void
    {
        $this->singleShiftCalendar();

        // Peak shipment week: the Friday is worked.
        FactoryHoliday::create([
            'factory_id' => $this->factory->id,
            'date' => '2026-08-21',
            'name' => 'Shipment push',
            'is_working_day' => true,
        ]);

        $result = $this->service->workingMinutesBetween(
            $this->factory,
            $this->dhaka('2026-08-21 00:00'),
            $this->dhaka('2026-08-22 00:00'),
        );

        $this->assertSame(14 * 60, $result['minutes']);
    }

    public function test_unpaid_breaks_are_excluded(): void
    {
        $this->singleShiftCalendar();

        $shift = Shift::where('code', 'DAY')->firstOrFail();

        ShiftBreak::create([
            'shift_id' => $shift->id,
            'name' => 'Lunch',
            'start_time' => '13:00:00',
            'end_time' => '14:00:00',
            'counts_as_operating_time' => false,
        ]);

        ShiftBreak::create([
            'shift_id' => $shift->id,
            'name' => 'Tea',
            'start_time' => '17:00:00',
            'end_time' => '17:15:00',
            'counts_as_operating_time' => true,
        ]);

        $result = $this->service->workingMinutesBetween(
            $this->factory,
            $this->dhaka('2026-08-17 00:00'),
            $this->dhaka('2026-08-18 00:00'),
        );

        // 14h shift, minus the unpaid hour. The paid tea break still counts.
        $this->assertSame(13 * 60, $result['minutes']);
    }

    public function test_a_breakdown_spanning_a_break_excludes_it(): void
    {
        $this->singleShiftCalendar();

        $shift = Shift::where('code', 'DAY')->firstOrFail();

        ShiftBreak::create([
            'shift_id' => $shift->id,
            'name' => 'Lunch',
            'start_time' => '13:00:00',
            'end_time' => '14:00:00',
            'counts_as_operating_time' => false,
        ]);

        // 12:30 to 14:30 is two hours of wall clock but one hour of running.
        $result = $this->service->workingMinutesBetween(
            $this->factory,
            $this->dhaka('2026-08-17 12:30'),
            $this->dhaka('2026-08-17 14:30'),
        );

        $this->assertSame(60, $result['minutes']);
    }

    public function test_a_night_shift_crossing_midnight_is_counted(): void
    {
        FactoryCalendar::create([
            'factory_id' => $this->factory->id,
            'operating_mode' => 'SHIFT_BASED',
            'weekly_off_days' => [],
            'effective_from' => '2026-01-01',
        ]);

        Shift::create([
            'factory_id' => $this->factory->id,
            'name' => 'Night Shift',
            'code' => 'NIGHT',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'crosses_midnight' => true,
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
            'effective_from' => '2026-01-01',
        ]);

        $result = $this->service->workingMinutesBetween(
            $this->factory,
            $this->dhaka('2026-08-17 23:30'),
            $this->dhaka('2026-08-18 02:30'),
        );

        $this->assertSame(180, $result['minutes']);
    }

    public function test_a_continuous_factory_uses_wall_clock(): void
    {
        FactoryCalendar::create([
            'factory_id' => $this->factory->id,
            'operating_mode' => 'CONTINUOUS',
            'weekly_off_days' => [],
            'effective_from' => '2026-01-01',
        ]);

        $result = $this->service->workingMinutesBetween(
            $this->factory,
            $this->dhaka('2026-08-17 21:50'),
            $this->dhaka('2026-08-18 06:10'),
        );

        $this->assertSame(500, $result['minutes']);
        $this->assertSame('CONTINUOUS', $result['basis']);
    }

    public function test_no_calendar_falls_back_to_continuous_and_says_so(): void
    {
        // The fallback must be visible. A report that silently changes basis
        // is worse than one that states which basis it used (SRS 47.2 rule 4).
        $result = $this->service->workingMinutesBetween(
            $this->factory,
            $this->dhaka('2026-08-17 21:50'),
            $this->dhaka('2026-08-18 06:10'),
        );

        $this->assertSame(500, $result['minutes']);
        $this->assertSame('CONTINUOUS_FALLBACK', $result['basis']);
    }

    public function test_an_inverted_or_empty_window_is_zero(): void
    {
        $this->singleShiftCalendar();

        $this->assertSame(0, $this->service->workingMinutesBetween(
            $this->factory,
            $this->dhaka('2026-08-17 12:00'),
            $this->dhaka('2026-08-17 12:00'),
        )['minutes']);

        $this->assertSame(0, $this->service->workingMinutesBetween(
            $this->factory,
            $this->dhaka('2026-08-17 14:00'),
            $this->dhaka('2026-08-17 12:00'),
        )['minutes']);
    }

    public function test_editing_a_shift_does_not_rewrite_a_closed_period(): void
    {
        $this->singleShiftCalendar();

        // From September the factory moves to a 10-hour shift. The August
        // figure must not change (SRS 47.2 rule 1).
        Shift::where('code', 'DAY')->update(['effective_to' => '2026-08-31']);

        Shift::create([
            'factory_id' => $this->factory->id,
            'name' => 'Day Shift',
            'code' => 'DAY2',
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'days_of_week' => [1, 2, 3, 4, 6, 7],
            'effective_from' => '2026-09-01',
        ]);

        $august = $this->service->workingMinutesBetween(
            $this->factory,
            $this->dhaka('2026-08-17 00:00'),
            $this->dhaka('2026-08-18 00:00'),
        );

        $september = $this->service->workingMinutesBetween(
            $this->factory,
            $this->dhaka('2026-09-14 00:00'),
            $this->dhaka('2026-09-15 00:00'),
        );

        $this->assertSame(14 * 60, $august['minutes']);
        $this->assertSame(10 * 60, $september['minutes']);
    }

    public function test_is_working_day_respects_off_days_and_holidays(): void
    {
        $this->singleShiftCalendar();

        $this->assertTrue($this->service->isWorkingDay($this->factory, $this->dhaka('2026-08-17 09:00')));
        $this->assertFalse($this->service->isWorkingDay($this->factory, $this->dhaka('2026-08-21 09:00')));
    }

    public function test_non_working_day_policy_moves_a_due_date(): void
    {
        $this->singleShiftCalendar();

        $friday = $this->dhaka('2026-08-21 09:00');

        $next = $this->service->applyNonWorkingDayPolicy($this->factory, $friday, 'NEXT_WORKING_DAY');
        $prev = $this->service->applyNonWorkingDayPolicy($this->factory, $friday, 'PREVIOUS_WORKING_DAY');
        $none = $this->service->applyNonWorkingDayPolicy($this->factory, $friday, 'NONE');

        $this->assertSame('2026-08-22', $next->setTimezone('Asia/Dhaka')->toDateString());
        $this->assertSame('2026-08-20', $prev->setTimezone('Asia/Dhaka')->toDateString());
        $this->assertSame('2026-08-21', $none->setTimezone('Asia/Dhaka')->toDateString());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Modules\HrPayroll\Services\WorkingTimeService;
use App\Modules\HrPayroll\Support\FactoryCalendarSetup;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * SRS 47 and ADR-048, on HR & Payroll's calendar (the user's decision of
 * 2026-11-15): shifts from Shift Master, holidays from Holidays, the weekly
 * off-days from Settings → Working days, the mode from factory.operating_mode.
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

    /** One 08:00-22:00 shift, Saturday to Thursday (the default working week), Friday off. */
    private function singleShiftCalendar(int $breakMin = 0): string
    {
        FactoryCalendarSetup::factory($this->company->id, $this->factory->id, 'SHIFT_BASED');

        return FactoryCalendarSetup::shift($this->company->id, 'DAY', 'Day Shift', '08:00', '22:00', $breakMin);
    }

    /** UTC instant for a local Asia/Dhaka wall-clock time. */
    private function dhaka(string $local): CarbonImmutable
    {
        return CarbonImmutable::parse($local, 'Asia/Dhaka')->utc();
    }

    private function minutes(string $from, string $to): array
    {
        return $this->service->workingMinutesBetween($this->factory, $this->dhaka($from), $this->dhaka($to));
    }

    public function test_the_overnight_case_from_adr_048(): void
    {
        $this->singleShiftCalendar();

        // Breakdown at 21:50, production resumes 06:10 next morning. Wall
        // clock says 8h20m; the factory ran for 10 minutes of it.
        $result = $this->minutes('2026-08-17 21:50', '2026-08-18 06:10');

        $this->assertSame(10, $result['minutes']);
        $this->assertSame('SHIFT_CALENDAR', $result['basis']);
    }

    public function test_a_window_inside_one_shift_is_wall_clock(): void
    {
        $this->singleShiftCalendar();

        $this->assertSame(150, $this->minutes('2026-08-17 10:00', '2026-08-17 12:30')['minutes']);
    }

    public function test_a_full_working_day_counts_the_shift_length(): void
    {
        $this->singleShiftCalendar();

        $this->assertSame(14 * 60, $this->minutes('2026-08-17 00:00', '2026-08-18 00:00')['minutes']);
    }

    public function test_the_weekly_off_day_contributes_nothing(): void
    {
        $this->singleShiftCalendar();

        // 2026-08-21 is a Friday.
        $this->assertSame(5, CarbonImmutable::parse('2026-08-21')->dayOfWeekIso);
        $this->assertSame(0, $this->minutes('2026-08-21 00:00', '2026-08-22 00:00')['minutes']);
    }

    public function test_a_factory_that_works_fridays_says_so_in_its_working_week(): void
    {
        $this->singleShiftCalendar();
        FactoryCalendarSetup::factory($this->company->id, $this->factory->id, 'SHIFT_BASED', ['SAT', 'SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI']);

        $this->assertSame(14 * 60, $this->minutes('2026-08-21 00:00', '2026-08-22 00:00')['minutes']);
    }

    public function test_an_hr_holiday_contributes_nothing_for_every_day_it_spans(): void
    {
        $this->singleShiftCalendar();

        // Eid: three days from Monday 17 August.
        FactoryCalendarSetup::holiday($this->company->id, '2026-08-17', 'Eid-ul-Adha', 3);

        $this->assertSame(0, $this->minutes('2026-08-17 00:00', '2026-08-20 00:00')['minutes']);
        $this->assertSame(14 * 60, $this->minutes('2026-08-20 00:00', '2026-08-21 00:00')['minutes']);
    }

    public function test_a_recurring_holiday_falls_on_its_day_every_year(): void
    {
        $this->singleShiftCalendar();
        FactoryCalendarSetup::holiday($this->company->id, '2025-12-16', 'Victory Day', 1, true);

        // 2026-12-16 is a Wednesday.
        $this->assertFalse($this->service->isWorkingDay($this->factory, $this->dhaka('2026-12-16 09:00')));
        $this->assertTrue($this->service->isWorkingDay($this->factory, $this->dhaka('2026-12-17 09:00')));
    }

    public function test_an_inactive_holiday_is_not_a_day_off(): void
    {
        $this->singleShiftCalendar();
        $id = FactoryCalendarSetup::holiday($this->company->id, '2026-08-17', 'Cancelled');
        DB::table('hr_holidays')->where('id', $id)->update(['active' => false]);

        $this->assertSame(14 * 60, $this->minutes('2026-08-17 00:00', '2026-08-18 00:00')['minutes']);
    }

    public function test_the_shift_break_is_taken_out_of_the_middle(): void
    {
        $this->singleShiftCalendar(60);

        // 14 h shift less its hour of break; the break sits mid-shift, 14:30-15:30.
        $this->assertSame(13 * 60, $this->minutes('2026-08-17 00:00', '2026-08-18 00:00')['minutes']);
        $this->assertSame(60, $this->minutes('2026-08-17 14:00', '2026-08-17 16:00')['minutes']);
        $this->assertSame(120, $this->minutes('2026-08-17 10:00', '2026-08-17 12:00')['minutes']);
    }

    public function test_a_night_shift_crossing_midnight_is_counted(): void
    {
        FactoryCalendarSetup::factory($this->company->id, $this->factory->id, 'SHIFT_BASED');
        FactoryCalendarSetup::shift($this->company->id, 'NIGHT', 'Night Shift', '22:00', '06:00');

        // Monday night into Tuesday morning.
        $this->assertSame(180, $this->minutes('2026-08-17 23:30', '2026-08-18 02:30')['minutes']);
    }

    public function test_a_break_is_taken_out_at_its_clock_time(): void
    {
        FactoryCalendarSetup::factory($this->company->id, $this->factory->id, 'SHIFT_BASED');
        FactoryCalendarSetup::shift($this->company->id, 'DAY', 'Day Shift', '08:00', '17:00', 60, true, '13:00');
        FactoryCalendarSetup::shift($this->company->id, 'NIGHT', 'Night Shift', '20:00', '05:00', 60, true, '01:00');

        // 12:30 is the middle of the day; the break is 13:00–14:00.
        $this->assertSame(60, $this->minutes('2026-08-17 12:00', '2026-08-17 13:00')['minutes']);
        $this->assertSame(0, $this->minutes('2026-08-17 13:00', '2026-08-17 14:00')['minutes']);
        // The night's break is the next morning's 01:00–02:00.
        $this->assertSame(0, $this->minutes('2026-08-18 01:00', '2026-08-18 02:00')['minutes']);
        $this->assertSame(16 * 60, $this->minutes('2026-08-17 08:00', '2026-08-18 08:00')['minutes']);
    }

    public function test_overlapping_shifts_are_counted_once(): void
    {
        FactoryCalendarSetup::factory($this->company->id, $this->factory->id, 'SHIFT_BASED');
        FactoryCalendarSetup::shift($this->company->id, 'ST-MOR', 'Morning', '06:00', '14:00');
        FactoryCalendarSetup::shift($this->company->id, 'ST-GEN', 'General', '08:00', '17:00');
        FactoryCalendarSetup::shift($this->company->id, 'ST-EVE', 'Evening', '14:00', '22:00');

        // 06:00-22:00 covered, once: 16 h, not 8 + 9 + 8.
        $this->assertSame(16 * 60, $this->minutes('2026-08-17 00:00', '2026-08-18 00:00')['minutes']);
    }

    public function test_the_factory_runs_the_shifts_its_people_are_on(): void
    {
        FactoryCalendarSetup::factory($this->company->id, $this->factory->id, 'SHIFT_BASED');
        $general = FactoryCalendarSetup::shift($this->company->id, 'ST-GEN', 'General', '08:00', '17:00');
        FactoryCalendarSetup::shift($this->company->id, 'ST-NGT', 'Night', '22:00', '06:00');
        $this->employee($general);

        // Only General is staffed here, so the night shift does not count.
        $this->assertSame(9 * 60, $this->minutes('2026-08-17 00:00', '2026-08-18 00:00')['minutes']);
    }

    public function test_a_shift_counts_only_from_its_effective_date(): void
    {
        $this->singleShiftCalendar();
        $later = FactoryCalendarSetup::shift($this->company->id, 'NIGHT', 'Night Shift', '22:00', '06:00');
        DB::table('hr_shifts')->where('id', $later)->update(['effective_date' => '2026-09-01']);

        $this->assertSame(14 * 60, $this->minutes('2026-08-17 00:00', '2026-08-18 00:00')['minutes']);
    }

    public function test_a_continuous_factory_uses_wall_clock(): void
    {
        FactoryCalendarSetup::factory($this->company->id, $this->factory->id, 'CONTINUOUS');

        $result = $this->minutes('2026-08-17 21:50', '2026-08-18 06:10');

        $this->assertSame(500, $result['minutes']);
        $this->assertSame('CONTINUOUS', $result['basis']);
    }

    public function test_no_operating_mode_falls_back_to_continuous_and_says_so(): void
    {
        // The fallback must be visible. A report that silently changes basis
        // is worse than one that states which basis it used (SRS 47.2 rule 4).
        $result = $this->minutes('2026-08-17 21:50', '2026-08-18 06:10');

        $this->assertSame(500, $result['minutes']);
        $this->assertSame('CONTINUOUS_FALLBACK', $result['basis']);
    }

    public function test_a_shift_based_factory_without_shifts_says_so(): void
    {
        FactoryCalendarSetup::factory($this->company->id, $this->factory->id, 'SHIFT_BASED');

        $this->assertSame(['minutes' => 0, 'basis' => 'NO_SHIFTS_DEFINED'], $this->minutes('2026-08-17 00:00', '2026-08-18 00:00'));
    }

    public function test_the_company_level_mode_reaches_every_factory(): void
    {
        FactoryCalendarSetup::setting($this->company->id, null, 'factory.operating_mode', 'ENUM', 'SHIFT_BASED');
        FactoryCalendarSetup::shift($this->company->id, 'DAY', 'Day Shift', '08:00', '22:00');

        $this->assertSame('SHIFT_CALENDAR', $this->minutes('2026-08-17 00:00', '2026-08-18 00:00')['basis']);
    }

    public function test_an_inverted_or_empty_window_is_zero(): void
    {
        $this->singleShiftCalendar();

        $this->assertSame(0, $this->minutes('2026-08-17 12:00', '2026-08-17 12:00')['minutes']);
        $this->assertSame(0, $this->minutes('2026-08-17 14:00', '2026-08-17 12:00')['minutes']);
    }

    public function test_is_working_day_respects_off_days_and_holidays(): void
    {
        $this->singleShiftCalendar();
        FactoryCalendarSetup::holiday($this->company->id, '2026-08-18', 'Holiday');

        $this->assertTrue($this->service->isWorkingDay($this->factory, $this->dhaka('2026-08-17 09:00')));
        $this->assertFalse($this->service->isWorkingDay($this->factory, $this->dhaka('2026-08-18 09:00')));
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

    public function test_working_dates_skip_off_days_and_holidays(): void
    {
        $this->singleShiftCalendar();
        FactoryCalendarSetup::holiday($this->company->id, '2026-08-18', 'Holiday');

        $this->assertSame(
            ['2026-08-17', '2026-08-19', '2026-08-20', '2026-08-22'],
            $this->service->workingDates($this->factory, CarbonImmutable::parse('2026-08-17'), CarbonImmutable::parse('2026-08-22')),
        );
    }

    /** An active HR & Payroll employee of this factory on a shift (the least HR needs). */
    private function employee(string $shiftId): void
    {
        $now = now();
        $gazette = (string) Str::ulid();
        DB::table('hr_gazettes')->insert(['id' => $gazette, 'company_id' => $this->company->id, 'name' => 'RMG 2023', 'effective_date' => '2023-12-01', 'created_at' => $now, 'updated_at' => $now]);
        $type = (string) Str::ulid();
        DB::table('hr_employment_types')->insert(['id' => $type, 'company_id' => $this->company->id, 'name' => 'Worker', 'name_bn' => 'শ্রমিক', 'short_name' => 'WRK', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('hr_employees')->insert([
            'id' => (string) Str::ulid(), 'company_id' => $this->company->id, 'employee_code' => 'EMP-000201', 'card_no' => 'C0201',
            'name' => 'Rahima', 'nid' => '1234567890', 'mobile' => '01710000201', 'status' => 'ACTIVE', 'gazette_id' => $gazette,
            'employment_type_id' => $type, 'gross_a' => 14000, 'gross_b' => 18000, 'factory_id' => $this->factory->id,
            'shift_id' => $shiftId, 'joining_date' => '2025-02-01', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}

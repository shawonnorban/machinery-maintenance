<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Modules\Calendar\Models\FactoryCalendar;
use App\Modules\Calendar\Models\FactoryHoliday;
use App\Modules\Calendar\Models\Shift;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * When a factory is running (SRS 7).
 *
 * Two numbers are computed from nothing else: availability divides downtime by
 * scheduled operating time, and a maintenance date landing on a rest day moves
 * to the next working one. Until a factory can set its own week, both are
 * derived from somebody else's.
 */
class CalendarScreenTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        // Frozen: the screen defaults "in force from" to today, and several
        // assertions here name a date.
        CarbonImmutable::setTestNow('2026-06-15 09:00:00');

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        TenantFixture::actingAsTenant($this->delta);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_a_factory_can_set_its_working_week(): void
    {
        $this->actingAs($this->manager)
            ->post('/app/settings/calendar', [
                'factory_id' => $this->dhaka->id,
                'operating_mode' => 'SHIFT_BASED',
                'weekly_off_days' => [5],
                'effective_from' => '2026-07-01',
            ])
            ->assertRedirect();

        $calendar = FactoryCalendar::where('factory_id', $this->dhaka->id)->firstOrFail();

        $this->assertSame('SHIFT_BASED', $calendar->operating_mode);
        $this->assertSame([5], $calendar->weekly_off_days);
        $this->assertNull($calendar->effective_to);
    }

    /**
     * The rule the whole screen is built around.
     */
    public function test_a_new_week_supersedes_the_old_one_rather_than_editing_it(): void
    {
        $this->actingAs($this->manager)->post('/app/settings/calendar', [
            'factory_id' => $this->dhaka->id,
            'operating_mode' => 'SHIFT_BASED',
            'weekly_off_days' => [5],
            'effective_from' => '2026-07-01',
        ]);

        $this->actingAs($this->manager)->post('/app/settings/calendar', [
            'factory_id' => $this->dhaka->id,
            'operating_mode' => 'SHIFT_BASED',
            'weekly_off_days' => [5, 6],
            'effective_from' => '2026-08-01',
        ]);

        $history = FactoryCalendar::where('factory_id', $this->dhaka->id)
            ->orderBy('effective_from')
            ->get();

        $this->assertCount(2, $history);

        // Closed the day before the new one opens: no day is claimed by two
        // calendars, and none by neither. Last quarter's availability keeps
        // reading against last quarter's week.
        $this->assertSame('2026-07-31', $history[0]->effective_to->toDateString());
        $this->assertSame('2026-08-01', $history[1]->effective_from->toDateString());
        $this->assertNull($history[1]->effective_to);
    }

    public function test_a_week_cannot_start_on_or_before_the_one_in_force(): void
    {
        $this->actingAs($this->manager)->post('/app/settings/calendar', [
            'factory_id' => $this->dhaka->id,
            'operating_mode' => 'SHIFT_BASED',
            'weekly_off_days' => [5],
            'effective_from' => '2026-07-01',
        ]);

        $this->actingAs($this->manager)
            ->from('/app/settings/calendar')
            ->post('/app/settings/calendar', [
                'factory_id' => $this->dhaka->id,
                'operating_mode' => 'SHIFT_BASED',
                'weekly_off_days' => [6],
                'effective_from' => '2026-06-01',
            ])
            ->assertSessionHasErrors('effective_from');

        $this->assertSame(1, FactoryCalendar::where('factory_id', $this->dhaka->id)->count());
    }

    public function test_a_continuous_plant_has_no_weekly_off_day(): void
    {
        // Whatever the form sends: a plant that never stops has no rest day,
        // and availability is measured against the whole clock.
        $this->actingAs($this->manager)->post('/app/settings/calendar', [
            'factory_id' => $this->dhaka->id,
            'operating_mode' => 'CONTINUOUS',
            'weekly_off_days' => [5, 6],
            'effective_from' => '2026-07-01',
        ]);

        $calendar = FactoryCalendar::where('factory_id', $this->dhaka->id)->firstOrFail();

        $this->assertSame([], $calendar->weekly_off_days);
        $this->assertTrue($calendar->isContinuous());
    }

    public function test_a_night_shift_is_recorded_as_crossing_midnight(): void
    {
        $this->actingAs($this->manager)
            ->post('/app/settings/calendar/shifts', [
                'factory_id' => $this->dhaka->id,
                'name' => 'Night',
                'code' => 'night',
                'start_time' => '20:00',
                'end_time' => '08:00',
                'days_of_week' => [1, 2, 3, 4, 6, 7],
                'effective_from' => '2026-07-01',
            ])
            ->assertRedirect();

        $shift = Shift::where('code', 'NIGHT')->firstOrFail();

        // It ends before it starts on the clock, and every duration downstream
        // needs to know that without re-deriving it.
        $this->assertTrue((bool) $shift->crosses_midnight);
        $this->assertSame([1, 2, 3, 4, 6, 7], $shift->days_of_week);
    }

    public function test_a_day_shift_does_not_cross_midnight(): void
    {
        $this->actingAs($this->manager)->post('/app/settings/calendar/shifts', [
            'factory_id' => $this->dhaka->id,
            'name' => 'Day',
            'code' => 'DAY',
            'start_time' => '08:00',
            'end_time' => '20:00',
            'days_of_week' => [1, 2, 3, 4, 6, 7],
            'effective_from' => '2026-07-01',
        ]);

        $this->assertFalse((bool) Shift::where('code', 'DAY')->firstOrFail()->crosses_midnight);
    }

    public function test_a_shift_is_ended_rather_than_deleted(): void
    {
        $this->actingAs($this->manager)->post('/app/settings/calendar/shifts', [
            'factory_id' => $this->dhaka->id,
            'name' => 'Day',
            'code' => 'DAY',
            'start_time' => '08:00',
            'end_time' => '20:00',
            'days_of_week' => [1, 2, 3, 4, 6, 7],
            'effective_from' => '2026-07-01',
        ]);

        $shift = Shift::where('code', 'DAY')->firstOrFail();

        $this->actingAs($this->manager)
            ->delete('/app/settings/calendar/shifts/'.$shift->id)
            ->assertRedirect();

        // Still there: past availability was measured against these hours.
        $this->assertNotNull(Shift::find($shift->id));
        $this->assertSame('INACTIVE', $shift->fresh()->status);
        $this->assertSame('2026-06-15', $shift->fresh()->effective_to->toDateString());
    }

    public function test_a_shift_code_cannot_be_used_twice_in_one_factory(): void
    {
        $payload = [
            'factory_id' => $this->dhaka->id,
            'name' => 'Day',
            'code' => 'DAY',
            'start_time' => '08:00',
            'end_time' => '20:00',
            'days_of_week' => [1, 2, 3, 4, 6, 7],
            'effective_from' => '2026-07-01',
        ];

        $this->actingAs($this->manager)->post('/app/settings/calendar/shifts', $payload);

        $this->actingAs($this->manager)
            ->from('/app/settings/calendar')
            ->post('/app/settings/calendar/shifts', $payload)
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Shift::where('factory_id', $this->dhaka->id)->count());
    }

    public function test_a_holiday_and_a_working_friday_are_the_same_table(): void
    {
        $this->actingAs($this->manager)->post('/app/settings/calendar/holidays', [
            'factory_id' => $this->dhaka->id,
            'date' => '2026-07-16',
            'name' => 'Eid holiday',
        ]);

        $this->actingAs($this->manager)->post('/app/settings/calendar/holidays', [
            'factory_id' => $this->dhaka->id,
            'date' => '2026-07-17',
            'name' => 'Shipment week — working Friday',
            'is_working_day' => '1',
        ]);

        $closed = FactoryHoliday::whereDate('date', '2026-07-16')->firstOrFail();
        $working = FactoryHoliday::whereDate('date', '2026-07-17')->firstOrFail();

        $this->assertFalse((bool) $closed->is_working_day);
        $this->assertTrue((bool) $working->is_working_day);
    }

    public function test_the_same_date_cannot_be_listed_twice(): void
    {
        $payload = [
            'factory_id' => $this->dhaka->id,
            'date' => '2026-07-16',
            'name' => 'Eid holiday',
        ];

        $this->actingAs($this->manager)->post('/app/settings/calendar/holidays', $payload);

        $this->actingAs($this->manager)
            ->from('/app/settings/calendar')
            ->post('/app/settings/calendar/holidays', $payload + ['name' => 'Typed twice'])
            ->assertSessionHasErrors('date');

        $this->assertSame(1, FactoryHoliday::whereDate('date', '2026-07-16')->count());
    }

    public function test_another_factory_cannot_be_configured_from_here(): void
    {
        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        $theirFactory = TenantFixture::factory($other, 'Their Unit', 'BTU');

        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($this->manager)
            ->from('/app/settings/calendar')
            ->post('/app/settings/calendar', [
                'factory_id' => $theirFactory->id,
                'operating_mode' => 'SHIFT_BASED',
                'weekly_off_days' => [5],
                'effective_from' => '2026-07-01',
            ])
            ->assertSessionHasErrors('factory_id');

        $this->assertSame(0, FactoryCalendar::withoutGlobalScopes()
            ->where('factory_id', $theirFactory->id)->count());
    }

    public function test_the_screen_is_closed_to_roles_that_do_not_configure(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $this->actingAs($technician)->get('/app/settings/calendar')->assertForbidden();
        $this->actingAs($technician)
            ->post('/app/settings/calendar', [
                'factory_id' => $this->dhaka->id,
                'operating_mode' => 'CONTINUOUS',
                'effective_from' => '2026-07-01',
            ])
            ->assertForbidden();
    }

    public function test_the_screen_renders_what_is_in_force(): void
    {
        $this->actingAs($this->manager)->post('/app/settings/calendar', [
            'factory_id' => $this->dhaka->id,
            'operating_mode' => 'SHIFT_BASED',
            'weekly_off_days' => [5],
            'effective_from' => '2026-06-15',
        ]);

        $this->actingAs($this->manager)->post('/app/settings/calendar/shifts', [
            'factory_id' => $this->dhaka->id,
            'name' => 'Night',
            'code' => 'NIGHT',
            'start_time' => '20:00',
            'end_time' => '08:00',
            'days_of_week' => [1, 2, 3],
            'effective_from' => '2026-06-15',
        ]);

        $this->actingAs($this->manager)
            ->get('/app/settings/calendar?factory_id='.$this->dhaka->id)
            ->assertOk()
            ->assertSee(__('calendar.friday'))
            ->assertSee('Night')
            ->assertSee(__('calendar.overnight'));
    }
}

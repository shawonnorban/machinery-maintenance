<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Modules\Api\Actions\IssueApiToken;
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
 * The working week, over the API (API 5.1, Gap Analysis 3.4 #36).
 *
 * Availability and every downtime figure resolve against this calendar
 * (ADR-048), so what is tested here is that a factory can set its own week
 * and get back exactly the number every other report already trusts.
 */
class CalendarApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $manager;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
    }

    public function test_a_factory_with_no_calendar_answers_with_none_rather_than_a_guess(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/factories/{$this->dhaka->id}/calendar")
            ->assertOk()
            ->assertJsonPath('data.calendar', null)
            ->assertJsonPath('data.shifts', []);
    }

    public function test_a_calendar_can_be_set_and_read_back(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->putJson("/api/v1/factories/{$this->dhaka->id}/calendar", [
                'operating_mode' => 'SHIFT_BASED',
                'weekly_off_days' => [5],
                'effective_from' => '2026-01-01',
            ])
            ->assertCreated()
            ->assertJsonPath('data.operating_mode', 'SHIFT_BASED')
            ->assertJsonPath('data.weekly_off_days', [5]);

        $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/factories/{$this->dhaka->id}/calendar")
            ->assertOk()
            ->assertJsonPath('data.calendar.operating_mode', 'SHIFT_BASED');
    }

    public function test_a_second_calendar_must_start_after_the_first(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->putJson("/api/v1/factories/{$this->dhaka->id}/calendar", [
                'operating_mode' => 'SHIFT_BASED',
                'weekly_off_days' => [5],
                'effective_from' => '2026-02-01',
            ])
            ->assertCreated();

        $this->withToken($this->tokenFor($this->manager))
            ->putJson("/api/v1/factories/{$this->dhaka->id}/calendar", [
                'operating_mode' => 'CONTINUOUS',
                'effective_from' => '2026-01-01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('effective_from');
    }

    public function test_a_shift_can_be_created_updated_and_ended(): void
    {
        $created = $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/factories/{$this->dhaka->id}/shifts", [
                'name' => 'Morning',
                'code' => 'morning',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'days_of_week' => [1, 2, 3, 4, 6],
                'effective_from' => '2026-01-01',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'MORNING')
            ->assertJsonPath('data.crosses_midnight', false)
            ->assertJsonPath('data.status', 'ACTIVE');

        $shiftId = $created->json('data.id');

        $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/factories/{$this->dhaka->id}/shifts")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($this->tokenFor($this->manager))
            ->patchJson("/api/v1/shifts/{$shiftId}", [
                'name' => 'Morning (revised)',
                'code' => 'morning',
                'start_time' => '08:30',
                'end_time' => '17:30',
                'days_of_week' => [1, 2, 3, 4, 6],
                'effective_from' => '2026-01-01',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Morning (revised)');

        $this->withToken($this->tokenFor($this->manager))
            ->deleteJson("/api/v1/shifts/{$shiftId}")
            ->assertNoContent();

        $shift = Shift::find($shiftId);
        $this->assertSame('INACTIVE', $shift->status);
        $this->assertNotNull($shift->effective_to);
    }

    public function test_a_night_shift_crossing_midnight_is_flagged(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/factories/{$this->dhaka->id}/shifts", [
                'name' => 'Night',
                'code' => 'night',
                'start_time' => '22:00',
                'end_time' => '06:00',
                'days_of_week' => [1, 2, 3, 4, 5],
                'effective_from' => '2026-01-01',
            ])
            ->assertCreated()
            ->assertJsonPath('data.crosses_midnight', true);
    }

    public function test_a_holiday_can_be_added_and_removed(): void
    {
        // A month out, not a fixed date: the listing only shows the trailing
        // three months onward (matching the web calendar screen), and a
        // hardcoded past date would age out of that window over time.
        $date = CarbonImmutable::now()->addMonth()->toDateString();

        $created = $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/factories/{$this->dhaka->id}/holidays", [
                'date' => $date,
                'name' => 'Pohela Boishakh',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Pohela Boishakh')
            ->assertJsonPath('data.is_working_day', false);

        $holidayId = $created->json('data.id');

        $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/factories/{$this->dhaka->id}/holidays")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($this->tokenFor($this->manager))
            ->deleteJson("/api/v1/holidays/{$holidayId}")
            ->assertNoContent();

        $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/factories/{$this->dhaka->id}/holidays")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_same_date_cannot_be_declared_a_holiday_twice(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/factories/{$this->dhaka->id}/holidays", [
                'date' => '2026-04-14',
                'name' => 'Pohela Boishakh',
            ])
            ->assertCreated();

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/factories/{$this->dhaka->id}/holidays", [
                'date' => '2026-04-14',
                'name' => 'Duplicate',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('date');
    }

    public function test_working_time_reports_continuous_fallback_with_no_calendar(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/factories/{$this->dhaka->id}/working-time?from=2026-01-05T00:00:00Z&to=2026-01-06T00:00:00Z")
            ->assertOk()
            ->assertJsonPath('data.basis', 'CONTINUOUS_FALLBACK')
            ->assertJsonPath('data.minutes', 1440);
    }

    public function test_working_time_resolves_against_a_shift_calendar(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->putJson("/api/v1/factories/{$this->dhaka->id}/calendar", [
                'operating_mode' => 'SHIFT_BASED',
                'weekly_off_days' => [],
                'effective_from' => '2026-01-01',
            ])
            ->assertCreated();

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/factories/{$this->dhaka->id}/shifts", [
                'name' => 'Day',
                'code' => 'day',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
                'effective_from' => '2026-01-01',
            ])
            ->assertCreated();

        // One full local day of an 08:00-17:00 shift is 9 hours = 540 minutes.
        $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/factories/{$this->dhaka->id}/working-time?from=2026-01-05T00:00:00Z&to=2026-01-06T00:00:00Z")
            ->assertOk()
            ->assertJsonPath('data.basis', 'SHIFT_CALENDAR')
            ->assertJsonPath('data.minutes', 540);
    }

    public function test_the_endpoints_are_closed_to_a_role_that_does_not_configure_the_calendar(): void
    {
        $this->withToken($this->tokenFor($this->technician))
            ->getJson("/api/v1/factories/{$this->dhaka->id}/calendar")
            ->assertForbidden();

        $this->withToken($this->tokenFor($this->technician))
            ->putJson("/api/v1/factories/{$this->dhaka->id}/calendar", [
                'operating_mode' => 'CONTINUOUS',
                'effective_from' => '2026-01-01',
            ])
            ->assertForbidden();
    }

    public function test_a_factory_outside_the_callers_reach_is_not_found(): void
    {
        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        $narayanganj = TenantFixture::factory($omega, 'Narayanganj Unit', 'NGJ');

        $this->withToken($this->tokenFor($this->manager))
            ->getJson("/api/v1/factories/{$narayanganj->id}/calendar")
            ->assertNotFound();
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}

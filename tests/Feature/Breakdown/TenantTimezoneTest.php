<?php

declare(strict_types=1);

namespace Tests\Feature\Breakdown;

use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Identity\Models\User;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Support\TenantTimezone;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Wall time in, UTC stored, wall time out (SRS 47.2).
 *
 * This is the boundary that decides whether every downtime, response and repair
 * figure in the product is right or six hours out. A `datetime-local` input
 * carries no timezone at all: the browser sends "2026-08-18T21:50" and nothing
 * more. Parsed with the application default that becomes 21:50 UTC, which is
 * ten to four the next morning in Dhaka — and the breakdown then reads as
 * having been repaired before it was reported, while looking entirely
 * plausible on screen.
 */
class TenantTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
        $this->manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
    }

    public function test_storage_is_always_utc(): void
    {
        // Non-negotiable: a company with factories in two zones cannot have one
        // local clock, and DST would make stored local times ambiguous twice a
        // year.
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_a_wall_time_typed_in_dhaka_is_stored_as_the_instant_it_names(): void
    {
        $this->actingAs($this->manager)
            ->post('/app/breakdowns', [
                'asset_id' => $this->asset->id,
                'problem_description' => 'Motor tripped at end of shift',
                // Exactly what a datetime-local input sends: no offset at all.
                'failure_at' => '2026-08-18T21:50',
                'reported_at' => '2026-08-18T21:55',
            ])
            ->assertRedirect();

        $breakdown = Breakdown::withoutGlobalScopes()->firstOrFail();

        // 21:50 in Dhaka is 15:50 UTC. Stored as 21:50 UTC it would sit six
        // hours in the future of the event it describes.
        $this->assertSame('2026-08-18 15:50:00', $breakdown->failure_at->utc()->toDateTimeString());
        $this->assertSame('2026-08-18 15:55:00', $breakdown->reported_at->utc()->toDateTimeString());
    }

    public function test_the_stored_instant_is_read_back_on_the_same_clock(): void
    {
        $this->actingAs($this->manager)->post('/app/breakdowns', [
            'asset_id' => $this->asset->id,
            'problem_description' => 'Motor tripped',
            'failure_at' => '2026-08-18T21:50',
            'reported_at' => '2026-08-18T21:50',
        ]);

        $breakdown = Breakdown::withoutGlobalScopes()->firstOrFail();

        // Round trip: what was typed is what is shown. Anything else and the
        // technician who entered it cannot recognise their own record.
        $this->actingAs($this->manager)
            ->get("/app/breakdowns/{$breakdown->id}")
            ->assertOk()
            ->assertSee('2026-08-18 21:50');
    }

    public function test_an_input_carrying_its_own_offset_is_not_reinterpreted(): void
    {
        // An API client sending ISO-8601 already names an instant. Treating it
        // as local wall time would corrupt a value that was correct.
        $this->actingAs($this->manager)->post('/app/breakdowns', [
            'asset_id' => $this->asset->id,
            'problem_description' => 'Reported through the API',
            'failure_at' => '2026-08-18T21:50:00+00:00',
            'reported_at' => '2026-08-18T21:50:00Z',
        ]);

        $breakdown = Breakdown::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('2026-08-18 21:50:00', $breakdown->failure_at->utc()->toDateTimeString());
        $this->assertSame('2026-08-18 21:50:00', $breakdown->reported_at->utc()->toDateTimeString());
    }

    public function test_a_users_own_timezone_wins_over_the_companys(): void
    {
        $timezone = app(TenantTimezone::class);

        $this->actingAs($this->manager);
        $timezone->forget();

        // The seeded user is on Asia/Dhaka, same as the company.
        $this->assertSame('Asia/Dhaka', $timezone->current());

        // A manager reviewing a Dhaka breakdown from Singapore wants their own
        // clock, not the factory's.
        $this->manager->forceFill(['timezone' => 'Asia/Singapore'])->save();
        $this->actingAs($this->manager->fresh());
        $timezone->forget();

        $this->assertSame('Asia/Singapore', $timezone->current());
    }

    public function test_conversion_is_reversible(): void
    {
        $timezone = app(TenantTimezone::class);
        $this->actingAs($this->manager);
        $timezone->forget();

        $utc = $timezone->toUtc('2026-08-18 21:50');

        $this->assertSame('2026-08-18 15:50:00', $utc->toDateTimeString());
        $this->assertSame('2026-08-18 21:50', $timezone->format($utc));
        $this->assertSame('2026-08-18T21:50', $timezone->forInput($utc));
    }

    public function test_a_backdated_correction_is_read_on_the_factory_clock(): void
    {
        $this->actingAs($this->manager)->post('/app/breakdowns', [
            'asset_id' => $this->asset->id,
            'problem_description' => 'Stopped earlier than reported',
            'failure_at' => '2026-08-18T22:00',
            'reported_at' => '2026-08-18T22:00',
        ]);

        $breakdown = Breakdown::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->manager)
            ->post("/app/breakdowns/{$breakdown->id}/timestamp", [
                'field' => 'failure_at',
                'value' => '2026-08-18T21:50',
            ])
            ->assertRedirect();

        // 21:50 Dhaka = 15:50 UTC, and still before the 16:00 UTC report, so the
        // chain stays in order. Parsed as UTC it would be 21:50 — five hours
        // after the report — and the correction would have been refused for a
        // reason that has nothing to do with what the user did.
        $this->assertSame(
            '2026-08-18 15:50:00',
            $breakdown->fresh()->failure_at->utc()->toDateTimeString(),
        );
    }

    public function test_a_work_order_schedule_is_stored_on_the_instant_it_names(): void
    {
        $this->actingAs($this->manager)
            ->post('/app/work-orders', [
                'asset_id' => $this->asset->id,
                'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')
                    ->firstOrFail()->id,
                'title' => 'Monthly service',
                'priority' => 'MEDIUM',
                'scheduled_start' => '2026-08-20T08:00',
            ])
            ->assertRedirect();

        $workOrder = WorkOrder::withoutGlobalScopes()->firstOrFail();

        // 08:00 Dhaka is 02:00 UTC. Stored as 08:00 UTC the job would appear on
        // the technician's screen at two in the afternoon.
        $this->assertSame(
            '2026-08-20 02:00:00',
            CarbonImmutable::parse($workOrder->scheduled_start)->utc()->toDateTimeString(),
        );
    }
}

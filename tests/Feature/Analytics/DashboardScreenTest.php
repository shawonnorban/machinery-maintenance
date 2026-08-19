<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Modules\Analytics\Services\KpiCalculator;
use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Breakdown\Actions\TransitionBreakdown;
use App\Modules\Calendar\Models\FactoryCalendar;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The dashboard over HTTP (SRS 30).
 *
 * What a person sees is decided by what they can act on. A storekeeper landing
 * on a fleet availability figure they cannot influence is noise; a technician
 * landing on an empty page is worse.
 */
class DashboardScreenTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
    }

    private function user(string $role, string $email): User
    {
        $user = TenantFixture::user($this->delta, $role, $email);
        TenantFixture::actingAsTenant($this->delta);

        return $user;
    }

    public function test_a_manager_sees_the_management_panel(): void
    {
        $this->actingAs($this->user('MAINTENANCE_MANAGER', 'mm@delta.test'))
            ->get('/app/dashboard')
            ->assertOk()
            ->assertSee(__('dashboard.availability'))
            ->assertSee(__('dashboard.mtbf'))
            ->assertSee(__('dashboard.mttr'));
    }

    public function test_a_storekeeper_sees_stock_not_fleet_availability(): void
    {
        $this->actingAs($this->user('STOREKEEPER', 'store@delta.test'))
            ->get('/app/dashboard')
            ->assertOk()
            ->assertSee(__('dashboard.stock_value'))
            // Not merely absent from their menu: the panel is not computed at
            // all, so the page load does not pay for a scan they cannot read.
            ->assertDontSee(__('dashboard.mtbf'));
    }

    public function test_a_technician_is_told_plainly_there_is_no_panel(): void
    {
        $this->actingAs($this->user('TECHNICIAN', 'tech@delta.test'))
            ->get('/app/dashboard')
            ->assertOk()
            // A technician's work is in the lists, not on a dashboard. Saying so
            // beats rendering a blank page that reads as a bug.
            ->assertSee(__('dashboard.no_panels'));
    }

    public function test_a_kpi_with_no_data_reads_as_not_available_not_zero(): void
    {
        $this->actingAs($this->user('MAINTENANCE_MANAGER', 'mm2@delta.test'))
            ->get('/app/dashboard')
            ->assertOk()
            // No failures yet, so MTBF has no value. "0" would say the machine
            // fails constantly (SRS 31.2 rule 2).
            ->assertSee(__('common.not_available'));
    }

    public function test_the_period_is_limited_to_the_offered_options(): void
    {
        $user = $this->user('MAINTENANCE_MANAGER', 'mm3@delta.test');

        $this->actingAs($user)
            ->get('/app/dashboard?days=7')
            ->assertOk()
            ->assertSee(__('dashboard.last_days', ['days' => 7]));

        // An arbitrary window would let a link scan years of downtime rows on
        // a page that has to render in under two seconds (SRS 45).
        $this->actingAs($user)
            ->get('/app/dashboard?days=99999')
            ->assertOk()
            ->assertSee(__('dashboard.last_days', ['days' => 30]));
    }

    public function test_the_dashboard_renders_in_bengali(): void
    {
        $user = $this->user('MAINTENANCE_MANAGER', 'mm4@delta.test');
        $user->update(['locale' => 'bn']);

        $this->actingAs($user)
            ->get('/app/dashboard')
            ->assertOk()
            ->assertSee(__('dashboard.availability', locale: 'bn'), false);
    }

    public function test_the_page_shows_what_the_calculator_computes(): void
    {
        CarbonImmutable::setTestNow('2026-06-20 12:00:00');

        FactoryCalendar::create([
            'factory_id' => $this->dhaka->id,
            'operating_mode' => 'CONTINUOUS',
            'weekly_off_days' => [],
            'effective_from' => '2026-01-01',
        ]);

        $asset = Asset::firstOrFail();
        $at = CarbonImmutable::parse('2026-06-10 09:00:00');

        $breakdown = app(ReportBreakdown::class)->handle([
            'asset_id' => $asset->id,
            'problem_description' => 'Line stopped',
            'failure_at' => $at,
            'reported_at' => $at,
        ], 'user-a');

        $transition = app(TransitionBreakdown::class);
        $breakdown = $transition->acknowledge($breakdown, 'user-b', $at->addMinutes(10));
        $breakdown = $transition->startRepair($breakdown, 'user-c', $at->addMinutes(10));
        $breakdown = $transition->completeRepair($breakdown, 'user-c', $at->addMinutes(130));
        $transition->resumeProduction($breakdown, 'user-c', $at->addMinutes(130));

        $to = CarbonImmutable::now();
        $expected = app(KpiCalculator::class)->forPeriod(
            $to->subDays(30)->startOfDay(), $to, ['factory_id' => $this->dhaka->id],
        );

        $this->actingAs($this->user('MAINTENANCE_MANAGER', 'mm5@delta.test'))
            ->get('/app/dashboard')
            ->assertOk()
            // Rule 7 end to end: the tile shows the calculator's figure, not a
            // second implementation that will drift away from it.
            ->assertSee($expected['availability_percent'].'%')
            ->assertSee('1');

        $this->assertNotNull($expected['availability_percent']);

        CarbonImmutable::setTestNow();
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get('/app/dashboard')->assertRedirect('/login');
    }
}

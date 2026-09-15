<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The three dashboards, over the API (API 21, SRS 30) — each panel behind
 * its own permission, reading the same service a report would.
 */
class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Asset $asset;

    private User $manager;

    private User $engineer;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->asset = WorkOrderFixture::runningAsset($this->delta, $dhaka);
    }

    public function test_the_management_panel_reports_fleet_and_cost_figures(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/dashboard/management')
            ->assertOk()
            ->assertJsonPath('data.assets.total', 1)
            ->assertJsonStructure(['data' => ['kpis', 'assets', 'overdue_maintenance', 'cost', 'period']]);
    }

    public function test_the_maintenance_panel_reports_open_work_and_workload(): void
    {
        $this->withToken($this->tokenFor($this->engineer))
            ->getJson('/api/v1/dashboard/maintenance')
            ->assertOk()
            ->assertJsonStructure(['data' => ['today', 'due', 'overdue', 'open_work_orders', 'active_breakdowns', 'workload']]);
    }

    public function test_the_store_panel_reports_stock_value(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/dashboard/store')
            ->assertOk()
            ->assertJsonStructure(['data' => ['stock_value', 'low_stock', 'out_of_stock', 'critical_low']]);
    }

    public function test_a_role_without_the_management_permission_cannot_reach_it(): void
    {
        $this->withToken($this->tokenFor($this->engineer))
            ->getJson('/api/v1/dashboard/management')
            ->assertForbidden();
    }

    public function test_kpis_are_reachable_by_any_dashboard_permission(): void
    {
        $this->withToken($this->tokenFor($this->engineer))
            ->getJson('/api/v1/dashboard/kpis')
            ->assertOk();

        $this->withToken($this->tokenFor($this->technician))
            ->getJson('/api/v1/dashboard/kpis')
            ->assertForbidden();
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}

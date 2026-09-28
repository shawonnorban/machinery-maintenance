<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\User;
use App\Modules\Maintenance\Models\MaintenancePlanRule;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Maintenance plans, over the API (API 8) — the rules that turn a machine's
 * calendar into scheduled work.
 */
class MaintenancePlanApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Asset $asset;

    private User $engineer;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        // MAINTENANCE_MANAGER rather than MAINTENANCE_ENGINEER: the delete
        // test needs maintenance.plan.delete, which only the manager role
        // (a superset of the engineer's permissions) carries.
        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'manager@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->asset = WorkOrderFixture::runningAsset($this->delta, $dhaka);
    }

    private function planPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Monthly lubrication',
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'trigger_type' => 'TIME',
            'schedule_mode' => 'ROLLING',
            'interval_value' => 30,
            'interval_unit' => 'DAY',
            'priority' => 'MEDIUM',
            'non_working_day_policy' => 'NEXT_WORKING_DAY',
            'start_date' => '2026-01-01',
        ], $overrides);
    }

    public function test_a_plan_can_be_created_and_activated_generating_a_schedule(): void
    {
        $created = $this->withToken($this->tokenFor($this->engineer))
            ->postJson('/api/v1/maintenance-plans', $this->planPayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Monthly lubrication')
            ->assertJsonPath('data.active', false)
            ->assertJsonCount(1, 'data.rules');

        $planId = $created->json('data.id');

        $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/maintenance-plans/{$planId}/activate")
            ->assertOk()
            ->assertJsonPath('data.active', true);

        $this->withToken($this->tokenFor($this->engineer))
            ->getJson('/api/v1/maintenance-schedules')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.maintenance_plan_id', $planId);
    }

    public function test_show_carries_the_template_version_number_and_a_schedules_trigger_facts(): void
    {
        $version = WorkOrderFixture::publishedChecklist($this->delta);

        $created = $this->withToken($this->tokenFor($this->engineer))
            ->postJson('/api/v1/maintenance-plans', $this->planPayload(['template_version_id' => $version->id]))
            ->assertCreated();

        $planId = $created->json('data.id');
        $this->assertSame(1, $created->json('data.template_version_number'));

        $show = $this->withToken($this->tokenFor($this->engineer))
            ->getJson("/api/v1/maintenance-plans/{$planId}")
            ->assertOk();
        $this->assertSame($version->id, $show->json('data.template_version_id'));
        $this->assertSame(1, $show->json('data.template_version_number'));

        $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/maintenance-plans/{$planId}/activate")
            ->assertOk();

        $schedules = $this->withToken($this->tokenFor($this->engineer))
            ->getJson('/api/v1/maintenance-schedules')
            ->assertOk();

        // A TIME-triggered occurrence has no meter reading behind it and is
        // triggered by the plan's own schedule, not a reading crossing a
        // threshold — both fields must still be present (null), mirroring
        // `plans/show.blade.php`'s own "—" fallback for a TIME plan's rows.
        $this->assertArrayHasKey('due_meter', $schedules->json('data.0'));
        $this->assertArrayHasKey('triggered_by', $schedules->json('data.0'));
    }

    public function test_a_plan_cannot_target_both_an_asset_and_a_type(): void
    {
        $this->withToken($this->tokenFor($this->engineer))
            ->postJson('/api/v1/maintenance-plans', $this->planPayload([
                'asset_type_id' => $this->asset->asset_type_id,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('asset_id');
    }

    public function test_a_time_trigger_without_an_interval_is_refused(): void
    {
        $this->withToken($this->tokenFor($this->engineer))
            ->postJson('/api/v1/maintenance-plans', $this->planPayload(['interval_value' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('interval_value');
    }

    public function test_activating_a_plan_with_no_rules_is_refused(): void
    {
        // Not reachable through the create payload itself (the request's own
        // validation demands an interval or a threshold) — this exercises
        // SaveMaintenancePlan::activate()'s own guard directly, against a
        // plan whose rule was removed after creation.
        $planId = $this->withToken($this->tokenFor($this->engineer))
            ->postJson('/api/v1/maintenance-plans', $this->planPayload())
            ->json('data.id');

        MaintenancePlanRule::where('maintenance_plan_id', $planId)->delete();

        $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/maintenance-plans/{$planId}/activate")
            ->assertStatus(422);
    }

    public function test_a_plan_with_occurrences_cannot_be_deleted_but_an_empty_one_can(): void
    {
        $withHistory = $this->withToken($this->tokenFor($this->engineer))
            ->postJson('/api/v1/maintenance-plans', $this->planPayload())
            ->json('data.id');

        $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/maintenance-plans/{$withHistory}/activate")
            ->assertOk();

        $this->withToken($this->tokenFor($this->engineer))
            ->deleteJson("/api/v1/maintenance-plans/{$withHistory}")
            ->assertStatus(409);

        $empty = $this->withToken($this->tokenFor($this->engineer))
            ->postJson('/api/v1/maintenance-plans', $this->planPayload(['name' => 'Never activated']))
            ->json('data.id');

        $this->withToken($this->tokenFor($this->engineer))
            ->deleteJson("/api/v1/maintenance-plans/{$empty}")
            ->assertNoContent();
    }

    public function test_form_options_serves_the_dropdowns_the_technician_role_cannot_open(): void
    {
        // Same shape as AssetApiController::formOptions()'s own finding: a
        // role that can only ever view_any plans (never create one) must
        // still be able to open one to read it, without its own dropdown
        // data 403ing under a higher masterdata-style permission.
        $response = $this->withToken($this->tokenFor($this->engineer))
            ->getJson('/api/v1/maintenance-plans/form-options')
            ->assertOk();

        $response->assertJsonStructure([
            'data' => ['assets', 'asset_types', 'maintenance_types', 'meter_types', 'teams', 'templates'],
        ]);

        $this->assertContains(
            $this->asset->id,
            array_column($response->json('data.assets'), 'id'),
        );
    }

    public function test_the_endpoints_are_closed_to_a_role_that_does_not_plan_maintenance(): void
    {
        $this->withToken($this->tokenFor($this->technician))
            ->getJson('/api/v1/maintenance-plans')
            ->assertForbidden();

        $this->withToken($this->tokenFor($this->technician))
            ->postJson('/api/v1/maintenance-plans', $this->planPayload())
            ->assertForbidden();
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}

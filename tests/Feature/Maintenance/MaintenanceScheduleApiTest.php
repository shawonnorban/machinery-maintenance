<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\User;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * One concrete occurrence of a plan, over the API (API 9).
 */
class MaintenanceScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Asset $asset;

    private User $engineer;

    private string $scheduleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test');
        $this->asset = WorkOrderFixture::runningAsset($this->delta, $dhaka);

        $planId = $this->withToken($this->tokenFor($this->engineer))
            ->postJson('/api/v1/maintenance-plans', [
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
            ])
            ->json('data.id');

        $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/maintenance-plans/{$planId}/activate")
            ->assertOk();

        $this->scheduleId = $this->withToken($this->tokenFor($this->engineer))
            ->getJson('/api/v1/maintenance-schedules')
            ->json('data.0.id');
    }

    public function test_a_schedule_can_be_completed(): void
    {
        $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/maintenance-schedules/{$this->scheduleId}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'COMPLETED');

        // Completed leaves the open list empty: the 30-day rolling interval
        // measured from now falls outside the plan's 14-day lead time, so the
        // next occurrence is not generated until closer to its own due date.
        $this->withToken($this->tokenFor($this->engineer))
            ->getJson('/api/v1/maintenance-schedules')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_schedule_can_be_skipped_with_a_reason(): void
    {
        $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/maintenance-schedules/{$this->scheduleId}/skip", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('skipped_reason');

        $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/maintenance-schedules/{$this->scheduleId}/skip", [
                'skipped_reason' => 'Line stopped for order change',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'SKIPPED');
    }

    public function test_a_schedule_can_be_rescheduled_with_a_reason(): void
    {
        $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/maintenance-schedules/{$this->scheduleId}/reschedule", [
                'due_at' => '2026-03-01T00:00:00Z',
                'rescheduled_reason' => 'Waiting on an imported part',
            ])
            ->assertOk()
            ->assertJsonPath('data.rescheduled_reason', 'Waiting on an imported part');
    }

    public function test_the_list_can_be_filtered_to_one_plan(): void
    {
        $otherPlanId = $this->withToken($this->tokenFor($this->engineer))
            ->postJson('/api/v1/maintenance-plans', [
                'name' => 'Quarterly inspection',
                'asset_id' => $this->asset->id,
                'maintenance_type_id' => \App\Modules\Maintenance\Models\MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
                'trigger_type' => 'TIME',
                'schedule_mode' => 'ROLLING',
                'interval_value' => 90,
                'interval_unit' => 'DAY',
                'priority' => 'MEDIUM',
                'non_working_day_policy' => 'NEXT_WORKING_DAY',
                'start_date' => '2026-01-01',
            ])
            ->json('data.id');

        $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/maintenance-plans/{$otherPlanId}/activate")
            ->assertOk();

        $response = $this->withToken($this->tokenFor($this->engineer))
            ->getJson("/api/v1/maintenance-schedules?maintenance_plan_id={$otherPlanId}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame($otherPlanId, $response->json('data.0.maintenance_plan_id'));
    }

    public function test_counts_mirror_the_web_kpi_tiles(): void
    {
        $status = $this->withToken($this->tokenFor($this->engineer))
            ->getJson("/api/v1/maintenance-schedules/{$this->scheduleId}")
            ->json('data.status');

        $response = $this->withToken($this->tokenFor($this->engineer))
            ->getJson('/api/v1/maintenance-schedules/counts')
            ->assertOk();

        $this->assertSame($status === 'DUE' ? 1 : 0, $response->json('data.due'));
        $this->assertSame($status === 'PLANNED' ? 1 : 0, $response->json('data.planned'));

        $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/maintenance-schedules/{$this->scheduleId}/complete")
            ->assertOk();

        // Completed drops out of every bucket.
        $afterComplete = $this->withToken($this->tokenFor($this->engineer))
            ->getJson('/api/v1/maintenance-schedules/counts')
            ->assertOk();

        $this->assertSame(0, $afterComplete->json('data.due'));
        $this->assertSame(0, $afterComplete->json('data.planned'));
    }

    public function test_a_completed_schedule_cannot_be_completed_again(): void
    {
        $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/maintenance-schedules/{$this->scheduleId}/complete")
            ->assertOk();

        $this->withToken($this->tokenFor($this->engineer))
            ->postJson("/api/v1/maintenance-schedules/{$this->scheduleId}/complete")
            ->assertStatus(409);
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}

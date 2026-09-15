<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrder;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\User;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Actions\AssignTechnicians;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Actions\TransitionWorkOrder;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Whose hours a labor entry is logged against, over the API (SRS 13.2).
 *
 * A shared login across several technicians was the exact problem that
 * motivated `technicians_user_unique` and this: without deriving the
 * technician from who is actually logged in, a technician could pick any
 * name from the dropdown and post time under it. A manager still may, on a
 * technician's behalf — that path is unchanged.
 */
class WorkOrderLaborApiTest extends TestCase
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
        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
    }

    private function inProgressWorkOrder(Technician $technician): WorkOrder
    {
        $workOrder = app(CreateWorkOrder::class)->handle([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'title' => 'Monthly service',
        ], $this->manager->id);

        $workOrder = app(TransitionWorkOrder::class)->schedule($workOrder, $this->manager->id);
        $workOrder = app(AssignTechnicians::class)->handle($workOrder, [$technician->id], $this->manager->id);

        return app(TransitionWorkOrder::class)->start($workOrder, $this->manager->id);
    }

    public function test_a_technician_logging_their_own_hours_needs_no_technician_id(): void
    {
        $technicianUser = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech-login@delta.test', $this->dhaka->id);
        $technician = WorkOrderFixture::technician($this->delta, $this->dhaka, 'Karim Mia', 'EMP-1001', $technicianUser);
        $workOrder = $this->inProgressWorkOrder($technician);

        $this->withToken($this->tokenFor($technicianUser))
            ->postJson("/api/v1/work-orders/{$workOrder->id}/labor", [
                'started_at' => '2026-08-17 09:00:00',
                'ended_at' => '2026-08-17 10:00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.technician.id', $technician->id);
    }

    public function test_a_technician_cannot_log_hours_under_a_different_technicians_name(): void
    {
        $technicianUser = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech-login2@delta.test', $this->dhaka->id);
        $own = WorkOrderFixture::technician($this->delta, $this->dhaka, 'Karim Mia', 'EMP-1002', $technicianUser);
        $someoneElse = WorkOrderFixture::technician($this->delta, $this->dhaka, 'Abdul Karim', 'EMP-1003');
        $workOrder = $this->inProgressWorkOrder($own);

        // Submitting somebody else's id is silently ignored, not honoured —
        // the entry is still theirs, exactly as if the field were absent.
        $this->withToken($this->tokenFor($technicianUser))
            ->postJson("/api/v1/work-orders/{$workOrder->id}/labor", [
                'technician_id' => $someoneElse->id,
                'started_at' => '2026-08-17 09:00:00',
                'ended_at' => '2026-08-17 10:00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.technician.id', $own->id);
    }

    public function test_a_manager_may_log_hours_on_a_technicians_behalf(): void
    {
        $technician = WorkOrderFixture::technician($this->delta, $this->dhaka, 'Karim Mia', 'EMP-1004');
        $workOrder = $this->inProgressWorkOrder($technician);

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/work-orders/{$workOrder->id}/labor", [
                'technician_id' => $technician->id,
                'started_at' => '2026-08-17 09:00:00',
                'ended_at' => '2026-08-17 10:00:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.technician.id', $technician->id);
    }

    public function test_a_login_with_no_technician_record_cannot_log_hours(): void
    {
        $technicianUser = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech-login3@delta.test', $this->dhaka->id);
        $someoneElse = WorkOrderFixture::technician($this->delta, $this->dhaka, 'Abdul Karim', 'EMP-1005');
        $workOrder = $this->inProgressWorkOrder($someoneElse);

        $this->withToken($this->tokenFor($technicianUser))
            ->postJson("/api/v1/work-orders/{$workOrder->id}/labor", [
                'started_at' => '2026-08-17 09:00:00',
                'ended_at' => '2026-08-17 10:00:00',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.technician_id.0', __('work_order.labor_needs_technician'));
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}

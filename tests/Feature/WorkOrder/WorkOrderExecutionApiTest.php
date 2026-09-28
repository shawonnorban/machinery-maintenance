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
 * The assignable-technicians lookup reused for the labor form.
 */
class WorkOrderExecutionApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private Technician $technician;

    private User $technicianUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
        $this->technician = WorkOrderFixture::technician($this->delta, $this->dhaka);

        $this->technicianUser = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
    }

    private function inProgress(): WorkOrder
    {
        $workOrder = app(CreateWorkOrder::class)->handle([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'title' => 'Hook replacement',
        ], 'user-a');

        $workOrder = app(TransitionWorkOrder::class)->schedule($workOrder, 'user-a');
        app(AssignTechnicians::class)->handle($workOrder, [$this->technician->id], 'user-a');

        return app(TransitionWorkOrder::class)->start($workOrder->fresh(), 'user-a');
    }

    /**
     * TECHNICIAN holds `work_order.labor.manage` (to record their own time)
     * but not `work_order.work_order.assign` — the labor form's technician
     * picker reuses `assignableTechnicians()`, which was gated on `.assign`
     * alone before this and would have 403'd exactly this caller.
     */
    public function test_a_technician_can_reach_the_technician_list_for_their_own_labor_form(): void
    {
        $workOrder = $this->inProgress();

        $response = $this->withToken($this->tokenFor($this->technicianUser))
            ->getJson("/api/v1/work-orders/{$workOrder->id}/assignable-technicians")
            ->assertOk();

        $this->assertContains($this->technician->id, array_column($response->json('data'), 'id'));
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}

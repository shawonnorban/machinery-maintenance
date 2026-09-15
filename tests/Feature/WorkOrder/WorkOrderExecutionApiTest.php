<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrder;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Services\InventoryLedger;
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
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The execution surfaces added this pass: a direct issue with no prior
 * request (`issueDirect`, no web-web-API gap existed for the requested-then-
 * issued path, only for this one), and the assignable-technicians lookup
 * reused for the labor form.
 */
class WorkOrderExecutionApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private Bin $bin;

    private SparePart $part;

    private Technician $technician;

    private User $storeManager;

    private User $technicianUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
        $this->bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $this->part = InventoryFixture::part($this->delta);
        $this->technician = WorkOrderFixture::technician($this->delta, $this->dhaka);

        app(InventoryLedger::class)->post($this->part, $this->bin, 'RECEIPT', '20', '250');

        $this->storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'sm@delta.test');
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
     * Mirrors the web `WorkOrderPartsController::issue` — the store handing
     * a part over unprompted, no request line to attach to yet. The API's
     * `WorkOrderPartApiController` only ever had `store()` (request) and
     * `issue(workOrder, line)` (issue against an existing line) before this;
     * `IssuePartsToWorkOrder::issue()` already accepted a null `$line`, so
     * the gap was purely the missing route/controller method.
     */
    public function test_a_part_can_be_issued_directly_with_no_prior_request(): void
    {
        $workOrder = $this->inProgress();

        $this->withToken($this->tokenFor($this->storeManager))
            ->postJson("/api/v1/work-orders/{$workOrder->id}/parts/issue", [
                'spare_part_id' => $this->part->id,
                'bin_id' => $this->bin->id,
                'quantity' => 4,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ISSUED')
            ->assertJsonPath('data.quantity_issued', '4.0000');

        $this->withToken($this->tokenFor($this->storeManager))
            ->getJson("/api/v1/work-orders/{$workOrder->id}/parts")
            ->assertOk()
            ->assertJsonCount(1, 'data');
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

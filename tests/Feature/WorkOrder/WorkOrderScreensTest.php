<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrder;

use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\User;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Actions\AssignTechnicians;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Actions\TransitionWorkOrder;
use App\Modules\WorkOrder\Models\LaborRateGrade;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The work order screens over HTTP, including who is allowed to do what.
 */
class WorkOrderScreensTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private User $manager;

    private User $technicianUser;

    private Technician $technician;

    private LaborRateGrade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
        $this->grade = WorkOrderFixture::grade($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
        $this->technicianUser = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->technician = WorkOrderFixture::technician(
            $this->delta, $this->dhaka, $this->grade, 'Karim Mia', 'EMP-1001', $this->technicianUser,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'template_version_id' => '',
            'title' => 'Monthly lockstitch service',
            'description' => 'Routine preventive service.',
            'priority' => 'MEDIUM',
        ], $overrides);
    }

    private function existing(): WorkOrder
    {
        TenantFixture::actingAsTenant($this->delta);

        return app(CreateWorkOrder::class)->handle([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'title' => 'Monthly service',
        ], $this->manager->id);
    }

    public function test_the_listing_renders_and_shows_the_work_order(): void
    {
        $workOrder = $this->existing();

        $this->actingAs($this->manager)
            ->get('/app/work-orders?status=ALL')
            ->assertOk()
            ->assertSee($workOrder->work_order_number)
            ->assertSee($this->asset->asset_code);
    }

    public function test_a_work_order_can_be_raised_from_the_form(): void
    {
        $this->actingAs($this->manager)
            ->post('/app/work-orders', $this->payload())
            ->assertRedirect();

        $workOrder = WorkOrder::withoutGlobalScopes()
            ->where('title', 'Monthly lockstitch service')
            ->firstOrFail();

        // Raised as a draft: committing it to the queue is a deliberate second
        // step, so a half-written job never lands in someone's day.
        $this->assertSame('DRAFT', $workOrder->status);
        $this->assertSame($this->dhaka->id, $workOrder->factory_id);
    }

    public function test_the_detail_screen_renders(): void
    {
        $workOrder = $this->existing();

        $this->actingAs($this->manager)
            ->get("/app/work-orders/{$workOrder->id}")
            ->assertOk()
            ->assertSee($workOrder->work_order_number)
            ->assertSee(__('work_order.nobody_assigned'));
    }

    public function test_the_full_lifecycle_works_over_http(): void
    {
        $workOrder = $this->existing();

        $this->actingAs($this->manager)
            ->post("/app/work-orders/{$workOrder->id}/schedule")->assertRedirect();
        $this->assertSame('SCHEDULED', $workOrder->fresh()->status);

        $this->actingAs($this->manager)
            ->post("/app/work-orders/{$workOrder->id}/assign", [
                'technician_ids' => [$this->technician->id],
            ])->assertRedirect();
        $this->assertSame('ASSIGNED', $workOrder->fresh()->status);

        $this->actingAs($this->technicianUser)
            ->post("/app/work-orders/{$workOrder->id}/start")->assertRedirect();
        $this->assertSame('IN_PROGRESS', $workOrder->fresh()->status);

        $this->actingAs($this->technicianUser)
            ->post("/app/work-orders/{$workOrder->id}/hold", [
                'reason_code' => 'AWAITING_PARTS',
                'notes' => 'Bobbin case on order',
            ])->assertRedirect();
        $this->assertSame('ON_HOLD', $workOrder->fresh()->status);

        $this->actingAs($this->technicianUser)
            ->post("/app/work-orders/{$workOrder->id}/resume")->assertRedirect();

        $this->actingAs($this->technicianUser)
            ->post("/app/work-orders/{$workOrder->id}/complete")->assertRedirect();
        $this->assertSame('COMPLETED', $workOrder->fresh()->status);

        $this->actingAs($this->manager)
            ->post("/app/work-orders/{$workOrder->id}/close")->assertRedirect();
        $this->assertSame('CLOSED', $workOrder->fresh()->status);
    }

    public function test_a_hold_without_a_reason_code_is_rejected(): void
    {
        $workOrder = $this->existing();

        app(TransitionWorkOrder::class)->schedule($workOrder, $this->manager->id);
        app(AssignTechnicians::class)->handle($workOrder->fresh(), [$this->technician->id], $this->manager->id);
        app(TransitionWorkOrder::class)->start($workOrder->fresh(), $this->manager->id);

        $this->actingAs($this->technicianUser)
            ->post("/app/work-orders/{$workOrder->id}/hold", ['reason_code' => ''])
            ->assertSessionHasErrors('reason_code');
    }

    public function test_a_technician_cannot_raise_close_or_cancel_work(): void
    {
        $workOrder = $this->existing();

        // Raising, closing and cancelling are planning decisions, not floor
        // decisions.
        $this->actingAs($this->technicianUser)->get('/app/work-orders/create')->assertForbidden();
        $this->actingAs($this->technicianUser)->post('/app/work-orders', $this->payload())->assertForbidden();
        $this->actingAs($this->technicianUser)
            ->post("/app/work-orders/{$workOrder->id}/cancel", ['cancellation_reason' => 'no'])
            ->assertForbidden();
    }

    public function test_a_viewer_cannot_change_anything(): void
    {
        $viewer = TenantFixture::user($this->delta, 'VIEWER', 'viewer@delta.test');
        $workOrder = $this->existing();

        $this->actingAs($viewer)->get('/app/work-orders')->assertOk();
        $this->actingAs($viewer)->post("/app/work-orders/{$workOrder->id}/schedule")->assertForbidden();
        $this->actingAs($viewer)->post("/app/work-orders/{$workOrder->id}/start")->assertForbidden();
    }

    public function test_another_company_cannot_reach_this_work_order(): void
    {
        $workOrder = $this->existing();

        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        TenantFixture::factory($omega, 'Narayanganj Unit', 'NGJ');
        $intruder = TenantFixture::user($omega, 'COMPANY_OWNER', 'owner@omega.test');

        // 404, not 403. A 403 confirms the id is real, which is itself a leak
        // (ADR-057).
        $this->actingAs($intruder)
            ->get("/app/work-orders/{$workOrder->id}")
            ->assertNotFound();

        $this->actingAs($intruder)
            ->post("/app/work-orders/{$workOrder->id}/schedule")
            ->assertNotFound();
    }

    public function test_the_checklist_can_be_answered_over_http(): void
    {
        $version = WorkOrderFixture::publishedChecklist($this->delta, [
            ['label' => 'Needle condition', 'input_type' => 'PASS_FAIL', 'required' => true],
        ]);

        $workOrder = app(CreateWorkOrder::class)->handle([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'template_version_id' => $version->id,
            'title' => 'Monthly service',
        ], $this->manager->id);

        app(TransitionWorkOrder::class)->schedule($workOrder, $this->manager->id);
        app(AssignTechnicians::class)->handle($workOrder->fresh(), [$this->technician->id], $this->manager->id);
        app(TransitionWorkOrder::class)->start($workOrder->fresh(), $this->manager->id);

        $item = $version->items()->firstOrFail();

        $this->actingAs($this->technicianUser)
            ->post("/app/work-orders/{$workOrder->id}/checklist", [
                'checklist_item_id' => $item->id,
                'result' => 'PASS',
            ])
            ->assertRedirect();

        $this->assertSame(1, $workOrder->fresh()->checklistResults()->count());

        $this->actingAs($this->technicianUser)
            ->get("/app/work-orders/{$workOrder->id}")
            ->assertOk()
            ->assertSee('Needle condition');
    }

    public function test_labour_can_be_recorded_over_http_without_a_rate_field(): void
    {
        $workOrder = $this->existing();

        app(TransitionWorkOrder::class)->schedule($workOrder, $this->manager->id);
        app(AssignTechnicians::class)->handle($workOrder->fresh(), [$this->technician->id], $this->manager->id);
        app(TransitionWorkOrder::class)->start($workOrder->fresh(), $this->manager->id);

        $this->actingAs($this->technicianUser)
            ->post("/app/work-orders/{$workOrder->id}/labor", [
                'technician_id' => $this->technician->id,
                'labor_category' => 'REGULAR',
                'started_at' => '2026-08-17T09:00',
                'ended_at' => '2026-08-17T11:00',
                // Sent, and ignored: only EXTERNAL labour carries a supplied
                // rate. Otherwise anyone could set what the work cost.
                'hourly_rate' => '99999',
            ])
            ->assertRedirect();

        $entry = $workOrder->fresh()->laborEntries()->firstOrFail();

        $this->assertSame('120.0000', $entry->hourly_rate);
        $this->assertSame('240.0000', $workOrder->fresh()->actual_labor_cost);
    }

    public function test_a_technician_does_not_see_the_cost_panel(): void
    {
        $workOrder = $this->existing();

        // Recording your own time should not mean being shown what the job cost
        // (SRS 25.1).
        $this->actingAs($this->technicianUser)
            ->get("/app/work-orders/{$workOrder->id}")
            ->assertOk()
            ->assertDontSee(__('work_order.cost'));

        $this->actingAs($this->manager)
            ->get("/app/work-orders/{$workOrder->id}")
            ->assertOk()
            ->assertSee(__('work_order.cost'));
    }

    public function test_my_work_lists_only_this_technicians_assignments(): void
    {
        $mine = $this->existing();
        $theirs = app(CreateWorkOrder::class)->handle([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'title' => 'Somebody else’s job',
        ], $this->manager->id);

        $other = WorkOrderFixture::technician(
            $this->delta, $this->dhaka, $this->grade, 'Jahangir Alam', 'EMP-2001',
        );

        app(TransitionWorkOrder::class)->schedule($mine, $this->manager->id);
        app(AssignTechnicians::class)->handle($mine->fresh(), [$this->technician->id], $this->manager->id);

        app(TransitionWorkOrder::class)->schedule($theirs, $this->manager->id);
        app(AssignTechnicians::class)->handle($theirs->fresh(), [$other->id], $this->manager->id);

        $this->actingAs($this->technicianUser)
            ->get('/app/my-work')
            ->assertOk()
            ->assertSee($mine->work_order_number)
            ->assertDontSee($theirs->work_order_number);
    }

    public function test_my_work_says_so_when_the_account_is_not_a_technician(): void
    {
        // An empty list would read as a bug. A manager simply has no queue.
        $this->actingAs($this->manager)
            ->get('/app/my-work')
            ->assertOk()
            ->assertSee(__('work_order.not_a_technician'));
    }

    public function test_per_page_is_capped(): void
    {
        $this->existing();

        // An unbounded page size is a denial of service handed to the client
        // (API 35.3 rule 4).
        $this->actingAs($this->manager)
            ->get('/app/work-orders?per_page=100000')
            ->assertOk();

        $this->assertSame(
            100,
            $this->actingAs($this->manager)
                ->get('/app/work-orders?per_page=100000')
                ->viewData('workOrders')
                ->perPage(),
        );
    }
}

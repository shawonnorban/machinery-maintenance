<?php

declare(strict_types=1);

namespace Tests\Feature\Approval;

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalWorkflow;
use App\Modules\Asset\Models\Asset;
use App\Modules\Costing\Models\CostCategory;
use App\Modules\Costing\Models\CostEntry;
use App\Modules\Costing\Services\CostPoster;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Actions\TransitionWorkOrder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The approval and cost screens over HTTP.
 */
class ApprovalScreensTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private User $engineer;

    private User $maintenanceManager;

    private User $factoryManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
        $this->asset->forceFill(['acquisition_cost' => '285000'])->save();

        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'eng@delta.test');
        $this->maintenanceManager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
        $this->factoryManager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');

        $this->workflow();
    }

    private function workflow(): void
    {
        TenantFixture::actingAsTenant($this->delta);

        $workflow = ApprovalWorkflow::create([
            'name' => 'Work order approval',
            'entity_type' => 'WORK_ORDER',
            'active' => true,
        ]);

        ApprovalRule::create([
            'workflow_id' => $workflow->id,
            'sequence' => 1,
            'role_id' => Role::whereNull('company_id')->where('code', 'MAINTENANCE_MANAGER')->firstOrFail()->id,
            'name' => 'Maintenance manager',
            'condition_json' => ['min_cost' => '20000'],
        ]);
    }

    private function pendingRequest(?User $raisedBy = null): ApprovalRequest
    {
        TenantFixture::actingAsTenant($this->delta);
        $raisedBy ??= $this->engineer;

        $workOrder = app(CreateWorkOrder::class)->handle([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'title' => 'Head overhaul',
            'estimated_labor_cost' => '60000',
        ], $raisedBy->id);

        app(TransitionWorkOrder::class)->schedule($workOrder, $raisedBy->id);

        return ApprovalRequest::firstOrFail();
    }

    public function test_the_queue_renders_and_shows_the_frozen_cost(): void
    {
        $this->pendingRequest();

        $this->actingAs($this->maintenanceManager)
            ->get('/app/approvals')
            ->assertOk()
            ->assertSee(__('approval.pending_for_me'))
            ->assertSee('60,000.00');
    }

    public function test_the_detail_screen_offers_a_decision_to_the_right_person(): void
    {
        $request = $this->pendingRequest();

        $this->actingAs($this->maintenanceManager)
            ->get("/app/approvals/{$request->id}")
            ->assertOk()
            ->assertSee(__('approval.approve'))
            ->assertSee(__('approval.context_hint'));
    }

    public function test_the_screen_does_not_offer_a_decision_to_the_requester(): void
    {
        // Raised by the manager themselves, which is the real self-approval
        // case: an engineer cannot open this screen at all.
        $request = $this->pendingRequest($this->maintenanceManager);

        // Rendering an approve button that returns 403 teaches people to
        // distrust the screen (Frontend 3.4).
        $this->actingAs($this->maintenanceManager)
            ->get("/app/approvals/{$request->id}")
            ->assertOk()
            ->assertSee(__('approval.cannot_approve_own_request'))
            ->assertDontSee(__('approval.rejection_needs_reason'));
    }

    public function test_approving_over_http_completes_the_request(): void
    {
        $request = $this->pendingRequest();

        $this->actingAs($this->maintenanceManager)
            ->post("/app/approvals/{$request->id}/approve", ['comment' => 'Worth keeping'])
            ->assertRedirect();

        $this->assertSame('APPROVED', $request->fresh()->status);
        $this->assertSame($this->maintenanceManager->id, $request->fresh()->actions->last()->approver_id);
    }

    public function test_the_requester_cannot_approve_over_http(): void
    {
        $request = $this->pendingRequest();

        $this->actingAs($this->engineer)
            ->post("/app/approvals/{$request->id}/approve")
            ->assertForbidden();

        $this->assertSame('PENDING', $request->fresh()->status);
    }

    public function test_rejecting_over_http_needs_a_comment(): void
    {
        $request = $this->pendingRequest();

        $this->actingAs($this->maintenanceManager)
            ->post("/app/approvals/{$request->id}/reject", [])
            ->assertSessionHasErrors('comment');

        $this->actingAs($this->maintenanceManager)
            ->post("/app/approvals/{$request->id}/reject", ['comment' => 'Get a vendor quote first'])
            ->assertRedirect();

        $this->assertSame('REJECTED', $request->fresh()->status);
    }

    public function test_a_technician_cannot_reach_the_approvals_queue(): void
    {
        $technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->actingAs($technician)->get('/app/approvals')->assertForbidden();
    }

    public function test_another_company_cannot_reach_this_request(): void
    {
        $request = $this->pendingRequest();

        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        TenantFixture::factory($omega, 'Narayanganj Unit', 'NGJ');
        $intruder = TenantFixture::user($omega, 'COMPANY_OWNER', 'owner@omega.test');

        // 404, not 403: a 403 confirms the id names a real request.
        $this->actingAs($intruder)
            ->get("/app/approvals/{$request->id}")
            ->assertNotFound();
    }

    public function test_the_asset_cost_screen_renders_the_lifetime_figure(): void
    {
        app(CostPoster::class)->post([
            'asset_id' => $this->asset->id,
            'cost_category_id' => CostCategory::where('code', 'VENDOR')->firstOrFail()->id,
            'amount' => '18500',
            'source_type' => 'VENDOR',
            'description' => 'Head overhaul by the service centre',
        ], $this->maintenanceManager->id);

        $this->actingAs($this->maintenanceManager)
            ->get("/app/assets/{$this->asset->id}/costs")
            ->assertOk()
            ->assertSee(__('cost.lifetime_total'))
            ->assertSee(__('cost.spend_against_value'))
            // Stated on the screen, so nobody reads the figure as a book value.
            ->assertSee(__('cost.depreciation_note'))
            ->assertSee('18,500');
    }

    public function test_a_cost_can_be_posted_over_http(): void
    {
        $this->actingAs($this->maintenanceManager)
            ->post('/app/costs', [
                'asset_id' => $this->asset->id,
                'cost_category_id' => CostCategory::where('code', 'TRANSPORT')->firstOrFail()->id,
                'amount' => '2400',
                'currency' => 'BDT',
                'source_type' => 'TRANSPORT',
                'description' => 'Carried to the service centre',
            ])
            ->assertRedirect();

        $this->assertSame('2400.0000', (string) CostEntry::firstOrFail()->amount);
    }

    public function test_a_derived_source_type_is_refused_by_the_form(): void
    {
        // The form does not offer labour or parts, and the endpoint refuses
        // them too: posting one by hand would charge the machine twice.
        $this->actingAs($this->maintenanceManager)
            ->post('/app/costs', [
                'asset_id' => $this->asset->id,
                'cost_category_id' => CostCategory::where('code', 'LABOR')->firstOrFail()->id,
                'amount' => '5000',
                'currency' => 'BDT',
                'source_type' => 'LABOR',
            ])
            ->assertSessionHasErrors('source_type');
    }

    public function test_only_a_factory_manager_may_reverse_a_posted_cost(): void
    {
        $entry = app(CostPoster::class)->post([
            'asset_id' => $this->asset->id,
            'cost_category_id' => CostCategory::where('code', 'VENDOR')->firstOrFail()->id,
            'amount' => '18500',
            'source_type' => 'VENDOR',
        ], $this->maintenanceManager->id);

        // Undoing a posted cost changes a figure somebody has already reported,
        // so it does not ride on the permission that created it.
        $this->actingAs($this->maintenanceManager)
            ->post("/app/costs/{$entry->id}/reverse", ['reason' => 'Wrong machine'])
            ->assertForbidden();

        $this->actingAs($this->factoryManager)
            ->post("/app/costs/{$entry->id}/reverse", ['reason' => 'Invoiced to the wrong machine'])
            ->assertRedirect();

        $this->assertSame(2, CostEntry::count());
        $this->assertSame('-18500.0000', (string) CostEntry::where('is_reversal', true)->firstOrFail()->amount);
    }

    public function test_a_viewer_may_read_costs_but_not_change_them(): void
    {
        $viewer = TenantFixture::user($this->delta, 'VIEWER', 'viewer@delta.test');

        // A viewer holds every read permission by design, costs included. The
        // boundary that matters is that nothing they do writes.
        $this->actingAs($viewer)
            ->get("/app/assets/{$this->asset->id}/costs")
            ->assertOk();

        $this->actingAs($viewer)
            ->post('/app/costs', [
                'asset_id' => $this->asset->id,
                'cost_category_id' => CostCategory::where('code', 'TRANSPORT')->firstOrFail()->id,
                'amount' => '1000',
                'currency' => 'BDT',
                'source_type' => 'TRANSPORT',
            ])
            ->assertForbidden();

        $this->assertSame(0, CostEntry::count());
    }
}

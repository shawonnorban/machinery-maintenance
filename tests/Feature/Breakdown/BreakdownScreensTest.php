<?php

declare(strict_types=1);

namespace Tests\Feature\Breakdown;

use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Breakdown\Actions\TransitionBreakdown;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\FailureCode;
use App\Modules\Breakdown\Models\RootCause;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The breakdown screens over HTTP, including who is allowed to do what.
 */
class BreakdownScreensTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private User $manager;

    private User $technicianUser;

    private Technician $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $this->manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
        $this->technicianUser = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->technician = WorkOrderFixture::technician(
            $this->delta, $this->dhaka, 'Karim Mia', 'EMP-1001', $this->technicianUser,
        );
    }

    private function existing(): Breakdown
    {
        TenantFixture::actingAsTenant($this->delta);

        return app(ReportBreakdown::class)->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Motor hums but shaft does not turn',
        ], $this->manager->id);
    }

    public function test_the_listing_renders(): void
    {
        $breakdown = $this->existing();

        $this->actingAs($this->manager)
            ->get('/app/breakdowns')
            ->assertOk()
            ->assertSee($breakdown->breakdown_number)
            ->assertSee($this->asset->asset_code);
    }

    public function test_a_breakdown_can_be_reported_from_the_form(): void
    {
        $this->actingAs($this->technicianUser)
            ->post('/app/breakdowns', [
                'asset_id' => $this->asset->id,
                'problem_description' => 'Needle keeps breaking on thick seams',
            ])
            ->assertRedirect();

        $breakdown = Breakdown::withoutGlobalScopes()->firstOrFail();

        // A technician can report. Reporting is deliberately the lowest-friction
        // thing in the product.
        $this->assertSame('REPORTED', $breakdown->status);
        $this->assertSame($this->dhaka->id, $breakdown->factory_id);
    }

    public function test_the_report_form_needs_only_a_machine_and_a_description(): void
    {
        $this->actingAs($this->technicianUser)
            ->post('/app/breakdowns', ['asset_id' => $this->asset->id])
            ->assertSessionHasErrors('problem_description');

        $this->actingAs($this->technicianUser)
            ->post('/app/breakdowns', ['problem_description' => 'Something broke'])
            ->assertSessionHasErrors('asset_id');
    }

    public function test_the_detail_screen_renders_the_chain_and_the_downtime(): void
    {
        $breakdown = $this->existing();

        $this->actingAs($this->manager)
            ->get("/app/breakdowns/{$breakdown->id}")
            ->assertOk()
            ->assertSee($breakdown->breakdown_number)
            ->assertSee(__('breakdown.chain'))
            ->assertSee(__('breakdown.failure_at'))
            ->assertSee(__('breakdown.production_resumed_at'))
            // The basis is stated on screen, not left implicit.
            ->assertSee(__('breakdown.calculation_basis'));
    }

    public function test_the_full_lifecycle_works_over_http(): void
    {
        $breakdown = $this->existing();

        $this->actingAs($this->manager)
            ->post("/app/breakdowns/{$breakdown->id}/acknowledge")->assertRedirect();
        $this->assertSame('ACKNOWLEDGED', $breakdown->fresh()->status);

        $this->actingAs($this->manager)
            ->post("/app/breakdowns/{$breakdown->id}/assign", [
                'assigned_technician_id' => $this->technician->id,
            ])->assertRedirect();
        $this->assertSame('ASSIGNED', $breakdown->fresh()->status);

        $this->actingAs($this->technicianUser)
            ->post("/app/breakdowns/{$breakdown->id}/start-repair")->assertRedirect();
        $this->assertSame('IN_REPAIR', $breakdown->fresh()->status);

        $this->actingAs($this->technicianUser)
            ->post("/app/breakdowns/{$breakdown->id}/hold", [
                'reason_code' => 'AWAITING_PARTS',
                'notes' => 'Bearing on order',
            ])->assertRedirect();
        $this->assertSame('ON_HOLD', $breakdown->fresh()->status);

        $this->actingAs($this->technicianUser)
            ->post("/app/breakdowns/{$breakdown->id}/resume")->assertRedirect();

        $this->actingAs($this->technicianUser)
            ->post("/app/breakdowns/{$breakdown->id}/complete-repair")->assertRedirect();
        $this->assertSame('REPAIRED', $breakdown->fresh()->status);

        $this->actingAs($this->technicianUser)
            ->post("/app/breakdowns/{$breakdown->id}/resume-production")->assertRedirect();
        $this->assertSame('PRODUCTION_RESUMED', $breakdown->fresh()->status);

        $this->actingAs($this->manager)
            ->post("/app/breakdowns/{$breakdown->id}/close", [
                'failure_code_id' => FailureCode::where('code', 'BEARING_FAILURE')->firstOrFail()->id,
                'root_cause_id' => RootCause::where('code', 'NORMAL_WEAR')->firstOrFail()->id,
            ])->assertRedirect();

        $this->assertSame('CLOSED', $breakdown->fresh()->status);
        $this->assertSame('RUNNING', Asset::find($this->asset->id)->status);
    }

    public function test_closing_without_a_cause_is_rejected_by_the_form(): void
    {
        $breakdown = $this->existing();
        $transition = app(TransitionBreakdown::class);

        $transition->acknowledge($breakdown, $this->manager->id);
        $transition->startRepair($breakdown->fresh(), $this->manager->id);
        $transition->completeRepair($breakdown->fresh(), $this->manager->id);

        $this->actingAs($this->manager)
            ->post("/app/breakdowns/{$breakdown->id}/close", [])
            ->assertSessionHasErrors(['failure_code_id', 'root_cause_id']);

        $this->assertSame('REPAIRED', $breakdown->fresh()->status);
    }

    public function test_raising_repair_work_from_a_breakdown_links_both_ways(): void
    {
        $breakdown = $this->existing();

        $this->actingAs($this->manager)
            ->post("/app/breakdowns/{$breakdown->id}/work-order")
            ->assertRedirect();

        $workOrder = WorkOrder::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($breakdown->id, $workOrder->breakdown_id);

        $this->actingAs($this->manager)
            ->get("/app/breakdowns/{$breakdown->id}")
            ->assertOk()
            ->assertSee($workOrder->work_order_number);
    }

    public function test_production_impact_is_recorded_in_pieces(): void
    {
        $breakdown = $this->existing();

        $this->actingAs($this->technicianUser)
            ->post("/app/breakdowns/{$breakdown->id}/impact", [
                'estimated_loss' => '420',
                'actual_loss' => '385',
            ])
            ->assertRedirect();

        $impact = $breakdown->fresh()->productionImpacts()->firstOrFail();

        $this->assertSame('PIECES', $impact->unit);
        $this->assertSame('385.0000', $impact->actual_loss);
    }

    public function test_a_viewer_cannot_change_anything(): void
    {
        $viewer = TenantFixture::user($this->delta, 'VIEWER', 'viewer@delta.test');
        $breakdown = $this->existing();

        $this->actingAs($viewer)->get('/app/breakdowns')->assertOk();
        $this->actingAs($viewer)->post("/app/breakdowns/{$breakdown->id}/acknowledge")->assertForbidden();
        $this->actingAs($viewer)->post("/app/breakdowns/{$breakdown->id}/start-repair")->assertForbidden();
    }

    public function test_a_technician_cannot_close_a_breakdown(): void
    {
        $breakdown = $this->existing();

        // Closing records the cause and ends the compliance trail; that is a
        // maintenance-management decision, not a floor one.
        $this->actingAs($this->technicianUser)
            ->post("/app/breakdowns/{$breakdown->id}/close", [
                'failure_code_id' => FailureCode::where('code', 'UNKNOWN')->firstOrFail()->id,
                'root_cause_id' => RootCause::where('code', 'UNDETERMINED')->firstOrFail()->id,
            ])
            ->assertForbidden();
    }

    public function test_another_company_cannot_reach_this_breakdown(): void
    {
        $breakdown = $this->existing();

        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        TenantFixture::factory($omega, 'Narayanganj Unit', 'NGJ');
        $intruder = TenantFixture::user($omega, 'COMPANY_OWNER', 'owner@omega.test');

        // 404, not 403. A 403 confirms the id is real, which is itself a leak
        // (ADR-057).
        $this->actingAs($intruder)
            ->get("/app/breakdowns/{$breakdown->id}")
            ->assertNotFound();

        $this->actingAs($intruder)
            ->post("/app/breakdowns/{$breakdown->id}/acknowledge")
            ->assertNotFound();
    }

    public function test_per_page_is_capped(): void
    {
        $this->existing();

        $this->assertSame(
            100,
            $this->actingAs($this->manager)
                ->get('/app/breakdowns?per_page=100000')
                ->viewData('breakdowns')
                ->perPage(),
        );
    }
}

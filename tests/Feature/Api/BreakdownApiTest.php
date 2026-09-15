<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Breakdown\Actions\TransitionBreakdown;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\DowntimeReasonCode;
use App\Modules\Breakdown\Models\FailureCode;
use App\Modules\Breakdown\Models\RootCause;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\WorkOrder\Actions\TransitionWorkOrder;
use App\Modules\WorkOrder\Models\Technician;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The Breakdown API (docs/03-API-Specification.md §12). `close`, the
 * `update` timestamp-correction endpoint, `downtime` and `root-cause` are
 * new this round; the rest was already built and tested — this suite
 * covers the four additions plus the version echoed on every response.
 */
class BreakdownApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $factory;

    private Asset $asset;

    private Technician $technician;

    private User $manager;

    private string $managerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);
        $this->technician = WorkOrderFixture::technician($this->delta, $this->factory);

        $this->manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'manager@delta.test');
        $this->managerToken = app(IssueApiToken::class)->forUser($this->manager, $this->delta->id, 'x')['plain'];
    }

    private function asManager(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->managerToken);

        return $this;
    }

    private function reported(): Breakdown
    {
        return app(ReportBreakdown::class)->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Machine stops mid-seam, motor hums but shaft does not turn',
        ], $this->manager->id);
    }

    private function inRepair(): Breakdown
    {
        $breakdown = $this->reported();
        $transition = app(TransitionBreakdown::class);

        $transition->acknowledge($breakdown, $this->manager->id);
        $transition->assign($breakdown->fresh(), $this->technician->id, $this->manager->id);

        return $transition->startRepair($breakdown->fresh(), $this->manager->id);
    }

    public function test_counts_mirror_the_web_kpi_tiles(): void
    {
        $this->reported(); // REPORTED: open + unacknowledged.
        $inRepair = $this->inRepair(); // IN_REPAIR: open + in_repair.

        $repaired = $this->inRepair();
        $this->asManager()->postJson("/api/v1/breakdowns/{$repaired->id}/complete-repair")->assertOk();
        // REPAIRED: awaiting_closure, but not "open" — Breakdown::OPEN_STATUSES excludes it.

        $closed = $this->inRepair();
        $this->asManager()->postJson("/api/v1/breakdowns/{$closed->id}/complete-repair")->assertOk();
        $this->asManager()->postJson("/api/v1/breakdowns/{$closed->id}/close", [
            'failure_code_id' => FailureCode::where('code', 'BEARING_FAILURE')->firstOrFail()->id,
            'root_cause_id' => RootCause::where('code', 'INADEQUATE_LUBRICATION')->firstOrFail()->id,
        ])->assertOk();
        // CLOSED: not counted anywhere in this response.

        $response = $this->asManager()->getJson('/api/v1/breakdowns/counts')->assertOk();

        $this->assertSame(2, $response->json('data.open'));
        $this->assertSame(1, $response->json('data.unacknowledged'));
        $this->assertSame(1, $response->json('data.in_repair'));
        $this->assertSame(1, $response->json('data.awaiting_closure'));
    }

    public function test_create_form_options_serves_production_lines_and_reason_codes(): void
    {
        $department = Department::create([
            'company_id' => $this->delta->id, 'factory_id' => $this->factory->id, 'name' => 'Sewing', 'code' => 'SEW',
        ]);
        $line = ProductionLine::create([
            'company_id' => $this->delta->id, 'department_id' => $department->id, 'name' => 'Line 3', 'code' => 'L3',
        ]);
        $reason = DowntimeReasonCode::create([
            'company_id' => $this->delta->id,
            'code' => 'AWAITING_PARTS',
            'name' => 'Awaiting parts',
            'downtime_class' => 'UNPLANNED',
            'counts_against_availability' => true,
            'active' => true,
        ]);

        $response = $this->asManager()->getJson('/api/v1/breakdowns/create-form-options')->assertOk();

        $response->assertJsonStructure(['data' => ['assets', 'production_lines', 'failure_codes', 'reason_codes']]);
        $this->assertContains($this->asset->id, array_column($response->json('data.assets'), 'id'));
        $this->assertContains($line->id, array_column($response->json('data.production_lines'), 'id'));
        $this->assertContains(
            FailureCode::where('code', 'BEARING_FAILURE')->firstOrFail()->id,
            array_column($response->json('data.failure_codes'), 'id'),
        );
        $this->assertContains($reason->id, array_column($response->json('data.reason_codes'), 'id'));
    }

    public function test_closing_requires_a_failure_code_and_root_cause(): void
    {
        $breakdown = $this->inRepair();

        $this->asManager()->postJson("/api/v1/breakdowns/{$breakdown->id}/complete-repair")->assertOk();

        $this->asManager()->postJson("/api/v1/breakdowns/{$breakdown->id}/close", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['failure_code_id', 'root_cause_id']);

        $close = $this->asManager()->postJson("/api/v1/breakdowns/{$breakdown->id}/close", [
            'failure_code_id' => FailureCode::where('code', 'BEARING_FAILURE')->firstOrFail()->id,
            'root_cause_id' => RootCause::where('code', 'INADEQUATE_LUBRICATION')->firstOrFail()->id,
            'corrective_action' => 'Replaced bearing and relubricated',
        ])->assertOk();

        $this->assertSame('CLOSED', $close->json('data.status'));
        $this->assertSame('Replaced bearing and relubricated', $close->json('data.corrective_action'));
        $this->assertNotNull($close->json('data.root_cause'));
    }

    public function test_closing_without_a_repair_completed_timestamp_is_refused(): void
    {
        $breakdown = $this->inRepair();

        $this->asManager()->postJson("/api/v1/breakdowns/{$breakdown->id}/close", [
            'failure_code_id' => FailureCode::where('code', 'BEARING_FAILURE')->firstOrFail()->id,
            'root_cause_id' => RootCause::where('code', 'INADEQUATE_LUBRICATION')->firstOrFail()->id,
        ])->assertStatus(422)->assertJsonValidationErrors('repair_completed_at');
    }

    public function test_a_timestamp_is_corrected_without_changing_status(): void
    {
        $breakdown = $this->reported();
        $backdated = $breakdown->failure_at->subHours(2);

        $response = $this->asManager()->patchJson("/api/v1/breakdowns/{$breakdown->id}", [
            'field' => 'failure_at',
            'value' => $backdated->toIso8601String(),
        ])->assertOk();

        $this->assertSame('REPORTED', $response->json('data.status'));
        // toIso8601String() truncates to whole seconds, so the round trip
        // through the request body loses whatever sub-second precision the
        // in-memory value carried — compare at that same resolution.
        $this->assertTrue($backdated->startOfSecond()->equalTo($breakdown->fresh()->failure_at->startOfSecond()));
    }

    public function test_an_unknown_timestamp_field_is_refused(): void
    {
        $breakdown = $this->reported();

        $this->asManager()->patchJson("/api/v1/breakdowns/{$breakdown->id}", [
            'field' => 'closed_at',
            'value' => now()->toIso8601String(),
        ])->assertStatus(422);
    }

    public function test_downtime_is_read_after_the_breakdown_is_closed(): void
    {
        $breakdown = $this->inRepair();
        $this->asManager()->postJson("/api/v1/breakdowns/{$breakdown->id}/complete-repair")->assertOk();
        $this->asManager()->postJson("/api/v1/breakdowns/{$breakdown->id}/resume-production")->assertOk();

        $downtime = $this->asManager()->getJson("/api/v1/breakdowns/{$breakdown->id}/downtime")->assertOk();

        $this->assertIsInt($downtime->json('data.total_downtime_minutes'));
        $this->assertNotNull($downtime->json('data.response_minutes'));
    }

    public function test_root_cause_reflects_the_recorded_closure_analysis(): void
    {
        $breakdown = $this->inRepair();
        $this->asManager()->postJson("/api/v1/breakdowns/{$breakdown->id}/complete-repair")->assertOk();

        $before = $this->asManager()->getJson("/api/v1/breakdowns/{$breakdown->id}/root-cause")->assertOk();
        $this->assertNull($before->json('data.root_cause'));

        $this->asManager()->postJson("/api/v1/breakdowns/{$breakdown->id}/close", [
            'failure_code_id' => FailureCode::where('code', 'BEARING_FAILURE')->firstOrFail()->id,
            'root_cause_id' => RootCause::where('code', 'INADEQUATE_LUBRICATION')->firstOrFail()->id,
            'preventive_action' => 'Add lubrication to the weekly PM checklist',
        ])->assertOk();

        $after = $this->asManager()->getJson("/api/v1/breakdowns/{$breakdown->id}/root-cause")->assertOk();
        $this->assertSame('Add lubrication to the weekly PM checklist', $after->json('data.preventive_action'));
        $this->assertNotNull($after->json('data.root_cause.id'));
    }

    public function test_arrival_is_recorded_separately_from_repair_start(): void
    {
        $breakdown = $this->reported();
        $transition = app(TransitionBreakdown::class);
        $transition->acknowledge($breakdown, $this->manager->id);
        $transition->assign($breakdown->fresh(), $this->technician->id, $this->manager->id);

        $response = $this->asManager()->postJson("/api/v1/breakdowns/{$breakdown->id}/arrive")->assertOk();

        $this->assertNotNull($response->json('data.timestamps.technician_arrival_at'));
        $this->assertNull($response->json('data.timestamps.repair_started_at'));
    }

    /**
     * The guard found live: clicking "Raise work order" three times raised
     * three work orders against one breakdown. A second one is legitimate
     * once the first has actually closed (or been cancelled) — not while
     * it's still open, which is what the next test covers.
     */
    public function test_a_second_work_order_can_be_raised_once_the_first_is_no_longer_open(): void
    {
        $breakdown = $this->inRepair();
        $first = $breakdown->fresh()->activeWorkOrder();
        $this->assertNotNull($first);

        app(TransitionWorkOrder::class)->cancel($first, $this->manager->id, 'Wrong part ordered');

        $response = $this->asManager()->postJson("/api/v1/breakdowns/{$breakdown->id}/work-order")->assertCreated();

        $this->assertNotNull($response->json('data.work_order_number'));
        $this->assertNotSame($first->id, $response->json('data.id'));
        $this->assertSame('DRAFT', $response->json('data.status'));
    }

    public function test_a_second_work_order_is_refused_while_the_first_is_still_open(): void
    {
        $breakdown = $this->inRepair();
        $first = $breakdown->fresh()->activeWorkOrder();
        $this->assertNotNull($first);

        $response = $this->asManager()->postJson("/api/v1/breakdowns/{$breakdown->id}/work-order")
            ->assertStatus(409);

        $this->assertStringContainsString($first->work_order_number, $response->json('message'));
    }

    public function test_a_work_order_cannot_be_raised_against_a_closed_breakdown(): void
    {
        $breakdown = $this->inRepair();
        $this->asManager()->postJson("/api/v1/breakdowns/{$breakdown->id}/complete-repair")->assertOk();
        $this->asManager()->postJson("/api/v1/breakdowns/{$breakdown->id}/close", [
            'failure_code_id' => FailureCode::where('code', 'BEARING_FAILURE')->firstOrFail()->id,
            'root_cause_id' => RootCause::where('code', 'INADEQUATE_LUBRICATION')->firstOrFail()->id,
        ])->assertOk();

        $this->asManager()->postJson("/api/v1/breakdowns/{$breakdown->id}/work-order")->assertStatus(409);
    }

    /**
     * The full chain over HTTP: Manager acknowledges, raises a work order
     * (which puts the line's whole roster on it — `RaiseBreakdownWorkOrder::
     * assignRoster()`), and one of those roster technicians starts it —
     * that single `POST /work-orders/{id}/start` call is what moves the
     * breakdown itself from ACKNOWLEDGED to IN_REPAIR now (`WorkOrderApi
     * Controller::start()`'s own sync), not a separate "Start repair" call
     * on the breakdown.
     */
    public function test_starting_the_linked_work_order_advances_the_breakdown_to_in_repair(): void
    {
        $department = Department::create([
            'company_id' => $this->delta->id, 'factory_id' => $this->factory->id, 'name' => 'Sewing', 'code' => 'SEW2',
        ]);
        $line = ProductionLine::create([
            'company_id' => $this->delta->id, 'department_id' => $department->id, 'name' => 'Line 9', 'code' => 'L9',
        ]);
        AssetLocation::find($this->asset->asset_location_id)->forceFill([
            'department_id' => $department->id,
            'production_line_id' => $line->id,
        ])->save();

        $rosterUser = TenantFixture::user($this->delta, 'TECHNICIAN', 'roster@delta.test', $this->factory->id);
        WorkOrderFixture::technician(
            $this->delta, $this->factory, 'Roster Tech', 'EMP-ROSTER', $rosterUser, null, $department->id, $line->id,
        );
        $rosterToken = app(IssueApiToken::class)->forUser($rosterUser, $this->delta->id, 'x')['plain'];

        $breakdown = $this->reported();
        $this->asManager()->postJson("/api/v1/breakdowns/{$breakdown->id}/acknowledge")->assertOk();

        $raised = $this->asManager()->postJson("/api/v1/breakdowns/{$breakdown->id}/work-order")->assertCreated();
        $workOrderId = $raised->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$rosterToken)
            ->postJson("/api/v1/work-orders/{$workOrderId}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'IN_PROGRESS');

        $breakdown = $this->asManager()->getJson("/api/v1/breakdowns/{$breakdown->id}")->assertOk();

        $this->assertSame('IN_REPAIR', $breakdown->json('data.status'));
        $this->assertSame('Roster Tech', $breakdown->json('data.assigned_technician'));
        $this->assertNotNull($breakdown->json('data.timestamps.repair_started_at'));
    }

    /**
     * Reporting is Line Chief's job now, not the repair technician's (a
     * floor supervisor is standing next to the machine when it stops; the
     * technician who fixes it self-claims by starting the linked work
     * order instead — see the test above). `TECHNICIAN` lost `breakdown.
     * breakdown.create` in `RoleSeeder` for exactly this.
     */
    public function test_a_technician_can_no_longer_report_a_breakdown(): void
    {
        $user = TenantFixture::user($this->delta, 'TECHNICIAN', 'floortech@delta.test', $this->factory->id);
        $token = app(IssueApiToken::class)->forUser($user, $this->delta->id, 'x')['plain'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/breakdowns', [
                'asset_id' => $this->asset->id,
                'problem_description' => 'Machine stops mid-seam',
            ])
            ->assertForbidden();
    }

    public function test_a_line_chief_can_report_a_breakdown(): void
    {
        $user = TenantFixture::user($this->delta, 'LINE_CHIEF', 'linechief@delta.test', $this->factory->id);
        $token = app(IssueApiToken::class)->forUser($user, $this->delta->id, 'x')['plain'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/breakdowns', [
                'asset_id' => $this->asset->id,
                'problem_description' => 'Machine stops mid-seam',
            ])
            ->assertCreated();
    }

    public function test_a_breakdown_in_an_unreachable_factory_is_not_found(): void
    {
        $other = TenantFixture::factory($this->delta, 'Savar Unit', 'SAV');
        $asset = WorkOrderFixture::runningAsset($this->delta, $other, 'SEW-SAV-00001');

        $breakdown = app(ReportBreakdown::class)->handle([
            'asset_id' => $asset->id,
            'problem_description' => 'Needle bar jammed',
        ], $this->manager->id);

        $engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test', $this->factory->id);
        $token = app(IssueApiToken::class)->forUser($engineer, $this->delta->id, 'x')['plain'];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/breakdowns/'.$breakdown->id)
            ->assertNotFound();
    }
}

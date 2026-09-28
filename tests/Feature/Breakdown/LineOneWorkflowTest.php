<?php

declare(strict_types=1);

namespace Tests\Feature\Breakdown;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Actions\CreateAsset;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Breakdown\Models\FailureCode;
use App\Modules\Breakdown\Models\RootCause;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The exact scenario asked for: Sewing / Production's own Line 1, with a
 * Line Chief, a Technician and a machine all scoped to it (not left
 * factory-wide the way most other Breakdown tests build their fixtures),
 * walked through the full five-role sign-off chain end to end — and, at
 * every step, every other role refused both by the API and by
 * `/auth/permissions` (the exact list `BreakdownActions`'s own
 * `ACTION_PERMISSIONS` map checks client-side to decide which button
 * renders, so a wrong entry here is the same bug that would show, or hide,
 * the wrong button in the browser).
 *
 * Chain under test: Line Chief reports -> Manager/Engineer acknowledges
 * (which also raises the work order and rosters Line 1's technician in the
 * same click) -> Technician starts the work order and completes the repair
 * -> Line Chief verifies, then separately submits for approval -> Manager/
 * Engineer gives final approval, closing it.
 */
class LineOneWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Department $sewing;

    private ProductionLine $lineOne;

    private Asset $machine;

    private User $lineChiefUser;

    private User $technicianUser;

    private User $managerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->sewing = Department::create([
            'company_id' => $this->delta->id,
            'factory_id' => $this->dhaka->id,
            'name' => 'Sewing / Production Department',
            'code' => 'SEW',
        ]);

        $this->lineOne = ProductionLine::create([
            'company_id' => $this->delta->id,
            'department_id' => $this->sewing->id,
            'name' => 'Line 1',
            'code' => 'Line1',
        ]);

        // The machine: located precisely on Line 1, not just "somewhere in
        // the factory" — this is what makes `BreakdownScopeGuard` and the
        // roster auto-assign have something real to match against.
        $location = AssetLocation::create([
            'factory_id' => $this->dhaka->id,
            'code' => $this->dhaka->code.'-SEW-L1',
            'name' => 'Line 1',
            'department_id' => $this->sewing->id,
            'production_line_id' => $this->lineOne->id,
            'full_path' => 'Dhaka Unit 1 › Sewing / Production Department › Line 1',
        ]);

        $this->machine = app(CreateAsset::class)->handle([
            'asset_type_id' => AssetType::where('code', 'SEWING')->firstOrFail()->id,
            'asset_category_id' => AssetCategory::where('code', 'LOCKSTITCH')->firstOrFail()->id,
            'asset_code' => 'SEW-DHK-L1-001',
            'name' => 'Juki DDL-9000C',
            'criticality' => 'MEDIUM',
            'current_factory_id' => $this->dhaka->id,
            'asset_location_id' => $location->id,
        ]);

        $changeStatus = app(ChangeAssetStatus::class);
        foreach (['PURCHASED', 'INSTALLED', 'COMMISSIONED', 'RUNNING'] as $step) {
            $this->machine = $changeStatus->handle($this->machine, $step);
        }

        // The technician: assigned under Line 1, not factory-wide — the
        // whole point of this fixture set is a genuinely *restricted* roster,
        // not the unrestricted one most other Breakdown tests use.
        $this->technicianUser = TenantFixture::user($this->delta, 'TECHNICIAN', 'line1.technician@delta.test', $this->dhaka->id);
        Technician::create([
            'company_id' => $this->delta->id,
            'factory_id' => $this->dhaka->id,
            'user_id' => $this->technicianUser->id,
            'department_id' => $this->sewing->id,
            'production_line_id' => $this->lineOne->id,
            'employee_id' => 'EMP-L1-001',
            'name' => 'Karim Mia',
            'status' => 'ACTIVE',
        ]);

        // The Line Chief: also scoped to Line 1 (a Line Chief carries
        // coverage straight off the User row — `BreakdownScopeGuard::
        // coverageFor()`'s own fallback, since they hold no Technician row).
        $this->lineChiefUser = TenantFixture::user($this->delta, 'LINE_CHIEF', 'line1.linechief@delta.test', $this->dhaka->id);
        $this->lineChiefUser->forceFill([
            'department_id' => $this->sewing->id,
            'production_line_id' => $this->lineOne->id,
        ])->save();

        // The Manager/Engineer: deliberately left unrestricted — exempt from
        // line coverage outright (`BreakdownScopeGuard::isExempt()`), same
        // as a real factory needs someone who can act across every line.
        $this->managerUser = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'line1.manager@delta.test', $this->dhaka->id);
    }

    private function as(User $user): self
    {
        $token = app(IssueApiToken::class)->forUser($user, $this->delta->id, 'x')['plain'];
        $this->withHeader('Authorization', 'Bearer '.$token);

        return $this;
    }

    private function permissionsFor(User $user): array
    {
        return $this->as($user)->getJson('/api/v1/auth/permissions')->assertOk()->json('data.permissions');
    }

    /**
     * The exact button-visibility contract `BreakdownActions`' own
     * `ACTION_PERMISSIONS` map depends on — proved once per role here so a
     * regression in `RoleSeeder`'s matrix is caught the same place a wrong
     * button in the browser would come from.
     */
    public function test_each_role_holds_exactly_its_own_step_permissions(): void
    {
        $technician = $this->permissionsFor($this->technicianUser);
        $this->assertContains('breakdown.breakdown.repair', $technician);
        $this->assertContains('work_order.work_order.start', $technician);
        $this->assertContains('work_order.work_order.complete', $technician);
        $this->assertNotContains('breakdown.breakdown.create', $technician);
        $this->assertNotContains('breakdown.breakdown.acknowledge', $technician);
        $this->assertNotContains('breakdown.breakdown.assign', $technician);
        $this->assertNotContains('breakdown.breakdown.verify', $technician);
        $this->assertNotContains('breakdown.breakdown.close', $technician);

        $lineChief = $this->permissionsFor($this->lineChiefUser);
        $this->assertContains('breakdown.breakdown.create', $lineChief);
        $this->assertContains('breakdown.breakdown.verify', $lineChief);
        $this->assertNotContains('breakdown.breakdown.acknowledge', $lineChief);
        $this->assertNotContains('breakdown.breakdown.assign', $lineChief);
        $this->assertNotContains('breakdown.breakdown.close', $lineChief);
        $this->assertNotContains('breakdown.breakdown.repair', $lineChief);

        $manager = $this->permissionsFor($this->managerUser);
        $this->assertContains('breakdown.breakdown.create', $manager);
        $this->assertContains('breakdown.breakdown.acknowledge', $manager);
        $this->assertContains('breakdown.breakdown.assign', $manager);
        $this->assertContains('breakdown.breakdown.close', $manager);
        $this->assertContains('work_order.work_order.create', $manager);
        $this->assertNotContains('breakdown.breakdown.verify', $manager);
    }

    public function test_the_full_line_one_sign_off_chain_reaches_closed_with_every_wrong_role_refused(): void
    {
        // --- Step 1: Line Chief reports the breakdown on Line 1 ---------
        $this->as($this->technicianUser)->postJson('/api/v1/breakdowns', [
            'asset_id' => $this->machine->id,
            'problem_description' => 'Needle bar seized mid-seam',
        ])->assertForbidden();

        $report = $this->as($this->lineChiefUser)->postJson('/api/v1/breakdowns', [
            'asset_id' => $this->machine->id,
            'problem_description' => 'Needle bar seized mid-seam',
        ])->assertCreated();

        $breakdownId = $report->json('data.id');
        $this->assertSame('REPORTED', $report->json('data.status'));

        // --- Step 2: Manager/Engineer acknowledges; a work order is ------
        //     raised and Line 1's own technician rostered onto it in the
        //     same click.
        $this->as($this->technicianUser)->postJson("/api/v1/breakdowns/{$breakdownId}/acknowledge")->assertForbidden();
        $this->as($this->lineChiefUser)->postJson("/api/v1/breakdowns/{$breakdownId}/acknowledge")->assertForbidden();

        $acknowledged = $this->as($this->managerUser)->postJson("/api/v1/breakdowns/{$breakdownId}/acknowledge")->assertOk();
        $this->assertSame('ACKNOWLEDGED', $acknowledged->json('data.status'));

        $detail = $this->as($this->managerUser)->getJson("/api/v1/breakdowns/{$breakdownId}")->assertOk();
        $workOrderId = $detail->json('data.work_order.id');
        $this->assertNotNull($workOrderId, 'acknowledging should auto-raise a work order');

        $workOrder = WorkOrder::findOrFail($workOrderId);
        $assignedTechnicianIds = $workOrder->activeAssignments()->pluck('technician_id')->all();
        $this->assertContains(
            Technician::where('user_id', $this->technicianUser->id)->firstOrFail()->id,
            $assignedTechnicianIds,
            'Line 1\'s own technician should be on the auto-raised work order\'s roster',
        );

        // --- Step 3: the Technician self-claims by starting the work -----
        //     order, then completes the repair.
        $this->as($this->lineChiefUser)->postJson("/api/v1/work-orders/{$workOrderId}/start")->assertForbidden();

        $this->as($this->technicianUser)->postJson("/api/v1/work-orders/{$workOrderId}/start")
            ->assertOk()->assertJsonPath('data.status', 'IN_PROGRESS');

        $inRepair = $this->as($this->managerUser)->getJson("/api/v1/breakdowns/{$breakdownId}")->assertOk();
        $this->assertSame('IN_REPAIR', $inRepair->json('data.status'));

        $this->as($this->lineChiefUser)->postJson("/api/v1/breakdowns/{$breakdownId}/complete-repair", [
            'failure_code_id' => FailureCode::where('code', 'BEARING_FAILURE')->firstOrFail()->id,
            'root_cause_id' => RootCause::where('code', 'INADEQUATE_LUBRICATION')->firstOrFail()->id,
        ])->assertForbidden();

        $completed = $this->as($this->technicianUser)->postJson("/api/v1/breakdowns/{$breakdownId}/complete-repair", [
            'failure_code_id' => FailureCode::where('code', 'BEARING_FAILURE')->firstOrFail()->id,
            'root_cause_id' => RootCause::where('code', 'INADEQUATE_LUBRICATION')->firstOrFail()->id,
            'corrective_action' => 'Replaced the needle bar assembly',
        ])->assertOk();
        $this->assertSame('REPAIRED', $completed->json('data.status'));

        // --- Step 3b: the Line Chief reviews it, finds it isn't actually --
        //     fixed, and sends it back — before ever clicking Verify. Only
        //     a Line Chief covering Line 1 may do this, with a reason.
        $this->as($this->technicianUser)->postJson("/api/v1/breakdowns/{$breakdownId}/reject-verification", [
            'rejection_reason' => 'Needle still skipping stitches',
        ])->assertForbidden();
        $this->as($this->managerUser)->postJson("/api/v1/breakdowns/{$breakdownId}/reject-verification", [
            'rejection_reason' => 'Needle still skipping stitches',
        ])->assertForbidden();

        $this->as($this->lineChiefUser)->postJson("/api/v1/breakdowns/{$breakdownId}/reject-verification", [])
            ->assertStatus(422)->assertJsonValidationErrors('rejection_reason');

        $sentBack = $this->as($this->lineChiefUser)->postJson("/api/v1/breakdowns/{$breakdownId}/reject-verification", [
            'rejection_reason' => 'Needle still skipping stitches',
        ])->assertOk();
        $this->assertSame('IN_REPAIR', $sentBack->json('data.status'));
        $this->assertNull($sentBack->json('data.timestamps.repair_completed_at'));

        // The technician redoes the repair — the reject is a real loop back
        // into the same chain, not a dead end.
        $redone = $this->as($this->technicianUser)->postJson("/api/v1/breakdowns/{$breakdownId}/complete-repair", [
            'failure_code_id' => FailureCode::where('code', 'BEARING_FAILURE')->firstOrFail()->id,
            'root_cause_id' => RootCause::where('code', 'INADEQUATE_LUBRICATION')->firstOrFail()->id,
            'corrective_action' => 'Replaced the needle bar with the correct part this time',
        ])->assertOk();
        $this->assertSame('REPAIRED', $redone->json('data.status'));

        // --- Step 4: the Line Chief verifies, then separately submits -----
        //     for the Manager/Engineer's approval.
        $this->as($this->technicianUser)->postJson("/api/v1/breakdowns/{$breakdownId}/verify")->assertForbidden();
        $this->as($this->managerUser)->postJson("/api/v1/breakdowns/{$breakdownId}/verify")->assertForbidden();

        $verified = $this->as($this->lineChiefUser)->postJson("/api/v1/breakdowns/{$breakdownId}/verify")->assertOk();
        $this->assertSame('VERIFIED', $verified->json('data.status'));

        $this->as($this->technicianUser)->postJson("/api/v1/breakdowns/{$breakdownId}/submit-for-approval")->assertForbidden();
        $this->as($this->managerUser)->postJson("/api/v1/breakdowns/{$breakdownId}/submit-for-approval")->assertForbidden();

        $submitted = $this->as($this->lineChiefUser)->postJson("/api/v1/breakdowns/{$breakdownId}/submit-for-approval")->assertOk();
        $this->assertSame('PENDING_APPROVAL', $submitted->json('data.status'));

        // --- Step 5: the Manager/Engineer gives final approval ------------
        $this->as($this->technicianUser)->postJson("/api/v1/breakdowns/{$breakdownId}/close")->assertForbidden();
        $this->as($this->lineChiefUser)->postJson("/api/v1/breakdowns/{$breakdownId}/close")->assertForbidden();

        $closed = $this->as($this->managerUser)->postJson("/api/v1/breakdowns/{$breakdownId}/close", [
            'closure_notes' => 'Confirmed running normally on the floor',
        ])->assertOk();

        $this->assertSame('CLOSED', $closed->json('data.status'));
        $this->assertFalse($closed->json('data.is_open'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Approval;

use App\Modules\Approval\Actions\DecideApproval;
use App\Modules\Approval\Actions\RequestApproval;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalWorkflow;
use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Actions\AssignTechnicians;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Actions\TransitionWorkOrder;
use App\Modules\WorkOrder\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Approval workflow (SRS 14, ERD Section 20).
 *
 * Each guard here exists because of a specific way approval otherwise becomes
 * theatre: signing your own request, signing somebody else's step, or a cost
 * edited after the signature so nobody can say what was actually agreed.
 */
class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private User $engineer;

    private User $maintenanceManager;

    private User $factoryManager;

    private RequestApproval $requests;

    private DecideApproval $decisions;

    private TransitionWorkOrder $transition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'eng@delta.test');
        $this->maintenanceManager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
        $this->factoryManager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');

        $this->requests = app(RequestApproval::class);
        $this->decisions = app(DecideApproval::class);
        $this->transition = app(TransitionWorkOrder::class);
    }

    /**
     * The example chain from SRS 14: low-cost work needs the maintenance
     * manager, high-cost work needs the factory manager as well.
     */
    private function workflow(): ApprovalWorkflow
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
            // Anything at or above 20,000 needs a signature. Below that the
            // rule does not apply and the job goes straight through.
            'condition_json' => ['min_cost' => '20000'],
        ]);

        ApprovalRule::create([
            'workflow_id' => $workflow->id,
            'sequence' => 2,
            'role_id' => Role::whereNull('company_id')->where('code', 'FACTORY_MANAGER')->firstOrFail()->id,
            'name' => 'Factory manager',
            'condition_json' => ['min_cost' => '100000'],
        ]);

        return $workflow->fresh();
    }

    private function workOrder(string $labour = '0', string $parts = '0'): WorkOrder
    {
        TenantFixture::actingAsTenant($this->delta);

        return app(CreateWorkOrder::class)->handle([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'title' => 'Overhaul',
            'estimated_labor_cost' => $labour,
            'estimated_parts_cost' => $parts,
        ], $this->engineer->id);
    }

    public function test_cheap_work_needs_no_signature_at_all(): void
    {
        $this->workflow();

        $workOrder = $this->transition->schedule($this->workOrder('4000', '2000'), $this->engineer->id);

        // Requiring a signature for a needle change teaches everybody to
        // approve without reading.
        $this->assertSame('NOT_REQUIRED', $workOrder->fresh()->approval_status);
        $this->assertSame(0, ApprovalRequest::count());
    }

    public function test_not_required_is_distinguishable_from_approved(): void
    {
        $this->workflow();

        $workOrder = $this->transition->schedule($this->workOrder('1000'), $this->engineer->id);

        // Different facts. An audit should be able to tell "nobody had to sign"
        // from "somebody signed".
        $this->assertSame('NOT_REQUIRED', $workOrder->fresh()->approval_status);
        $this->assertNotSame('APPROVED', $workOrder->fresh()->approval_status);
    }

    public function test_a_costly_job_raises_a_request_and_freezes_its_context(): void
    {
        $this->workflow();

        $workOrder = $this->transition->schedule($this->workOrder('30000', '15000'), $this->engineer->id);

        $request = ApprovalRequest::firstOrFail();

        $this->assertSame('PENDING', $workOrder->fresh()->approval_status);
        $this->assertSame(1, $request->total_steps);
        $this->assertSame('45000.0000', $request->context_json['cost']);
        $this->assertSame($this->asset->criticality, $request->context_json['criticality']);
    }

    public function test_a_later_cost_change_does_not_alter_what_was_approved(): void
    {
        $this->workflow();

        $workOrder = $this->transition->schedule($this->workOrder('30000'), $this->engineer->id);
        $request = ApprovalRequest::firstOrFail();

        // Somebody edits the estimate after the request went out.
        $workOrder->forceFill(['estimated_labor_cost' => '900000'])->save();

        // The request still shows what the approver was asked to sign. Without
        // this, "what did they actually agree to" is unanswerable
        // (ERD Section 20 rule 1).
        $this->assertSame('30000.0000', $request->fresh()->context_json['cost']);
        $this->assertSame(1, $request->fresh()->total_steps);
    }

    public function test_the_requester_cannot_approve_their_own_request(): void
    {
        $this->workflow();

        $this->transition->schedule($this->workOrder('30000'), $this->engineer->id);
        $request = ApprovalRequest::firstOrFail();

        try {
            // An approval you can grant yourself is a checkbox, not a control.
            $this->decisions->approve($request, $this->engineer);
            $this->fail('Self-approval must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(403, $e->status);
        }

        $this->assertSame('PENDING', $request->fresh()->status);
    }

    public function test_only_the_named_step_may_act(): void
    {
        $this->workflow();

        $this->transition->schedule($this->workOrder('30000'), $this->engineer->id);
        $request = ApprovalRequest::firstOrFail();

        // Step one is the maintenance manager. The factory manager holds a more
        // senior role and still may not sign it, because the ordering is the
        // workflow.
        try {
            $this->decisions->approve($request, $this->factoryManager);
            $this->fail('Acting on somebody else\'s step must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(403, $e->status);
        }

        $approved = $this->decisions->approve($request->fresh(), $this->maintenanceManager);

        $this->assertSame('APPROVED', $approved->status);
    }

    public function test_an_expensive_job_needs_both_signatures_in_order(): void
    {
        $this->workflow();

        $workOrder = $this->transition->schedule($this->workOrder('150000'), $this->engineer->id);
        $request = ApprovalRequest::firstOrFail();

        $this->assertSame(2, $request->total_steps);

        $request = $this->decisions->approve($request, $this->maintenanceManager, 'Agreed, the machine is worth keeping');

        // Not approved until the last signature: one signature on a two-step
        // chain is an unfinished decision.
        $this->assertSame('PENDING', $request->status);
        $this->assertSame(2, $request->current_step);
        $this->assertSame('PENDING', $workOrder->fresh()->approval_status);

        $request = $this->decisions->approve($request, $this->factoryManager);

        $this->assertSame('APPROVED', $request->status);
        $this->assertSame('APPROVED', $workOrder->fresh()->approval_status);
        $this->assertNotNull($request->completed_at);
    }

    public function test_every_decision_is_recorded_with_who_and_when(): void
    {
        $this->workflow();

        $this->transition->schedule($this->workOrder('150000'), $this->engineer->id);
        $request = ApprovalRequest::firstOrFail();

        $request = $this->decisions->approve($request, $this->maintenanceManager, 'Fine');
        $this->decisions->approve($request, $this->factoryManager, 'Approved');

        $actions = ApprovalRequest::firstOrFail()->actions;

        // "Who approved this" must stay answerable after the person has left
        // the company (ERD Section 20 rule 4).
        $this->assertCount(2, $actions);
        $this->assertSame($this->maintenanceManager->id, $actions[0]->approver_id);
        $this->assertSame(1, $actions[0]->step);
        $this->assertSame($this->factoryManager->id, $actions[1]->approver_id);
        $this->assertSame(2, $actions[1]->step);
    }

    public function test_a_rejection_needs_a_reason_and_ends_the_request(): void
    {
        $this->workflow();

        $workOrder = $this->transition->schedule($this->workOrder('150000'), $this->engineer->id);
        $request = ApprovalRequest::firstOrFail();

        try {
            $this->decisions->reject($request, $this->maintenanceManager, '  ');
            $this->fail('A rejection with no reason must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('comment', $e->errors());
        }

        $rejected = $this->decisions->reject(
            $request->fresh(), $this->maintenanceManager, 'Quote the vendor first',
        );

        // Ends outright rather than stepping back: returning it to the previous
        // approver would mean the same person signing the same job twice with
        // no record of what changed.
        $this->assertSame('REJECTED', $rejected->status);
        $this->assertSame('REJECTED', $workOrder->fresh()->approval_status);
    }

    public function test_work_cannot_start_while_approval_is_pending(): void
    {
        $this->workflow();

        $workOrder = $this->transition->schedule($this->workOrder('150000'), $this->engineer->id);

        $grade = WorkOrderFixture::grade($this->delta);
        $technician = WorkOrderFixture::technician($this->delta, $this->dhaka, $grade);
        app(AssignTechnicians::class)->handle($workOrder->fresh(), [$technician->id], $this->engineer->id);

        try {
            // Work that begins before its approval makes the approval a
            // formality performed after the money is spent.
            $this->transition->start($workOrder->fresh(), $this->engineer->id);
            $this->fail('Starting unapproved work must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }

        $request = ApprovalRequest::firstOrFail();
        $request = $this->decisions->approve($request, $this->maintenanceManager);
        $this->decisions->approve($request, $this->factoryManager);

        $started = $this->transition->start($workOrder->fresh(), $this->engineer->id);
        $this->assertSame('IN_PROGRESS', $started->status);
    }

    public function test_rejected_work_cannot_be_started(): void
    {
        $this->workflow();

        $workOrder = $this->transition->schedule($this->workOrder('150000'), $this->engineer->id);

        $grade = WorkOrderFixture::grade($this->delta);
        $technician = WorkOrderFixture::technician($this->delta, $this->dhaka, $grade);
        app(AssignTechnicians::class)->handle($workOrder->fresh(), [$technician->id], $this->engineer->id);

        $this->decisions->reject(ApprovalRequest::firstOrFail(), $this->maintenanceManager, 'Not this quarter');

        $this->expectException(ValidationException::class);
        $this->transition->start($workOrder->fresh(), $this->engineer->id);
    }

    public function test_a_rule_that_names_no_approver_blocks_rather_than_opens(): void
    {
        TenantFixture::actingAsTenant($this->delta);

        $workflow = ApprovalWorkflow::create([
            'name' => 'Broken workflow',
            'entity_type' => 'WORK_ORDER',
            'active' => true,
        ]);

        // A misconfigured step naming nobody.
        ApprovalRule::create(['workflow_id' => $workflow->id, 'sequence' => 1, 'name' => 'Nobody']);

        $this->transition->schedule($this->workOrder('50000'), $this->engineer->id);
        $request = ApprovalRequest::firstOrFail();

        // Treating it as open to everyone would let one bad row disable the
        // whole control.
        $this->assertFalse($this->decisions->canAct($request, $this->maintenanceManager));
        $this->assertFalse($this->decisions->canAct($request, $this->factoryManager));
    }

    public function test_a_named_user_step_is_satisfied_only_by_that_user(): void
    {
        TenantFixture::actingAsTenant($this->delta);

        $workflow = ApprovalWorkflow::create([
            'name' => 'Named approver',
            'entity_type' => 'WORK_ORDER',
            'active' => true,
        ]);

        ApprovalRule::create([
            'workflow_id' => $workflow->id,
            'sequence' => 1,
            'user_id' => $this->factoryManager->id,
            'name' => 'Nasrin only',
        ]);

        $this->transition->schedule($this->workOrder('50000'), $this->engineer->id);
        $request = ApprovalRequest::firstOrFail();

        $this->assertFalse($this->decisions->canAct($request, $this->maintenanceManager));
        $this->assertTrue($this->decisions->canAct($request, $this->factoryManager));
    }

    public function test_an_expired_request_is_recorded_rather_than_flipped_silently(): void
    {
        $this->workflow();

        $this->transition->schedule($this->workOrder('150000'), $this->engineer->id);

        ApprovalRequest::firstOrFail()
            ->forceFill(['expires_at' => CarbonImmutable::now()->subDay()])
            ->save();

        $this->assertSame(1, $this->decisions->expireOverdue());

        $request = ApprovalRequest::firstOrFail();

        // A job that stalled because nobody looked at it for a fortnight is a
        // fact worth having in the history.
        $this->assertSame('EXPIRED', $request->status);
        $this->assertSame('EXPIRED', $request->actions->last()->action);
    }

    public function test_a_second_request_is_not_raised_while_one_is_pending(): void
    {
        $this->workflow();

        $workOrder = $this->transition->schedule($this->workOrder('50000'), $this->engineer->id);

        $again = $this->requests->forWorkOrder($workOrder->fresh(), $this->engineer->id);

        $this->assertSame(1, ApprovalRequest::count());
        $this->assertSame(ApprovalRequest::firstOrFail()->id, $again->id);
    }

    public function test_a_decision_on_a_settled_request_is_refused(): void
    {
        $this->workflow();

        $this->transition->schedule($this->workOrder('30000'), $this->engineer->id);
        $request = ApprovalRequest::firstOrFail();

        $this->decisions->approve($request, $this->maintenanceManager);

        try {
            $this->decisions->approve($request->fresh(), $this->maintenanceManager);
            $this->fail('Approving twice must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }
    }
}

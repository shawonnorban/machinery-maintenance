<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Asset\Models\Asset;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Actions\ReceiveStock;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Maintenance\Actions\CreateTemplateVersion;
use App\Modules\Maintenance\Models\MaintenanceTemplate;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Models\WorkOrder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The Work Order API (docs/03-API-Specification.md §11).
 *
 * Every write delegates to the same Action the web `WorkOrderController`,
 * `WorkOrderTransitionController`, `ChecklistExecutionController`,
 * `LaborEntryController` and Inventory's `WorkOrderPartsController` call
 * (ADR-003), so this suite proves the wiring and the state-machine
 * refusals a client actually has to handle, not the domain rules those
 * Actions already cover in their own tests.
 */
class WorkOrderApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $factory;

    private Asset $asset;

    private User $manager;

    private User $engineer;

    private User $admin;

    private string $managerToken;

    private string $engineerToken;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->factory = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->factory);

        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test', $this->factory->id);
        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test', $this->factory->id);
        $this->admin = TenantFixture::user($this->delta, 'FACTORY_ADMIN', 'admin@delta.test', $this->factory->id);

        $this->managerToken = app(IssueApiToken::class)->forUser($this->manager, $this->delta->id, 'x')['plain'];
        $this->engineerToken = app(IssueApiToken::class)->forUser($this->engineer, $this->delta->id, 'x')['plain'];
        $this->adminToken = app(IssueApiToken::class)->forUser($this->admin, $this->delta->id, 'x')['plain'];
    }

    private function asManager(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->managerToken);

        return $this;
    }

    private function asEngineer(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->engineerToken);

        return $this;
    }

    private function asAdmin(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken);

        return $this;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'CORRECTIVE')->firstOrFail()->id,
            'title' => 'Bearing noise on head 3',
            'priority' => 'HIGH',
        ], $overrides);
    }

    public function test_a_work_order_is_created_with_the_correct_permission(): void
    {
        $response = $this->asManager()->postJson('/api/v1/work-orders', $this->payload())->assertCreated();

        $this->assertSame('DRAFT', $response->json('data.status'));
        $this->assertSame(1, $response->json('data.version'));
        $this->assertDatabaseHas('work_orders', ['title' => 'Bearing noise on head 3']);
    }

    public function test_creation_requires_a_known_maintenance_type(): void
    {
        $this->asManager()->postJson('/api/v1/work-orders', $this->payload(['maintenance_type_id' => str_repeat('0', 26)]))
            ->assertStatus(422);
    }

    public function test_an_invalid_transition_is_refused(): void
    {
        $workOrder = $this->createWorkOrder();

        // DRAFT cannot go straight to IN_PROGRESS.
        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/start")->assertStatus(409);
    }

    public function test_technicians_are_assigned_and_unassigned(): void
    {
        $workOrder = $this->createWorkOrder();
        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/submit-for-approval")->assertOk();

        $technician = WorkOrderFixture::technician($this->delta, $this->factory);

        $assign = $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/assign", [
            'technician_ids' => [$technician->id],
        ])->assertOk();

        $this->assertSame('ASSIGNED', $assign->json('data.status'));
        $this->assertCount(1, $assign->json('data.assignments'));

        $unassign = $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/unassign", [
            'technician_id' => $technician->id,
        ])->assertOk();

        // Nobody left on an ASSIGNED job sends it back to the queue.
        $this->assertSame('SCHEDULED', $unassign->json('data.status'));
    }

    public function test_the_full_lifecycle_from_draft_to_closed(): void
    {
        // Verification is required only for the criticalities the tenant
        // configures (default CRITICAL/HIGH) — WorkOrderFixture's asset is
        // MEDIUM, so this is set explicitly to exercise the VERIFIED step.
        $workOrder = $this->createWorkOrder(['requires_verification' => true]);
        $technician = WorkOrderFixture::technician($this->delta, $this->factory);

        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/submit-for-approval")->assertOk();
        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/assign", [
            'technician_ids' => [$technician->id],
        ])->assertOk();

        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/start")
            ->assertOk()->assertJsonPath('data.status', 'IN_PROGRESS');

        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/hold", [
            'reason_code' => 'AWAITING_PARTS',
        ])->assertOk()->assertJsonPath('data.status', 'ON_HOLD');

        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/resume")
            ->assertOk()->assertJsonPath('data.status', 'IN_PROGRESS');

        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/labor", [
            'technician_id' => $technician->id,
            'started_at' => now()->subHour()->toIso8601String(),
            'ended_at' => now()->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.minutes', 60);

        $this->asManager()->getJson("/api/v1/work-orders/{$workOrder->id}/labor")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/complete")
            ->assertOk()->assertJsonPath('data.status', 'COMPLETED');

        // The completer cannot also verify.
        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/verify")->assertStatus(403);

        $this->asEngineer()->postJson("/api/v1/work-orders/{$workOrder->id}/verify")
            ->assertOk()->assertJsonPath('data.status', 'VERIFIED');

        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/close")
            ->assertOk()->assertJsonPath('data.status', 'CLOSED');

        $this->asManager()->getJson("/api/v1/work-orders/{$workOrder->id}/history")
            ->assertOk()
            ->assertJsonFragment(['to_status' => 'CLOSED']);
    }

    public function test_completing_is_refused_while_a_required_checklist_item_is_unanswered(): void
    {
        $version = WorkOrderFixture::publishedChecklist($this->delta);

        $workOrder = $this->createWorkOrder(['template_version_id' => $version->id]);
        $technician = WorkOrderFixture::technician($this->delta, $this->factory);

        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/submit-for-approval")->assertOk();
        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/assign", ['technician_ids' => [$technician->id]])
            ->assertOk();
        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/start")->assertOk();

        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/complete")
            ->assertStatus(422)
            ->assertJsonValidationErrors('checklist');

        $index = $this->asManager()->getJson("/api/v1/work-orders/{$workOrder->id}/checklist")->assertOk();
        $this->assertSame(0, $index->json('data.progress.answered'));
        $item = $index->json('data.items.0');

        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/checklist/results", [
            'checklist_item_id' => $item['id'],
            'result' => 'PASS',
        ])->assertCreated()->assertJsonPath('data.result', 'PASS');

        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/complete")
            ->assertOk()->assertJsonPath('data.status', 'COMPLETED');
    }

    public function test_parts_are_requested_issued_consumed_and_reconciled_before_close(): void
    {
        $bin = InventoryFixture::bin($this->delta, $this->factory);
        $part = InventoryFixture::part($this->delta);
        app(ReceiveStock::class)->handle($part, $bin, '10', '500', $this->admin->id);

        $workOrder = $this->createWorkOrder();
        $technician = WorkOrderFixture::technician($this->delta, $this->factory);

        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/submit-for-approval")->assertOk();
        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/assign", ['technician_ids' => [$technician->id]])
            ->assertOk();
        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/start")->assertOk();

        $line = $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/parts", [
            'spare_part_id' => $part->id,
            'quantity' => 2,
        ])->assertCreated();

        $lineId = $line->json('data.id');
        $this->assertSame('REQUESTED', $line->json('data.status'));

        // Issuing needs FACTORY_ADMIN (inventory.stock.issue); the requester
        // alone (FACTORY_MANAGER) cannot move stock.
        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/parts/{$lineId}/issue", [
            'bin_id' => $bin->id,
            'quantity' => 2,
        ])->assertStatus(403);

        $issue = $this->asAdmin()->postJson("/api/v1/work-orders/{$workOrder->id}/parts/{$lineId}/issue", [
            'bin_id' => $bin->id,
            'quantity' => 2,
        ])->assertOk();

        $this->assertSame('ISSUED', $issue->json('data.status'));

        // Complete succeeds (no checklist on this work order, and this
        // asset's MEDIUM criticality needs no verification by default);
        // close is blocked until the issued quantity is reconciled.
        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/complete")->assertOk();

        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/close")->assertStatus(422);

        $this->asAdmin()->postJson("/api/v1/work-orders/{$workOrder->id}/parts/{$lineId}/consume", [
            'quantity' => 2,
        ])->assertOk()->assertJsonPath('data.status', 'CONSUMED');

        $parts = $this->asManager()->getJson("/api/v1/work-orders/{$workOrder->id}/parts")->assertOk();
        $this->assertSame('2.0000', $parts->json('data.0.quantity_consumed'));

        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/close")
            ->assertOk()->assertJsonPath('data.status', 'CLOSED');
    }

    public function test_part_requests_are_aggregated_across_open_work_orders(): void
    {
        // Mirrors `PartRequestController::index` — what the floor is
        // waiting for, across every open work order, a critical/stopped
        // machine's request first regardless of when it was typed.
        $part = InventoryFixture::part($this->delta);

        $workOrder = $this->createWorkOrder(['priority' => 'LOW']);
        $line = $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/parts", [
            'spare_part_id' => $part->id,
            'quantity' => 5,
        ])->assertCreated();

        $response = $this->asManager()->getJson('/api/v1/part-requests')->assertOk();

        $response->assertJsonCount(1, 'data');
        $this->assertSame($line->json('data.id'), $response->json('data.0.id'));
        $this->assertSame($workOrder->id, $response->json('data.0.work_order.id'));
        $this->assertSame($part->id, $response->json('data.0.spare_part.id'));
        // No stock received anywhere for this part yet — the shortage flag
        // the whole screen exists for.
        $this->assertSame('0.0000', $response->json('data.0.on_hand'));
        $this->assertTrue($response->json('data.0.short'));

        // Cancelling drops it off the aggregate view — only REQUESTED lines
        // are shown.
        $this->asManager()->patchJson("/api/v1/work-orders/{$workOrder->id}/parts/".$line->json('data.id'), [
            'status' => 'CANCELLED',
        ])->assertOk();

        $this->asManager()->getJson('/api/v1/part-requests')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_part_request_is_cancelled_before_anything_is_issued(): void
    {
        $part = InventoryFixture::part($this->delta);
        $workOrder = $this->createWorkOrder();

        $line = $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/parts", [
            'spare_part_id' => $part->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->asManager()->patchJson("/api/v1/work-orders/{$workOrder->id}/parts/".$line->json('data.id'), [
            'status' => 'CANCELLED',
        ])->assertOk()->assertJsonPath('data.status', 'CANCELLED');
    }

    public function test_reopen_requires_a_reason_and_increments_the_count(): void
    {
        $workOrder = $this->createWorkOrder();
        $technician = WorkOrderFixture::technician($this->delta, $this->factory);

        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/submit-for-approval")->assertOk();
        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/assign", ['technician_ids' => [$technician->id]])
            ->assertOk();
        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/start")->assertOk();
        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/complete")->assertOk();

        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/reopen")
            ->assertStatus(422)->assertJsonValidationErrors('reason');

        $reopen = $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/reopen", [
            'reason' => 'Bearing failed again within a day',
        ])->assertOk();

        $this->assertSame('IN_PROGRESS', $reopen->json('data.status'));
        $this->assertDatabaseHas('work_orders', ['id' => $workOrder->id, 'reopened_count' => 1]);
    }

    public function test_costs_and_history_are_read_only_summaries(): void
    {
        // Through the actual create endpoint, not the raw test helper: the
        // opening status-history row is written by CreateWorkOrder itself,
        // and a work order built with a bare WorkOrder::create() has none.
        $created = $this->asManager()->postJson('/api/v1/work-orders', $this->payload())->assertCreated();
        $id = $created->json('data.id');

        $this->asManager()->getJson("/api/v1/work-orders/{$id}/costs")
            ->assertOk()
            ->assertJsonStructure(['data' => ['currency', 'estimated_parts_cost', 'actual_parts_cost', 'actual_cost']]);

        $this->asManager()->getJson("/api/v1/work-orders/{$id}/history")
            ->assertOk()
            ->assertJsonFragment(['to_status' => 'DRAFT']);
    }

    public function test_a_work_order_in_an_unreachable_factory_is_not_found(): void
    {
        $other = TenantFixture::factory($this->delta, 'Savar Unit', 'SAV');
        $asset = WorkOrderFixture::runningAsset($this->delta, $other, 'SEW-SAV-00001');

        $workOrder = WorkOrder::create([
            'factory_id' => $other->id,
            'asset_id' => $asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'CORRECTIVE')->firstOrFail()->id,
            'work_order_number' => 'WO-SAV-0001',
            'title' => 'Out of reach',
            'status' => 'DRAFT',
            'priority' => 'MEDIUM',
            'source' => 'MANUAL',
        ]);

        $this->asManager()->getJson('/api/v1/work-orders/'.$workOrder->id)->assertNotFound();
    }

    public function test_form_options_are_reachable_by_a_caller_who_can_create_but_not_manage_master_data(): void
    {
        // MAINTENANCE_ENGINEER has work_order.work_order.create but not
        // masterdata.manage — the same gap already found and closed for
        // Asset's and Inventory's create forms.
        $response = $this->asEngineer()->getJson('/api/v1/work-orders/form-options')->assertOk();

        $response->assertJsonStructure(['data' => ['assets', 'maintenance_types', 'teams', 'templates']]);
        $this->assertContains($this->asset->id, array_column($response->json('data.assets'), 'id'));
        $this->assertContains(
            MaintenanceType::where('code', 'CORRECTIVE')->firstOrFail()->id,
            array_column($response->json('data.maintenance_types'), 'id'),
        );
    }

    /**
     * Mirrors the web `WorkOrderController::formOptions()`'s own template
     * list exactly: published versions only, so a work order created from
     * a template is checklisted against the version a technician cannot
     * see change underneath them mid-job. A template with only a draft
     * version (never published) is filtered out the same way.
     */
    public function test_form_options_lists_only_templates_with_a_published_version(): void
    {
        $published = WorkOrderFixture::publishedChecklist($this->delta, [], 'PM-PUBLISHED');
        $draftOnly = MaintenanceTemplate::create([
            'company_id' => $this->delta->id,
            'name' => 'Draft-only template',
            'code' => 'PM-DRAFT-ONLY',
            'status' => 'ACTIVE',
        ]);
        app(CreateTemplateVersion::class)->handle($draftOnly);

        $response = $this->asEngineer()->getJson('/api/v1/work-orders/form-options')->assertOk();

        $templateIds = array_column($response->json('data.templates'), 'id');
        $this->assertContains($published->template_id, $templateIds);
        $this->assertNotContains($draftOnly->id, $templateIds);

        $entry = collect($response->json('data.templates'))->firstWhere('id', $published->template_id);
        $this->assertSame($published->id, $entry['current_version_id']);
    }

    /**
     * A work order created from a published template checklists itself
     * automatically (`CreateWorkOrder`'s own `template_version_id`
     * handling) — proves the field the Next.js form now sends actually
     * does something, not just that it's accepted.
     */
    public function test_a_work_order_created_from_a_template_inherits_its_checklist(): void
    {
        $published = WorkOrderFixture::publishedChecklist($this->delta, [
            ['label' => 'Guard in place', 'input_type' => 'PASS_FAIL', 'required' => true],
        ], 'PM-INHERIT');

        $response = $this->asManager()->postJson('/api/v1/work-orders', [
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'CORRECTIVE')->firstOrFail()->id,
            'title' => 'From template',
            'priority' => 'MEDIUM',
            'template_version_id' => $published->id,
        ])->assertCreated();

        $checklist = $this->asManager()
            ->getJson('/api/v1/work-orders/'.$response->json('data.id').'/checklist')
            ->assertOk();

        $this->assertSame('Guard in place', $checklist->json('data.items.0.label'));
    }

    public function test_counts_mirror_the_web_kpi_tiles(): void
    {
        $this->createWorkOrder(['work_order_number' => 'WO-1', 'status' => 'DRAFT']);
        $this->createWorkOrder(['work_order_number' => 'WO-2', 'status' => 'IN_PROGRESS']);
        $this->createWorkOrder(['work_order_number' => 'WO-3', 'status' => 'ON_HOLD']);
        $this->createWorkOrder(['work_order_number' => 'WO-4', 'status' => 'COMPLETED', 'requires_verification' => true]);
        // Completed but not awaiting verification — must not count toward it.
        $this->createWorkOrder(['work_order_number' => 'WO-5', 'status' => 'COMPLETED', 'requires_verification' => false]);
        // Closed: not open, and never counted anywhere in this response.
        $this->createWorkOrder(['work_order_number' => 'WO-6', 'status' => 'CLOSED']);

        $response = $this->asManager()->getJson('/api/v1/work-orders/counts')->assertOk();

        // DRAFT, IN_PROGRESS and ON_HOLD are all open statuses.
        $this->assertSame(3, $response->json('data.open'));
        $this->assertSame(1, $response->json('data.in_progress'));
        $this->assertSame(1, $response->json('data.on_hold'));
        $this->assertSame(1, $response->json('data.awaiting_verification'));
    }

    public function test_assignable_technicians_are_reachable_by_a_caller_who_can_assign_but_not_manage_the_roster(): void
    {
        // MAINTENANCE_ENGINEER has work_order.work_order.assign but not
        // technician.technician.manage (GET /technicians' own permission)
        // — the same shape of gap again.
        $workOrder = $this->createWorkOrder();
        $technician = WorkOrderFixture::technician($this->delta, $this->factory);

        $response = $this->asEngineer()
            ->getJson("/api/v1/work-orders/{$workOrder->id}/assignable-technicians")
            ->assertOk();

        $this->assertContains($technician->id, array_column($response->json('data'), 'id'));
    }

    public function test_assigned_to_me_lists_only_this_callers_own_open_assignments(): void
    {
        // Mirrors `MyWorkController::index` — the technician's own queue,
        // not a filtered slice of everyone's, with its own fixed ordering
        // rather than whatever `sort` a caller might otherwise pass.
        $technicianUser = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test', $this->factory->id);
        $technician = WorkOrderFixture::technician($this->delta, $this->factory, 'Karim Mia', 'EMP-9001', $technicianUser);
        $technicianToken = app(IssueApiToken::class)->forUser($technicianUser, $this->delta->id, 'x')['plain'];

        $mine = $this->createWorkOrder(['title' => 'Assigned to me']);
        $this->asManager()->postJson("/api/v1/work-orders/{$mine->id}/submit-for-approval")->assertOk();
        $this->asManager()->postJson("/api/v1/work-orders/{$mine->id}/assign", ['technician_ids' => [$technician->id]])
            ->assertOk();

        // A draft nobody is assigned to yet — must not appear in the queue.
        $this->createWorkOrder(['title' => 'Not assigned to anyone']);

        // Assigned to a different technician entirely.
        $someoneElse = WorkOrderFixture::technician($this->delta, $this->factory, 'Rahim Uddin', 'EMP-9002');
        $notMine = $this->createWorkOrder(['title' => 'Assigned to someone else']);
        $this->asManager()->postJson("/api/v1/work-orders/{$notMine->id}/submit-for-approval")->assertOk();
        $this->asManager()->postJson("/api/v1/work-orders/{$notMine->id}/assign", ['technician_ids' => [$someoneElse->id]])
            ->assertOk();

        $response = $this->withHeader('Authorization', 'Bearer '.$technicianToken)
            ->getJson('/api/v1/work-orders?assigned_to_me=true')
            ->assertOk();

        $response->assertJsonCount(1, 'data');
        $this->assertSame($mine->id, $response->json('data.0.id'));
    }

    public function test_assigned_to_me_is_empty_for_a_person_with_no_technician_record(): void
    {
        $this->asManager()->getJson('/api/v1/work-orders?assigned_to_me=true')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_show_includes_the_maintenance_type_and_current_assignments(): void
    {
        $workOrder = $this->createWorkOrder();
        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/submit-for-approval")->assertOk();

        $technician = WorkOrderFixture::technician($this->delta, $this->factory);
        $this->asManager()->postJson("/api/v1/work-orders/{$workOrder->id}/assign", [
            'technician_ids' => [$technician->id],
        ])->assertOk();

        $response = $this->asManager()->getJson('/api/v1/work-orders/'.$workOrder->id)->assertOk();

        $this->assertSame('Corrective maintenance', $response->json('data.maintenance_type'));
        $this->assertCount(1, $response->json('data.assignments'));
        $this->assertSame($technician->id, $response->json('data.assignments.0.technician_id'));
    }

    private function createWorkOrder(array $overrides = []): WorkOrder
    {
        return WorkOrder::create(array_merge([
            'factory_id' => $this->factory->id,
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'CORRECTIVE')->firstOrFail()->id,
            'work_order_number' => 'WO-'.random_int(10000, 99999),
            'title' => 'Bearing noise on head 3',
            'status' => 'DRAFT',
            'priority' => 'HIGH',
            'source' => 'MANUAL',
        ], $overrides));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Procurement;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalWorkflow;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\Notification\Models\Notification;
use App\Modules\Procurement\Models\PurchaseRequisition;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ItemFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Purchase requisitions over the API (Procurement spec §5).
 *
 * Draft, submit, approve — with and without a configured chain — and the
 * §2.10 rule that a retired item blocks a submit but not a signed document.
 */
class PurchaseRequisitionApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $storekeeper;

    private User $manager;

    private User $technician;

    private Item $bolt;

    private Item $dye;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->storekeeper = TenantFixture::user($this->delta, 'STOREKEEPER', 'sk@delta.test');
        $this->manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->bolt = ItemFixture::item($this->delta);
        $this->dye = ItemFixture::item($this->delta, 'CHM-DYE-BLK', 'Reactive black dye', [
            'item_type' => 'CHEMICAL', 'tracking_mode' => 'LOT', 'uom_id' => ItemFixture::uomId($this->delta, 'KG'),
        ]);
    }

    public function test_a_draft_snapshots_its_lines_and_submits_straight_to_approved_without_a_workflow(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        $created = $this->withToken($token)
            ->postJson('/api/v1/purchase-requisitions', [
                'factory_id' => $this->dhaka->id,
                'priority' => 'HIGH',
                'notes' => 'Line 4 restart',
                'lines' => [
                    ['item_id' => $this->bolt->id, 'requested_qty' => '200', 'estimated_rate' => '2.50'],
                    ['item_id' => $this->dye->id, 'requested_qty' => '25', 'estimated_rate' => '900', 'required_date' => '2026-10-01'],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.approval_status', 'NOT_REQUIRED')
            ->assertJsonPath('data.lines.0.item_code', 'GEN-BOLT-M8')
            ->assertJsonPath('data.lines.0.current_stock_qty', '0.0000')
            ->assertJsonPath('data.lines.1.uom.code', 'KG')
            ->assertJsonPath('data.can_submit', true)
            ->json('data');

        $this->assertMatchesRegularExpression('/^PR-DHK-\d{4}-\d{4}$/', $created['pr_no']);

        // Retire the bolt and a resubmit must name it; restore and it goes through.
        $this->bolt->forceFill(['active' => false])->save();

        $this->withToken($token)
            ->postJson("/api/v1/purchase-requisitions/{$created['id']}/submit")
            ->assertStatus(409)
            ->assertJsonPath('errors.lines.0', fn (string $m) => str_contains($m, 'GEN-BOLT-M8'));

        $this->bolt->forceFill(['active' => true])->save();

        $submitted = $this->withToken($token)
            ->postJson("/api/v1/purchase-requisitions/{$created['id']}/submit")
            ->assertOk()
            // No workflow for requisitions: nothing to sign, so approved at once.
            ->assertJsonPath('data.status', 'APPROVED')
            ->assertJsonPath('data.approval_status', 'NOT_REQUIRED')
            ->assertJsonPath('data.estimated_total', '23000.0000')
            ->assertJsonPath('data.can_submit', false)
            ->json('data');

        $this->assertNotNull($submitted['submitted_at']);
        $this->assertNotNull($submitted['approved_at']);

        // The snapshot outlives the item's name.
        $this->bolt->forceFill(['name' => 'Renamed bolt'])->save();
        $this->withToken($token)
            ->getJson("/api/v1/purchase-requisitions/{$created['id']}")
            ->assertJsonPath('data.lines.0.item_name', 'Hex bolt M8 x 40');
    }

    public function test_a_workflow_routes_the_requisition_through_the_approval_engine(): void
    {
        $this->workflowRequiringManagerAbove('10000');

        $token = $this->tokenFor($this->storekeeper);

        // Below the threshold: no step applies, straight through.
        $small = $this->createDraft($token, [['item_id' => $this->bolt->id, 'requested_qty' => '10', 'estimated_rate' => '2']]);
        $this->withToken($token)->postJson("/api/v1/purchase-requisitions/{$small}/submit")
            ->assertOk()->assertJsonPath('data.status', 'APPROVED')->assertJsonPath('data.approval_status', 'NOT_REQUIRED');

        // Above it: pending, with a request the manager can act on.
        $big = $this->createDraft($token, [['item_id' => $this->dye->id, 'requested_qty' => '25', 'estimated_rate' => '900']]);
        $pending = $this->withToken($token)->postJson("/api/v1/purchase-requisitions/{$big}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'PENDING_APPROVAL')
            ->assertJsonPath('data.approval_status', 'PENDING')
            ->assertJsonPath('data.approval_request.can_act', false)
            ->json('data');

        $requestId = $pending['approval_request']['id'];
        $request = ApprovalRequest::findOrFail($requestId);
        $this->assertSame('PURCHASE_REQUISITION', $request->entity_type);
        $this->assertSame('22500.0000', $request->context_json['cost']);

        // The manager was told, and sees the requisition named on the approvals list.
        $this->assertSame(1, Notification::where('event_type', 'APPROVAL_REQUESTED')->where('user_id', $this->manager->id)->count());

        $managerToken = $this->tokenFor($this->manager);
        $this->withToken($managerToken)->getJson('/api/v1/approval-requests')
            ->assertOk()
            ->assertJsonPath('data.0.entity.type', 'PURCHASE_REQUISITION')
            ->assertJsonPath('data.0.entity.number', $pending['pr_no'])
            ->assertJsonPath('data.0.can_act', true);

        $this->withToken($managerToken)->postJson("/api/v1/approval-requests/{$requestId}/approve", ['comment' => 'Go ahead'])
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');

        $this->withToken($token)->getJson("/api/v1/purchase-requisitions/{$big}")
            ->assertJsonPath('data.status', 'APPROVED')
            ->assertJsonPath('data.approval_status', 'APPROVED')
            ->assertJsonPath('data.can_create_order', false);

        // The requester hears the verdict.
        $this->assertSame(1, Notification::where('event_type', 'APPROVAL_DECIDED')->where('user_id', $this->storekeeper->id)->count());

        // And a rejection lands as REJECTED on the document.
        $rejected = $this->createDraft($token, [['item_id' => $this->dye->id, 'requested_qty' => '30', 'estimated_rate' => '900']]);
        $req = $this->withToken($token)->postJson("/api/v1/purchase-requisitions/{$rejected}/submit")->json('data.approval_request.id');
        $this->withToken($managerToken)->postJson("/api/v1/approval-requests/{$req}/reject", ['comment' => 'Too much dye'])->assertOk();

        $this->assertSame('REJECTED', PurchaseRequisition::findOrFail($rejected)->status);

        // Cancelling a requisition that is waiting on an approver withdraws
        // the request too, so nothing lingers on the approvals list.
        $withdrawn = $this->createDraft($token, [['item_id' => $this->dye->id, 'requested_qty' => '40', 'estimated_rate' => '900']]);
        $req = $this->withToken($token)->postJson("/api/v1/purchase-requisitions/{$withdrawn}/submit")->json('data.approval_request.id');
        $this->withToken($token)->postJson("/api/v1/purchase-requisitions/{$withdrawn}/cancel", ['reason' => 'Not needed'])
            ->assertOk()->assertJsonPath('data.status', 'CANCELLED')->assertJsonPath('data.approval_status', 'NOT_REQUIRED');
        $this->assertSame('CANCELLED', ApprovalRequest::findOrFail($req)->status);
    }

    public function test_only_a_draft_can_be_edited_and_open_duplicates_are_a_warning_not_a_block(): void
    {
        $token = $this->tokenFor($this->storekeeper);

        $first = $this->createDraft($token, [['item_id' => $this->bolt->id, 'requested_qty' => '10']]);
        $second = $this->createDraft($token, [['item_id' => $this->bolt->id, 'requested_qty' => '5']]);

        $this->withToken($token)->getJson("/api/v1/purchase-requisitions/{$second}")
            ->assertJsonPath('open_duplicates', null)
            ->assertJsonPath('data.open_duplicates.0.id', $first)
            ->assertJsonPath('data.open_duplicates.0.items.0', 'GEN-BOLT-M8');

        $this->withToken($token)->patchJson("/api/v1/purchase-requisitions/{$first}", [
            'priority' => 'URGENT',
            'lines' => [['item_id' => $this->dye->id, 'requested_qty' => '3']],
        ])->assertOk()->assertJsonPath('data.priority', 'URGENT')->assertJsonPath('data.lines.0.item_code', 'CHM-DYE-BLK');

        $this->withToken($token)->postJson("/api/v1/purchase-requisitions/{$first}/submit")->assertOk();

        $this->withToken($token)->patchJson("/api/v1/purchase-requisitions/{$first}", [
            'lines' => [['item_id' => $this->dye->id, 'requested_qty' => '4']],
        ])->assertStatus(409);

        $this->withToken($token)->postJson("/api/v1/purchase-requisitions/{$second}/cancel", ['reason' => 'Duplicate'])
            ->assertOk()->assertJsonPath('data.status', 'CANCELLED');
    }

    public function test_the_manager_reads_but_does_not_raise_and_a_technician_sees_nothing(): void
    {
        $token = $this->tokenFor($this->storekeeper);
        $id = $this->createDraft($token, [['item_id' => $this->bolt->id, 'requested_qty' => '10']]);

        $this->withToken($this->tokenFor($this->manager))->getJson('/api/v1/purchase-requisitions?status=DRAFT')
            ->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('meta.total', 1);

        $this->withToken($this->tokenFor($this->manager))->postJson('/api/v1/purchase-requisitions', [
            'factory_id' => $this->dhaka->id, 'lines' => [['item_id' => $this->bolt->id, 'requested_qty' => '1']],
        ])->assertForbidden();

        $this->withToken($this->tokenFor($this->technician))->getJson('/api/v1/purchase-requisitions')->assertForbidden();

        $this->withToken($token)->getJson('/api/v1/purchase-requisitions/form-options')
            ->assertOk()->assertJsonPath('data.factories.0.id', $this->dhaka->id);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function createDraft(string $token, array $lines): string
    {
        return $this->withToken($token)
            ->postJson('/api/v1/purchase-requisitions', ['factory_id' => $this->dhaka->id, 'lines' => $lines])
            ->assertCreated()
            ->json('data.id');
    }

    private function workflowRequiringManagerAbove(string $minCost): void
    {
        $workflow = ApprovalWorkflow::create([
            'company_id' => $this->delta->id, 'name' => 'PR sign-off', 'entity_type' => 'PURCHASE_REQUISITION', 'active' => true,
        ]);

        ApprovalRule::create([
            'company_id' => $this->delta->id,
            'workflow_id' => $workflow->id,
            'name' => 'Manager',
            'sequence' => 1,
            'role_id' => Role::whereNull('company_id')->where('name', 'MAINTENANCE_MANAGER')->firstOrFail()->id,
            'condition_json' => ['min_cost' => $minCost],
        ]);
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}

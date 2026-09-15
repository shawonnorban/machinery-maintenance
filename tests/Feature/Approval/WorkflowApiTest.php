<?php

declare(strict_types=1);

namespace Tests\Feature\Approval;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalWorkflow;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Who has to sign, and above what, over the API (SRS 14) — mirrors the web
 * `WorkflowConfigurationTest`'s approval-chain scenarios exactly, since both
 * sides call the same `ManageApprovalWorkflow` action per ADR-003.
 */
class WorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $owner;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
    }

    private function role(string $code): Role
    {
        return Role::whereNull('company_id')->where('name', $code)->firstOrFail();
    }

    private function workflow(): string
    {
        return $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/approval-workflows', ['name' => 'Work order approvals', 'entity_type' => 'WORK_ORDER'])
            ->assertCreated()
            ->json('data.id');
    }

    public function test_a_company_can_write_its_own_chain(): void
    {
        $workflowId = $this->workflow();

        $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/v1/approval-workflows/{$workflowId}/rules", [
                'name' => 'Factory manager signs above 50,000',
                'role_id' => $this->role('FACTORY_MANAGER')->id,
                'min_cost' => '50000',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sequence', 1)
            ->assertJsonPath('data.conditions.min_cost', '50000.0000');

        $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/v1/approval-workflows/{$workflowId}/rules", [
                'name' => 'Owner signs above 500,000',
                'role_id' => $this->role('COMPANY_OWNER')->id,
                'min_cost' => '500000',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sequence', 2);

        $this->withToken($this->tokenFor($this->owner))
            ->getJson('/api/v1/approval-workflows')
            ->assertOk()
            ->assertJsonCount(2, 'data.0.rules');
    }

    /** A step with no condition matches everything, which would send a needle change to the company owner. */
    public function test_a_step_without_a_condition_is_refused(): void
    {
        $workflowId = $this->workflow();

        $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/v1/approval-workflows/{$workflowId}/rules", [
                'name' => 'Everything',
                'role_id' => $this->role('COMPANY_OWNER')->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('min_cost');

        $this->assertSame(0, ApprovalRule::where('workflow_id', $workflowId)->count());
    }

    public function test_removing_a_step_closes_the_gap_in_the_chain(): void
    {
        $workflowId = $this->workflow();

        foreach ([['A', '10000'], ['B', '50000'], ['C', '100000']] as [$name, $cost]) {
            $this->withToken($this->tokenFor($this->owner))
                ->postJson("/api/v1/approval-workflows/{$workflowId}/rules", [
                    'name' => $name,
                    'role_id' => $this->role('FACTORY_MANAGER')->id,
                    'min_cost' => $cost,
                ]);
        }

        $middle = ApprovalRule::where('workflow_id', $workflowId)->where('name', 'B')->firstOrFail();

        $this->withToken($this->tokenFor($this->owner))
            ->deleteJson("/api/v1/approval-workflows/{$workflowId}/rules/{$middle->id}")
            ->assertNoContent();

        $remaining = ApprovalRule::where('workflow_id', $workflowId)->orderBy('sequence')->get();

        $this->assertSame([1, 2], $remaining->pluck('sequence')->all());
        $this->assertSame(['A', 'C'], $remaining->pluck('name')->all());
    }

    public function test_a_rule_from_another_workflow_cannot_be_removed_through_this_one(): void
    {
        $workflowId = $this->workflow();

        $otherId = $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/approval-workflows', ['name' => 'Transfer approvals', 'entity_type' => 'INVENTORY_TRANSFER'])
            ->json('data.id');

        $rule = $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/v1/approval-workflows/{$otherId}/rules", [
                'name' => 'Store manager',
                'role_id' => $this->role('STORE_MANAGER')->id,
                'min_cost' => '1000',
            ])
            ->json('data.id');

        $this->withToken($this->tokenFor($this->owner))
            ->deleteJson("/api/v1/approval-workflows/{$workflowId}/rules/{$rule}")
            ->assertNotFound();

        $this->assertNotNull(ApprovalRule::find($rule));
    }

    public function test_toggling_flips_active_and_the_list_reports_how_often_the_chain_has_been_used(): void
    {
        $workflowId = $this->workflow();

        $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/v1/approval-workflows/{$workflowId}/toggle")
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.request_count', 0);

        $this->assertFalse(ApprovalWorkflow::findOrFail($workflowId)->active);
    }

    public function test_the_endpoints_are_closed_to_a_role_that_does_not_configure_the_company(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/approval-workflows')
            ->assertForbidden();
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}

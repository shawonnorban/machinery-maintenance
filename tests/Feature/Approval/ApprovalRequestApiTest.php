<?php

declare(strict_types=1);

namespace Tests\Feature\Approval;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Models\ApprovalWorkflow;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Approvals, over the API (API 27, SRS 14).
 */
class ApprovalRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $manager;

    private User $requester;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'manager@delta.test');
        $this->requester = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'engineer@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
    }

    private function pendingRequest(?User $requestedBy = null): ApprovalRequest
    {
        $workflow = ApprovalWorkflow::create([
            'company_id' => $this->delta->id,
            'name' => 'High-value repairs',
            'entity_type' => 'WORK_ORDER',
            'active' => true,
        ]);

        $role = Role::whereNull('company_id')->where('name', 'MAINTENANCE_MANAGER')->firstOrFail();

        ApprovalRule::create([
            'company_id' => $this->delta->id,
            'workflow_id' => $workflow->id,
            'name' => 'Manager sign-off',
            'sequence' => 1,
            'role_id' => $role->id,
            'condition_json' => [],
        ]);

        return ApprovalRequest::create([
            'company_id' => $this->delta->id,
            'workflow_id' => $workflow->id,
            'entity_type' => 'WORK_ORDER',
            'entity_id' => (string) Str::ulid(),
            'status' => 'PENDING',
            'current_step' => 1,
            'total_steps' => 1,
            'requested_by' => ($requestedBy ?? $this->requester)->id,
            'requested_at' => CarbonImmutable::now(),
            'context_json' => ['cost' => '50000.0000'],
        ]);
    }

    public function test_a_pending_request_can_be_listed_and_approved(): void
    {
        $approval = $this->pendingRequest();

        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/approval-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approval->id);

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/approval-requests/{$approval->id}/approve", ['comment' => 'Looks correct'])
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');
    }

    public function test_the_requester_cannot_approve_their_own_request(): void
    {
        $approval = $this->pendingRequest();

        $this->withToken($this->tokenFor($this->requester))
            ->postJson("/api/v1/approval-requests/{$approval->id}/approve")
            ->assertStatus(403);
    }

    public function test_someone_outside_the_current_step_cannot_act(): void
    {
        $approval = $this->pendingRequest();

        // The technician holds neither the role the rule names nor the
        // approval.request.approve permission at all.
        $this->withToken($this->tokenFor($this->technician))
            ->postJson("/api/v1/approval-requests/{$approval->id}/approve")
            ->assertForbidden();
    }

    public function test_a_rejection_needs_a_reason(): void
    {
        $approval = $this->pendingRequest();

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/approval-requests/{$approval->id}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('comment');

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/approval-requests/{$approval->id}/reject", [
                'comment' => 'Quote looks inflated, get a second one',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'REJECTED');
    }

    /**
     * `can_act` and `meta.counts.mine` mirror what the web index computes
     * locally (`ApprovalController::index`'s `$actionable`/`$counts`) — a
     * client rendering an approve button that would 403 teaches people to
     * distrust the screen, so the API has to say who can act, not just list
     * the rows.
     */
    public function test_the_list_says_which_requests_the_caller_can_act_on(): void
    {
        $this->pendingRequest();

        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/approval-requests')
            ->assertOk()
            ->assertJsonPath('data.0.can_act', true)
            ->assertJsonPath('meta.counts.pending', 1)
            ->assertJsonPath('meta.counts.mine', 1);
    }

    /**
     * The requester may never approve their own request, and the "mine"
     * count has to agree — a manager who happens to be the one who filed
     * this particular request cannot use it to hit their own approval
     * quota.
     */
    public function test_the_caller_cannot_act_on_their_own_request(): void
    {
        $this->pendingRequest($this->manager);

        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/approval-requests')
            ->assertOk()
            ->assertJsonPath('data.0.can_act', false)
            ->assertJsonPath('meta.counts.pending', 1)
            ->assertJsonPath('meta.counts.mine', 0);
    }

    public function test_a_decided_request_cannot_be_decided_again(): void
    {
        $approval = $this->pendingRequest();

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/approval-requests/{$approval->id}/approve")
            ->assertOk();

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/approval-requests/{$approval->id}/approve")
            ->assertStatus(409);
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}

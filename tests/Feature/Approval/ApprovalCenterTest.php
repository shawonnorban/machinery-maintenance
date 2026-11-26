<?php

declare(strict_types=1);

namespace Tests\Feature\Approval;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Approval\Models\ApprovalAction;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRequestType;
use App\Modules\Approval\Models\ApprovalTicket;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\Notification;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Approval Center (AnnotechERP 04) and the Approval Workflow Designer (02/03):
 * hand-written requests through Request To and the type's chain, return for
 * revision, the lists and who sees them, and the designer's safeguards.
 */
class ApprovalCenterTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private User $merchant;

    private User $manager;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->merchant = TenantFixture::user($this->delta, 'STORE_MANAGER', 'merchant@delta.test');
        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'manager@delta.test');
        $this->director = TenantFixture::user($this->delta, 'STORE_MANAGER', 'director@delta.test');
    }

    public function test_request_types_start_with_brickland_defaults_and_validate(): void
    {
        $rows = $this->as($this->owner)->getJson('/api/v1/erp/approval-request-types')->assertOk()->json('data');
        $this->assertCount(14, $rows);
        $this->assertSame('FABRIC-BOOKING', $rows[0]['code']);

        $this->as($this->owner)->postJson('/api/v1/erp/approval-request-types', ['code' => 'fabric-booking', 'name' => 'Dup', 'module' => 'X'])->assertUnprocessable();
        $this->as($this->owner)->postJson('/api/v1/erp/approval-request-types', ['code' => 'wash-approval', 'name' => 'Wash', 'module' => 'Washing', 'due_days' => 2])
            ->assertCreated()->assertJsonPath('data.code', 'WASH-APPROVAL');
        // A store manager may raise requests but not reshape the types.
        $this->as($this->merchant)->postJson('/api/v1/erp/approval-request-types', ['code' => 'X1', 'name' => 'X', 'module' => 'X'])->assertForbidden();
    }

    public function test_a_request_goes_to_request_to_then_through_the_chain_by_amount(): void
    {
        $chain = $this->as($this->owner)->postJson('/api/v1/approval-center/workflows', ['name' => 'Fabric chain', 'document_type' => 'Fabric Booking', 'entity_type' => 'MANUAL_REQUEST'])
            ->assertCreated()->assertJsonPath('data.type_code', 'AT-001')->json('data.id');
        $steps = $this->as($this->owner)->putJson("/api/v1/approval-center/workflows/{$chain}/steps", ['steps' => [
            ['sequence' => 1, 'name' => 'Raise', 'level' => 0],
            ['sequence' => 2, 'name' => 'Director sign-off', 'level' => 1, 'min_cost' => 10000],
        ]])->assertOk()->json('data.steps');
        $this->as($this->owner)->putJson("/api/v1/approval-center/steps/{$steps[1]['id']}/users", ['user_ids' => [$this->director->id]])->assertOk();

        $type = $this->typeId('FABRIC-BOOKING');
        $this->as($this->owner)->patchJson("/api/v1/erp/approval-request-types/{$type}", ['workflow_id' => $chain])->assertOk();

        // A draft: no number, nobody told, only its author sees it.
        $draft = $this->as($this->merchant)->postJson('/api/v1/approval-center/tickets', ['request_type_id' => $type, 'reference_no' => 'FB-1', 'amount' => 25000, 'currency' => 'USD'])
            ->assertCreated()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.request_no', null)->json('data.id');
        $this->as($this->merchant)->getJson('/api/v1/approval-center/requests?scope=mine')->assertJsonPath('data.0.status', 'DRAFT');
        $this->as($this->manager)->getJson("/api/v1/approval-center/tickets/{$draft}")->assertNotFound();

        // Submitting needs Request To, and not oneself.
        $this->as($this->merchant)->postJson("/api/v1/approval-center/tickets/{$draft}/submit")->assertUnprocessable();
        $this->as($this->merchant)->patchJson("/api/v1/approval-center/tickets/{$draft}", ['request_to_user_id' => $this->merchant->id, 'submit' => true])->assertUnprocessable();
        $ticket = $this->as($this->merchant)->patchJson("/api/v1/approval-center/tickets/{$draft}", ['request_to_user_id' => $this->manager->id, 'buyer_name' => 'H&M', 'submit' => true])
            ->assertOk()->assertJsonPath('data.status', 'PENDING')->json('data');
        $this->assertStringStartsWith('APR-', $ticket['request_no']);
        $request = $ticket['approval_request_id'];

        // Level 1 is Request To; the level-0 working step is not a level; 25,000 is in the director's band.
        $detail = $this->as($this->manager)->getJson("/api/v1/approval-center/requests/{$request}")->assertOk()->assertJsonPath('data.can_act', true)->json('data');
        $this->assertCount(2, $detail['levels']);
        // Not yet theirs: to the director the request does not exist.
        $this->as($this->director)->postJson("/api/v1/approval-center/requests/{$request}/approve")->assertNotFound();
        $this->as($this->manager)->getJson('/api/v1/approval-center/requests?scope=assigned')->assertJsonCount(1, 'data');

        $this->as($this->manager)->postJson("/api/v1/approval-center/requests/{$request}/approve")->assertOk()->assertJsonPath('data.status', 'PENDING')->assertJsonPath('data.level', 2);
        $this->as($this->director)->postJson("/api/v1/approval-center/requests/{$request}/approve")->assertOk()->assertJsonPath('data.status', 'APPROVED');

        TenantFixture::actingAsTenant($this->delta);
        $this->assertSame('APPROVED', ApprovalTicket::query()->findOrFail($draft)->status);
        $this->assertSame(['SUBMITTED', 'APPROVED', 'APPROVED'], ApprovalAction::query()->where('approval_request_id', $request)->orderBy('acted_at')->orderBy('id')->pluck('action')->all());
        $this->as($this->merchant)->getJson('/api/v1/approval-center/requests?scope=approved')->assertJsonPath('data.0.decided_by', $this->director->name);
    }

    public function test_a_small_amount_skips_the_band_and_a_returned_request_comes_back_as_a_new_round(): void
    {
        $type = $this->typeId('GENERAL');
        $request = $this->submit($type, 500)['approval_request_id'];

        $this->as($this->manager)->postJson("/api/v1/approval-center/requests/{$request}/return")->assertUnprocessable();
        $this->as($this->manager)->postJson("/api/v1/approval-center/requests/{$request}/return", ['comment' => 'Attach the PI'])->assertOk()->assertJsonPath('data.status', 'RETURNED');
        $this->as($this->merchant)->getJson('/api/v1/approval-center/requests?scope=returned')->assertJsonPath('data.0.reason', 'Attach the PI');

        TenantFixture::actingAsTenant($this->delta);
        $ticket = ApprovalTicket::query()->where('approval_request_id', $request)->firstOrFail();
        $this->assertSame('RETURNED', $ticket->status);

        $again = $this->as($this->merchant)->patchJson("/api/v1/approval-center/tickets/{$ticket->id}", ['remarks' => 'PI attached', 'submit' => true])->assertOk()->json('data');
        $this->assertNotSame($request, $again['approval_request_id']);
        $this->assertSame($ticket->request_no, $again['request_no']);

        // Shown once, by its new round; the history keeps both rounds.
        $this->as($this->merchant)->getJson('/api/v1/approval-center/requests?scope=all')->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'PENDING');
        $history = $this->as($this->merchant)->getJson("/api/v1/approval-center/requests/{$again['approval_request_id']}")->json('data.history');
        $this->assertSame(['SUBMITTED', 'RETURNED', 'RESUBMITTED'], array_column($history, 'action'));

        $this->as($this->manager)->postJson("/api/v1/approval-center/requests/{$again['approval_request_id']}/reject", ['comment' => 'No budget'])->assertOk()->assertJsonPath('data.status', 'REJECTED');
        $this->as($this->owner)->getJson('/api/v1/approval-center/history?action=REJECTED')->assertJsonCount(1, 'data')->assertJsonPath('data.0.comment', 'No budget');
    }

    public function test_people_see_only_what_concerns_them(): void
    {
        $request = $this->submit($this->typeId('GENERAL'), 100)['approval_request_id'];

        $this->as($this->director)->getJson('/api/v1/approval-center/requests?scope=all')->assertJsonCount(0, 'data');
        $this->as($this->director)->getJson("/api/v1/approval-center/requests/{$request}")->assertNotFound();
        $this->as($this->owner)->getJson('/api/v1/approval-center/requests?scope=all')->assertJsonCount(1, 'data');
        $this->as($this->manager)->getJson('/api/v1/approval-center/requests?scope=pending')->assertJsonCount(1, 'data')->assertJsonPath('meta.stats.pending', 1);

        // The requester withdraws it; it cannot be decided afterwards.
        TenantFixture::actingAsTenant($this->delta);
        $ticketId = ApprovalTicket::query()->where('approval_request_id', $request)->value('id');
        $this->as($this->merchant)->postJson("/api/v1/approval-center/tickets/{$ticketId}/cancel")->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->as($this->manager)->postJson("/api/v1/approval-center/requests/{$request}/approve")->assertStatus(409);
    }

    public function test_a_chain_with_waiting_requests_keeps_its_shape(): void
    {
        $chain = $this->as($this->owner)->postJson('/api/v1/approval-center/workflows', ['name' => 'Payment chain', 'document_type' => 'Payment', 'entity_type' => 'MANUAL_REQUEST'])->json('data.id');
        $steps = $this->as($this->owner)->putJson("/api/v1/approval-center/workflows/{$chain}/steps", ['steps' => [['sequence' => 1, 'name' => 'Finance', 'level' => 1]]])->json('data.steps');
        $this->as($this->owner)->putJson("/api/v1/approval-center/steps/{$steps[0]['id']}/users", ['user_ids' => [$this->director->id]])->assertOk();
        $type = $this->typeId('PAYMENT-APPROVAL');
        $this->as($this->owner)->patchJson("/api/v1/erp/approval-request-types/{$type}", ['workflow_id' => $chain])->assertOk();
        $this->submit($type, 100);

        // Renaming is fine; adding a step is not while one waits.
        $this->as($this->owner)->putJson("/api/v1/approval-center/workflows/{$chain}/steps", ['steps' => [['id' => $steps[0]['id'], 'sequence' => 1, 'name' => 'Finance head', 'level' => 1]]])->assertOk();
        $this->as($this->owner)->putJson("/api/v1/approval-center/workflows/{$chain}/steps", ['steps' => [
            ['id' => $steps[0]['id'], 'sequence' => 1, 'name' => 'Finance head', 'level' => 1],
            ['sequence' => 2, 'name' => 'Owner', 'level' => 2],
        ]])->assertUnprocessable();

        // Only this company's people can be assigned.
        $other = TenantFixture::company('Other Ltd', 'OTH');
        $stranger = TenantFixture::user($other, 'COMPANY_OWNER', 'stranger@other.test');
        TenantFixture::actingAsTenant($this->delta);
        $this->as($this->owner)->putJson("/api/v1/approval-center/steps/{$steps[0]['id']}/users", ['user_ids' => [$stranger->id]])->assertUnprocessable();
    }

    public function test_the_dashboard_counts_what_the_person_may_see_and_overdue_requests_escalate_once(): void
    {
        $type = $this->typeId('GENERAL');
        $this->as($this->owner)->patchJson("/api/v1/erp/approval-request-types/{$type}", ['escalate_to_user_id' => $this->owner->id, 'escalate_after_hours' => 1])->assertOk();
        $first = $this->submit($type, 100)['approval_request_id'];
        $this->submit($type, 200);
        $this->as($this->manager)->postJson("/api/v1/approval-center/requests/{$first}/reject", ['comment' => 'No budget'])->assertOk();

        $dash = $this->as($this->owner)->getJson('/api/v1/approval-center/dashboard')->assertOk()->json('data');
        $this->assertSame(2, $dash['tiles']['total']);
        $this->assertSame(1, $dash['tiles']['pending']);
        $this->assertSame([['label' => 'No budget', 'count' => 1]], $dash['rejected_reasons']);
        $this->assertSame(0, $this->as($this->director)->getJson('/api/v1/approval-center/dashboard')->json('data.tiles.total'));

        // Two days late, past the one-hour grace: told once, not every run.
        TenantFixture::actingAsTenant($this->delta);
        ApprovalRequest::query()->where('status', 'PENDING')->update(['due_at' => now()->subDays(2)]);
        $this->artisan('approval:escalate-overdue')->assertSuccessful();
        $this->artisan('approval:escalate-overdue')->assertSuccessful();
        TenantFixture::actingAsTenant($this->delta);
        $this->assertSame(1, Notification::query()->where('event_type', 'APPROVAL_OVERDUE')->where('user_id', $this->owner->id)->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function submit(string $type, float $amount): array
    {
        return $this->as($this->merchant)->postJson('/api/v1/approval-center/tickets', [
            'request_type_id' => $type, 'reference_no' => 'REF-'.$amount, 'amount' => $amount, 'currency' => 'BDT',
            'request_to_user_id' => $this->manager->id, 'submit' => true,
        ])->assertCreated()->json('data');
    }

    private function typeId(string $code): string
    {
        $this->as($this->owner)->getJson('/api/v1/approval-center/form-options')->assertOk();
        TenantFixture::actingAsTenant($this->delta);

        return ApprovalRequestType::query()->where('code', $code)->value('id');
    }

    private function as(User $user): static
    {
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}

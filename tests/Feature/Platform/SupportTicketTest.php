<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\Notification;
use App\Modules\Platform\Actions\ManageSupportTicket;
use App\Modules\Platform\Models\SupportTicket;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformFixture;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * A customer asking the platform something, in writing (SRS 5).
 *
 * The opposite direction from a support grant: a ticket touches nobody's
 * data, so it carries none of a grant's ceremony — no reason length minimum,
 * no expiry. What it has to get right instead is who is told: platform staff
 * hear about a new ticket without going to look, and the customer hears about
 * a reply the same way, and neither side is told about its own message.
 */
class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private string $staffToken;

    private User $colleague;

    private Company $delta;

    private User $owner;

    private string $ownerToken;

    private User $otherTenantUser;

    private string $otherTenantToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->ownerToken = app(IssueApiToken::class)->forUser($this->owner, $this->delta->id, 'x')['plain'];

        // A different member of the same company — not the ticket's opener.
        $this->otherTenantUser = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'other@delta.test');
        $this->otherTenantToken = app(IssueApiToken::class)->forUser($this->otherTenantUser, $this->delta->id, 'x')['plain'];

        $this->staff = PlatformFixture::staff();
        $this->staffToken = PlatformFixture::token($this->staff);
        $this->colleague = PlatformFixture::staff('second@platform.test', 'Second Administrator');
    }

    public function test_a_customer_can_open_a_ticket(): void
    {
        $this->actingAs($this->owner)
            ->post('/app/support/tickets', [
                'subject' => 'Work orders missing after a factory transfer',
                'body' => 'We moved an asset from Dhaka Unit 1 and its work order history disappeared.',
            ])
            ->assertRedirect();

        $ticket = SupportTicket::firstOrFail();

        $this->assertSame($this->delta->id, $ticket->company_id);
        $this->assertSame($this->owner->id, $ticket->opened_by);
        $this->assertSame('OPEN', $ticket->status);
        $this->assertCount(1, $ticket->messages);
        $this->assertFalse($ticket->messages->first()->author_is_platform);
    }

    public function test_platform_staff_are_told_a_ticket_was_opened(): void
    {
        $this->openTicket();

        $notification = Notification::withoutGlobalScope(TenantScope::class)
            ->where('event_type', 'PLATFORM_TICKET_OPENED')
            ->firstOrFail();

        $this->assertNull($notification->company_id);
        $this->assertStringContainsString('Delta Apparels', $notification->title);
    }

    public function test_a_customer_only_sees_their_own_tickets(): void
    {
        $this->openTicket();

        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        TenantFixture::factory($omega, 'Savar Unit 1', 'SAV');
        TenantFixture::actingAsTenant($omega);
        $stranger = TenantFixture::user($omega, 'COMPANY_OWNER', 'owner@omega.test');

        $this->signOut();
        $this->actingAs($stranger)->get('/app/support/tickets')->assertOk()->assertDontSee('missing after');

        $ticket = SupportTicket::withoutGlobalScope(TenantScope::class)->firstOrFail();

        // Route-model binding runs through the tenant scope: another
        // company's ticket id resolves to nothing.
        $this->actingAs($stranger)->get('/app/support/tickets/'.$ticket->id)->assertNotFound();
    }

    public function test_platform_staff_can_reply_and_the_customer_is_told(): void
    {
        $ticket = $this->openTicket();

        $this->asStaff()
            ->postJson('/api/v1/platform/tickets/'.$ticket->id.'/reply', ['body' => 'Checked — this was a caching bug, now fixed.'])
            ->assertOk();

        $ticket->refresh();

        // Picked up automatically: a reply from staff on an OPEN ticket moves
        // it to IN_PROGRESS without anybody having to set that by hand.
        $this->assertSame('IN_PROGRESS', $ticket->status);
        $this->assertCount(2, $ticket->messages()->get());

        $notification = Notification::where('event_type', 'TICKET_REPLIED')
            ->where('user_id', $this->owner->id)
            ->firstOrFail();

        $this->assertStringContainsString('Platform Support', $notification->body);
    }

    public function test_a_reply_from_staff_does_not_notify_staff(): void
    {
        $ticket = $this->openTicket();

        $this->asStaff()
            ->postJson('/api/v1/platform/tickets/'.$ticket->id.'/reply', ['body' => 'Looking into it.'])
            ->assertOk();

        // Only the opener's own PLATFORM_TICKET_OPENED notice exists; nothing
        // was raised for the reply staff just wrote themselves.
        $this->assertSame(0, Notification::withoutGlobalScope(TenantScope::class)
            ->where('event_type', 'PLATFORM_TICKET_REPLIED')->count());
    }

    public function test_a_customer_reply_tells_platform_staff_not_the_customer(): void
    {
        $ticket = $this->openTicket();

        $this->actingAs($this->owner)
            ->post('/app/support/tickets/'.$ticket->id.'/reply', ['body' => 'Any update?'])
            ->assertRedirect();

        // Both platform administrators in this test hear about it — there is
        // no author on this side to leave out, unlike a staff reply, which
        // leaves out whoever wrote it. Checked by who, not by a total count:
        // `PlatformAdminSeeder` (`database/seeders/DatabaseSeeder.php`) seeds
        // a real, permanent platform admin alongside these two fixtures, so
        // a bare count would be pinned to however many platform admins exist
        // globally rather than to this property.
        $recipients = Notification::withoutGlobalScope(TenantScope::class)
            ->where('event_type', 'PLATFORM_TICKET_REPLIED')
            ->pluck('user_id');

        $this->assertContains($this->staff->id, $recipients);
        $this->assertContains($this->colleague->id, $recipients);

        $this->assertSame(0, Notification::where('event_type', 'TICKET_REPLIED')
            ->where('user_id', $this->owner->id)->count());
    }

    public function test_resolving_notifies_the_customer_and_a_reply_reopens_it(): void
    {
        $ticket = $this->openTicket();

        $this->asStaff()
            ->patchJson('/api/v1/platform/tickets/'.$ticket->id.'/status', ['status' => 'RESOLVED'])
            ->assertOk();

        $this->assertSame('RESOLVED', $ticket->fresh()->status);
        $this->assertSame(1, Notification::where('event_type', 'TICKET_RESOLVED')
            ->where('user_id', $this->owner->id)->count());

        // "Resolved" was somebody's belief; the customer's own reply is them
        // saying it was wrong, and that reopens it without anybody having to
        // notice and change the status by hand.
        $this->actingAs($this->owner)
            ->post('/app/support/tickets/'.$ticket->id.'/reply', ['body' => 'This is still happening.'])
            ->assertRedirect();

        $this->assertSame('OPEN', $ticket->fresh()->status);
    }

    public function test_a_closed_ticket_refuses_a_reply(): void
    {
        $ticket = $this->openTicket();

        $this->asStaff()
            ->patchJson('/api/v1/platform/tickets/'.$ticket->id.'/status', ['status' => 'CLOSED'])
            ->assertOk();

        $this->actingAs($this->owner)
            ->from('/app/support/tickets/'.$ticket->id)
            ->post('/app/support/tickets/'.$ticket->id.'/reply', ['body' => 'Hello?'])
            ->assertSessionHasErrors('body');

        $this->assertCount(1, $ticket->messages()->get());
    }

    public function test_a_ticket_can_be_assigned(): void
    {
        $ticket = $this->openTicket();

        $this->asStaff()
            ->patchJson('/api/v1/platform/tickets/'.$ticket->id.'/assign', ['assigned_to' => $this->colleague->id])
            ->assertOk();

        $this->assertSame($this->colleague->id, $ticket->fresh()->assigned_to);
    }

    public function test_the_global_inbox_lists_tickets_across_customers(): void
    {
        $this->openTicket();

        $this->asStaff()
            ->getJson('/api/v1/platform/tickets')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Delta Apparels Ltd'])
            ->assertJsonFragment(['subject' => 'Work orders missing after a factory transfer']);
    }

    public function test_the_tickets_tab_shows_this_customers_tickets(): void
    {
        $this->openTicket();

        $this->asStaff()
            ->getJson('/api/v1/platform/tickets?company_id='.$this->delta->id)
            ->assertOk()
            ->assertJsonFragment(['subject' => 'Work orders missing after a factory transfer']);
    }

    public function test_a_ticket_defaults_to_medium_priority_and_always_opens_general_category(): void
    {
        $ticket = $this->openTicket();

        $this->assertSame('MEDIUM', $ticket->priority);
        $this->assertSame('GENERAL', $ticket->category);
        $this->assertNotNull($ticket->first_response_due_at);
        $this->assertNotNull($ticket->resolution_due_at);
        // MEDIUM: one business day to first response, three to resolution.
        $this->assertTrue($ticket->first_response_due_at->diffInMinutes($ticket->created_at) <= 1440 + 1);
    }

    /**
     * Priority is a customer suggestion accepted at face value on the way
     * in — platform staff still have the final say via `reclassify()`.
     * Category is never a customer choice, so a submitted one is silently
     * ignored rather than erroring.
     */
    public function test_a_customer_can_suggest_a_priority_but_never_a_category(): void
    {
        $this->actingAs($this->owner)->post('/app/support/tickets', [
            'subject' => 'Payroll export is failing',
            'body' => "The export has 500'd twice today.",
            'priority' => 'URGENT',
            'category' => 'BILLING',
        ]);

        $ticket = SupportTicket::firstOrFail();

        $this->assertSame('URGENT', $ticket->priority);
        $this->assertSame('GENERAL', $ticket->category);
        // URGENT: one hour to first response.
        $this->assertTrue($ticket->first_response_due_at->diffInMinutes($ticket->created_at) <= 60 + 1);
    }

    /**
     * `Rule::in` already refuses an invalid priority before it reaches
     * `ManageSupportTicket::open()` through either controller — this
     * exercises the Action's own defensive fallback directly, for any
     * caller that skips that validation layer.
     */
    public function test_the_open_action_falls_back_to_medium_for_an_invalid_priority(): void
    {
        $ticket = app(ManageSupportTicket::class)->open(
            $this->delta,
            $this->owner,
            'Payroll export is failing',
            "The export has 500'd twice today.",
            'NOT_A_REAL_PRIORITY',
        );

        $this->assertSame('MEDIUM', $ticket->priority);
    }

    public function test_the_web_route_refuses_an_invalid_suggested_priority(): void
    {
        $this->actingAs($this->owner)->post('/app/support/tickets', [
            'subject' => 'Payroll export is failing',
            'body' => "The export has 500'd twice today.",
            'priority' => 'NOT_A_REAL_PRIORITY',
        ])->assertSessionHasErrors('priority');

        $this->assertSame(0, SupportTicket::count());
    }

    public function test_platform_staff_can_reclassify_a_ticket_and_the_sla_recomputes(): void
    {
        $ticket = $this->openTicket();
        $originalDue = $ticket->first_response_due_at;

        $this->asStaff()
            ->patchJson('/api/v1/platform/tickets/'.$ticket->id.'/classification', [
                'priority' => 'URGENT',
                'category' => 'BUG',
            ])
            ->assertOk()
            ->assertJsonPath('data.priority', 'URGENT')
            ->assertJsonPath('data.category', 'BUG');

        $ticket->refresh();
        $this->assertSame('URGENT', $ticket->priority);
        $this->assertSame('BUG', $ticket->category);
        $this->assertNotEquals($originalDue, $ticket->first_response_due_at);
    }

    public function test_reclassifying_rejects_an_unknown_priority(): void
    {
        $ticket = $this->openTicket();

        $this->asStaff()
            ->patchJson('/api/v1/platform/tickets/'.$ticket->id.'/classification', [
                'priority' => 'SUPER_URGENT',
                'category' => 'GENERAL',
            ])
            ->assertStatus(422);
    }

    public function test_an_internal_note_never_reaches_the_customer(): void
    {
        $ticket = $this->openTicket();

        $this->asStaff()
            ->postJson('/api/v1/platform/tickets/'.$ticket->id.'/reply', [
                'body' => 'Checking the database directly for this one.',
                'is_internal' => true,
            ])
            ->assertOk();

        // Invisible on the tenant's own side (the web view, same as every
        // other customer-facing screen in this test file).
        $this->actingAs($this->owner)
            ->get('/app/support/tickets/'.$ticket->id)
            ->assertOk()
            ->assertDontSee('database directly');

        $this->assertCount(1, $ticket->fresh()->messages()->where('is_internal', false)->get());

        // Visible on the platform's own side, flagged as internal.
        $platformView = $this->asStaff()->getJson('/api/v1/platform/tickets/'.$ticket->id)->assertOk();
        $this->assertCount(2, $platformView->json('data.messages'));

        $internal = collect($platformView->json('data.messages'))->firstWhere('is_internal', true);
        $this->assertNotNull($internal);
        $this->assertStringContainsString('database directly', $internal['body']);
    }

    public function test_an_internal_note_does_not_move_status_or_count_as_first_response(): void
    {
        $ticket = $this->openTicket();

        $this->asStaff()
            ->postJson('/api/v1/platform/tickets/'.$ticket->id.'/reply', [
                'body' => 'Internal-only note.',
                'is_internal' => true,
            ])
            ->assertOk();

        $ticket->refresh();
        $this->assertSame('OPEN', $ticket->status);
        $this->assertNull($ticket->first_responded_at);

        // A real reply right after this one still counts as the first
        // response — the internal note did not consume it.
        $this->asStaff()
            ->postJson('/api/v1/platform/tickets/'.$ticket->id.'/reply', ['body' => 'Here is the real answer.'])
            ->assertOk();

        $ticket->refresh();
        $this->assertSame('IN_PROGRESS', $ticket->status);
        $this->assertNotNull($ticket->first_responded_at);
    }

    public function test_the_opener_can_edit_a_ticket_nobody_has_replied_to_yet(): void
    {
        $ticket = $this->openTicket();

        $this->asOwnerApi()
            ->patchJson('/api/v1/support/tickets/'.$ticket->id, [
                'subject' => 'Corrected subject',
                'body' => 'Corrected body text.',
            ])
            ->assertOk()
            ->assertJsonPath('data.subject', 'Corrected subject');

        $ticket->refresh();
        $this->assertSame('Corrected subject', $ticket->subject);
        $this->assertSame('Corrected body text.', $ticket->messages()->first()->body);
    }

    public function test_editing_is_refused_once_anyone_has_replied(): void
    {
        $ticket = $this->openTicket();

        $this->asOwnerApi()
            ->postJson('/api/v1/support/tickets/'.$ticket->id.'/reply', ['body' => 'One more thing.'])
            ->assertOk();

        $this->asOwnerApi()
            ->patchJson('/api/v1/support/tickets/'.$ticket->id, [
                'subject' => 'Too late',
                'body' => 'Too late.',
            ])
            ->assertStatus(409);
    }

    public function test_only_the_opener_can_edit_or_delete_their_own_ticket(): void
    {
        $ticket = $this->openTicket();

        $this->asOtherTenantUser()
            ->patchJson('/api/v1/support/tickets/'.$ticket->id, ['subject' => 'Hijacked', 'body' => 'Hijacked.'])
            ->assertForbidden();

        $this->asOtherTenantUser()
            ->deleteJson('/api/v1/support/tickets/'.$ticket->id)
            ->assertForbidden();

        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'subject' => $ticket->subject]);
    }

    public function test_the_opener_can_delete_a_ticket_nobody_has_replied_to_yet(): void
    {
        $ticket = $this->openTicket();

        $this->asOwnerApi()
            ->deleteJson('/api/v1/support/tickets/'.$ticket->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('support_tickets', ['id' => $ticket->id]);
    }

    public function test_deleting_is_refused_once_anyone_has_replied(): void
    {
        $ticket = $this->openTicket();

        $this->asStaff()
            ->postJson('/api/v1/platform/tickets/'.$ticket->id.'/reply', ['body' => 'We are looking into it.'])
            ->assertOk();

        $this->asOwnerApi()
            ->deleteJson('/api/v1/support/tickets/'.$ticket->id)
            ->assertStatus(409);

        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id]);
    }

    public function test_can_edit_reflects_ownership_and_reply_state(): void
    {
        $ticket = $this->openTicket();

        $this->asOwnerApi()
            ->getJson('/api/v1/support/tickets/'.$ticket->id)
            ->assertJsonPath('data.can_edit', true);

        $this->asOtherTenantUser()
            ->getJson('/api/v1/support/tickets/'.$ticket->id)
            ->assertJsonPath('data.can_edit', false);

        $this->asStaff()
            ->postJson('/api/v1/platform/tickets/'.$ticket->id.'/reply', ['body' => 'On it.'])
            ->assertOk();

        $this->asOwnerApi()
            ->getJson('/api/v1/support/tickets/'.$ticket->id)
            ->assertJsonPath('data.can_edit', false);
    }

    public function test_check_sla_command_flags_a_breach_exactly_once(): void
    {
        $ticket = $this->openTicket();
        $ticket->forceFill([
            'first_response_due_at' => now()->subHour(),
            'resolution_due_at' => now()->subHour(),
        ])->save();

        $this->artisan('support:check-sla')->assertSuccessful();

        $breachCount = Notification::withoutGlobalScope(TenantScope::class)
            ->where('event_type', 'PLATFORM_TICKET_SLA_BREACHED')->count();
        $this->assertGreaterThan(0, $breachCount);

        $this->artisan('support:check-sla')->assertSuccessful();

        // A second run must not duplicate — the dedup columns were stamped
        // by the first.
        $this->assertSame(
            $breachCount,
            Notification::withoutGlobalScope(TenantScope::class)
                ->where('event_type', 'PLATFORM_TICKET_SLA_BREACHED')->count(),
        );
    }

    private function asStaff(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->staffToken);

        return $this;
    }

    private function asOwnerApi(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->ownerToken);

        return $this;
    }

    private function asOtherTenantUser(): self
    {
        $this->withHeader('Authorization', 'Bearer '.$this->otherTenantToken);

        return $this;
    }

    private function openTicket(): SupportTicket
    {
        $this->actingAs($this->owner)->post('/app/support/tickets', [
            'subject' => 'Work orders missing after a factory transfer',
            'body' => 'We moved an asset and its work order history disappeared.',
        ]);

        return SupportTicket::firstOrFail();
    }

    private function signOut(): void
    {
        $this->flushSession();

        $this->app['auth']->forgetGuards();
    }
}

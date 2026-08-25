<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\Notification;
use App\Modules\Platform\Models\SupportTicket;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private User $colleague;

    private Company $delta;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);
        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');

        $this->staff = User::create([
            'name' => 'Platform Support', 'email' => 'support@platform.test',
            'password' => 'correct-horse-battery', 'status' => 'ACTIVE', 'locale' => 'en',
            'is_platform_admin' => true,
        ]);
        $this->colleague = User::create([
            'name' => 'Second Administrator', 'email' => 'second@platform.test',
            'password' => 'correct-horse-battery', 'status' => 'ACTIVE', 'locale' => 'en',
            'is_platform_admin' => true,
        ]);
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

        $this->signOut();
        $this->actingAs($this->staff)
            ->post('/platform/tickets/'.$ticket->id.'/reply', ['body' => 'Checked — this was a caching bug, now fixed.'])
            ->assertRedirect();

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

        $this->signOut();
        $this->actingAs($this->staff)
            ->post('/platform/tickets/'.$ticket->id.'/reply', ['body' => 'Looking into it.'])
            ->assertRedirect();

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
        // leaves out whoever wrote it.
        $this->assertSame(2, Notification::withoutGlobalScope(TenantScope::class)
            ->where('event_type', 'PLATFORM_TICKET_REPLIED')->count());

        $this->assertSame(0, Notification::where('event_type', 'TICKET_REPLIED')
            ->where('user_id', $this->owner->id)->count());
    }

    public function test_resolving_notifies_the_customer_and_a_reply_reopens_it(): void
    {
        $ticket = $this->openTicket();

        $this->signOut();
        $this->actingAs($this->staff)
            ->post('/platform/tickets/'.$ticket->id.'/status', ['status' => 'RESOLVED'])
            ->assertRedirect();

        $this->assertSame('RESOLVED', $ticket->fresh()->status);
        $this->assertSame(1, Notification::where('event_type', 'TICKET_RESOLVED')
            ->where('user_id', $this->owner->id)->count());

        // "Resolved" was somebody's belief; the customer's own reply is them
        // saying it was wrong, and that reopens it without anybody having to
        // notice and change the status by hand.
        $this->signOut();
        $this->actingAs($this->owner)
            ->post('/app/support/tickets/'.$ticket->id.'/reply', ['body' => 'This is still happening.'])
            ->assertRedirect();

        $this->assertSame('OPEN', $ticket->fresh()->status);
    }

    public function test_a_closed_ticket_refuses_a_reply(): void
    {
        $ticket = $this->openTicket();

        $this->signOut();
        $this->actingAs($this->staff)
            ->post('/platform/tickets/'.$ticket->id.'/status', ['status' => 'CLOSED'])
            ->assertRedirect();

        $this->signOut();
        $this->actingAs($this->owner)
            ->from('/app/support/tickets/'.$ticket->id)
            ->post('/app/support/tickets/'.$ticket->id.'/reply', ['body' => 'Hello?'])
            ->assertSessionHasErrors('body');

        $this->assertCount(1, $ticket->messages()->get());
    }

    public function test_a_ticket_can_be_assigned(): void
    {
        $ticket = $this->openTicket();

        $this->signOut();
        $this->actingAs($this->staff)
            ->post('/platform/tickets/'.$ticket->id.'/assign', ['assigned_to' => $this->colleague->id])
            ->assertRedirect();

        $this->assertSame($this->colleague->id, $ticket->fresh()->assigned_to);
    }

    public function test_the_global_inbox_lists_tickets_across_customers(): void
    {
        $this->openTicket();

        $this->signOut();
        $this->actingAs($this->staff)
            ->get('/platform/tickets')
            ->assertOk()
            ->assertSee('Delta Apparels Ltd')
            ->assertSee('missing after a factory transfer');
    }

    public function test_the_tickets_tab_shows_this_customers_tickets(): void
    {
        $this->openTicket();

        $this->signOut();
        $this->actingAs($this->staff)
            ->get('/platform/tenants/'.$this->delta->id.'/tickets')
            ->assertOk()
            ->assertSee('missing after a factory transfer');
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

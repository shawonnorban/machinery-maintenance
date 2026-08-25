<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\Notification;
use App\Modules\Platform\Services\PlatformNotifier;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The platform shell, and the notifications behind its bell.
 *
 * Platform staff belong to no company, and notifications were company-scoped
 * and NOT NULL — so the one group who most need telling when a colleague opens
 * support access to a customer were the one group the notification system
 * could not address at all.
 *
 * A null company_id means "the platform". The tenant scope never matches null,
 * which is what keeps these rows out of every customer's system without a
 * single extra condition anywhere — and that is the property worth a test,
 * because it is the one that would leak.
 */
class PlatformDeskTest extends TestCase
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

        $this->staff = $this->platformUser('support@platform.test', 'Platform Support');
        $this->colleague = $this->platformUser('second@platform.test', 'Second Administrator');
    }

    public function test_the_support_desk_renders(): void
    {
        $this->actingAs($this->staff)
            ->get('/platform/support')
            ->assertOk()
            ->assertSee(__('platform.support_none_open'));
    }

    public function test_the_notifications_page_renders(): void
    {
        $this->actingAs($this->staff)
            ->get('/platform/notifications')
            ->assertOk();
    }

    public function test_the_shell_is_on_every_platform_screen(): void
    {
        $response = $this->actingAs($this->staff)->get('/platform')->assertOk();

        // The sidebar, its three real destinations, and the badge that says
        // which side of the tenancy this is.
        $response->assertSee('sidebar', false)
            ->assertSee(__('platform.tenants'))
            ->assertSee(__('platform.support_access'))
            ->assertSee(__('notification.notifications'))
            ->assertSee(__('platform.staff'));
    }

    public function test_opening_support_tells_the_other_platform_staff(): void
    {
        $this->actingAs($this->staff)
            ->post('/platform/tenants/'.$this->delta->id.'/support', [
                'reason' => 'Ticket 4471: work orders not appearing after a factory transfer.',
                'hours' => 2,
            ])
            ->assertRedirect();

        $rows = Notification::withoutGlobalScope(TenantScope::class)
            ->where('event_type', 'PLATFORM_SUPPORT_OPENED')
            ->get();

        // The colleague hears about it. The person who did it does not — a
        // notification saying "you opened support access" is noise, and noise
        // is how a bell stops being read.
        $this->assertCount(1, $rows);
        $this->assertSame($this->colleague->id, $rows->first()->user_id);
        $this->assertNull($rows->first()->company_id);
    }

    public function test_a_platform_notification_never_reaches_a_customer(): void
    {
        $this->actingAs($this->staff)
            ->post('/platform/tenants/'.$this->delta->id.'/support', [
                'reason' => 'Ticket 4471: work orders not appearing after a factory transfer.',
                'hours' => 2,
            ])
            ->assertRedirect();

        TenantFixture::actingAsTenant($this->delta);

        // Scoped normally, as every screen inside a tenant does. A scope
        // matching on company_id cannot match null, which is the whole of what
        // keeps these rows on this side of the tenancy.
        $this->assertSame(0, Notification::query()
            ->whereIn('event_type', Notification::PLATFORM_EVENT_TYPES)
            ->count());
    }

    public function test_the_bell_counts_only_unread_platform_rows(): void
    {
        $this->notifyColleague();
        $this->notifyColleague();

        $this->actingAs($this->colleague)
            ->get('/platform')
            ->assertOk()
            ->assertSee('notification-bell-badge', false);

        $this->actingAs($this->colleague)
            ->post('/platform/notifications/read')
            ->assertRedirect();

        $this->assertSame(0, Notification::withoutGlobalScope(TenantScope::class)
            ->where('user_id', $this->colleague->id)
            ->whereNull('read_at')
            ->count());
    }

    public function test_marking_read_leaves_other_peoples_notifications_alone(): void
    {
        $this->notifyColleague();

        // Everybody except the colleague, which here is only the other member
        // of staff.
        app(PlatformNotifier::class)->notify(
            'PLATFORM_TENANT_SUSPENDED',
            'Somebody suspended somebody',
            exceptUserId: $this->colleague->id,
        );

        $mine = Notification::withoutGlobalScope(TenantScope::class)
            ->where('user_id', $this->staff->id)
            ->firstOrFail();

        $this->actingAs($this->colleague)->post('/platform/notifications/read')->assertRedirect();

        $this->assertNull($mine->fresh()->read_at);
    }

    public function test_a_customer_cannot_reach_the_platform_desk(): void
    {
        // 404 rather than 403: the platform area does not announce itself to
        // somebody who has no business knowing it is there.
        $this->actingAs($this->owner)->get('/platform/support')->assertNotFound();
        $this->actingAs($this->owner)->get('/platform/notifications')->assertNotFound();
    }

    /**
     * Through the real notifier, not a hand-written row.
     *
     * A hand-written one gets company_id stamped on it by BelongsToTenant from
     * whatever context the fixture left set, which is exactly the bug this
     * class exists to catch — and a test that reproduces the bug in its own
     * setup tests nothing.
     */
    private function notifyColleague(): void
    {
        app(PlatformNotifier::class)->notify(
            'PLATFORM_SUPPORT_OPENED',
            'Somebody opened support access',
            severity: 'WARNING',
            exceptUserId: $this->staff->id,
        );
    }

    private function platformUser(string $email, string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'correct-horse-battery',
            'status' => 'ACTIVE',
            'locale' => 'en',
            'is_platform_admin' => true,
        ]);
    }
}

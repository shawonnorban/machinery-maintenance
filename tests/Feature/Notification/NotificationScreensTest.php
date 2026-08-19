<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationPreference;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The notification screens over HTTP.
 */
class NotificationScreensTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $manager;

    private User $engineer;

    private NotificationDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'eng@delta.test');

        $this->dispatcher = app(NotificationDispatcher::class);
    }

    private function notify(?User $to = null): Notification
    {
        TenantFixture::actingAsTenant($this->delta);

        return $this->dispatcher->send(
            $to ?? $this->manager,
            'BREAKDOWN_CRITICAL',
            ['asset' => 'SEW-DHK-00412', 'number' => 'BD-DHK-1', 'problem' => 'Line stopped'],
            'CRITICAL',
        );
    }

    public function test_the_list_renders_the_unread_ones(): void
    {
        $this->notify();

        $this->actingAs($this->manager)
            ->get('/app/notifications')
            ->assertOk()
            ->assertSee('SEW-DHK-00412')
            ->assertSee(__('notification.acknowledge'));
    }

    public function test_the_header_shows_the_unread_count(): void
    {
        $this->notify();
        $this->notify();

        $this->actingAs($this->manager)
            ->get('/app/dashboard')
            ->assertOk()
            // A count, not a list: the header renders on every screen.
            ->assertSee('cil-bell', false)
            ->assertSee('badge bg-danger', false)
            // Whitespace around the number, so the assertion cannot be a
            // literal '>2<'.
            ->assertSeeInOrder(['badge bg-danger', '2', '</span>'], false);
    }

    public function test_marking_read_does_not_acknowledge(): void
    {
        $notification = $this->notify();

        $this->actingAs($this->manager)
            ->post("/app/notifications/{$notification->id}/read")
            ->assertRedirect();

        // Two different statements, and only the second stops an escalation.
        $this->assertTrue($notification->fresh()->isRead());
        $this->assertFalse($notification->fresh()->isAcknowledged());
    }

    public function test_acknowledging_over_http_settles_it(): void
    {
        $notification = $this->notify();

        $this->actingAs($this->manager)
            ->post("/app/notifications/{$notification->id}/acknowledge")
            ->assertRedirect();

        $this->assertTrue($notification->fresh()->isAcknowledged());
        // Acknowledging implies having seen it.
        $this->assertTrue($notification->fresh()->isRead());
    }

    public function test_mark_all_read_clears_the_badge(): void
    {
        $this->notify();
        $this->notify();
        $this->notify();

        $this->actingAs($this->manager)
            ->post('/app/notifications/read-all')
            ->assertRedirect();

        $this->assertSame(0, $this->dispatcher->unreadCount($this->manager->fresh()));
    }

    public function test_somebody_elses_notification_is_not_reachable(): void
    {
        $notification = $this->notify($this->engineer);

        // 404, not 403: inside the same company, a notification addressed to
        // somebody else is not this person's to read or acknowledge, and
        // confirming it exists would leak that they were told something.
        $this->actingAs($this->manager)
            ->post("/app/notifications/{$notification->id}/read")
            ->assertNotFound();

        $this->actingAs($this->manager)
            ->post("/app/notifications/{$notification->id}/acknowledge")
            ->assertNotFound();

        $this->assertFalse($notification->fresh()->isRead());
    }

    public function test_the_list_shows_only_your_own(): void
    {
        $mine = $this->notify($this->manager);
        $theirs = $this->notify($this->engineer);

        $this->actingAs($this->manager)
            ->get('/app/notifications?filter=ALL')
            ->assertOk();

        $this->assertSame(
            1,
            Notification::where('user_id', $this->manager->id)->count(),
        );

        unset($mine, $theirs);
    }

    public function test_the_preferences_screen_lists_every_event(): void
    {
        $this->actingAs($this->manager)
            ->get('/app/notifications/preferences')
            ->assertOk()
            ->assertSee(__('notification.event_breakdown_critical'))
            ->assertSee(__('notification.event_low_stock'))
            // Stated rather than letting somebody switch on email and wonder
            // why nothing arrives.
            ->assertSee(__('notification.not_yet_delivered_hint'));
    }

    public function test_preferences_are_saved_and_in_app_stays_on(): void
    {
        $this->actingAs($this->manager)
            ->post('/app/notifications/preferences', [
                'preferences' => [
                    'BREAKDOWN_CRITICAL' => ['email' => '1', 'sms' => '0', 'whatsapp' => '0'],
                    'LOW_STOCK' => ['email' => '0', 'sms' => '0', 'whatsapp' => '0'],
                ],
            ])
            ->assertRedirect();

        $critical = NotificationPreference::where('user_id', $this->manager->id)
            ->where('event_type', 'BREAKDOWN_CRITICAL')
            ->firstOrFail();

        $this->assertTrue($critical->email);
        // Not switchable, whatever the form sends.
        $this->assertTrue($critical->in_app);
    }

    public function test_an_unknown_event_in_the_form_is_ignored(): void
    {
        $this->actingAs($this->manager)
            ->post('/app/notifications/preferences', [
                'preferences' => [
                    'MACHINE_EXPLODED' => ['email' => '1'],
                ],
            ])
            ->assertRedirect();

        // A crafted form field must not create a preference for an event the
        // product does not raise.
        $this->assertSame(0, NotificationPreference::count());
    }

    public function test_another_company_cannot_reach_this_notification(): void
    {
        $notification = $this->notify();

        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        TenantFixture::factory($omega, 'Narayanganj Unit', 'NGJ');
        $intruder = TenantFixture::user($omega, 'COMPANY_OWNER', 'owner@omega.test');

        $this->actingAs($intruder)
            ->post("/app/notifications/{$notification->id}/read")
            ->assertNotFound();
    }
}

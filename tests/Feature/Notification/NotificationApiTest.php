<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\Notification;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * A person's own notifications, over the API (API 19, SRS 27).
 *
 * No permission check beyond being signed in: these are addressed to the
 * caller by name, and the control that matters is the scope, not a role.
 */
class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $manager;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);

        $this->manager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
    }

    private function notificationFor(User $user, array $overrides = []): Notification
    {
        return Notification::create(array_merge([
            'company_id' => $this->delta->id,
            'user_id' => $user->id,
            'event_type' => 'MAINTENANCE_DUE',
            'title' => 'A machine needs service',
            'body' => 'SEW-DHK-00412 is due for its monthly service.',
            'locale' => 'en',
            'severity' => 'INFO',
        ], $overrides));
    }

    public function test_a_person_sees_only_their_own_unread_notifications(): void
    {
        $mine = $this->notificationFor($this->manager);
        $this->notificationFor($this->technician);

        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    }

    /**
     * `meta.unread_count` is what the web index reads off
     * `NotificationDispatcher::unreadCount()` for the bell-icon-style badge
     * — the "ALL" filter's own `total` counts read ones too, so a client
     * needs this separately.
     */
    public function test_the_list_carries_the_unread_count(): void
    {
        $this->notificationFor($this->manager);
        $read = $this->notificationFor($this->manager);
        $read->forceFill(['read_at' => now()])->save();

        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/notifications?filter=ALL')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.unread_count', 1);
    }

    /**
     * `is_escalation` says why a notification arrived — an escalation that
     * looks ordinary gives the reader no reason to treat it differently,
     * same as the web's own badge (`Notification::isEscalation()`).
     */
    public function test_an_escalated_notification_says_so(): void
    {
        $root = $this->notificationFor($this->manager, ['event_type' => 'BREAKDOWN_CRITICAL']);
        $escalation = $this->notificationFor($this->manager, [
            'event_type' => 'BREAKDOWN_CRITICAL',
            'source_notification_id' => $root->id,
            'escalation_level' => 1,
        ]);

        $response = $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/notifications?filter=ALL')
            ->assertOk()
            ->json('data');

        $byId = collect($response)->keyBy('id');

        $this->assertFalse($byId[$root->id]['is_escalation']);
        $this->assertTrue($byId[$escalation->id]['is_escalation']);
    }

    public function test_a_notification_can_be_marked_read_and_all_read(): void
    {
        $notification = $this->notificationFor($this->manager);

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->notificationFor($this->manager);
        $this->notificationFor($this->manager);

        $this->withToken($this->tokenFor($this->manager))
            ->postJson('/api/v1/notifications/read-all')
            ->assertNoContent();

        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_notification_addressed_to_someone_else_cannot_be_read_or_acknowledged(): void
    {
        $theirs = $this->notificationFor($this->technician);

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/notifications/{$theirs->id}/read")
            ->assertNotFound();

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/notifications/{$theirs->id}/acknowledge")
            ->assertNotFound();
    }

    public function test_acknowledging_stops_the_whole_escalation_chain(): void
    {
        $root = $this->notificationFor($this->manager, ['event_type' => 'BREAKDOWN_CRITICAL']);
        $escalation = $this->notificationFor($this->manager, [
            'event_type' => 'BREAKDOWN_CRITICAL',
            'source_notification_id' => $root->id,
            'escalation_level' => 1,
        ]);

        $this->withToken($this->tokenFor($this->manager))
            ->postJson("/api/v1/notifications/{$root->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath('data.is_acknowledged', true);

        $this->assertNotNull($escalation->fresh()->acknowledged_at);
    }

    public function test_preferences_can_be_read_and_saved(): void
    {
        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/notification-preferences')
            ->assertOk()
            ->assertJsonPath('data.MAINTENANCE_DUE.in_app', true)
            ->assertJsonPath('data.MAINTENANCE_DUE.email', false);

        $this->withToken($this->tokenFor($this->manager))
            ->patchJson('/api/v1/notification-preferences', [
                'preferences' => [
                    'MAINTENANCE_DUE' => ['email' => true, 'sms' => false, 'whatsapp' => false],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.MAINTENANCE_DUE.email', true);

        $this->withToken($this->tokenFor($this->manager))
            ->getJson('/api/v1/notification-preferences')
            ->assertOk()
            ->assertJsonPath('data.MAINTENANCE_DUE.email', true);
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}

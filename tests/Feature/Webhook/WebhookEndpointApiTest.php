<?php

declare(strict_types=1);

namespace Tests\Feature\Webhook;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Webhook\Models\WebhookDelivery;
use App\Modules\Webhook\Models\WebhookEndpoint;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Outgoing integrations, over the API (API 28, SRS 43).
 */
class WebhookEndpointApiTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $owner;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);

        $this->owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        $this->technician = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
    }

    public function test_an_endpoint_can_be_created_with_a_secret_shown_once(): void
    {
        $created = $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.test/webhooks/receive',
                'description' => 'ERP sync',
                'events' => ['breakdown.reported'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'ACTIVE');

        $this->assertStringStartsWith('whsec_', $created->json('data.secret'));

        $endpointId = $created->json('data.id');

        // Never shown again on a plain read.
        $this->withToken($this->tokenFor($this->owner))
            ->getJson("/api/v1/webhooks/{$endpointId}")
            ->assertOk()
            ->assertJsonMissingPath('data.secret')
            ->assertJsonPath('data.events', ['breakdown.reported']);
    }

    public function test_the_event_catalogue_is_reachable_before_any_endpoint_exists(): void
    {
        $this->withToken($this->tokenFor($this->owner))
            ->getJson('/api/v1/webhooks/events')
            ->assertOk()
            ->assertJsonFragment(['value' => 'breakdown.reported']);
    }

    public function test_an_unknown_event_is_refused(): void
    {
        $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.test/webhooks/receive',
                'events' => ['not.a.real.event'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('events.0');
    }

    public function test_a_private_address_is_refused(): void
    {
        $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://169.254.169.254/steal-metadata',
                'events' => ['breakdown.reported'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('url');
    }

    public function test_a_secret_can_be_rotated_and_the_endpoint_paused_and_enabled(): void
    {
        $endpointId = $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.test/webhooks/receive',
                'events' => ['breakdown.reported'],
            ])
            ->json('data.id');

        $rotated = $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/v1/webhooks/{$endpointId}/rotate-secret")
            ->assertOk();

        $this->assertStringStartsWith('whsec_', $rotated->json('data.secret'));

        $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/v1/webhooks/{$endpointId}/pause")
            ->assertOk()
            ->assertJsonPath('data.status', 'PAUSED');

        $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/v1/webhooks/{$endpointId}/enable")
            ->assertOk()
            ->assertJsonPath('data.status', 'ACTIVE');
    }

    public function test_deleting_an_endpoint_pauses_it_rather_than_erasing_history(): void
    {
        $endpointId = $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.test/webhooks/receive',
                'events' => ['breakdown.reported'],
            ])
            ->json('data.id');

        $this->withToken($this->tokenFor($this->owner))
            ->deleteJson("/api/v1/webhooks/{$endpointId}")
            ->assertNoContent();

        $endpoint = WebhookEndpoint::find($endpointId);
        $this->assertNotNull($endpoint);
        $this->assertSame('PAUSED', $endpoint->status);
    }

    public function test_a_delivery_can_be_redelivered(): void
    {
        // The job itself calls a real receiver; faked so this test does not
        // depend on network reachability.
        Queue::fake();

        $endpointId = $this->withToken($this->tokenFor($this->owner))
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.test/webhooks/receive',
                'events' => ['breakdown.reported'],
            ])
            ->json('data.id');

        $delivery = WebhookDelivery::create([
            'company_id' => $this->delta->id,
            'webhook_endpoint_id' => $endpointId,
            'event_type' => 'breakdown.reported',
            'event_id' => 'evt_1',
            'payload_json' => ['id' => 'b1'],
            'status' => 'FAILED',
            'attempt_count' => 7,
            'created_at' => now(),
        ]);

        $this->withToken($this->tokenFor($this->owner))
            ->postJson("/api/v1/webhook-deliveries/{$delivery->id}/redeliver")
            ->assertOk()
            ->assertJsonPath('data.status', 'PENDING');
    }

    public function test_the_endpoints_are_closed_to_a_role_without_webhook_access(): void
    {
        $this->withToken($this->tokenFor($this->technician))
            ->getJson('/api/v1/webhooks')
            ->assertForbidden();
    }

    private function tokenFor(User $user): string
    {
        $companyId = $user->memberships()->latest()->value('company_id');

        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $companyId, 'Test token');

        return $plain;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Webhook;

use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Webhook\Actions\ManageWebhookEndpoint;
use App\Modules\Webhook\Models\WebhookDelivery;
use App\Modules\Webhook\Models\WebhookEndpoint;
use App\Modules\Webhook\Services\WebhookEvents;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Managing endpoints over HTTP (SRS 43, ERD Section 22).
 *
 * The screen carries three obligations the service layer cannot meet on its
 * own: the secret is shown exactly once, a URL pointing inside the network is
 * refused before it is ever called, and one tenant cannot reach another
 * tenant's integration or its delivery log.
 */
class WebhookEndpointScreenTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
    }

    private function user(string $role, string $email): User
    {
        $user = TenantFixture::user($this->delta, $role, $email);
        TenantFixture::actingAsTenant($this->delta);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'url' => 'https://erp.example.test/hooks/machinery',
            'description' => 'ERP',
            'events' => [WebhookEvents::BREAKDOWN_REPORTED],
        ], $overrides);
    }

    public function test_the_secret_is_shown_once_and_never_again(): void
    {
        $owner = $this->user('COMPANY_OWNER', 'owner@delta.test');

        $this->actingAs($owner)
            ->post('/app/webhooks', $this->payload())
            ->assertRedirect('/app/webhooks');

        $endpoint = WebhookEndpoint::firstOrFail();
        $secret = $endpoint->secret;

        // Once, on the page the creation redirects to.
        $this->actingAs($owner)->get('/app/webhooks')->assertOk()->assertSee($secret);

        // And never again. A secret that can be read back is a secret that
        // leaves through a support session or a screenshot.
        $this->actingAs($owner)->get('/app/webhooks')->assertOk()->assertDontSee($secret);
        $this->actingAs($owner)->get('/app/webhooks/'.$endpoint->id)->assertOk()->assertDontSee($secret);

        $this->assertArrayNotHasKey('secret', $endpoint->toArray());
        $this->assertArrayNotHasKey('previous_secret', $endpoint->toArray());
    }

    public function test_a_rotated_secret_is_shown_once_too(): void
    {
        $owner = $this->user('COMPANY_OWNER', 'owner@delta.test');

        $endpoint = app(ManageWebhookEndpoint::class)->create($this->payload(), $owner->id)['endpoint'];
        $oldSecret = $endpoint->secret;

        $this->actingAs($owner)
            ->post('/app/webhooks/'.$endpoint->id.'/rotate')
            ->assertRedirect('/app/webhooks');

        $newSecret = $endpoint->fresh()->secret;

        $this->assertNotSame($oldSecret, $newSecret);

        $this->actingAs($owner)->get('/app/webhooks')->assertOk()->assertSee($newSecret);
        $this->actingAs($owner)->get('/app/webhooks')->assertOk()->assertDontSee($newSecret);
    }

    /**
     * The reason the guard exists at all.
     *
     * A webhook is a request this server makes to an address a customer typed
     * in. Left unchecked, a tenant could point one at the cloud metadata
     * service and have the platform fetch it for them from inside the
     * perimeter.
     */
    public function test_an_address_inside_the_network_is_refused(): void
    {
        $owner = $this->user('COMPANY_OWNER', 'owner@delta.test');

        $refused = [
            'https://169.254.169.254/latest/meta-data/',
            'https://127.0.0.1/hooks',
            'https://10.1.2.3/hooks',
            'https://192.168.0.10/hooks',
            'https://user:pass@erp.example.test/hooks',
        ];

        foreach ($refused as $url) {
            $this->actingAs($owner)
                ->from('/app/webhooks')
                ->post('/app/webhooks', $this->payload(['url' => $url]))
                ->assertRedirect('/app/webhooks')
                ->assertSessionHasErrors('url');
        }

        $this->assertSame(0, WebhookEndpoint::count());
    }

    public function test_an_unknown_event_is_refused(): void
    {
        $owner = $this->user('COMPANY_OWNER', 'owner@delta.test');

        $this->actingAs($owner)
            ->from('/app/webhooks')
            ->post('/app/webhooks', $this->payload(['events' => ['breakdown.invented']]))
            ->assertSessionHasErrors('events');

        // An endpoint subscribed to an event that will never fire looks like a
        // working integration and behaves like a dead one.
        $this->assertSame(0, WebhookEndpoint::count());
    }

    public function test_the_screen_is_closed_to_roles_that_do_not_manage_integrations(): void
    {
        $manager = $this->user('MAINTENANCE_MANAGER', 'mm@delta.test');

        $this->actingAs($manager)->get('/app/webhooks')->assertForbidden();
        $this->actingAs($manager)->post('/app/webhooks', $this->payload())->assertForbidden();

        $this->assertSame(0, WebhookEndpoint::count());
    }

    public function test_one_tenant_cannot_reach_another_tenants_endpoint(): void
    {
        $owner = $this->user('COMPANY_OWNER', 'owner@delta.test');

        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        TenantFixture::actingAsTenant($other);

        $theirs = app(ManageWebhookEndpoint::class)->create([
            'url' => 'https://their-erp.example.test/hooks',
            'events' => [WebhookEvents::BREAKDOWN_REPORTED],
        ])['endpoint'];

        TenantFixture::actingAsTenant($this->delta);

        // Not 403: whether that endpoint exists is itself none of this
        // company's business.
        $this->actingAs($owner)->get('/app/webhooks/'.$theirs->id)->assertNotFound();
        $this->actingAs($owner)->post('/app/webhooks/'.$theirs->id.'/pause')->assertNotFound();
        $this->actingAs($owner)->post('/app/webhooks/'.$theirs->id.'/rotate')->assertNotFound();

        $this->assertSame('ACTIVE', $theirs->fresh()->status);
    }

    public function test_pausing_and_enabling_an_endpoint_from_the_screen(): void
    {
        $owner = $this->user('COMPANY_OWNER', 'owner@delta.test');

        $endpoint = app(ManageWebhookEndpoint::class)->create($this->payload(), $owner->id)['endpoint'];

        $this->actingAs($owner)->post('/app/webhooks/'.$endpoint->id.'/pause')->assertRedirect();
        $this->assertSame('PAUSED', $endpoint->fresh()->status);

        $this->actingAs($owner)->post('/app/webhooks/'.$endpoint->id.'/enable')->assertRedirect();
        $this->assertSame('ACTIVE', $endpoint->fresh()->status);
    }

    public function test_a_delivery_can_be_sent_again_under_the_same_event_id(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $owner = $this->user('COMPANY_OWNER', 'owner@delta.test');

        $endpoint = app(ManageWebhookEndpoint::class)->create($this->payload(), $owner->id)['endpoint'];

        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => WebhookEvents::BREAKDOWN_REPORTED,
            'event_id' => 'evt_fixed',
            'payload_json' => ['id' => 'b1'],
            'status' => 'FAILED',
            'attempt_count' => 1,
            'created_at' => CarbonImmutable::now(),
        ]);

        $this->actingAs($owner)
            ->post('/app/webhooks/deliveries/'.$delivery->id.'/redeliver')
            ->assertRedirect();

        $delivery = $delivery->fresh();

        // The same event id goes out, so a receiver that did get the first one
        // can recognise the repeat rather than acting on it twice.
        $this->assertSame('evt_fixed', $delivery->event_id);
        $this->assertSame('DELIVERED', $delivery->status);
    }

    public function test_the_delivery_log_is_visible_on_the_endpoint(): void
    {
        $owner = $this->user('COMPANY_OWNER', 'owner@delta.test');

        $endpoint = app(ManageWebhookEndpoint::class)->create($this->payload(), $owner->id)['endpoint'];

        WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => WebhookEvents::BREAKDOWN_REPORTED,
            'event_id' => 'evt_visible',
            'payload_json' => ['id' => 'b1'],
            'status' => 'DELIVERED',
            'attempt_count' => 1,
            'response_status' => 200,
            'created_at' => CarbonImmutable::now(),
        ]);

        // When an integration is not working, the argument is always about
        // whether the event was sent, and the only way to end it is to show it.
        $this->actingAs($owner)
            ->get('/app/webhooks/'.$endpoint->id)
            ->assertOk()
            ->assertSee('evt_visible');
    }
}

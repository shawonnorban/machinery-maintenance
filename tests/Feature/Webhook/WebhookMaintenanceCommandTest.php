<?php

declare(strict_types=1);

namespace Tests\Feature\Webhook;

use App\Modules\Tenancy\Models\Company;
use App\Modules\Webhook\Actions\ManageWebhookEndpoint;
use App\Modules\Webhook\Models\WebhookDelivery;
use App\Modules\Webhook\Models\WebhookEndpoint;
use App\Modules\Webhook\Services\WebhookEvents;
use App\Shared\Scopes\TenantScope;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * The scheduled half of webhook delivery (ERD Section 22, SRS 49.1).
 *
 * Retries live on the delivery row rather than in the queue, which only works
 * if something sweeps for them. And the payload — a copy of tenant data nobody
 * reads after the week it was sent — is dropped while the record of what was
 * sent survives.
 */
class WebhookMaintenanceCommandTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private WebhookEndpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);

        CarbonImmutable::setTestNow('2026-06-15 09:00:00');

        $this->endpoint = app(ManageWebhookEndpoint::class)->create([
            'url' => 'https://erp.example.test/hooks/machinery',
            'events' => [WebhookEvents::BREAKDOWN_REPORTED],
        ])['endpoint'];
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function delivery(array $overrides = []): WebhookDelivery
    {
        return WebhookDelivery::create(array_merge([
            'webhook_endpoint_id' => $this->endpoint->id,
            'event_type' => WebhookEvents::BREAKDOWN_REPORTED,
            'event_id' => 'evt_'.uniqid(),
            'payload_json' => ['id' => 'b1'],
            'request_headers_json' => ['X-Machinery-Event' => WebhookEvents::BREAKDOWN_REPORTED],
            'response_body_excerpt' => 'nope',
            'status' => 'FAILED',
            'attempt_count' => 1,
            'created_at' => CarbonImmutable::now(),
        ], $overrides));
    }

    /**
     * The sweep belongs to no tenant and leaves none behind, so the test has to
     * say who it is again before reading a tenant-scoped row.
     */
    private function sweep(): void
    {
        $this->artisan('webhooks:retry')->assertSuccessful();

        TenantFixture::actingAsTenant($this->delta);
    }

    public function test_a_delivery_whose_retry_time_has_come_is_sent_again(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $due = $this->delivery(['next_retry_at' => CarbonImmutable::now()->subMinute()]);

        $this->sweep();

        // The queue is sync in tests, so a requeue is an attempt.
        $this->assertSame('DELIVERED', $due->fresh()->status);
    }

    public function test_a_delivery_that_is_not_due_yet_is_left_alone(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $later = $this->delivery(['next_retry_at' => CarbonImmutable::now()->addHour()]);

        $this->sweep();

        // Retrying early turns a receiver's outage into our queue backlog,
        // which is the thing the backoff exists to prevent.
        $this->assertSame('FAILED', $later->fresh()->status);
        $this->assertSame(1, $later->fresh()->attempt_count);
        Http::assertNothingSent();
    }

    public function test_an_exhausted_delivery_is_never_picked_up_again(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $exhausted = $this->delivery([
            'status' => 'EXHAUSTED',
            'next_retry_at' => CarbonImmutable::now()->subDay(),
        ]);

        $this->sweep();

        $this->assertSame('EXHAUSTED', $exhausted->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_the_sweep_crosses_tenants_because_nobody_is_logged_in_for_it(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        TenantFixture::actingAsTenant($other);

        $theirEndpoint = app(ManageWebhookEndpoint::class)->create([
            'url' => 'https://their-erp.example.test/hooks',
            'events' => [WebhookEvents::BREAKDOWN_REPORTED],
        ])['endpoint'];

        $theirs = WebhookDelivery::create([
            'webhook_endpoint_id' => $theirEndpoint->id,
            'event_type' => WebhookEvents::BREAKDOWN_REPORTED,
            'event_id' => 'evt_theirs',
            'payload_json' => ['id' => 'b2'],
            'status' => 'FAILED',
            'attempt_count' => 1,
            'next_retry_at' => CarbonImmutable::now()->subMinute(),
            'created_at' => CarbonImmutable::now(),
        ]);

        TenantFixture::actingAsTenant($this->delta);

        $mine = $this->delivery(['next_retry_at' => CarbonImmutable::now()->subMinute()]);

        $this->sweep();

        // A scheduled command has no tenant, so every company's due deliveries
        // have to be found — otherwise retries would only ever happen for
        // whichever company the process happened to be pointed at.
        $this->assertSame('DELIVERED', $mine->fresh()->status);
        $this->assertSame(
            'DELIVERED',
            WebhookDelivery::withoutGlobalScope(TenantScope::class)->findOrFail($theirs->id)->status,
        );
    }

    public function test_old_payloads_are_dropped_but_the_record_of_the_delivery_stays(): void
    {
        $old = $this->delivery([
            'status' => 'DELIVERED',
            'response_status' => 200,
            'created_at' => CarbonImmutable::now()->subDays(31),
        ]);

        $recent = $this->delivery([
            'status' => 'DELIVERED',
            'response_status' => 200,
            'created_at' => CarbonImmutable::now()->subDays(3),
        ]);

        $this->artisan('webhooks:prune')->assertSuccessful();

        $old = $old->fresh();

        $this->assertNull($old->payload_json);
        $this->assertNull($old->request_headers_json);
        $this->assertNull($old->response_body_excerpt);

        // What an integration argument actually needs — that it was sent, when,
        // and what came back — costs almost nothing to keep.
        $this->assertSame('DELIVERED', $old->status);
        $this->assertSame(200, $old->response_status);
        $this->assertNotNull($old->event_id);

        $this->assertNotNull($recent->fresh()->payload_json);
    }
}

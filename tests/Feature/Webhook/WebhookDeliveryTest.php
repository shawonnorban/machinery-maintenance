<?php

declare(strict_types=1);

namespace Tests\Feature\Webhook;

use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Notification\Models\Notification;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Webhook\Actions\ManageWebhookEndpoint;
use App\Modules\Webhook\Models\WebhookDelivery;
use App\Modules\Webhook\Models\WebhookEndpoint;
use App\Modules\Webhook\Services\WebhookDeliverer;
use App\Modules\Webhook\Services\WebhookEvents;
use App\Modules\Webhook\Services\WebhookSigner;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Delivering events to somebody else's system (ERD Section 22, SRS 43).
 *
 * Three things decide whether an integration is trustworthy: the receiver can
 * prove the message came from us, a failure is retried rather than lost, and a
 * dead endpoint is switched off and its owner told. Each is pinned here.
 */
class WebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        CarbonImmutable::setTestNow('2026-06-15 09:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  list<string>  $events
     */
    private function endpoint(array $events = [WebhookEvents::BREAKDOWN_REPORTED]): WebhookEndpoint
    {
        return app(ManageWebhookEndpoint::class)->create([
            'url' => 'https://erp.example.test/hooks/machinery',
            'description' => 'ERP',
            'events' => $events,
        ])['endpoint'];
    }

    private function reportBreakdown(): void
    {
        app(ReportBreakdown::class)->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Line stopped',
            'failure_at' => CarbonImmutable::now(),
            'reported_at' => CarbonImmutable::now(),
        ], 'user-a');
    }

    public function test_a_domain_event_reaches_a_subscribed_endpoint(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $endpoint = $this->endpoint();

        $this->reportBreakdown();

        $delivery = WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->firstOrFail();

        $this->assertSame(WebhookEvents::BREAKDOWN_REPORTED, $delivery->event_type);
        $this->assertSame('DELIVERED', $delivery->status);
        $this->assertSame(200, $delivery->response_status);
        $this->assertNotNull($delivery->delivered_at);
    }

    public function test_an_endpoint_only_receives_what_it_asked_for(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $endpoint = $this->endpoint([WebhookEvents::STOCK_CHANGED]);

        $this->reportBreakdown();

        // An ERP that only cares about stock should not have to receive, verify
        // and discard every breakdown in the factory.
        $this->assertSame(0, WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->count());
    }

    public function test_the_payload_is_signed_so_a_receiver_can_prove_it_came_from_us(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $endpoint = $this->endpoint();

        $this->reportBreakdown();

        $delivery = WebhookDelivery::firstOrFail();

        Http::assertSent(function ($request) use ($endpoint, $delivery) {
            $signature = $request->header(WebhookSigner::SIGNATURE_HEADER)[0] ?? '';
            $timestamp = $request->header(WebhookSigner::TIMESTAMP_HEADER)[0] ?? '';

            $this->assertNotSame('', $signature);
            $this->assertSame($delivery->event_id, $request->header(WebhookSigner::DELIVERY_HEADER)[0] ?? null);

            // Verified exactly the way a receiver would, using the same code
            // the documentation points at.
            return app(WebhookSigner::class)->verify(
                $endpoint->fresh(),
                $signature,
                $timestamp,
                $request->body(),
            );
        });
    }

    public function test_the_signature_covers_the_timestamp_so_a_capture_cannot_be_replayed(): void
    {
        $endpoint = $this->endpoint();
        $signer = app(WebhookSigner::class);

        $body = '{"id":"x"}';
        $signature = $signer->sign($endpoint->secret, '1000', $body);

        // The same body at a different time is a different signature. Without
        // that, a captured request is valid for ever.
        $this->assertNotSame($signature, $signer->sign($endpoint->secret, '2000', $body));
        $this->assertFalse($signer->verify($endpoint, 'v1='.$signature, '2000', $body));
    }

    public function test_a_rotated_secret_keeps_the_old_one_working_for_a_window(): void
    {
        $endpoint = $this->endpoint();
        $oldSecret = $endpoint->secret;

        app(ManageWebhookEndpoint::class)->rotateSecret($endpoint);

        $endpoint = $endpoint->fresh();
        $signer = app(WebhookSigner::class);

        $body = '{"id":"x"}';
        $timestamp = (string) CarbonImmutable::now()->getTimestamp();

        // Both signatures are sent, so a receiver switches over on its own
        // schedule instead of breaking at 3am.
        $headers = $signer->headers($endpoint, $body, 'breakdown.reported', 'evt');

        $this->assertStringContainsString($signer->sign($oldSecret, $timestamp, $body), $headers[WebhookSigner::SIGNATURE_HEADER]);
        $this->assertStringContainsString($signer->sign($endpoint->secret, $timestamp, $body), $headers[WebhookSigner::SIGNATURE_HEADER]);

        // And after the window, only the new one.
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addHours(WebhookEndpoint::ROTATION_WINDOW_HOURS + 1));

        $later = $signer->headers($endpoint, $body, 'breakdown.reported', 'evt');
        $laterTimestamp = (string) CarbonImmutable::now()->getTimestamp();

        $this->assertStringNotContainsString($signer->sign($oldSecret, $laterTimestamp, $body), $later[WebhookSigner::SIGNATURE_HEADER]);
    }

    public function test_a_failure_is_scheduled_for_retry_on_a_backoff(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $this->endpoint();
        $this->reportBreakdown();

        $delivery = WebhookDelivery::firstOrFail();

        // One minute for the first retry, five for the second: a receiver that
        // is down is usually down for hours, and retrying every thirty seconds
        // turns their outage into our queue backlog.
        $this->assertSame('FAILED', $delivery->status);
        $this->assertSame(1, $delivery->attempt_count);
        $this->assertSame(
            CarbonImmutable::now()->addMinute()->format('Y-m-d H:i'),
            $delivery->next_retry_at->format('Y-m-d H:i'),
        );

        app(WebhookDeliverer::class)->attempt($delivery->fresh());

        $this->assertSame(
            CarbonImmutable::now()->addMinutes(5)->format('Y-m-d H:i'),
            WebhookDelivery::firstOrFail()->next_retry_at->format('Y-m-d H:i'),
        );
    }

    public function test_attempts_run_out_and_the_delivery_is_given_up(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $this->endpoint();
        $this->reportBreakdown();

        $deliverer = app(WebhookDeliverer::class);

        for ($i = 0; $i < count(WebhookDelivery::BACKOFF_MINUTES); $i++) {
            $deliverer->attempt(WebhookDelivery::firstOrFail());
        }

        $delivery = WebhookDelivery::firstOrFail();

        $this->assertSame('EXHAUSTED', $delivery->status);
        $this->assertNull($delivery->next_retry_at);
    }

    public function test_an_endpoint_is_disabled_after_enough_consecutive_failures_and_its_owner_told(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'owner@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        $endpoint = $this->endpoint();
        $this->reportBreakdown();

        $deliverer = app(WebhookDeliverer::class);

        for ($i = 0; $i < WebhookEndpoint::FAILURE_LIMIT; $i++) {
            $deliverer->attempt(WebhookDelivery::firstOrFail());
        }

        $endpoint = $endpoint->fresh();

        $this->assertSame('DISABLED', $endpoint->status);
        $this->assertNotNull($endpoint->disabled_at);

        // An integration that quietly stops is discovered weeks later by
        // somebody wondering why their ERP has no breakdowns in it.
        $this->assertSame(
            1,
            Notification::where('event_type', 'WEBHOOK_DISABLED')->where('user_id', $owner->id)->count(),
        );
    }

    public function test_one_success_clears_the_failure_run(): void
    {
        // Driven by a variable rather than a second Http::fake() call: stubs
        // are appended and the first match wins, so re-faking the same URL
        // would leave the receiver failing for ever.
        $status = 500;
        Http::fake(function () use (&$status) {
            return Http::response($status === 200 ? '' : 'nope', $status);
        });

        $endpoint = $this->endpoint();
        $this->reportBreakdown();

        app(WebhookDeliverer::class)->attempt(WebhookDelivery::firstOrFail());

        $this->assertGreaterThan(0, $endpoint->fresh()->consecutive_failure_count);

        $status = 200;

        app(WebhookDeliverer::class)->attempt(WebhookDelivery::firstOrFail());

        // Otherwise a receiver with a bad afternoon is switched off a month
        // later for failures it long since recovered from.
        $this->assertSame(0, $endpoint->fresh()->consecutive_failure_count);
    }

    public function test_a_redirect_is_treated_as_a_failure(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://elsewhere.example.test'])]);

        $this->endpoint();
        $this->reportBreakdown();

        // Following a redirect would let the receiver choose the destination at
        // request time, which undoes the point of validating the URL.
        $this->assertSame('FAILED', WebhookDelivery::firstOrFail()->status);
    }

    public function test_the_event_id_is_stable_across_retries(): void
    {
        Http::fake(['*' => Http::response('nope', 500)]);

        $this->endpoint();
        $this->reportBreakdown();

        $first = WebhookDelivery::firstOrFail()->event_id;

        app(WebhookDeliverer::class)->attempt(WebhookDelivery::firstOrFail());

        // A timeout that actually arrived must not become a second work order
        // on the receiver's side.
        $this->assertSame($first, WebhookDelivery::firstOrFail()->event_id);
    }

    public function test_a_paused_endpoint_receives_nothing(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $endpoint = $this->endpoint();

        app(ManageWebhookEndpoint::class)->pause($endpoint);

        $this->reportBreakdown();

        $this->assertSame(0, WebhookDelivery::count());
    }

    public function test_events_never_cross_a_tenant_boundary(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $other = TenantFixture::company('Beta Textiles Ltd', 'BTL');
        TenantFixture::actingAsTenant($other);

        $theirEndpoint = app(ManageWebhookEndpoint::class)->create([
            'url' => 'https://their-erp.example.test/hooks',
            'events' => [WebhookEvents::BREAKDOWN_REPORTED],
        ])['endpoint'];

        TenantFixture::actingAsTenant($this->delta);

        $this->reportBreakdown();

        // The single most important assertion here: one company's breakdown
        // must never be posted to another company's integration.
        $this->assertSame(
            0,
            WebhookDelivery::withoutGlobalScopes()->where('webhook_endpoint_id', $theirEndpoint->id)->count(),
        );
    }

    public function test_an_asset_status_change_is_forwarded_too(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $this->endpoint([WebhookEvents::ASSET_STATUS_CHANGED]);

        app(ChangeAssetStatus::class)->handle($this->asset, 'IDLE', null, 'End of shift', 'MANUAL');

        $delivery = WebhookDelivery::firstOrFail();

        $this->assertSame(WebhookEvents::ASSET_STATUS_CHANGED, $delivery->event_type);
        $this->assertSame('RUNNING', $delivery->payload_json['from_status']);
        $this->assertSame('IDLE', $delivery->payload_json['to_status']);
    }
}

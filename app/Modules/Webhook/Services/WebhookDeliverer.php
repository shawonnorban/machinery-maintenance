<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PermissionResolver;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Webhook\Models\WebhookDelivery;
use App\Modules\Webhook\Models\WebhookEndpoint;
use App\Shared\Scopes\TenantScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Makes one attempt and records exactly what happened (ERD Section 22).
 *
 * Anything other than a 2xx is a failure, including a redirect: a webhook that
 * follows a redirect is a webhook whose destination is decided by the receiver
 * at request time, which undoes the point of validating the URL.
 *
 * A successful delivery clears the endpoint's failure count. Consecutive
 * failures are what disable an endpoint, so one success has to reset the run —
 * otherwise a receiver with a bad afternoon is switched off a month later for
 * failures it long since recovered from.
 */
class WebhookDeliverer
{
    /** Enough for a slow receiver, short enough not to hold a worker hostage. */
    private const TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly WebhookSigner $signer,
        private readonly WebhookUrlGuard $guard,
        private readonly NotificationDispatcher $notifications,
        private readonly PermissionResolver $permissions,
    ) {}

    public function attempt(WebhookDelivery $delivery): WebhookDelivery
    {
        // Looked up against the delivery's own company rather than through the
        // tenant-scoped relation. The boundary is still enforced — by the
        // company_id on the query — but it no longer depends on which tenant
        // the process happens to be pointed at, and a sweep running under
        // somebody else's context can no longer mistake a live endpoint for a
        // deleted one and give the delivery up.
        $endpoint = WebhookEndpoint::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $delivery->company_id)
            ->find($delivery->webhook_endpoint_id);

        if ($endpoint === null || ! $endpoint->isDeliverable()) {
            return $this->exhaust($delivery, __('webhook.endpoint_not_deliverable'));
        }

        // Checked again at send time, not only at creation: DNS moves, and a
        // hostname that was public last week can point at the private network
        // today.
        if (! $this->guard->isCallable($endpoint->url)) {
            return $this->exhaust($delivery, __('webhook.url_private'));
        }

        $body = json_encode($delivery->payload_json ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $headers = $this->signer->headers(
            $endpoint,
            $body,
            $delivery->event_type,
            $delivery->event_id,
        );

        $startedAt = microtime(true);

        try {
            $response = Http::withHeaders($headers)
                ->withoutRedirecting()
                ->timeout(self::TIMEOUT_SECONDS)
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $duration = (int) round((microtime(true) - $startedAt) * 1000);

            return $response->successful()
                ? $this->succeed($delivery, $endpoint, $headers, $response->status(), $response->body(), $duration)
                : $this->fail($delivery, $endpoint, $headers, $response->status(), $response->body(), $duration);
        } catch (Throwable $e) {
            // A timeout or a refused connection is a failure like any other:
            // the receiver did not take it.
            return $this->fail(
                $delivery,
                $endpoint,
                $headers,
                null,
                $e->getMessage(),
                (int) round((microtime(true) - $startedAt) * 1000),
            );
        }
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function succeed(
        WebhookDelivery $delivery,
        WebhookEndpoint $endpoint,
        array $headers,
        int $status,
        string $body,
        int $duration,
    ): WebhookDelivery {
        $delivery->forceFill([
            'status' => 'DELIVERED',
            'attempt_count' => $delivery->attempt_count + 1,
            'response_status' => $status,
            'response_body_excerpt' => Str::limit($body, 500),
            'duration_ms' => $duration,
            'request_headers_json' => $headers,
            'signature' => $headers[WebhookSigner::SIGNATURE_HEADER] ?? null,
            'last_attempted_at' => CarbonImmutable::now(),
            'delivered_at' => CarbonImmutable::now(),
            'next_retry_at' => null,
        ])->save();

        // Unconditionally, and without reading the current value first: this
        // instance was loaded before the request went out, so a guard on its
        // failure count would be a guard on a stale number and the reset would
        // silently not happen.
        $endpoint->forceFill(['consecutive_failure_count' => 0])->save();

        return $delivery->fresh();
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function fail(
        WebhookDelivery $delivery,
        WebhookEndpoint $endpoint,
        array $headers,
        ?int $status,
        string $body,
        int $duration,
    ): WebhookDelivery {
        $attempts = $delivery->attempt_count + 1;

        $delivery->forceFill([
            'attempt_count' => $attempts,
            'response_status' => $status,
            'response_body_excerpt' => Str::limit($body, 500),
            'duration_ms' => $duration,
            'request_headers_json' => $headers,
            'signature' => $headers[WebhookSigner::SIGNATURE_HEADER] ?? null,
            'last_attempted_at' => CarbonImmutable::now(),
        ])->save();

        $delivery = $delivery->fresh();

        $nextRetry = $delivery->nextRetryAt();

        $delivery->forceFill([
            'status' => $nextRetry === null ? 'EXHAUSTED' : 'FAILED',
            'next_retry_at' => $nextRetry,
        ])->save();

        $endpoint->forceFill([
            'consecutive_failure_count' => $endpoint->consecutive_failure_count + 1,
        ])->save();

        if ($endpoint->fresh()->consecutive_failure_count >= WebhookEndpoint::FAILURE_LIMIT) {
            $this->disable($endpoint->fresh());
        }

        return $delivery->fresh();
    }

    private function exhaust(WebhookDelivery $delivery, string $reason): WebhookDelivery
    {
        $delivery->forceFill([
            'status' => 'EXHAUSTED',
            'response_body_excerpt' => $reason,
            'last_attempted_at' => CarbonImmutable::now(),
            'next_retry_at' => null,
        ])->save();

        return $delivery->fresh();
    }

    /**
     * Switch off an endpoint that has stopped answering, and say so.
     *
     * Telling the customer is not optional. An integration that quietly stops
     * is discovered weeks later by somebody wondering why their ERP has no
     * breakdowns in it.
     */
    private function disable(WebhookEndpoint $endpoint): void
    {
        $endpoint->forceFill([
            'status' => 'DISABLED',
            'disabled_at' => CarbonImmutable::now(),
            'disabled_reason' => __('webhook.disabled_after_failures', [
                'count' => WebhookEndpoint::FAILURE_LIMIT,
            ]),
        ])->save();

        foreach ($this->recipientsFor($endpoint->company_id) as $recipient) {
            $this->notifications->send(
                $recipient,
                'WEBHOOK_DISABLED',
                ['url' => Str::limit($endpoint->url, 80), 'count' => WebhookEndpoint::FAILURE_LIMIT],
                'WARNING',
                null,
                'webhook_endpoint',
                $endpoint->id,
            );
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function recipientsFor(string $companyId)
    {
        return User::whereHas('memberships', fn ($q) => $q->where('company_id', $companyId)
            ->where('status', 'ACTIVE'))
            ->where('status', 'ACTIVE')
            ->get()
            ->filter(fn (User $user) => $this->permissions->has($user, $companyId, 'webhook.endpoint.manage'));
    }
}

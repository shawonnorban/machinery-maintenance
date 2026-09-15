<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Http\Controllers\Api;

use App\Modules\Webhook\Actions\ManageWebhookEndpoint;
use App\Modules\Webhook\Jobs\DeliverWebhook;
use App\Modules\Webhook\Models\WebhookDelivery;
use App\Modules\Webhook\Models\WebhookEndpoint;
use App\Modules\Webhook\Services\WebhookEvents;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Outgoing integrations, over the wire (API 28; mirrors the web
 * `WebhookController`, which this delegates every write to unchanged per
 * ADR-003).
 *
 * The signing secret is returned once, on the response that creates or
 * rotates it, and never again — not here, not on `show`. `WebhookEndpoint`
 * hides both secret columns from serialization at the model level, so this
 * merges the plaintext in only for that one response.
 */
class WebhookEndpointApiController extends ApiController
{
    public function index(): JsonResponse
    {
        $this->allow('webhook.endpoint.manage');

        $endpoints = WebhookEndpoint::withCount('subscriptions')->latest()->get();

        return ApiResponse::ok($endpoints->map(fn (WebhookEndpoint $e): array => $this->summary($e))->all());
    }

    /**
     * The fixed event catalogue (`WebhookEvents::all()`) a subscription form
     * picks from — exposed here because the web screen reads the PHP
     * constant directly and the API has no equivalent way to reach it.
     */
    public function events(): JsonResponse
    {
        $this->allow('webhook.endpoint.manage');

        $events = collect(WebhookEvents::all())
            ->map(fn (string $event): array => ['value' => $event, 'label' => __(WebhookEvents::label($event))])
            ->all();

        return ApiResponse::ok($events);
    }

    public function store(Request $request, ManageWebhookEndpoint $action): JsonResponse
    {
        $this->allow('webhook.endpoint.manage');

        $data = $this->validated($request);

        $result = $action->create($data, $this->caller()->auditUserId());

        return ApiResponse::created($this->summary($result['endpoint']) + ['secret' => $result['secret']]);
    }

    public function show(WebhookEndpoint $endpoint): JsonResponse
    {
        $this->allow('webhook.endpoint.manage');

        $endpoint->load('subscriptions');

        $deliveries = WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return ApiResponse::ok($this->detail($endpoint) + [
            'recent_deliveries' => $deliveries->map(fn (WebhookDelivery $d): array => $this->deliverySummary($d))->all(),
        ]);
    }

    public function update(Request $request, WebhookEndpoint $endpoint, ManageWebhookEndpoint $action): JsonResponse
    {
        $this->allow('webhook.endpoint.manage');

        $updated = $action->update($endpoint, $this->validated($request), $this->caller()->auditUserId());

        return ApiResponse::ok($this->detail($updated));
    }

    public function rotateSecret(WebhookEndpoint $endpoint, ManageWebhookEndpoint $action): JsonResponse
    {
        $this->allow('webhook.endpoint.manage');

        $result = $action->rotateSecret($endpoint);

        return ApiResponse::ok($this->summary($result['endpoint']) + ['secret' => $result['secret']]);
    }

    public function enable(WebhookEndpoint $endpoint, ManageWebhookEndpoint $action): JsonResponse
    {
        $this->allow('webhook.endpoint.manage');

        return ApiResponse::ok($this->summary($action->enable($endpoint)));
    }

    public function pause(WebhookEndpoint $endpoint, ManageWebhookEndpoint $action): JsonResponse
    {
        $this->allow('webhook.endpoint.manage');

        return ApiResponse::ok($this->summary($action->pause($endpoint)));
    }

    /**
     * Send one delivery again, under the same event id so a receiver that
     * did get the first one can recognise the repeat rather than acting on
     * it twice.
     */
    public function redeliver(WebhookDelivery $delivery): JsonResponse
    {
        $this->allow('webhook.endpoint.manage');

        $delivery->forceFill(['status' => 'PENDING', 'next_retry_at' => null])->save();

        DeliverWebhook::dispatch($delivery->id, $delivery->company_id);

        return ApiResponse::ok($this->deliverySummary($delivery->fresh()));
    }

    /**
     * No hard delete: an endpoint with delivery history stays resolvable,
     * same principle as archiving a vendor or closing a factory. "Remove
     * it" over the API pauses it, exactly what the web screen's own destroy
     * action would do if it had one — it has pause and enable instead.
     */
    public function destroy(WebhookEndpoint $endpoint, ManageWebhookEndpoint $action): JsonResponse
    {
        $this->allow('webhook.endpoint.manage');

        $action->pause($endpoint);

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:'.implode(',', WebhookEvents::all())],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(WebhookEndpoint $endpoint): array
    {
        return [
            'id' => $endpoint->id,
            'url' => $endpoint->url,
            'description' => $endpoint->description,
            'status' => $endpoint->status,
            'signing_algorithm' => $endpoint->signing_algorithm,
            'consecutive_failure_count' => $endpoint->consecutive_failure_count,
            'disabled_at' => $endpoint->disabled_at?->toIso8601String(),
            'disabled_reason' => $endpoint->disabled_reason,
            'subscriptions_count' => $endpoint->subscriptions_count ?? null,
            'created_at' => $endpoint->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(WebhookEndpoint $endpoint): array
    {
        return $this->summary($endpoint) + [
            'events' => $endpoint->relationLoaded('subscriptions')
                ? $endpoint->subscriptions->pluck('event_type')->all()
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deliverySummary(WebhookDelivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'event_type' => $delivery->event_type,
            'event_id' => $delivery->event_id,
            'status' => $delivery->status,
            'attempt_count' => $delivery->attempt_count,
            'response_status' => $delivery->response_status,
            'duration_ms' => $delivery->duration_ms,
            'last_attempted_at' => $delivery->last_attempted_at?->toIso8601String(),
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'created_at' => $delivery->created_at?->toIso8601String(),
        ];
    }
}

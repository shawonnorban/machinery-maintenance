<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Services;

use App\Modules\Webhook\Jobs\DeliverWebhook;
use App\Modules\Webhook\Models\WebhookDelivery;
use App\Modules\Webhook\Models\WebhookEndpoint;
use App\Shared\Scopes\TenantScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Queues an event for every endpoint that asked for it (ERD Section 22).
 *
 * One delivery row per endpoint, each with its own retry clock. A tenant with
 * three endpoints where one is down should still have the other two working,
 * and a shared row would either block them or lose the failure.
 *
 * The row is written before anything is sent. A delivery that vanishes because
 * the queue dropped a job is indistinguishable, from the customer's side, from
 * an event that never happened.
 */
class WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $payload
     * @return list<WebhookDelivery>
     */
    public function dispatch(string $companyId, string $eventType, array $payload): array
    {
        if (! WebhookEvents::isKnown($eventType)) {
            // A webhook is a published interface. An event that appears because
            // somebody added a model observer is a promise nobody made.
            return [];
        }

        $endpoints = WebhookEndpoint::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $companyId)
            ->where('status', 'ACTIVE')
            ->whereHas('subscriptions', fn ($q) => $q->where('event_type', $eventType))
            ->get();

        $deliveries = [];

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::withoutGlobalScope(TenantScope::class)->create([
                'company_id' => $companyId,
                'webhook_endpoint_id' => $endpoint->id,
                'event_type' => $eventType,
                // Stable across every retry of this delivery.
                'event_id' => Str::ulid()->toString(),
                'payload_json' => $payload,
                'status' => 'PENDING',
                'attempt_count' => 0,
                'created_at' => CarbonImmutable::now(),
            ]);

            // After commit: a receiver told about a breakdown the database then
            // rolled back has an order for work that never existed.
            DeliverWebhook::dispatch($delivery->id, $companyId)->afterCommit();

            $deliveries[] = $delivery;
        }

        return $deliveries;
    }
}

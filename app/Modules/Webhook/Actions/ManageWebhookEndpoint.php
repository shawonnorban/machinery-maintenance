<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Actions;

use App\Modules\Webhook\Models\WebhookEndpoint;
use App\Modules\Webhook\Models\WebhookSubscription;
use App\Modules\Webhook\Services\WebhookEvents;
use App\Modules\Webhook\Services\WebhookUrlGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creating, rotating and switching endpoints (ERD Section 22).
 *
 * The secret is generated here and returned once. It is never readable
 * afterwards — not through the model, not through an API response, not on a
 * screen — because a secret that can be read back is a secret that leaves
 * through a support session or a screenshot.
 */
class ManageWebhookEndpoint
{
    public function __construct(private readonly WebhookUrlGuard $guard) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{endpoint: WebhookEndpoint, secret: string}
     */
    public function create(array $data, ?string $userId = null): array
    {
        $this->guard->assertCallable($data['url']);

        $events = $this->validEvents($data['events'] ?? []);

        $secret = $this->generateSecret();

        $endpoint = DB::transaction(function () use ($data, $events, $secret, $userId): WebhookEndpoint {
            $endpoint = WebhookEndpoint::create([
                'url' => $data['url'],
                'description' => $data['description'] ?? null,
                'secret' => $secret,
                'signing_algorithm' => 'HMAC_SHA256',
                'status' => 'ACTIVE',
                'created_by' => $userId,
            ]);

            $this->syncEvents($endpoint, $events);

            return $endpoint;
        });

        // Handed back once, to be shown once. Nothing stores it anywhere the
        // customer can retrieve it again.
        return ['endpoint' => $endpoint->fresh(), 'secret' => $secret];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(WebhookEndpoint $endpoint, array $data, ?string $userId = null): WebhookEndpoint
    {
        if (isset($data['url']) && $data['url'] !== $endpoint->url) {
            $this->guard->assertCallable($data['url']);
        }

        DB::transaction(function () use ($endpoint, $data): void {
            $endpoint->update([
                'url' => $data['url'] ?? $endpoint->url,
                'description' => $data['description'] ?? $endpoint->description,
            ]);

            if (array_key_exists('events', $data)) {
                $this->syncEvents($endpoint, $this->validEvents($data['events']));
            }
        });

        return $endpoint->fresh();
    }

    /**
     * Issue a new secret, keeping the old one alive for a window.
     *
     * Both are sent on every delivery until the window closes, so a receiver
     * updates on their own schedule. A hard cutover breaks somebody's
     * integration at the moment they are least able to fix it.
     *
     * @return array{endpoint: WebhookEndpoint, secret: string}
     */
    public function rotateSecret(WebhookEndpoint $endpoint): array
    {
        $secret = $this->generateSecret();

        $endpoint->forceFill([
            'previous_secret' => $endpoint->secret,
            'secret' => $secret,
            'secret_rotated_at' => CarbonImmutable::now(),
        ])->save();

        return ['endpoint' => $endpoint->fresh(), 'secret' => $secret];
    }

    /**
     * Turn an endpoint back on after the receiver has been fixed.
     */
    public function enable(WebhookEndpoint $endpoint): WebhookEndpoint
    {
        $this->guard->assertCallable($endpoint->url);

        $endpoint->forceFill([
            'status' => 'ACTIVE',
            // Reset, because the run of failures that switched it off is over.
            // Carrying the count forward would disable it again on the first
            // hiccup after it came back.
            'consecutive_failure_count' => 0,
            'disabled_at' => null,
            'disabled_reason' => null,
        ])->save();

        return $endpoint->fresh();
    }

    public function pause(WebhookEndpoint $endpoint): WebhookEndpoint
    {
        $endpoint->forceFill(['status' => 'PAUSED'])->save();

        return $endpoint->fresh();
    }

    /**
     * @return list<string>
     */
    private function validEvents(mixed $events): array
    {
        $events = array_values(array_unique(array_filter((array) $events)));

        if ($events === []) {
            // An endpoint subscribed to nothing would sit in the list looking
            // like an integration while never sending anything.
            throw ValidationException::withMessages(['events' => __('webhook.events_required')]);
        }

        foreach ($events as $event) {
            if (! WebhookEvents::isKnown($event)) {
                throw ValidationException::withMessages([
                    'events' => __('webhook.unknown_event', ['event' => $event]),
                ]);
            }
        }

        return $events;
    }

    /**
     * @param  list<string>  $events
     */
    private function syncEvents(WebhookEndpoint $endpoint, array $events): void
    {
        WebhookSubscription::where('webhook_endpoint_id', $endpoint->id)
            ->whereNotIn('event_type', $events)
            ->delete();

        foreach ($events as $event) {
            WebhookSubscription::firstOrCreate([
                'webhook_endpoint_id' => $endpoint->id,
                'event_type' => $event,
            ]);
        }
    }

    /**
     * Long and random. A signing key a person could guess, or type from
     * memory, is not a signing key.
     */
    private function generateSecret(): string
    {
        return 'whsec_'.Str::random(48);
    }
}

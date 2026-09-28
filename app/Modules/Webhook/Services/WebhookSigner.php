<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Services;

use App\Modules\Webhook\Models\WebhookEndpoint;
use Carbon\CarbonImmutable;

/**
 * Proves a webhook came from us (ERD Section 22).
 *
 * HMAC-SHA256 over the timestamp and the exact body that will be sent. The
 * timestamp is inside the signed string, not merely beside it: without that, a
 * captured request can be replayed for ever and the signature still checks out.
 *
 * The receiver is told the timestamp so they can reject anything old. How old
 * is their decision; five minutes is the usual answer.
 */
class WebhookSigner
{
    public const SIGNATURE_HEADER = 'X-Machinery-Signature';

    public const TIMESTAMP_HEADER = 'X-Machinery-Timestamp';

    public const EVENT_HEADER = 'X-Machinery-Event';

    public const DELIVERY_HEADER = 'X-Machinery-Delivery';

    /**
     * The headers for one delivery attempt.
     *
     * During a rotation window the body is signed with both secrets and both
     * are sent. A receiver that has not switched over yet still validates, and
     * one that has already switched validates too — which is the entire point
     * of a rotation window rather than a hard cutover that breaks somebody's
     * integration at 3am.
     *
     * @return array<string, string>
     */
    public function headers(
        WebhookEndpoint $endpoint,
        string $body,
        string $eventType,
        string $eventId,
        ?CarbonImmutable $at = null,
    ): array {
        $at ??= CarbonImmutable::now();
        $timestamp = (string) $at->getTimestamp();

        $signatures = ['v1='.$this->sign($endpoint->secret, $timestamp, $body)];

        if ($endpoint->honoursPreviousSecret($at)) {
            $signatures[] = 'v1='.$this->sign($endpoint->previous_secret, $timestamp, $body);
        }

        return [
            'Content-Type' => 'application/json',
            'User-Agent' => 'MachineryMaintenance-Webhook/1',
            self::EVENT_HEADER => $eventType,
            // Stable across retries, so a receiver can deduplicate: a timeout
            // that actually arrived must not become a second work order.
            self::DELIVERY_HEADER => $eventId,
            self::TIMESTAMP_HEADER => $timestamp,
            self::SIGNATURE_HEADER => implode(',', $signatures),
        ];
    }

    public function sign(string $secret, string $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /**
     * Verify a signature the way a receiver would.
     *
     * Exposed so the documentation and the tests check the same code the
     * sender uses, and so a customer debugging their integration can be given
     * an exact answer rather than "check your implementation".
     */
    public function verify(WebhookEndpoint $endpoint, string $header, string $timestamp, string $body): bool
    {
        $candidates = [$endpoint->secret];

        if ($endpoint->honoursPreviousSecret()) {
            $candidates[] = $endpoint->previous_secret;
        }

        foreach (explode(',', $header) as $offered) {
            $offered = trim(str_replace('v1=', '', $offered));

            foreach ($candidates as $secret) {
                // Constant time: a fast string compare leaks the signature one
                // byte at a time to anybody willing to measure.
                if (hash_equals($this->sign($secret, $timestamp, $body), $offered)) {
                    return true;
                }
            }
        }

        return false;
    }
}

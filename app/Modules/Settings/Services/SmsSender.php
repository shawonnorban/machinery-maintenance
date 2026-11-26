<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\SmsGateway;
use App\Modules\Webhook\Services\WebhookUrlGuard;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends a text through a configured SMS gateway (Settings → SMS Gateway,
 * AnnotechERP 03/02) and logs the attempt. The API key is read from the
 * environment variable the gateway names; it is never stored or logged.
 */
class SmsSender
{
    /** Each provider's own address when the gateway leaves it blank. */
    private const DEFAULT_URLS = [
        'BULKSMSBD' => 'http://bulksmsbd.net/api/smsapi',
        'SSL_WIRELESS' => 'https://smsplus.sslwireless.com/api/v3/send-sms',
        'TWILIO' => 'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
    ];

    public function __construct(
        private readonly IntegrationLogger $log,
        private readonly WebhookUrlGuard $guard,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function send(SmsGateway $gateway, string $to, string $message, string $event = 'SMS_SEND'): array
    {
        $secret = $this->secret($gateway->api_key_env);
        if ($secret === null) {
            return $this->finish($gateway, $to, $event, false, __('settings.hub.sms.env_missing', ['name' => $gateway->api_key_env]));
        }

        // An address typed by an administrator is called only if it is public
        // (no private network, no cloud metadata); the providers' own are fixed.
        if ($gateway->base_url && ! $this->guard->isCallable($gateway->base_url)) {
            return $this->finish($gateway, $to, $event, false, __('settings.hub.url_not_allowed'));
        }

        try {
            $response = $this->request($gateway, $secret, $to, $message);
        } catch (Throwable $e) {
            return $this->finish($gateway, $to, $event, false, $e->getMessage());
        }

        $ok = $response->successful();
        $detail = 'HTTP '.$response->status().' '.mb_substr(trim($response->body()), 0, 300);
        if ($ok) {
            $gateway->forceFill(['last_used_at' => CarbonImmutable::now()])->save();
        }

        return $this->finish($gateway, $to, $event, $ok, $detail);
    }

    private function request(SmsGateway $gateway, string $secret, string $to, string $message): Response
    {
        // No redirects: a public address must not bounce the call into the private network.
        $http = Http::timeout(15)->withoutRedirecting()->acceptJson();
        $url = $gateway->base_url ?: (self::DEFAULT_URLS[$gateway->provider] ?? '');

        return match ($gateway->provider) {
            'BULKSMSBD' => $http->get($url, ['api_key' => $secret, 'type' => 'text', 'number' => $to, 'senderid' => $gateway->sender_id, 'message' => $message]),
            'SSL_WIRELESS' => $http->post($url, ['api_token' => $secret, 'sid' => $gateway->sender_id, 'msisdn' => $to, 'sms' => $message, 'csms_id' => uniqid('ann', true)]),
            'TWILIO' => $http->asForm()->withBasicAuth((string) $gateway->username, $secret)
                ->post(sprintf($url, $gateway->username), ['To' => $to, 'From' => $gateway->sender_id, 'Body' => $message]),
            default => $http->get($url, ['api_key' => $secret, 'to' => $to, 'sender_id' => $gateway->sender_id, 'message' => $message]),
        };
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function finish(SmsGateway $gateway, string $to, string $event, bool $ok, string $detail): array
    {
        $this->log->record('SMS', $event, $ok, $detail, $gateway->name, $to);

        return ['ok' => $ok, 'message' => $detail];
    }

    private function secret(string $name): ?string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}

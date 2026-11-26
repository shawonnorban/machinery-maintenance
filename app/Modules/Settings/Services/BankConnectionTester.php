<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\BankIntegration;
use App\Modules\Webhook\Services\WebhookUrlGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Settings → Bank & Payment Integration → "Test connection" (AnnotechERP
 * 03/02). An API bank is called; an SFTP bank's host is reached on its port;
 * a file-exchange bank is checked for a complete configuration. Every test is
 * logged and stamped on the integration.
 */
class BankConnectionTester
{
    public function __construct(
        private readonly IntegrationLogger $log,
        private readonly WebhookUrlGuard $guard,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function test(BankIntegration $bank): array
    {
        [$ok, $message] = match ($bank->channel) {
            'API' => $this->api($bank),
            'SFTP' => $this->sftp($bank),
            default => $bank->file_format !== 'NONE'
                ? [true, __('settings.hub.bank.file_ready', ['format' => $bank->file_format])]
                : [false, __('settings.hub.bank.file_format_missing')],
        };

        $bank->forceFill(['last_tested_at' => CarbonImmutable::now(), 'last_test_status' => $ok ? 'SUCCEEDED' : 'FAILED'])->save();
        $this->log->record('BANK', 'CONNECTION_TEST', $ok, $message, $bank->bank_name, $bank->endpoint);

        return ['ok' => $ok, 'message' => $message];
    }

    /** @return array{0: bool, 1: string} */
    private function api(BankIntegration $bank): array
    {
        if (! $bank->endpoint) {
            return [false, __('settings.hub.bank.endpoint_missing')];
        }

        if (! $this->guard->isCallable($bank->endpoint)) {
            return [false, __('settings.hub.url_not_allowed')];
        }

        try {
            $response = Http::timeout(10)->withoutRedirecting()->get($bank->endpoint);

            return [$response->successful(), 'HTTP '.$response->status()];
        } catch (Throwable $e) {
            return [false, $e->getMessage()];
        }
    }

    /** @return array{0: bool, 1: string} */
    private function sftp(BankIntegration $bank): array
    {
        if (! $bank->endpoint) {
            return [false, __('settings.hub.bank.endpoint_missing')];
        }

        $host = parse_url(str_contains($bank->endpoint, '://') ? $bank->endpoint : 'sftp://'.$bank->endpoint);
        // The same public-address rule as any other outside call.
        if (! $this->guard->isCallable('https://'.($host['host'] ?? ''))) {
            return [false, __('settings.hub.url_not_allowed')];
        }
        $socket = @fsockopen((string) ($host['host'] ?? ''), (int) ($host['port'] ?? 22), $code, $error, 5);
        if ($socket === false) {
            return [false, $error ?: __('settings.hub.bank.unreachable')];
        }
        fclose($socket);

        return [true, __('settings.hub.bank.reachable')];
    }
}

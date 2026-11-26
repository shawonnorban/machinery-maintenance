<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Controllers\Api;

use App\Modules\Identity\Models\MailService;
use App\Modules\Settings\Models\BankIntegration;
use App\Modules\Settings\Models\IntegrationLog;
use App\Modules\Settings\Models\SmsGateway;
use App\Modules\Settings\Services\BankConnectionTester;
use App\Modules\Settings\Services\SmsSender;
use App\Modules\Webhook\Models\WebhookDelivery;
use App\Modules\Webhook\Models\WebhookEndpoint;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Settings → Integration Hub (AnnotechERP 03/02): the list of every place the
 * system talks to the outside, the activity log of those calls, and the test
 * actions of the SMS gateways and bank integrations.
 */
class IntegrationHubApiController extends ApiController
{
    /** Integration Endpoints: inbound API and every outbound address, with where each is set. */
    public function endpoints(): JsonResponse
    {
        $this->allow('settings.company.manage');

        $rows = [[
            'id' => 'api',
            'endpoint' => 'REST API',
            'direction' => 'INBOUND',
            'address' => rtrim((string) config('app.url'), '/').'/api/v1',
            'port' => parse_url((string) config('app.url'), PHP_URL_PORT) ?: (str_starts_with((string) config('app.url'), 'https') ? 443 : 80),
            'set_in' => 'API Keys & Webhooks',
            'notes' => null,
            'active' => true,
        ]];

        foreach (WebhookEndpoint::query()->get() as $hook) {
            $rows[] = $this->outbound('webhook-'.$hook->id, 'Webhook: '.($hook->description ?? $hook->url), (string) $hook->url, 'API Keys & Webhooks', $hook->isDeliverable());
        }
        foreach (MailService::query()->get() as $mail) {
            $rows[] = ['id' => 'mail-'.$mail->id, 'endpoint' => 'Mail: '.$mail->service_name, 'direction' => 'OUTBOUND', 'address' => $mail->smtp_host, 'port' => $mail->port,
                'set_in' => 'Mail Service Configuration', 'notes' => $mail->encryption.($mail->is_default ? ' · default' : ''), 'active' => $mail->active];
        }
        foreach (SmsGateway::query()->get() as $sms) {
            $rows[] = $this->outbound('sms-'.$sms->id, 'SMS: '.$sms->name, $sms->base_url ?: $sms->provider, 'SMS Gateway', $sms->active, $sms->is_default ? 'default' : null);
        }
        foreach (BankIntegration::query()->get() as $bank) {
            $rows[] = $this->outbound('bank-'.$bank->id, 'Bank: '.$bank->bank_name, $bank->endpoint ?: $bank->channel, 'Bank & Payment Integration', $bank->active, $bank->channel.' · '.$bank->file_format);
        }

        return ApiResponse::ok($rows);
    }

    /**
     * Integration Activity Log: the hub's own log plus webhook deliveries, newest
     * first, filtered by channel, result and date.
     */
    public function activity(Request $request): JsonResponse
    {
        $this->allow('settings.company.manage');

        $filters = $request->validate([
            'channel' => ['nullable', Rule::in(IntegrationLog::CHANNELS)],
            'status' => ['nullable', Rule::in(IntegrationLog::STATUSES)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $logs = IntegrationLog::query()
            ->when($filters['channel'] ?? null, fn ($q, $c) => $q->where('channel', $c))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('occurred_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('occurred_at', '<', date('Y-m-d', strtotime($d.' +1 day'))))
            ->latest('occurred_at')->limit(500)->get()
            ->map(fn (IntegrationLog $log): array => [
                'id' => $log->id, 'occurred_at' => $log->occurred_at?->toIso8601String(), 'channel' => $log->channel, 'event' => $log->event,
                'source' => $log->source_name, 'target' => $log->target, 'records' => $log->records, 'details' => $log->details, 'status' => $log->status,
            ]);

        $rows = $logs;
        if (! isset($filters['channel']) || $filters['channel'] === 'WEBHOOK') {
            $urls = WebhookEndpoint::query()->pluck('url', 'id');
            $deliveries = WebhookDelivery::query()
                ->whereNotNull('last_attempted_at')
                ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('last_attempted_at', '>=', $d))
                ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('last_attempted_at', '<', date('Y-m-d', strtotime($d.' +1 day'))))
                ->latest('last_attempted_at')->limit(500)->get()
                ->map(fn (WebhookDelivery $d): array => [
                    'id' => 'wh-'.$d->id, 'occurred_at' => $d->last_attempted_at?->toIso8601String(), 'channel' => 'WEBHOOK', 'event' => $d->event_type ?? 'DELIVERY',
                    'source' => null, 'target' => $urls[$d->webhook_endpoint_id] ?? null, 'records' => 1,
                    'details' => $d->response_status ? 'HTTP '.$d->response_status : null,
                    'status' => $d->status === 'DELIVERED' ? 'SUCCEEDED' : 'FAILED',
                ])
                ->filter(fn (array $row): bool => ! isset($filters['status']) || $row['status'] === $filters['status']);
            $rows = $logs->concat($deliveries)->sortByDesc('occurred_at')->values();
        }

        return ApiResponse::ok($rows->take(500)->values()->all());
    }

    /** SMS Gateway → "Send test SMS". */
    public function testSms(Request $request, string $gateway, SmsSender $sender): JsonResponse
    {
        $this->allow('settings.company.manage');

        $data = $request->validate([
            'to' => ['required', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
            'message' => ['nullable', 'string', 'max:300'],
        ]);
        $row = SmsGateway::query()->whereKey($gateway)->firstOrFail();

        $result = $sender->send($row, $data['to'], $data['message'] ?? __('settings.hub.sms.test_text'), 'SMS_TEST');

        return ApiResponse::ok($result);
    }

    /** Bank & Payment Integration → "Test connection". */
    public function testBank(string $bank, BankConnectionTester $tester): JsonResponse
    {
        $this->allow('settings.company.manage');

        $row = BankIntegration::query()->whereKey($bank)->firstOrFail();

        return ApiResponse::ok($tester->test($row));
    }

    /**
     * @return array<string, mixed>
     */
    private function outbound(string $id, string $name, string $address, string $setIn, bool $active, ?string $notes = null): array
    {
        $parts = parse_url($address);

        return [
            'id' => $id,
            'endpoint' => $name,
            'direction' => 'OUTBOUND',
            'address' => $address,
            'port' => $parts['port'] ?? (isset($parts['scheme']) ? ($parts['scheme'] === 'https' ? 443 : 80) : null),
            'set_in' => $setIn,
            'notes' => $notes,
            'active' => $active,
        ];
    }
}

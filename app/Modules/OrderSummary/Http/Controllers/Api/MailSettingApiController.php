<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Http\Controllers\Api;

use App\Modules\Identity\Models\MailService;
use App\Modules\OrderSummary\Models\OsChange;
use App\Modules\OrderSummary\Models\OsMailSetting;
use App\Modules\OrderSummary\Support\MailDigest;
use App\Modules\OrderSummary\Support\OrderKeyMail;
use App\Modules\OrderSummary\Support\Sheet;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Order Summary → Mail Schedule (AnnotechERP 32): whether the edit mail is
 * on, the times of day it goes (the factory's clock), which mail service
 * sends it, when it last went and how many edits are waiting; "Send now"
 * sends what is waiting without waiting for the next time.
 */
class MailSettingApiController extends ApiController
{
    private const MANAGE = 'ordersummary.mail.manage';

    public function show(): JsonResponse
    {
        $this->allow(self::MANAGE);
        $s = OsMailSetting::query()->first();

        return ApiResponse::ok([
            'enabled' => (bool) $s?->enabled, 'times' => $s?->times ?? [], 'mail_service_id' => $s?->mail_service_id,
            'last_sent_at' => $s?->last_sent_at?->toIso8601String(), 'last_slot' => $s?->last_slot,
            'waiting' => OsChange::query()->where('kind', 'EDIT')->whereNull('mailed_at')->count(),
            // The instant mail (user, 2026-11-22): on unless turned off; JOB No / PO Number / Shipment ID unless others are chosen.
            'instant_enabled' => OrderKeyMail::settings($s)[0], 'instant_columns' => OrderKeyMail::settings($s)[1],
            'columns' => (new Sheet($this->caller()->companyId))->columns->reject(fn ($c) => $c->formula || in_array($c->type, ['system', 'image'], true))
                ->map(fn ($c) => ['value' => $c->key, 'label' => $c->label])->values()->all(),
            'services' => MailService::query()->where('active', true)->orderByDesc('is_default')->orderBy('service_name')->get(['id', 'service_name', 'smtp_host', 'from_address', 'is_default'])
                ->map(fn ($m) => ['value' => $m->id, 'label' => trim("{$m->service_name} ({$m->from_address})").($m->is_default ? ' ★' : '')])->all(),
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $this->allow(self::MANAGE);
        $c = $this->caller()->companyId;
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'times' => ['present', 'array', 'max:24'], 'times.*' => ['distinct', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'mail_service_id' => ['nullable', Rule::exists('mail_services', 'id')->where('company_id', $c)],
            'instant_enabled' => ['sometimes', 'boolean'],
            'instant_columns' => ['sometimes', 'array', 'max:20'], 'instant_columns.*' => ['string', Rule::in((new Sheet($c))->columns->pluck('key')->all())],
        ], ['times.*.regex' => __('ordersummary.v.time')], __('ordersummary.mailset.attrs'));
        if ($data['enabled'] && $data['times'] === []) {
            return response()->json(['success' => false, 'message' => __('ordersummary.v.times_needed'), 'errors' => ['times' => [__('ordersummary.v.times_needed')]]], 422);
        }
        $times = array_values(array_unique($data['times']));
        sort($times);
        $s = OsMailSetting::query()->firstOrNew(['company_id' => $c]);
        // A time already past today is not sent today when it is first added.
        $slot = now('Asia/Dhaka')->format('Y-m-d').' '.(collect($times)->filter(fn ($t) => $t <= now('Asia/Dhaka')->format('H:i'))->last() ?? '');
        $s->fill(['enabled' => $data['enabled'], 'times' => $times, 'mail_service_id' => $data['mail_service_id'] ?? null, 'updated_by' => $this->caller()->auditUserId()]
            + (array_key_exists('instant_enabled', $data) ? ['instant_enabled' => $data['instant_enabled']] : [])
            + (array_key_exists('instant_columns', $data) ? ['instant_columns' => array_values(array_unique($data['instant_columns']))] : []));
        if (! $s->exists || $s->isDirty('times')) {
            $s->last_slot = trim($slot);
        }
        $s->save();

        return ApiResponse::ok(['saved' => true]);
    }

    /** Send what is waiting now. */
    public function sendNow(): JsonResponse
    {
        $this->allow(self::MANAGE);
        try {
            $result = MailDigest::run($this->caller()->companyId, CarbonImmutable::now(), true);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => __('ordersummary.mailset.failed', ['error' => $e->getMessage()])], 422);
        }
        if (($result['skipped'] ?? null) === 'no_mail_service') {
            return response()->json(['success' => false, 'message' => __('ordersummary.mailset.no_service')], 422);
        }

        return ApiResponse::ok($result);
    }
}

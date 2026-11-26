<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Http\Controllers\Api;

use App\Modules\SalesShipment\Models\SsSetting;
use App\Modules\SalesShipment\Support\SsSettings;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sales & Shipment → Operation → Sales & Shipment Settings (AnnotechERP
 * 21/01), as Brickland keeps it: the current settings (one row, its rules,
 * alert windows, prefixes, sequence width and who changed it last) and the
 * edit — ship only what has been delivered, require export documents before
 * confirming a shipment, the due and certificate-expiry alert windows (1 to
 * 365 days), nine document prefixes (2 to 6 capital letters, each different
 * from every other) and the sequence width (4 to 8 digits).
 */
class SsSettingsApiController extends ApiController
{
    public function show(): JsonResponse
    {
        $this->allow('salesshipment.settings.view');

        return ApiResponse::ok($this->present(SsSettings::get()));
    }

    public function save(Request $request): JsonResponse
    {
        $this->allow('salesshipment.settings.manage');
        $prefix = ['required', 'string', 'regex:/^[A-Z]{2,6}$/'];
        $messages = ['due_alert_days.*' => __('salesshipment.v.days'), 'cert_alert_days.*' => __('salesshipment.v.days'), 'seq_width.*' => __('salesshipment.v.width')];
        foreach (array_keys(SsSetting::PREFIXES) as $k) {
            $messages["{$k}.regex"] = __('salesshipment.v.prefix');
            $messages["{$k}.required"] = __('salesshipment.v.prefix');
        }
        $data = $request->validate([
            'ship_against_delivered' => ['required', 'boolean'], 'require_export_docs' => ['required', 'boolean'],
            'due_alert_days' => ['required', 'integer', 'min:1', 'max:365'], 'cert_alert_days' => ['required', 'integer', 'min:1', 'max:365'],
            ...array_fill_keys(array_keys(SsSetting::PREFIXES), $prefix),
            'seq_width' => ['required', 'integer', 'min:4', 'max:8'],
        ], $messages);
        $seen = [];
        foreach (array_keys(SsSetting::PREFIXES) as $k) {
            if (isset($seen[$data[$k]])) {
                throw ValidationException::withMessages([$k => __('salesshipment.v.prefix_taken', ['prefix' => $data[$k], 'other' => __('salesshipment.set.'.$seen[$data[$k]])])]);
            }
            $seen[$data[$k]] = $k;
        }
        $row = SsSetting::query()->first() ?? new SsSetting;
        $row->fill($data + ['updated_by' => $this->caller()->auditUserId()])->save();

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /** @return array<string, mixed> */
    private function present(SsSetting $s): array
    {
        $out = ['ship_against_delivered' => (bool) $s->ship_against_delivered, 'require_export_docs' => (bool) $s->require_export_docs,
            'due_alert_days' => (int) $s->due_alert_days, 'cert_alert_days' => (int) $s->cert_alert_days];
        foreach (SsSetting::PREFIXES as $k => $default) {
            $out[$k] = $s->{$k} ?? $default;
        }

        return $out + [
            'seq_width' => (int) $s->seq_width, 'saved' => $s->exists,
            'updated_by' => $s->updated_by ? DB::table('users')->where('id', $s->updated_by)->value('name') : null, 'updated_at' => $s->updated_at?->toIso8601String(),
            'example' => $out['prefix_shipment'].'-'.now('Asia/Dhaka')->format('Y').'-'.str_pad('1', (int) $s->seq_width, '0', STR_PAD_LEFT),
        ];
    }
}

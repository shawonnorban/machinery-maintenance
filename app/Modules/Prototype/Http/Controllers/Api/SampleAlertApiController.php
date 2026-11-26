<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Http\Controllers\Api;

use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleAlert;
use App\Modules\Prototype\Support\SampleLedger;
use App\Modules\Prototype\Support\SampleWording;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Prototype Desk → Notifications & Alerts (AnnotechERP 07/02): what the desk
 * has raised — overdue samples, material asked for or short, QC and
 * approvals waiting, targets missed, capacity overloaded, samples approved
 * and delivered — for whom, and against which document. Reading it marks it
 * read; Resolve closes it by hand and Reopen brings back one closed too
 * soon. Opening the list first runs the desk's sweep.
 */
class SampleAlertApiController extends ApiController
{
    public function index(): JsonResponse
    {
        $this->allow('prototype.alerts.view');
        SampleLedger::sweep();

        return ApiResponse::ok(['alerts' => self::present(SampleAlert::query()->orderByDesc('raised_at')->get()), 'kinds' => SampleAlert::KINDS, 'severities' => SampleAlert::SEVERITIES]);
    }

    public function read(string $alert): JsonResponse
    {
        $this->allow('prototype.alerts.view');
        $row = SampleAlert::query()->findOrFail($alert);
        $row->update(['read_at' => $row->read_at ?? now()]);

        return ApiResponse::ok(self::present(collect([$row->fresh()]))[0]);
    }

    public function resolve(string $alert): JsonResponse
    {
        $this->allow('prototype.alerts.manage');
        $row = SampleAlert::query()->findOrFail($alert);
        if ($row->resolved_at) {
            throw ValidationException::withMessages(['alert' => __('prototype.alerts.already_resolved')]);
        }
        $row->update(['resolved_at' => now(), 'resolved_by' => $this->caller()->auditUserId(), 'read_at' => $row->read_at ?? now()]);

        return ApiResponse::ok(self::present(collect([$row->fresh()]))[0]);
    }

    public function reopen(string $alert): JsonResponse
    {
        $this->allow('prototype.alerts.manage');
        $row = SampleAlert::query()->findOrFail($alert);
        if (! $row->resolved_at) {
            throw ValidationException::withMessages(['alert' => __('prototype.alerts.not_resolved')]);
        }
        $row->update(['resolved_at' => null, 'resolved_by' => null]);

        return ApiResponse::ok(self::present(collect([$row->fresh()]))[0]);
    }

    /** @return list<array<string, mixed>> */
    public static function present(Collection $rows): array
    {
        $samples = Sample::query()->whereIn('id', $rows->pluck('sample_id')->filter())->pluck('sample_no', 'id');

        return $rows->map(fn (SampleAlert $a): array => [
            'id' => $a->id, 'kind' => $a->kind, 'severity' => $a->severity, ...SampleWording::alert($a),
            'sample_no' => $a->sample_id ? ($samples[$a->sample_id] ?? null) : null, 'for_desk' => $a->for_desk, 'doc_no' => $a->doc_no,
            'raised_at' => $a->raised_at->toIso8601String(), 'raised_date' => $a->raised_at->toDateString(),
            'read' => $a->read_at !== null, 'resolved' => $a->resolved_at !== null, 'resolved_at' => $a->resolved_at?->toIso8601String(),
            'state' => $a->resolved_at ? 'RESOLVED' : 'OPEN', 'resolved_today' => $a->resolved_at?->isToday() ?? false,
        ])->values()->all();
    }
}

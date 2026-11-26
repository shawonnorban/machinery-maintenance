<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Http\Controllers\Api;

use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleApproval;
use App\Modules\Prototype\Models\SampleDevelopment;
use App\Modules\Prototype\Models\SampleQualityCheck;
use App\Modules\Prototype\Models\SampleQualityCheckpoint;
use App\Modules\Prototype\Models\SampleRequisition;
use App\Modules\Prototype\Support\Prototype;
use App\Modules\Prototype\Support\SampleCards;
use App\Modules\Prototype\Support\SampleLedger;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Prototype Desk → Sample Quality Check (AnnotechERP 07/03): the internal
 * check a finished sample passes before approval (QC-2026-00001), against
 * its current version, checkpoint by checkpoint. A Not OK checkpoint needs
 * its defect, a Major or Critical one cannot be passed outright, and Fail or
 * Rework Required need the defect. Pass or Pass With Comment puts the sample
 * up for approval; Fail keeps it in Quality; Rework Required sends it back
 * to production, reopening its development. Only the latest check of the
 * current version is corrected, and not once an approval is raised on it.
 */
class SampleQualityCheckApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('prototype.quality.view');

        return ApiResponse::ok([
            'samples' => SampleCards::options($this->waiting()), 'users' => Prototype::users(),
            'results' => SampleQualityCheck::RESULTS, 'severities' => SampleQualityCheck::SEVERITIES, 'checkpoints' => SampleQualityCheck::CHECKPOINTS,
        ]);
    }

    public function index(): JsonResponse
    {
        $this->allow('prototype.quality.view');

        return ApiResponse::ok($this->present(SampleQualityCheck::query()->orderByDesc('check_date')->orderByDesc('qc_no')->get()));
    }

    public function show(string $check): JsonResponse
    {
        $this->allow('prototype.quality.view');

        return ApiResponse::ok($this->present(collect([$this->find($check)]), true)[0]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('prototype.quality.manage');
        $data = $this->validated($request, true);
        $sample = Sample::query()->findOrFail($data['sample_id']);
        if (! $this->waiting()->contains('id', $sample->id)) {
            throw ValidationException::withMessages(['sample_id' => __('prototype.quality.not_waiting')]);
        }
        $row = DB::transaction(function () use ($data, $sample): SampleQualityCheck {
            $row = SampleQualityCheck::query()->create([
                ...collect($data)->except('checkpoints')->all(),
                'qc_no' => Prototype::nextNumber('QC', 'proto_quality_checks', 'qc_no', $data['check_date']),
                'version' => $sample->version, 'checked_by' => $data['checked_by'] ?? $this->caller()->auditUserId(),
            ]);
            $this->writeCheckpoints($row, $data['checkpoints'] ?? []);
            $this->move($row, $sample);
            SampleLedger::log($sample, 'qc_recorded', ['qc' => $row->qc_no, 'result' => $row->result], $row->qc_no);

            return $row;
        });

        return ApiResponse::created($this->present(collect([$row->fresh()]), true)[0]);
    }

    public function update(Request $request, string $check): JsonResponse
    {
        $this->allow('prototype.quality.manage');
        $row = $this->find($check);
        $sample = Sample::query()->findOrFail($row->sample_id);
        $latest = SampleQualityCheck::query()->where('sample_id', $sample->id)->where('version', $sample->version)->orderByDesc('created_at')->orderByDesc('qc_no')->value('id');
        if ($row->version !== $sample->version || $latest !== $row->id || SampleApproval::query()->where('quality_check_id', $row->id)->exists()) {
            throw ValidationException::withMessages(['qc_no' => __('prototype.quality.locked')]);
        }
        $data = $this->validated($request, false);
        DB::transaction(function () use ($row, $data, $sample): void {
            $row->update([...collect($data)->except(['checkpoints', 'sample_id'])->all(), 'checked_by' => $data['checked_by'] ?? $row->checked_by]);
            $this->writeCheckpoints($row, $data['checkpoints'] ?? []);
            $this->move($row, $sample);
        });

        return ApiResponse::ok($this->present(collect([$row->fresh()]), true)[0]);
    }

    /** Samples waiting in Quality, or up for approval with nothing raised on the version yet (a re-check). */
    private function waiting(): Collection
    {
        $raised = SampleApproval::query()->get(['sample_id', 'version'])->map(fn ($a) => $a->sample_id.':'.$a->version)->all();

        return Sample::query()->where('state', '!=', 'CANCELLED')->whereIn('phase', ['QUALITY', 'APPROVAL'])->orderByDesc('sample_no')->get()
            ->filter(fn (Sample $s) => $s->phase === 'QUALITY' || ! in_array($s->id.':'.$s->version, $raised, true))->values();
    }

    /** The verdict moves the sample. */
    private function move(SampleQualityCheck $row, Sample $sample): void
    {
        $this->record($row, $sample);
        if (in_array($row->result, SampleQualityCheck::PASSED, true)) {
            $sample->update(['phase' => 'APPROVAL', 'state' => 'PENDING']);
        } elseif ($row->result === 'FAIL') {
            $sample->update(['phase' => 'QUALITY', 'state' => 'FAILED']);
        } else {
            // Rework Required: back to production — the version's development reopens.
            $sample->update(['phase' => 'DEVELOPMENT', 'state' => 'REWORK']);
            SampleDevelopment::query()->where('sample_id', $sample->id)->where('version', $sample->version)->update(['status' => 'IN_PROGRESS', 'actual_completion' => null]);
            SampleRequisition::query()->whereKey($sample->requisition_id)->whereNotIn('status', ['CANCELLED'])->update(['status' => 'IN_DEVELOPMENT']);
        }
    }

    /** The check on the timeline; a pass hands the sample to approval, a rework takes it back to the sample room. */
    private function record(SampleQualityCheck $row, Sample $sample): void
    {
        $doc = "{$sample->sample_no}-V{$row->version}";
        if (in_array($row->result, SampleQualityCheck::PASSED, true)) {
            SampleLedger::resolve('QC_REQUIRED', $doc);
            SampleLedger::raise('APPROVAL_PENDING', 'INFO', $sample, 'MERCHANDISING', $doc, ['sample' => $sample->sample_no, 'version' => $row->version]);
        } elseif ($row->result === 'REWORK_REQUIRED') {
            SampleLedger::resolve('QC_REQUIRED', $doc);
        }
    }

    private function writeCheckpoints(SampleQualityCheck $row, array $checkpoints): void
    {
        $sent = collect($checkpoints)->keyBy('checkpoint');
        SampleQualityCheckpoint::query()->where('quality_check_id', $row->id)->delete();
        foreach (SampleQualityCheck::CHECKPOINTS as $i => $point) {
            $c = $sent[$point] ?? [];
            SampleQualityCheckpoint::query()->create([
                'quality_check_id' => $row->id, 'checkpoint' => $point, 'result' => $c['result'] ?? null, 'defect' => $c['defect'] ?? null,
                'severity' => $c['severity'] ?? null, 'remarks' => $c['remarks'] ?? null, 'sort_order' => $i + 1,
            ]);
        }
    }

    private function find(string $check): SampleQualityCheck
    {
        return SampleQualityCheck::query()->where('qc_no', $check)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'sample_id' => [$creating ? 'required' : 'nullable', Rule::exists('proto_samples', 'id')->where('company_id', $company)],
            'checked_by' => ['nullable', Rule::exists('company_users', 'user_id')->where('company_id', $company)],
            'check_date' => ['required', 'date'],
            'result' => ['required', Rule::in(SampleQualityCheck::RESULTS)],
            'severity' => ['nullable', Rule::in(SampleQualityCheck::SEVERITIES)],
            'defect_type' => ['nullable', 'string', 'max:255'],
            'defect' => ['nullable', 'string', 'max:4000'],
            'correction' => ['nullable', 'string', 'max:4000'],
            'remarks' => ['nullable', 'string', 'max:4000'],
            'attachment' => ['nullable', 'string', 'max:500'],
            'checkpoints' => ['nullable', 'array'],
            'checkpoints.*.checkpoint' => ['required', Rule::in(SampleQualityCheck::CHECKPOINTS)],
            'checkpoints.*.result' => ['nullable', Rule::in(SampleQualityCheck::CHECKPOINT_RESULTS)],
            'checkpoints.*.defect' => ['nullable', 'string', 'max:500'],
            'checkpoints.*.severity' => ['nullable', Rule::in(SampleQualityCheck::SEVERITIES)],
            'checkpoints.*.remarks' => ['nullable', 'string', 'max:500'],
        ], ['sample_id.required' => __('prototype.quality.sample_required'), 'check_date.required' => __('prototype.quality.date_required'), 'result.required' => __('prototype.quality.result_required')]);

        if (in_array($data['result'], ['FAIL', 'REWORK_REQUIRED'], true) && blank($data['defect'] ?? null)) {
            throw ValidationException::withMessages(['defect' => __('prototype.quality.defect_required')]);
        }
        foreach ($data['checkpoints'] ?? [] as $c) {
            $label = __('prototype.checkpoint.'.$c['checkpoint']);
            if (($c['result'] ?? null) === 'NOT_OK' && blank($c['defect'] ?? null)) {
                throw ValidationException::withMessages(['checkpoints' => __('prototype.quality.checkpoint_defect', ['point' => $label])]);
            }
            if ($data['result'] === 'PASS' && ($c['result'] ?? null) === 'NOT_OK' && in_array($c['severity'] ?? null, ['MAJOR', 'CRITICAL'], true)) {
                throw ValidationException::withMessages(['result' => __('prototype.quality.cannot_pass', ['point' => $label])]);
            }
        }

        return $data;
    }

    /** @return list<array<string, mixed>> */
    private function present($rows, bool $detail = false): array
    {
        $samples = Sample::query()->whereIn('id', $rows->pluck('sample_id'))->get()->keyBy('id');
        $cards = SampleCards::of($samples->values());
        // Correctable: the latest check of its sample's current version, with no approval raised on it.
        $latest = SampleQualityCheck::query()->whereIn('sample_id', $samples->keys())->orderByDesc('created_at')->orderByDesc('qc_no')->get(['id', 'sample_id', 'version'])
            ->unique(fn ($c) => $c->sample_id.':'.$c->version)->pluck('id')->all();
        $raised = SampleApproval::query()->whereIn('quality_check_id', $rows->pluck('id'))->pluck('quality_check_id')->all();
        $points = SampleQualityCheckpoint::query()->whereIn('quality_check_id', $rows->pluck('id'))->orderBy('sort_order')->get()->groupBy('quality_check_id');
        $names = Prototype::names($rows->pluck('checked_by')->all());

        return $rows->map(function (SampleQualityCheck $r) use ($cards, $points, $names, $detail, $samples, $latest, $raised): array {
            $list = $points[$r->id] ?? collect();
            $out = [
                'id' => $r->id, 'qc_no' => $r->qc_no, 'version' => $r->version, 'check_date' => $r->check_date->toDateString(), 'result' => $r->result,
                'severity' => $r->severity, 'defect_type' => $r->defect_type, 'defect' => $r->defect, 'correction' => $r->correction, 'remarks' => $r->remarks, 'attachment' => $r->attachment,
                'checked_by' => $r->checked_by, 'checked_by_name' => $names[$r->checked_by] ?? null, 'defects' => $list->where('result', 'NOT_OK')->count(),
                'correctable' => $r->version === $samples->get($r->sample_id)?->version && in_array($r->id, $latest, true) && ! in_array($r->id, $raised, true),
            ] + ($cards[$r->sample_id] ?? []);
            if ($detail) {
                $out['checkpoints'] = $list->map(fn (SampleQualityCheckpoint $c): array => $c->only(['checkpoint', 'result', 'defect', 'severity', 'remarks']))->values()->all();
            }

            return $out;
        })->values()->all();
    }
}

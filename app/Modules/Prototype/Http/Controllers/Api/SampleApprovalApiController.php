<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Http\Controllers\Api;

use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleApproval;
use App\Modules\Prototype\Models\SampleQualityCheck;
use App\Modules\Prototype\Support\Prototype;
use App\Modules\Prototype\Support\SampleCards;
use App\Modules\Prototype\Support\SampleLedger;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Prototype Desk → Sample Approval (AnnotechERP 07/03): a QC-passed sample
 * put up for merchandising review or buyer approval (APR-2026-00001), and the
 * decision taken on it. Approval without a passed QC is refused; buyer
 * approval needs an approved merchandising review on the same version;
 * Rejected and Revision Required need a comment and are answered with a
 * revision. A decided approval is locked. The buyer's approval readies the
 * sample for handover.
 */
class SampleApprovalApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('prototype.approval.view');
        $samples = Sample::query()->where('phase', 'APPROVAL')->whereIn('state', ['PENDING', 'IN_PROGRESS'])->orderByDesc('sample_no')->get();

        return ApiResponse::ok(['samples' => SampleCards::options($samples), 'steps' => SampleApproval::STEPS, 'decisions' => SampleApproval::DECISIONS]);
    }

    public function index(): JsonResponse
    {
        $this->allow('prototype.approval.view');

        return ApiResponse::ok($this->present(SampleApproval::query()->orderByDesc('submitted_date')->orderByDesc('approval_no')->get()));
    }

    public function show(string $approval): JsonResponse
    {
        $this->allow('prototype.approval.view');

        return ApiResponse::ok($this->present(collect([$this->find($approval)]))[0]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('prototype.approval.manage');
        $data = $this->validated($request, true);
        $sample = Sample::query()->findOrFail($data['sample_id']);
        if ($sample->phase !== 'APPROVAL' || ! in_array($sample->state, ['PENDING', 'IN_PROGRESS'], true)) {
            throw ValidationException::withMessages(['sample_id' => __('prototype.approval.not_waiting')]);
        }
        // The latest check of the version must have passed; it is linked as the QC reference.
        $qc = SampleQualityCheck::query()->where('sample_id', $sample->id)->where('version', $sample->version)->orderByDesc('created_at')->orderByDesc('qc_no')->first();
        if (! $qc || ! in_array($qc->result, SampleQualityCheck::PASSED, true)) {
            throw ValidationException::withMessages(['sample_id' => __('prototype.approval.qc_required')]);
        }
        $sameStep = SampleApproval::query()->where('sample_id', $sample->id)->where('version', $sample->version)->where('step', $data['step']);
        if ((clone $sameStep)->whereIn('decision', ['PENDING', ...SampleApproval::APPROVED])->exists()) {
            throw ValidationException::withMessages(['step' => __('prototype.approval.step_taken', ['step' => __('prototype.step.'.$data['step'])])]);
        }
        if ($data['step'] === 'BUYER_APPROVAL' && ! SampleApproval::query()->where('sample_id', $sample->id)->where('version', $sample->version)->where('step', 'MERCHANDISING_REVIEW')->whereIn('decision', SampleApproval::APPROVED)->exists()) {
            throw ValidationException::withMessages(['step' => __('prototype.approval.review_first')]);
        }
        $row = DB::transaction(function () use ($data, $sample, $qc): SampleApproval {
            $row = SampleApproval::query()->create([
                ...$this->decisionFields($data),
                'approval_no' => Prototype::nextNumber('APR', 'proto_approvals', 'approval_no', $data['submitted_date']),
                'sample_id' => $sample->id, 'version' => $sample->version, 'quality_check_id' => $qc->id, 'step' => $data['step'], 'submitted_date' => $data['submitted_date'],
            ]);
            $this->move($row, $sample);

            return $row;
        });

        return ApiResponse::created($this->present(collect([$row->fresh()]))[0]);
    }

    /** Deciding a pending approval; a decided one is locked. */
    public function update(Request $request, string $approval): JsonResponse
    {
        $this->allow('prototype.approval.manage');
        $row = $this->find($approval);
        if ($row->isDecided()) {
            throw ValidationException::withMessages(['decision' => __('prototype.approval.locked', ['decision' => __('prototype.decision_sa.'.$row->decision)])]);
        }
        $data = $this->validated($request, false);
        DB::transaction(function () use ($row, $data): void {
            $row->update([...$this->decisionFields($data), 'submitted_date' => $data['submitted_date']]);
            $this->move($row, Sample::query()->findOrFail($row->sample_id));
        });

        return ApiResponse::ok($this->present(collect([$row->fresh()]))[0]);
    }

    /** @return array<string, mixed> */
    private function decisionFields(array $data): array
    {
        $decision = $data['decision'] ?? 'PENDING';

        return [
            'decision' => $decision,
            // Left blank on a decision, today.
            'approval_date' => $decision === 'PENDING' ? null : ($data['approval_date'] ?? CarbonImmutable::today()->toDateString()),
            'approval_by' => $data['approval_by'] ?? null, 'buyer_comment' => $data['buyer_comment'] ?? null, 'internal_comment' => $data['internal_comment'] ?? null,
            'attachment' => $data['attachment'] ?? null, 'decided_by' => $decision === 'PENDING' ? null : $this->caller()->auditUserId(),
        ];
    }

    /** The decision moves the sample. */
    private function move(SampleApproval $row, Sample $sample): void
    {
        $state = match (true) {
            $row->decision === 'REJECTED' => ['phase' => 'APPROVAL', 'state' => 'REJECTED'],
            $row->decision === 'REVISION_REQUIRED' => ['phase' => 'APPROVAL', 'state' => 'REVISION_REQUIRED'],
            $row->step === 'BUYER_APPROVAL' && in_array($row->decision, SampleApproval::APPROVED, true) => ['phase' => 'HANDOVER', 'state' => 'READY'],
            default => ['phase' => 'APPROVAL', 'state' => 'IN_PROGRESS'],
        };
        $sample->update($state);
        SampleLedger::log($sample, $row->isDecided() ? 'approval_decided' : 'approval_submitted', ['apr' => $row->approval_no, 'step' => $row->step, 'decision' => $row->decision], $row->approval_no);
        if (! $row->isDecided()) {
            return;
        }
        $doc = "{$sample->sample_no}-V{$row->version}";
        if (in_array($row->decision, ['REJECTED', 'REVISION_REQUIRED'], true)) {
            SampleLedger::resolve('APPROVAL_PENDING', $doc);
            SampleLedger::raise('REVISION_REQUIRED', 'WARNING', $sample, 'SAMPLE_ROOM', $row->approval_no, ['sample' => $sample->sample_no, 'apr' => $row->approval_no, 'decision' => $row->decision]);
        } elseif ($row->step === 'BUYER_APPROVAL') {
            // The buyer's approval: the sample is approved and ready to hand over.
            SampleLedger::resolve('APPROVAL_PENDING', $doc);
            SampleLedger::raise('SAMPLE_APPROVED', 'SUCCESS', $sample, 'MERCHANDISING', $row->approval_no, ['sample' => $sample->sample_no, 'apr' => $row->approval_no]);
            SampleLedger::raise('HANDOVER_READY', 'INFO', $sample, 'MERCHANDISING', $sample->sample_no, ['sample' => $sample->sample_no]);
        }
    }

    private function find(string $approval): SampleApproval
    {
        return SampleApproval::query()->where('approval_no', $approval)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'sample_id' => [$creating ? 'required' : 'nullable', Rule::exists('proto_samples', 'id')->where('company_id', $company)],
            'step' => [$creating ? 'required' : 'nullable', Rule::in(SampleApproval::STEPS)],
            'submitted_date' => ['required', 'date'],
            'decision' => ['nullable', Rule::in(SampleApproval::DECISIONS)],
            'approval_date' => ['nullable', 'date'],
            'approval_by' => ['nullable', 'string', 'max:255'],
            'buyer_comment' => ['nullable', 'string', 'max:4000'],
            'internal_comment' => ['nullable', 'string', 'max:4000'],
            'attachment' => ['nullable', 'string', 'max:500'],
        ], ['sample_id.required' => __('prototype.approval.sample_required'), 'step.required' => __('prototype.approval.step_required'), 'submitted_date.required' => __('prototype.approval.date_required')]);
        if (in_array($data['decision'] ?? null, ['REJECTED', 'REVISION_REQUIRED'], true) && blank($data['buyer_comment'] ?? null) && blank($data['internal_comment'] ?? null)) {
            throw ValidationException::withMessages(['buyer_comment' => __('prototype.approval.comment_required')]);
        }
        if (! empty($data['approval_date']) && CarbonImmutable::parse($data['approval_date'])->lt(CarbonImmutable::parse($data['submitted_date']))) {
            throw ValidationException::withMessages(['approval_date' => __('prototype.approval.date_order')]);
        }

        return $data;
    }

    /** @return list<array<string, mixed>> */
    private function present($rows): array
    {
        $cards = SampleCards::of(Sample::query()->whereIn('id', $rows->pluck('sample_id'))->get());
        $qcs = SampleQualityCheck::query()->whereIn('id', $rows->pluck('quality_check_id')->filter())->pluck('qc_no', 'id');
        $today = CarbonImmutable::today();

        return $rows->map(fn (SampleApproval $r): array => [
            'id' => $r->id, 'approval_no' => $r->approval_no, 'version' => $r->version, 'step' => $r->step, 'submitted_date' => $r->submitted_date->toDateString(),
            'decision' => $r->decision, 'approval_date' => $r->approval_date?->toDateString(), 'approval_by' => $r->approval_by,
            'buyer_comment' => $r->buyer_comment, 'internal_comment' => $r->internal_comment, 'comment' => $r->buyer_comment ?? $r->internal_comment, 'attachment' => $r->attachment,
            'qc_no' => $r->quality_check_id ? ($qcs[$r->quality_check_id] ?? null) : null, 'decided' => $r->isDecided(),
            'waiting_days' => (int) $r->submitted_date->diffInDays($r->approval_date ?? $today),
            'answer_with_revision' => in_array($r->decision, ['REJECTED', 'REVISION_REQUIRED'], true),
        ] + ($cards[$r->sample_id] ?? []))->values()->all();
    }
}

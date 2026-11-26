<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Http\Controllers\Api;

use App\Modules\Merchandise\Models\SampleType;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleApproval;
use App\Modules\Prototype\Models\SampleFeedback;
use App\Modules\Prototype\Models\SampleRequisition;
use App\Modules\Prototype\Models\SampleRevision;
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
 * Prototype Desk → Sample Revision (AnnotechERP 07/03): a change to a sample
 * after feedback or correction (REV-2026-00001) — why, and what changes in
 * measurement, fabric, colour, accessories and construction. Saving a new
 * revision moves the sample to its next version, back to Revision · In
 * Progress; the previous version's work stays on file. Edited only while
 * Open, never removed; Approve Revision signs it off.
 */
class SampleRevisionApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('prototype.revision.view');
        $samples = Sample::query()->where('state', '!=', 'CANCELLED')->orderByDesc('sample_no')->get();
        // What a revision answers: a rejected or revision-required approval, or the buyer's feedback.
        $approvals = SampleApproval::query()->whereIn('sample_id', $samples->pluck('id'))->whereIn('decision', ['REJECTED', 'REVISION_REQUIRED'])->orderBy('approval_no')->get();
        $feedback = SampleFeedback::query()->whereIn('sample_id', $samples->pluck('id'))->orderBy('feedback_no')->get();

        return ApiResponse::ok([
            'samples' => SampleCards::options($samples), 'users' => Prototype::users(), 'statuses' => SampleRevision::STATUSES,
            'approvals' => $approvals->map(fn (SampleApproval $a): array => ['value' => $a->id, 'no' => $a->approval_no, 'sample_id' => $a->sample_id, 'label' => "{$a->approval_no} · V{$a->version} · ".__('prototype.decision_sa.'.$a->decision), 'comment' => $a->buyer_comment ?? $a->internal_comment])->all(),
            'feedback' => $feedback->map(fn (SampleFeedback $f): array => [
                'value' => $f->id, 'no' => $f->feedback_no, 'sample_id' => $f->sample_id, 'label' => "{$f->feedback_no} · V{$f->version} · ".__('prototype.fb_type.'.$f->feedback_type), 'comment' => $f->comment,
                'measurement' => $f->measurement_change, 'fabric' => $f->fabric_change, 'color' => $f->color_change, 'construction' => $f->construction_change,
            ])->all(),
        ]);
    }

    public function index(): JsonResponse
    {
        $this->allow('prototype.revision.view');

        return ApiResponse::ok($this->present(SampleRevision::query()->orderByDesc('revision_date')->orderByDesc('rev_no')->get()));
    }

    public function show(string $revision): JsonResponse
    {
        $this->allow('prototype.revision.view');

        return ApiResponse::ok($this->present(collect([$this->find($revision)]), true)[0]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('prototype.revision.manage');
        $data = $this->validated($request, true);
        $sample = Sample::query()->findOrFail($data['sample_id']);
        if (! $sample->isOpen()) {
            throw ValidationException::withMessages(['sample_id' => __('prototype.revision.sample_closed')]);
        }
        if (! empty($data['source_approval_id']) && SampleApproval::query()->whereKey($data['source_approval_id'])->value('sample_id') !== $sample->id) {
            throw ValidationException::withMessages(['source_approval_id' => __('prototype.revision.source_other_sample')]);
        }
        if (! empty($data['source_feedback_id']) && SampleFeedback::query()->whereKey($data['source_feedback_id'])->value('sample_id') !== $sample->id) {
            throw ValidationException::withMessages(['source_feedback_id' => __('prototype.revision.source_other_sample')]);
        }
        $typeId = SampleRequisition::query()->whereKey($sample->requisition_id)->value('sample_type_id');
        if (SampleType::query()->whereKey($typeId)->value('revision_allowed') === false) {
            throw ValidationException::withMessages(['sample_id' => __('prototype.revision.not_allowed')]);
        }
        $row = DB::transaction(function () use ($data, $sample): SampleRevision {
            // A blank Requested By is the signed-in user (the form sends it as null, which must not win).
            $row = SampleRevision::query()->create([
                ...$data,
                'rev_no' => Prototype::nextNumber('REV', 'proto_revisions', 'rev_no', $data['revision_date']),
                'previous_version' => $sample->version, 'new_version' => $sample->version + 1, 'status' => 'OPEN',
                'requested_by' => $data['requested_by'] ?? $this->caller()->auditUserId(),
            ]);
            // The sample moves to its next version, back to Revision · In Progress.
            $sample->update(['version' => $row->new_version, 'phase' => 'REVISION', 'state' => 'IN_PROGRESS']);
            SampleRequisition::query()->whereKey($sample->requisition_id)->whereNotIn('status', ['CANCELLED'])->update(['status' => 'IN_DEVELOPMENT']);
            SampleLedger::log($sample, 'revision_raised', ['rev' => $row->rev_no, 'from' => $row->previous_version, 'to' => $row->new_version, 'reason' => $row->reason], $row->rev_no);
            SampleLedger::resolve('REVISION_REQUIRED', null, $sample);

            return $row;
        });

        return ApiResponse::created($this->present(collect([$row->fresh()]), true)[0]);
    }

    public function update(Request $request, string $revision): JsonResponse
    {
        $this->allow('prototype.revision.manage');
        $row = $this->find($revision);
        if ($row->status !== 'OPEN') {
            throw ValidationException::withMessages(['status' => __('prototype.revision.locked', ['status' => __('prototype.rev_status.'.$row->status)])]);
        }
        $row->update(collect($this->validated($request, false))->except(['sample_id', 'source_approval_id', 'source_feedback_id'])->all());

        return ApiResponse::ok($this->present(collect([$row->fresh()]), true)[0]);
    }

    /** Approve Revision: who signed it off, and when. */
    public function approve(string $revision): JsonResponse
    {
        $this->allow('prototype.revision.approve');
        $row = $this->find($revision);
        if ($row->approved_at !== null || $row->status === 'CANCELLED') {
            throw ValidationException::withMessages(['status' => __('prototype.revision.already_approved')]);
        }
        $row->update(['approved_by' => $this->caller()->auditUserId(), 'approved_at' => now()]);
        SampleLedger::log($row->sample_id, 'revision_approved', ['rev' => $row->rev_no], $row->rev_no);

        return ApiResponse::ok($this->present(collect([$row->fresh()]), true)[0]);
    }

    private function find(string $revision): SampleRevision
    {
        return SampleRevision::query()->where('rev_no', $revision)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $company = app(TenantContext::class)->companyId();
        $text = ['nullable', 'string', 'max:4000'];
        $data = $request->validate([
            'sample_id' => [$creating ? 'required' : 'nullable', Rule::exists('proto_samples', 'id')->where('company_id', $company)],
            'revision_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:500'],
            'buyer_comment' => $text, 'internal_comment' => $text,
            'changed_measurement' => $text, 'changed_fabric' => $text, 'changed_color' => $text, 'changed_accessories' => $text, 'changed_construction' => $text,
            'requested_by' => ['nullable', Rule::exists('company_users', 'user_id')->where('company_id', $company)],
            'source_approval_id' => ['nullable', Rule::exists('proto_approvals', 'id')->where('company_id', $company)],
            'source_feedback_id' => ['nullable', Rule::exists('proto_feedback', 'id')->where('company_id', $company)],
            'attachment' => ['nullable', 'string', 'max:500'],
        ], ['sample_id.required' => __('prototype.revision.sample_required'), 'revision_date.required' => __('prototype.revision.date_required'), 'reason.required' => __('prototype.revision.reason_required')]);
        if (! empty($data['due_date']) && CarbonImmutable::parse($data['due_date'])->lt(CarbonImmutable::parse($data['revision_date']))) {
            throw ValidationException::withMessages(['due_date' => __('prototype.revision.due_before_date')]);
        }

        return $data;
    }

    /** @return list<array<string, mixed>> */
    private function present($rows, bool $detail = false): array
    {
        $samples = Sample::query()->whereIn('id', $rows->pluck('sample_id'))->get();
        $cards = SampleCards::of($samples);
        $counts = SampleRevision::query()->whereIn('sample_id', $samples->pluck('id'))->selectRaw('sample_id, count(*) as n')->groupBy('sample_id')->pluck('n', 'sample_id');
        $names = Prototype::names([...$rows->pluck('requested_by'), ...$rows->pluck('approved_by')]);
        $approvalNos = SampleApproval::query()->whereIn('id', $rows->pluck('source_approval_id')->filter())->pluck('approval_no', 'id');
        $feedbackNos = SampleFeedback::query()->whereIn('id', $rows->pluck('source_feedback_id')->filter())->pluck('feedback_no', 'id');
        $history = $detail ? SampleRevision::query()->whereIn('sample_id', $samples->pluck('id'))->orderBy('new_version')->get()->groupBy('sample_id') : collect();

        return $rows->map(function (SampleRevision $r) use ($cards, $counts, $names, $history, $detail, $approvalNos, $feedbackNos): array {
            $out = [
                'id' => $r->id, 'rev_no' => $r->rev_no, 'revision_date' => $r->revision_date->toDateString(), 'due_date' => $r->due_date?->toDateString(),
                'previous_version' => $r->previous_version, 'new_version' => $r->new_version, 'status' => $r->status, 'reason' => $r->reason,
                'source_approval_id' => $r->source_approval_id, 'source_feedback_id' => $r->source_feedback_id,
                'source_approval_no' => $approvalNos[$r->source_approval_id] ?? null, 'source_feedback_no' => $feedbackNos[$r->source_feedback_id] ?? null,
                'buyer_comment' => $r->buyer_comment, 'internal_comment' => $r->internal_comment,
                'changed_measurement' => $r->changed_measurement, 'changed_fabric' => $r->changed_fabric, 'changed_color' => $r->changed_color,
                'changed_accessories' => $r->changed_accessories, 'changed_construction' => $r->changed_construction,
                'requested_by' => $r->requested_by, 'requested_by_name' => $names[$r->requested_by] ?? null,
                'approved_by_name' => $names[$r->approved_by] ?? null, 'approved_at' => $r->approved_at?->toIso8601String(),
                'attachment' => $r->attachment, 'revisions' => (int) ($counts[$r->sample_id] ?? 0),
            ] + ($cards[$r->sample_id] ?? []);
            if ($detail) {
                $out['history'] = ($history[$r->sample_id] ?? collect())->map(fn (SampleRevision $h): array => [
                    'rev_no' => $h->rev_no, 'revision_date' => $h->revision_date->toDateString(), 'previous_version' => $h->previous_version, 'new_version' => $h->new_version,
                    'reason' => $h->reason, 'status' => $h->status, 'approved' => $h->approved_at !== null,
                ])->values()->all();
            }

            return $out;
        })->values()->all();
    }
}

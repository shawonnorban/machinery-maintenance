<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleApproval;
use App\Modules\Prototype\Models\SampleFeedback;
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
 * Prototype Desk → Buyer Feedback (AnnotechERP 07/03): what the buyer said
 * about a sample (FB-2026-00001), the changes asked for and who has to act.
 * Action Required needs an assignee and a due date, and the assignee is
 * alerted on save; Resolved or Closed clears the alert. A closed feedback is
 * locked.
 */
class SampleFeedbackApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('prototype.feedback.view');
        $samples = Sample::query()->where('state', '!=', 'CANCELLED')->orderByDesc('sample_no')->get();
        $approvals = SampleApproval::query()->whereIn('sample_id', $samples->pluck('id'))->orderBy('approval_no')->get();

        return ApiResponse::ok([
            'samples' => SampleCards::options($samples), 'users' => Prototype::users(), 'types' => SampleFeedback::TYPES, 'statuses' => SampleFeedback::STATUSES,
            'approvals' => $approvals->map(fn (SampleApproval $a): array => ['value' => $a->id, 'sample_id' => $a->sample_id, 'label' => "{$a->approval_no} · V{$a->version} · ".__('prototype.step.'.$a->step).' · '.__('prototype.decision_sa.'.$a->decision)])->all(),
        ]);
    }

    public function index(): JsonResponse
    {
        $this->allow('prototype.feedback.view');

        return ApiResponse::ok($this->present(SampleFeedback::query()->orderByDesc('feedback_date')->orderByDesc('feedback_no')->get()));
    }

    public function show(string $feedback): JsonResponse
    {
        $this->allow('prototype.feedback.view');

        return ApiResponse::ok($this->present(collect([$this->find($feedback)]))[0]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('prototype.feedback.manage');
        $data = $this->validated($request, true);
        $sample = Sample::query()->findOrFail($data['sample_id']);
        $row = DB::transaction(function () use ($data, $sample): SampleFeedback {
            $row = SampleFeedback::query()->create([...$data, 'status' => $data['status'] ?? 'OPEN', 'version' => $sample->version,
                'feedback_no' => Prototype::nextNumber('FB', 'proto_feedback', 'feedback_no', $data['feedback_date'])]);
            $this->alert($row, null);

            return $row;
        });

        return ApiResponse::created($this->present(collect([$row->fresh()]))[0]);
    }

    public function update(Request $request, string $feedback): JsonResponse
    {
        $this->allow('prototype.feedback.manage');
        $row = $this->find($feedback);
        if ($row->status === 'CLOSED') {
            throw ValidationException::withMessages(['status' => __('prototype.feedback.locked')]);
        }
        $data = $this->validated($request, false, $row->sample_id);
        $before = $row->assigned_to;
        DB::transaction(function () use ($row, $data, $before): void {
            $row->update([...collect($data)->except('sample_id')->all(), 'status' => $data['status'] ?? $row->status]);
            $this->alert($row->fresh(), $before);
        });

        return ApiResponse::ok($this->present(collect([$row->fresh()]))[0]);
    }

    /** Alert a new assignee of an open action; clear the alerts once it is resolved or closed. */
    private function alert(SampleFeedback $row, ?string $before): void
    {
        SampleLedger::log($row->sample_id, 'feedback_recorded', ['fb' => $row->feedback_no, 'type' => $row->feedback_type, 'status' => $row->status], $row->feedback_no);
        if (in_array($row->status, ['RESOLVED', 'CLOSED'], true)) {
            SampleLedger::resolve('FEEDBACK_PENDING', $row->feedback_no);
        } elseif ($row->action_required) {
            SampleLedger::raise('FEEDBACK_PENDING', 'WARNING', $row->sample_id, 'SAMPLE_ROOM', $row->feedback_no, ['fb' => $row->feedback_no, 'due' => $row->due_date?->toDateString()]);
        }
        if (in_array($row->status, ['RESOLVED', 'CLOSED'], true)) {
            Notification::query()->where('entity_type', 'proto_feedback')->where('entity_id', $row->id)->whereNull('read_at')->update(['read_at' => now()]);

            return;
        }
        if (! $row->action_required || ! $row->assigned_to || $row->assigned_to === $before) {
            return;
        }
        $assignee = User::query()->find($row->assigned_to);
        $sample = Sample::query()->find($row->sample_id);
        if ($assignee && $sample) {
            app(NotificationDispatcher::class)->send($assignee, 'SAMPLE_FEEDBACK_ACTION', ['feedback' => $row->feedback_no, 'sample' => $sample->sample_no, 'due' => $row->due_date?->format('d M Y')],
                severity: 'WARNING', entityType: 'proto_feedback', entityId: $row->id, actionUrl: '/prototype-desk/buyer-feedback?tab=feedback&fb='.$row->feedback_no);
        }
    }

    private function find(string $feedback): SampleFeedback
    {
        return SampleFeedback::query()->where('feedback_no', $feedback)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating, ?string $sampleId = null): array
    {
        $company = app(TenantContext::class)->companyId();
        $text = ['nullable', 'string', 'max:4000'];
        $data = $request->validate([
            'sample_id' => [$creating ? 'required' : 'nullable', Rule::exists('proto_samples', 'id')->where('company_id', $company)],
            'approval_id' => ['nullable', Rule::exists('proto_approvals', 'id')->where('company_id', $company)],
            'feedback_date' => ['required', 'date'],
            'feedback_type' => ['required', Rule::in(SampleFeedback::TYPES)],
            'status' => ['nullable', Rule::in(SampleFeedback::STATUSES)],
            'comment' => $text, 'measurement_change' => $text, 'fabric_change' => $text, 'color_change' => $text, 'construction_change' => $text,
            'action_required' => ['sometimes', 'boolean'],
            'assigned_to' => ['nullable', Rule::exists('company_users', 'user_id')->where('company_id', $company)],
            'due_date' => ['nullable', 'date'],
            'attachment' => ['nullable', 'string', 'max:500'],
        ], ['sample_id.required' => __('prototype.feedback.sample_required'), 'feedback_date.required' => __('prototype.feedback.date_required'), 'feedback_type.required' => __('prototype.feedback.type_required')]);
        $sample = $sampleId ?? $data['sample_id'];
        if (! empty($data['approval_id']) && SampleApproval::query()->whereKey($data['approval_id'])->value('sample_id') !== $sample) {
            throw ValidationException::withMessages(['approval_id' => __('prototype.feedback.approval_other_sample')]);
        }
        $data['action_required'] = (bool) ($data['action_required'] ?? false);
        if ($data['action_required'] && (empty($data['assigned_to']) || empty($data['due_date']))) {
            throw ValidationException::withMessages(['assigned_to' => __('prototype.feedback.action_needs')]);
        }
        if (! empty($data['due_date']) && CarbonImmutable::parse($data['due_date'])->lt(CarbonImmutable::parse($data['feedback_date']))) {
            throw ValidationException::withMessages(['due_date' => __('prototype.feedback.due_before_date')]);
        }

        return $data;
    }

    /** @return list<array<string, mixed>> */
    private function present($rows): array
    {
        $cards = SampleCards::of(Sample::query()->whereIn('id', $rows->pluck('sample_id'))->get());
        $approvals = SampleApproval::query()->whereIn('id', $rows->pluck('approval_id')->filter())->pluck('approval_no', 'id');
        $names = Prototype::names($rows->pluck('assigned_to')->all());
        $today = CarbonImmutable::today();

        return $rows->map(fn (SampleFeedback $r): array => [
            'id' => $r->id, 'feedback_no' => $r->feedback_no, 'version' => $r->version, 'feedback_date' => $r->feedback_date->toDateString(), 'feedback_type' => $r->feedback_type,
            'status' => $r->status, 'comment' => $r->comment, 'measurement_change' => $r->measurement_change, 'fabric_change' => $r->fabric_change,
            'color_change' => $r->color_change, 'construction_change' => $r->construction_change, 'action_required' => $r->action_required,
            'assigned_to' => $r->assigned_to, 'assigned_to_name' => $names[$r->assigned_to] ?? null, 'due_date' => $r->due_date?->toDateString(),
            'approval_id' => $r->approval_id, 'approval_no' => $r->approval_id ? ($approvals[$r->approval_id] ?? null) : null, 'attachment' => $r->attachment,
            'overdue_days' => $r->due_date && $r->due_date->lt($today) && ! in_array($r->status, ['RESOLVED', 'CLOSED'], true) ? (int) $r->due_date->diffInDays($today) : null,
        ] + ($cards[$r->sample_id] ?? []))->values()->all();
    }
}

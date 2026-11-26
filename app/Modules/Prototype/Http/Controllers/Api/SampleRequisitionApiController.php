<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\SampleType;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleItem;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleAlert;
use App\Modules\Prototype\Models\SampleEvent;
use App\Modules\Prototype\Models\SampleRequisition;
use App\Modules\Prototype\Models\SampleRequisitionDecision;
use App\Modules\Prototype\Support\Prototype;
use App\Modules\Prototype\Support\SampleLedger;
use App\Modules\Tenancy\Models\Department;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;

/**
 * Prototype Desk → Sample Requisition (AnnotechERP 07/03), as Brickland works
 * it: the merchandiser raises it (SR-2026-00001) and submits it; the
 * merchandiser, the merchandising manager and the sample manager each
 * decide in turn, a rejection — which needs a reason — sending it back. The
 * sample manager's approval mints the sample (SMP-2026-00001), and assigns
 * it at once when it names who in the sample room takes it; Assign does the
 * same later, and the assignee is told. Decisions are kept, never changed.
 */
class SampleRequisitionApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('prototype.requisition.view');

        return ApiResponse::ok(Prototype::requisitionOptions());
    }

    public function index(): JsonResponse
    {
        $this->allow('prototype.requisition.view');
        $rows = SampleRequisition::query()->orderByDesc('req_date')->orderByDesc('req_no')->get();

        return ApiResponse::ok($this->present($rows));
    }

    public function show(string $requisition): JsonResponse
    {
        $this->allow('prototype.requisition.view');

        return ApiResponse::ok($this->present(collect([$this->find($requisition)]), true)[0]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('prototype.requisition.manage');
        $data = $this->validated($request);
        $row = DB::transaction(fn (): SampleRequisition => SampleRequisition::query()->create($data + [
            'req_no' => Prototype::nextNumber('SR', 'proto_requisitions', 'req_no', $data['req_date']),
            'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(),
        ]));
        SampleLedger::log(null, 'requisition_created', ['req' => $row->req_no], $row->req_no);

        return ApiResponse::created($this->present(collect([$row]), true)[0]);
    }

    public function update(Request $request, string $requisition): JsonResponse
    {
        $this->allow('prototype.requisition.manage');
        $row = $this->find($requisition);
        if (in_array($row->status, ['APPROVED', 'ASSIGNED', 'IN_DEVELOPMENT', 'COMPLETED'], true)) {
            // Once approved, only who in the sample room takes it is read from the form.
            $data = $request->validate(['assigned_to' => ['nullable', $this->userRule()]]);
            $row->update(['assigned_to' => $data['assigned_to'] ?? null]);
        } elseif ($row->isEditable()) {
            // Saving a rejected requisition returns it to Draft.
            $row->update($this->validated($request) + ['status' => 'DRAFT', 'pending_level' => null]);
        } else {
            throw ValidationException::withMessages(['status' => __('prototype.requisition.locked', ['status' => __('prototype.status.'.$row->status)])]);
        }

        return ApiResponse::ok($this->present(collect([$row->fresh()]), true)[0]);
    }

    /** Draft → Pending Approval, at the first level. */
    public function submit(string $requisition): JsonResponse
    {
        $this->allow('prototype.requisition.manage');
        $row = $this->find($requisition);
        if ($row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('prototype.requisition.submit_draft_only')]);
        }
        $row->update(['status' => 'PENDING_APPROVAL', 'pending_level' => SampleRequisition::LEVELS[0]]);
        SampleLedger::log($row->sample_id, 'requisition_submitted', ['req' => $row->req_no], $row->req_no);

        return ApiResponse::ok($this->present(collect([$row->fresh()]), true)[0]);
    }

    /** The level it is pending at decides: Approve moves it on (the last mints the sample); Reject, with a reason, sends it back. */
    public function decide(Request $request, string $requisition): JsonResponse
    {
        $this->allow('prototype.requisition.approve');
        $row = $this->find($requisition);
        if ($row->status !== 'PENDING_APPROVAL' || $row->pending_level === null) {
            throw ValidationException::withMessages(['decision' => __('prototype.requisition.not_pending')]);
        }
        $last = $row->pending_level === SampleRequisition::LEVELS[count(SampleRequisition::LEVELS) - 1];
        $data = $request->validate([
            'decision' => ['required', Rule::in(['APPROVE', 'REJECT'])],
            'comments' => ['nullable', 'string', 'max:2000'],
            'rejection_reason' => ['nullable', 'required_if:decision,REJECT', 'string', 'max:2000'],
            'assigned_to' => ['nullable', $this->userRule()],
            'attachment' => ['nullable', 'string', 'max:500'],
        ], ['decision.required' => __('prototype.requisition.decision_required'), 'rejection_reason.required_if' => __('prototype.requisition.reason_required')]);
        if (! empty($data['assigned_to']) && ! $last) {
            throw ValidationException::withMessages(['assigned_to' => __('prototype.requisition.assign_sample_manager')]);
        }

        DB::transaction(function () use ($row, $data, $last): void {
            SampleRequisitionDecision::query()->create([
                'requisition_id' => $row->id, 'level' => $row->pending_level, 'decision' => $data['decision'] === 'APPROVE' ? 'APPROVED' : 'REJECTED',
                'decided_by' => $this->caller()->auditUserId(), 'decided_at' => now(), 'comments' => $data['comments'] ?? null,
                'rejection_reason' => $data['decision'] === 'REJECT' ? $data['rejection_reason'] : null, 'attachment' => $data['attachment'] ?? null,
            ]);
            if ($data['decision'] === 'REJECT') {
                SampleLedger::log($row->sample_id, 'requisition_rejected', ['req' => $row->req_no, 'level' => $row->pending_level, 'reason' => $data['rejection_reason']], $row->req_no);
                $row->update(['status' => 'REJECTED', 'pending_level' => null]);

                return;
            }
            if (! $last) {
                SampleLedger::log($row->sample_id, 'requisition_level_approved', ['req' => $row->req_no, 'level' => $row->pending_level], $row->req_no);
                $row->update(['pending_level' => SampleRequisition::LEVELS[array_search($row->pending_level, SampleRequisition::LEVELS, true) + 1]]);

                return;
            }
            // The sample manager's approval mints the sample.
            $sample = Sample::query()->create([
                'sample_no' => Prototype::nextNumber('SMP', 'proto_samples', 'sample_no', now()->toDateString()),
                'requisition_id' => $row->id, 'version' => 1, 'phase' => 'REQUISITION', 'state' => 'APPROVED',
            ]);
            $row->update(['status' => 'APPROVED', 'pending_level' => null, 'sample_id' => $sample->id, 'assigned_to' => $data['assigned_to'] ?? $row->assigned_to]);
            // What happened to the requisition before the sample existed joins the sample's timeline.
            SampleEvent::query()->whereNull('sample_id')->where('doc_no', $row->req_no)->update(['sample_id' => $sample->id]);
            SampleLedger::log($sample, 'requisition_approved', ['req' => $row->req_no, 'sample' => $sample->sample_no], $row->req_no);
            if (! empty($data['assigned_to'])) {
                $this->assignSample($row->fresh(), $sample);
            }
        });

        return ApiResponse::ok($this->present(collect([$row->fresh()]), true)[0]);
    }

    /** Approved → Assigned: the sample goes to the person in Assigned To, who is told. */
    public function assign(Request $request, string $requisition): JsonResponse
    {
        $this->allow('prototype.requisition.approve');
        $row = $this->find($requisition);
        $data = $request->validate(['assigned_to' => ['nullable', $this->userRule()]]);
        $assignee = $data['assigned_to'] ?? $row->assigned_to;
        if ($row->status !== 'APPROVED' || $row->sample_id === null) {
            throw ValidationException::withMessages(['status' => __('prototype.requisition.assign_approved_only')]);
        }
        if (! $assignee) {
            throw ValidationException::withMessages(['assigned_to' => __('prototype.requisition.assignee_required')]);
        }
        DB::transaction(function () use ($row, $assignee): void {
            $row->update(['assigned_to' => $assignee]);
            $this->assignSample($row->fresh(), Sample::query()->findOrFail($row->sample_id));
        });

        return ApiResponse::ok($this->present(collect([$row->fresh()]), true)[0]);
    }

    /** Cancelled, before the sample room has started on it; a minted sample is cancelled with it. */
    public function cancel(string $requisition): JsonResponse
    {
        $this->allow('prototype.requisition.manage');
        $row = $this->find($requisition);
        if (in_array($row->status, ['IN_DEVELOPMENT', 'COMPLETED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['status' => __('prototype.requisition.cancel_locked', ['status' => __('prototype.status.'.$row->status)])]);
        }
        DB::transaction(function () use ($row): void {
            $row->update(['status' => 'CANCELLED', 'pending_level' => null]);
            Sample::query()->whereKey($row->sample_id)->update(['state' => 'CANCELLED']);
            SampleLedger::log($row->sample_id, 'requisition_cancelled', ['req' => $row->req_no], $row->req_no);
            if ($row->sample_id) {
                SampleLedger::resolve(SampleAlert::KINDS, null, $row->sample_id);
            }
        });

        return ApiResponse::ok($this->present(collect([$row->fresh()]), true)[0]);
    }

    private function assignSample(SampleRequisition $row, Sample $sample): void
    {
        $sample->update(['assigned_to' => $row->assigned_to, 'assigned_at' => now(), 'state' => 'ASSIGNED']);
        $row->update(['status' => 'ASSIGNED']);
        $assignee = User::query()->find($row->assigned_to);
        SampleLedger::log($sample, 'sample_assigned', ['sample' => $sample->sample_no, 'to' => $assignee?->name], $sample->sample_no);
        SampleLedger::raise('SAMPLE_ASSIGNED', 'INFO', $sample, 'SAMPLE_ROOM', $sample->sample_no, ['sample' => $sample->sample_no, 'to' => $assignee?->name]);
        if ($assignee) {
            app(NotificationDispatcher::class)->send($assignee, 'SAMPLE_ASSIGNED', ['sample' => $sample->sample_no, 'requisition' => $row->req_no],
                entityType: 'proto_sample', entityId: $sample->id, actionUrl: '/prototype-desk/sample-development');
        }
    }

    private function find(string $requisition): SampleRequisition
    {
        return SampleRequisition::query()->where('req_no', $requisition)->firstOrFail();
    }

    private function userRule(): Exists
    {
        return Rule::exists('company_users', 'user_id')->where('company_id', app(TenantContext::class)->companyId());
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'req_date' => ['required', 'date'],
            'buyer_id' => ['required', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'buyer_office_id' => ['nullable', Rule::exists('buyer_offices', 'id')->where('company_id', $company)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'style_id' => ['required', Rule::exists('merch_styles', 'id')->where('company_id', $company)],
            'season' => ['nullable', 'string', 'max:64'],
            'product_category' => ['nullable', 'string', 'max:120'],
            'sample_type_id' => ['required', Rule::exists('sample_types', 'id')->where('company_id', $company)],
            'sample_stage' => ['nullable', Rule::in(SampleType::STAGES)],
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'required_date' => ['required', 'date'],
            'priority' => ['nullable', Rule::in(SampleRequisition::PRIORITIES)],
            'requested_by' => ['nullable', $this->userRule()],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('company_id', $company)],
            'assigned_to' => ['nullable', $this->userRule()],
            'remarks' => ['nullable', 'string', 'max:4000'],
            'attachment' => ['nullable', 'string', 'max:500'],
        ], [
            'req_date.required' => __('prototype.requisition.date_required'), 'buyer_id.required' => __('prototype.requisition.buyer_required'),
            'style_id.required' => __('prototype.requisition.style_required'), 'sample_type_id.required' => __('prototype.requisition.type_required'),
            'qty.required' => __('prototype.requisition.qty_required'), 'qty.min' => __('prototype.requisition.qty_required'),
            'required_date.required' => __('prototype.requisition.required_date_required'),
        ]);

        // Only the chosen buyer's styles, orders and buying houses.
        $style = Style::query()->findOrFail($data['style_id']);
        if ($style->buyer_id !== $data['buyer_id']) {
            throw ValidationException::withMessages(['style_id' => __('prototype.requisition.style_other_buyer')]);
        }
        if (! empty($data['order_id']) && Order::query()->whereKey($data['order_id'])->value('buyer_id') !== $data['buyer_id']) {
            throw ValidationException::withMessages(['order_id' => __('prototype.requisition.order_other_buyer')]);
        }
        $type = SampleType::query()->findOrFail($data['sample_type_id']);
        if (($type->order_required || $type->po_required) && empty($data['order_id'])) {
            throw ValidationException::withMessages(['order_id' => __('prototype.requisition.order_required', ['type' => $type->name])]);
        }
        if (CarbonImmutable::parse($data['required_date'])->lt(CarbonImmutable::parse($data['req_date']))) {
            throw ValidationException::withMessages(['required_date' => __('prototype.requisition.required_before_date')]);
        }

        return [
            ...$data,
            'buyer_office_id' => $data['buyer_office_id'] ?? null, 'order_id' => $data['order_id'] ?? null,
            'season' => $data['season'] ?? $style->season,
            // Left blank, the style's garment and the sample type's stage.
            'product_category' => $data['product_category'] ?? StyleItem::query()->where('style_id', $style->id)->orderBy('sort_order')->value('item_type'),
            'sample_stage' => $data['sample_stage'] ?? $type->stage,
            'priority' => $data['priority'] ?? 'NORMAL',
            'requested_by' => $data['requested_by'] ?? $this->caller()->auditUserId(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function present($rows, bool $detail = false): array
    {
        $buyers = Buyer::query()->whereIn('id', $rows->pluck('buyer_id'))->pluck('short_name', 'id');
        $styles = Style::query()->whereIn('id', $rows->pluck('style_id'))->get(['id', 'style_ref', 'style_ext', 'description'])->keyBy('id');
        $orders = Order::query()->whereIn('id', $rows->pluck('order_id')->filter())->get(['id', 'order_no', 'job_no'])->keyBy('id');
        $types = SampleType::query()->whereIn('id', $rows->pluck('sample_type_id'))->pluck('name', 'id');
        $samples = Sample::query()->whereIn('id', $rows->pluck('sample_id')->filter())->get()->keyBy('id');
        $departments = Department::query()->whereIn('id', $rows->pluck('department_id')->filter())->pluck('name', 'id');
        $offices = DB::table('buyer_offices')->whereIn('id', $rows->pluck('buyer_office_id')->filter())->pluck('name', 'id');
        $decisions = $detail ? SampleRequisitionDecision::query()->whereIn('requisition_id', $rows->pluck('id'))->orderBy('decided_at')->get()->groupBy('requisition_id') : collect();
        $names = Prototype::names([...$rows->pluck('requested_by'), ...$rows->pluck('assigned_to'), ...$decisions->flatten()->pluck('decided_by')]);
        $today = CarbonImmutable::today();

        return $rows->map(function (SampleRequisition $r) use ($buyers, $styles, $orders, $types, $samples, $departments, $offices, $decisions, $names, $today, $detail): array {
            $style = $styles[$r->style_id] ?? null;
            $order = $r->order_id ? ($orders[$r->order_id] ?? null) : null;
            $out = [
                'id' => $r->id, 'req_no' => $r->req_no, 'req_date' => $r->req_date->toDateString(),
                'buyer_id' => $r->buyer_id, 'buyer' => $buyers[$r->buyer_id] ?? null, 'buyer_office_id' => $r->buyer_office_id, 'buying_house' => $r->buyer_office_id ? ($offices[$r->buyer_office_id] ?? null) : null,
                'order_id' => $r->order_id, 'order_no' => $order?->order_no, 'job_no' => $order?->job_no,
                'style_id' => $r->style_id, 'style_ref' => $style ? trim($style->style_ref.($style->style_ext ? "-{$style->style_ext}" : '')) : null, 'style_name' => $style?->description,
                'season' => $r->season, 'product_category' => $r->product_category,
                'sample_type_id' => $r->sample_type_id, 'sample_type' => $types[$r->sample_type_id] ?? null, 'sample_stage' => $r->sample_stage,
                'qty' => $r->qty, 'required_date' => $r->required_date->toDateString(),
                'overdue' => $r->required_date->lt($today) && ! in_array($r->status, ['COMPLETED', 'CANCELLED'], true),
                'priority' => $r->priority, 'requested_by' => $r->requested_by, 'requested_by_name' => $names[$r->requested_by] ?? null,
                'department_id' => $r->department_id, 'department' => $r->department_id ? ($departments[$r->department_id] ?? null) : null,
                'assigned_to' => $r->assigned_to, 'assigned_to_name' => $names[$r->assigned_to] ?? null,
                'remarks' => $r->remarks, 'attachment' => $r->attachment, 'status' => $r->status, 'pending_level' => $r->pending_level,
                'sample_no' => $r->sample_id ? ($samples[$r->sample_id]->sample_no ?? null) : null,
            ];
            if ($detail) {
                $out['decisions'] = ($decisions[$r->id] ?? collect())->map(fn (SampleRequisitionDecision $d): array => [
                    'level' => $d->level, 'decision' => $d->decision, 'decided_by' => $names[$d->decided_by] ?? null, 'decided_at' => $d->decided_at->toIso8601String(),
                    'comments' => $d->comments, 'rejection_reason' => $d->rejection_reason, 'attachment' => $d->attachment,
                ])->values()->all();
            }

            return $out;
        })->values()->all();
    }
}

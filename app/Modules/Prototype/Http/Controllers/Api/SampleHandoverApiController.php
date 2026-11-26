<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Http\Controllers\Api;

use App\Modules\Merchandise\Models\SampleType;
use App\Modules\Prototype\Models\Sample;
use App\Modules\Prototype\Models\SampleHandover;
use App\Modules\Prototype\Models\SampleRequisition;
use App\Modules\Prototype\Support\Prototype;
use App\Modules\Prototype\Support\SampleCards;
use App\Modules\Prototype\Support\SampleLedger;
use App\Modules\Tenancy\Models\Department;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Prototype Desk → Sample Handover / Delivery (AnnotechERP 07/03): an
 * approved sample handed over or couriered (HO-2026-00001), tracked to the
 * received confirmation. A sample is handed over only with the approval its
 * type requires — buyer approval, else a passed QC, else a finished
 * development — and never beyond its quantity. Couriered needs the courier
 * and tracking number, Delivered the actual delivery date, Received who
 * received it and when. Delivered or received, the sample is marked
 * delivered. A received handover is locked; only a Ready one is removed.
 */
class SampleHandoverApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('prototype.handover.view');

        return ApiResponse::ok([
            'samples' => SampleCards::options($this->waiting()), 'users' => Prototype::users(), 'statuses' => SampleHandover::STATUSES,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name'])->map(fn ($d): array => ['value' => $d->id, 'label' => $d->name])->all(),
        ]);
    }

    public function index(): JsonResponse
    {
        $this->allow('prototype.handover.view');

        return ApiResponse::ok($this->present(SampleHandover::query()->orderByDesc('handover_date')->orderByDesc('handover_no')->get()));
    }

    public function show(string $handover): JsonResponse
    {
        $this->allow('prototype.handover.view');

        return ApiResponse::ok($this->present(collect([$this->find($handover)]))[0]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('prototype.handover.manage');
        $data = $this->validated($request, null);
        $sample = Sample::query()->findOrFail($data['sample_id']);
        if (! $this->waiting()->contains('id', $sample->id)) {
            throw ValidationException::withMessages(['sample_id' => __('prototype.handover.not_approved')]);
        }
        $this->assertQty($sample, (int) $data['qty'], null);
        $row = DB::transaction(function () use ($data, $sample): SampleHandover {
            $row = SampleHandover::query()->create([
                ...$data, 'status' => $data['status'] ?? 'READY', 'version' => $sample->version,
                'handover_from' => $data['handover_from'] ?? $this->caller()->auditUserId(),
                'handover_no' => Prototype::nextNumber('HO', 'proto_handovers', 'handover_no', $data['handover_date']),
            ]);
            $this->move($sample);
            SampleLedger::log($sample, 'handover_recorded', ['ho' => $row->handover_no, 'status' => $row->status, 'to' => $row->handover_to], $row->handover_no);

            return $row;
        });

        return ApiResponse::created($this->present(collect([$row->fresh()]))[0]);
    }

    public function update(Request $request, string $handover): JsonResponse
    {
        $this->allow('prototype.handover.manage');
        $row = $this->find($handover);
        if ($row->status === 'RECEIVED') {
            throw ValidationException::withMessages(['status' => __('prototype.handover.locked')]);
        }
        $data = $this->validated($request, $row);
        $sample = Sample::query()->findOrFail($row->sample_id);
        $this->assertQty($sample, (int) $data['qty'], $row->id);
        DB::transaction(function () use ($row, $data, $sample): void {
            $row->update([...collect($data)->except('sample_id')->all(), 'status' => $data['status'] ?? $row->status, 'handover_from' => $data['handover_from'] ?? $row->handover_from]);
            $this->move($sample);
            SampleLedger::log($sample, 'handover_recorded', ['ho' => $row->handover_no, 'status' => $row->status, 'to' => $row->handover_to], $row->handover_no);
        });

        return ApiResponse::ok($this->present(collect([$row->fresh()]))[0]);
    }

    public function destroy(string $handover): JsonResponse
    {
        $this->allow('prototype.handover.manage');
        $row = $this->find($handover);
        if ($row->status !== 'READY') {
            throw ValidationException::withMessages(['status' => __('prototype.handover.remove_ready_only')]);
        }
        DB::transaction(function () use ($row): void {
            $sample = Sample::query()->findOrFail($row->sample_id);
            $row->delete();
            $this->move($sample);
        });

        return ApiResponse::ok(['removed' => $handover]);
    }

    /**
     * Samples cleared for handover: buyer-approved, or — where the type needs
     * no buyer approval — through QC (or, needing no QC either, developed).
     */
    private function waiting(): Collection
    {
        $samples = Sample::query()->where('state', '!=', 'CANCELLED')->whereIn('phase', ['QUALITY', 'APPROVAL', 'HANDOVER'])->orderByDesc('sample_no')->get();
        $typeIds = SampleRequisition::query()->whereIn('id', $samples->pluck('requisition_id'))->pluck('sample_type_id', 'id');
        $types = SampleType::query()->whereIn('id', $typeIds->filter())->get()->keyBy('id');

        return $samples->filter(function (Sample $s) use ($typeIds, $types): bool {
            if ($s->phase === 'HANDOVER') {
                return true;
            }
            $type = $types[$typeIds[$s->requisition_id] ?? null] ?? null;
            if ($type === null || $type->buyer_approval_required) {
                return false;
            }
            if ($s->phase === 'APPROVAL') {
                return in_array($s->state, ['PENDING', 'IN_PROGRESS'], true);
            }

            return ! $type->qc_required && $s->state === 'PENDING';
        })->values();
    }

    /** No more than the sample quantity, across its handovers not cancelled. */
    private function assertQty(Sample $sample, int $qty, ?string $except): void
    {
        $limit = (int) SampleRequisition::query()->whereKey($sample->requisition_id)->value('qty');
        $others = (int) SampleHandover::query()->where('sample_id', $sample->id)->where('status', '!=', 'CANCELLED')->when($except, fn ($q) => $q->whereKeyNot($except))->sum('qty');
        if ($others + $qty > $limit) {
            throw ValidationException::withMessages(['qty' => __('prototype.handover.qty_over', ['limit' => $limit, 'left' => max(0, $limit - $others)])]);
        }
    }

    /** With a live handover the sample is in Handover — delivered once any has arrived. */
    private function move(Sample $sample): void
    {
        $live = SampleHandover::query()->where('sample_id', $sample->id)->where('status', '!=', 'CANCELLED')->get(['status', 'handover_no']);
        if ($live->isEmpty() && $sample->phase !== 'HANDOVER') {
            return;
        }
        $arrived = $live->whereIn('status', SampleHandover::ARRIVED)->isNotEmpty();
        $sample->update(['phase' => 'HANDOVER', 'state' => $arrived ? 'DELIVERED' : 'READY']);
        if ($arrived) {
            SampleLedger::resolve(['HANDOVER_READY', 'SAMPLE_OVERDUE'], $sample->sample_no);
            SampleLedger::raise('SAMPLE_DELIVERED', 'SUCCESS', $sample, 'MERCHANDISING', $sample->sample_no, ['sample' => $sample->sample_no]);
        }
        if ($live->contains('status', 'RECEIVED')) {
            SampleLedger::raise('SAMPLE_COMPLETED', 'SUCCESS', $sample, 'DESK', "{$sample->sample_no}-DONE", ['sample' => $sample->sample_no]);
        }
    }

    private function find(string $handover): SampleHandover
    {
        return SampleHandover::query()->where('handover_no', $handover)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?SampleHandover $row): array
    {
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'sample_id' => [$row ? 'nullable' : 'required', Rule::exists('proto_samples', 'id')->where('company_id', $company)],
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'handover_date' => ['required', 'date'],
            'handover_from' => ['nullable', Rule::exists('company_users', 'user_id')->where('company_id', $company)],
            'handover_to' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('company_id', $company)],
            'status' => ['nullable', Rule::in(SampleHandover::STATUSES)],
            'courier' => ['nullable', 'string', 'max:120'],
            'tracking_no' => ['nullable', 'string', 'max:120'],
            'expected_delivery' => ['nullable', 'date'],
            'actual_delivery' => ['nullable', 'date'],
            'received_by' => ['nullable', 'string', 'max:255'],
            'received_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:4000'],
            'attachment' => ['nullable', 'string', 'max:500'],
        ], [
            'sample_id.required' => __('prototype.handover.sample_required'), 'qty.required' => __('prototype.handover.qty_required'), 'qty.min' => __('prototype.handover.qty_required'),
            'handover_date.required' => __('prototype.handover.date_required'), 'handover_to.required' => __('prototype.handover.to_required'),
        ]);
        foreach (['expected_delivery', 'actual_delivery', 'received_date'] as $later) {
            if (! empty($data[$later]) && CarbonImmutable::parse($data[$later])->lt(CarbonImmutable::parse($data['handover_date']))) {
                throw ValidationException::withMessages([$later => __('prototype.handover.date_order', ['field' => __('prototype.handover.field.'.$later)])]);
            }
        }
        $status = $data['status'] ?? null;
        if ($status === 'COURIERED' && (blank($data['courier'] ?? null) || blank($data['tracking_no'] ?? null))) {
            throw ValidationException::withMessages(['courier' => __('prototype.handover.courier_needed')]);
        }
        if ($status === 'DELIVERED' && empty($data['actual_delivery'])) {
            throw ValidationException::withMessages(['actual_delivery' => __('prototype.handover.delivery_needed')]);
        }
        if ($status === 'RECEIVED' && (blank($data['received_by'] ?? null) || empty($data['received_date']))) {
            throw ValidationException::withMessages(['received_by' => __('prototype.handover.receipt_needed')]);
        }

        return $data;
    }

    /** @return list<array<string, mixed>> */
    private function present($rows): array
    {
        $cards = SampleCards::of(Sample::query()->whereIn('id', $rows->pluck('sample_id'))->get());
        $names = Prototype::names($rows->pluck('handover_from')->all());
        $departments = Department::query()->whereIn('id', $rows->pluck('department_id')->filter())->pluck('name', 'id');
        $today = CarbonImmutable::today();

        return $rows->map(function (SampleHandover $r) use ($cards, $names, $departments, $today): array {
            $arrived = in_array($r->status, SampleHandover::ARRIVED, true) || $r->status === 'CANCELLED';
            $until = $r->actual_delivery ?? ($arrived ? null : $today);
            $delay = $r->expected_delivery && $until && $until->gt($r->expected_delivery) ? (int) $r->expected_delivery->diffInDays($until) : null;

            return [
                'id' => $r->id, 'handover_no' => $r->handover_no, 'version' => $r->version, 'qty' => $r->qty, 'handover_date' => $r->handover_date->toDateString(),
                'handover_from' => $r->handover_from, 'handover_from_name' => $names[$r->handover_from] ?? null, 'handover_to' => $r->handover_to,
                'department_id' => $r->department_id, 'department' => $r->department_id ? ($departments[$r->department_id] ?? null) : null,
                'status' => $r->status, 'courier' => $r->courier, 'tracking_no' => $r->tracking_no,
                'expected_delivery' => $r->expected_delivery?->toDateString(), 'actual_delivery' => $r->actual_delivery?->toDateString(),
                'received_by' => $r->received_by, 'received_date' => $r->received_date?->toDateString(), 'remarks' => $r->remarks, 'attachment' => $r->attachment,
                'delay_days' => $delay, 'locked' => $r->status === 'RECEIVED', 'removable' => $r->status === 'READY',
            ] + ($cards[$r->sample_id] ?? []);
        })->values()->all();
    }
}

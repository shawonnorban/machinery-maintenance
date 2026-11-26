<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\SampleBatch;
use App\Modules\Merchandise\Models\SampleLine;
use App\Modules\Merchandise\Models\SampleProgram;
use App\Modules\Merchandise\Models\SampleType;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Lab Dip & Sample → Sample Production Entry (AnnotechERP 06/10), as
 * Brickland works it: New Batch opens a batch on a date (SMB-00001), the
 * planned sample lines are ticked and added to it, and for each line the
 * sample room records what it sewed, delivered and rejected — delivered plus
 * rejected never more than sewn. Submit closes the batch: its lines are then
 * produced and no longer changed. A line on a batch is the batch's on Sample
 * Programme too.
 */
class SampleProductionApiController extends ApiController
{
    /** The planned lines still waiting for a batch, and the batches. */
    public function index(): JsonResponse
    {
        $this->allow('merchandise.sample.view');
        $lines = SampleLine::query()->where('status', 'PLANNED')->whereNull('batch_id')->orderBy('created_at')->orderBy('sort_order')->get();

        return ApiResponse::ok([
            'planned' => $this->rows($lines),
            'batches' => SampleBatch::query()->orderByDesc('batch_no')->get()->map(fn (SampleBatch $b): array => $this->head($b))->values()->all(),
            'card_formats' => SampleBatch::CARD_FORMATS,
        ]);
    }

    public function show(string $batch): JsonResponse
    {
        $this->allow('merchandise.sample.view');

        return ApiResponse::ok($this->present(SampleBatch::query()->whereKey($batch)->firstOrFail()));
    }

    /** New Batch: a batch opened on the date. */
    public function open(Request $request): JsonResponse
    {
        $this->allow('merchandise.sample.manage');
        $data = $request->validate(['batch_date' => ['required', 'date'], 'card_format' => ['nullable', Rule::in(SampleBatch::CARD_FORMATS)]], ['batch_date.required' => __('merchandise.sample.batch_date_required')]);
        $batch = DB::transaction(fn (): SampleBatch => SampleBatch::query()->create([
            'batch_no' => $this->nextNumber(), 'batch_date' => $data['batch_date'], 'card_format' => $data['card_format'] ?? 'LINDEX', 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(),
        ]));

        return ApiResponse::created($this->present($batch));
    }

    /** Add To Batch: the ticked planned lines join the batch. */
    public function add(Request $request, string $batch): JsonResponse
    {
        $this->allow('merchandise.sample.manage');
        $row = $this->draft($batch);
        $data = $request->validate(['line_ids' => ['required', 'array', 'min:1', 'max:300'], 'line_ids.*' => ['string']], ['line_ids.required' => __('merchandise.sample.tick_lines')]);
        $lines = SampleLine::query()->whereIn('id', $data['line_ids'])->get();
        if ($lines->count() !== count(array_unique($data['line_ids'])) || $lines->contains(fn (SampleLine $l): bool => $l->status !== 'PLANNED' || $l->batch_id !== null)) {
            throw ValidationException::withMessages(['line_ids' => __('merchandise.sample.not_planned')]);
        }
        DB::transaction(function () use ($row, $lines): void {
            foreach ($lines as $l) {
                $l->update(['batch_id' => $row->id, 'batch_no' => $row->batch_no, 'status' => 'IN_BATCH']);
            }
        });

        return ApiResponse::ok($this->present($row) + ['added' => $lines->count()]);
    }

    /** Take a line off the batch: it is planned again, its quantities cleared. */
    public function drop(string $batch, string $line): JsonResponse
    {
        $this->allow('merchandise.sample.manage');
        $row = $this->draft($batch);
        $target = SampleLine::query()->where('batch_id', $row->id)->whereKey($line)->firstOrFail();
        $target->update(['batch_id' => null, 'batch_no' => null, 'status' => 'PLANNED', 'sewn_qty' => 0, 'delivered_qty' => 0, 'rejected_qty' => 0, 'batch_remarks' => null]);

        return ApiResponse::ok($this->present($row));
    }

    /** Save: the card format and each line's sewn, delivered and rejected quantities. */
    public function save(Request $request, string $batch): JsonResponse
    {
        $this->allow('merchandise.sample.manage');
        $row = $this->draft($batch);
        $data = $request->validate([
            'card_format' => ['required', Rule::in(SampleBatch::CARD_FORMATS)],
            'lines' => ['present', 'array', 'max:300'],
            'lines.*.id' => ['required', 'string'],
            'lines.*.sewn_qty' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lines.*.delivered_qty' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lines.*.rejected_qty' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lines.*.batch_remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $mine = SampleLine::query()->where('batch_id', $row->id)->get()->keyBy('id');
        foreach ($data['lines'] as $i => $l) {
            if (! $mine->has($l['id'])) {
                throw ValidationException::withMessages(['lines' => __('merchandise.sample.line_off_batch')]);
            }
            // Brickland's rule: delivered plus rejected cannot exceed what was sewn.
            if ((float) ($l['delivered_qty'] ?? 0) + (float) ($l['rejected_qty'] ?? 0) > (float) ($l['sewn_qty'] ?? 0) + 1e-9) {
                throw ValidationException::withMessages(["lines.{$i}.delivered_qty" => __('merchandise.sample.over_sewn')]);
            }
        }
        DB::transaction(function () use ($row, $data, $mine): void {
            $row->update(['card_format' => $data['card_format']]);
            foreach ($data['lines'] as $l) {
                $mine[$l['id']]->update([
                    'sewn_qty' => $l['sewn_qty'] ?? 0, 'delivered_qty' => $l['delivered_qty'] ?? 0, 'rejected_qty' => $l['rejected_qty'] ?? 0,
                    'batch_remarks' => isset($l['batch_remarks']) && trim($l['batch_remarks']) !== '' ? trim($l['batch_remarks']) : null,
                ]);
            }
        });

        return ApiResponse::ok($this->present($row) + ['updated' => count($data['lines'])]);
    }

    /** Submit: the batch closes and its lines are produced. */
    public function submit(string $batch): JsonResponse
    {
        $this->allow('merchandise.sample.manage');
        $row = $this->draft($batch);
        $lines = SampleLine::query()->where('batch_id', $row->id)->get();
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['batch' => __('merchandise.sample.batch_empty')]);
        }
        DB::transaction(function () use ($row, $lines): void {
            $row->update(['status' => 'SUBMITTED', 'submitted_at' => now(), 'submitted_by' => $this->caller()->auditUserId()]);
            SampleLine::query()->whereIn('id', $lines->pluck('id'))->update(['status' => 'PRODUCED']);
        });

        return ApiResponse::ok($this->present($row->refresh()) + ['submitted' => $lines->count()]);
    }

    /** A batch still open to changes. */
    private function draft(string $batch): SampleBatch
    {
        $row = SampleBatch::query()->whereKey($batch)->firstOrFail();
        if ($row->status !== 'DRAFT') {
            throw ValidationException::withMessages(['batch' => __('merchandise.sample.batch_closed', ['no' => $row->batch_no])]);
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function present(SampleBatch $row): array
    {
        return ['batch' => $this->head($row), 'lines' => $this->rows(SampleLine::query()->where('batch_id', $row->id)->orderBy('created_at')->orderBy('sort_order')->get())];
    }

    /** @return array<string, mixed> */
    private function head(SampleBatch $b): array
    {
        return [
            'id' => $b->id, 'batch_no' => $b->batch_no, 'batch_date' => $b->batch_date->toDateString(), 'card_format' => $b->card_format, 'status' => $b->status,
            'submitted_at' => $b->submitted_at?->toIso8601String(),
        ];
    }

    /**
     * Each line with its programme's buyer account and Brickland's Job# (Ord#) — "529 (LPP-2026-000008)".
     *
     * @param  Collection<int, SampleLine>  $lines
     * @return list<array<string, mixed>>
     */
    private function rows(Collection $lines): array
    {
        $programs = SampleProgram::query()->whereIn('id', $lines->pluck('program_id'))->get()->keyBy('id');
        $orders = Order::query()->whereIn('id', $programs->pluck('order_id')->filter())->get(['id', 'order_no', 'job_no', 'work_style_no'])->keyBy('id');
        $offices = BuyerOffice::query()->whereIn('id', $programs->pluck('buyer_office_id')->filter())->pluck('name', 'id');
        $types = SampleType::query()->whereIn('id', $lines->pluck('sample_type_id'))->pluck('name', 'id');

        return $lines->map(function (SampleLine $l) use ($programs, $orders, $offices, $types): array {
            $p = $programs[$l->program_id];
            $o = $p->order_id ? ($orders[$p->order_id] ?? null) : null;
            $job = $p->work_style_no ?: ($o ? ($o->work_style_no ?: $o->job_no) : null);

            return [
                'id' => $l->id, 'program_no' => $p->program_no, 'buyer_ac' => $p->buyer_office_id ? ($offices[$p->buyer_office_id] ?? null) : null,
                'job' => $job && $o ? "{$job} ({$o->order_no})" : ($job ?? $o?->order_no ?? $p->program_no), 'type' => $types[$l->sample_type_id] ?? null,
                'item' => $l->item, 'colour' => $l->colour, 'size' => $l->size, 'req_qty' => $l->req_qty, 'unit' => $l->unit, 'remarks' => $l->remarks,
                'sewn_qty' => $l->sewn_qty, 'delivered_qty' => $l->delivered_qty, 'rejected_qty' => $l->rejected_qty, 'batch_remarks' => $l->batch_remarks, 'status' => $l->status,
            ];
        })->values()->all();
    }

    /** SMB-00001: the company's next batch, as Brickland numbers them; held to the end of the save. */
    private function nextNumber(): string
    {
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_sample_batches:'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) SampleBatch::query()->pluck('batch_no')->map(fn (string $v): int => (int) substr($v, 4))->max();

        return 'SMB-'.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}

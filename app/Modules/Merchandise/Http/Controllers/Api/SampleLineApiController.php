<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\SampleLine;
use App\Modules\Merchandise\Models\SampleProgram;
use App\Modules\Merchandise\Models\SampleType;
use App\Modules\Merchandise\Support\SampleTypeDefaults;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sample Programme → Sample Lines (AnnotechERP 06/10): what one programme
 * asks the sample room for. Add line keeps the new line at once, as
 * Brickland does ("Planned 3 × Proto Sample"); item, colour, size, quantity,
 * unit and remarks are then changed in place and saved together, and a line
 * left out is removed. A line already in a production batch is shown but no
 * longer edited or removed — the batch owns its quantities.
 */
class SampleLineApiController extends ApiController
{
    public function index(string $program): JsonResponse
    {
        $this->allow('merchandise.sample.view');
        $row = SampleProgram::query()->whereKey($program)->firstOrFail();
        // The company's sample types start from Brickland's defaults the first time they are asked for.
        SampleTypeDefaults::ensure($this->caller()->companyId);

        return ApiResponse::ok($this->present($row) + [
            'types' => SampleType::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn (SampleType $t): array => ['value' => $t->id, 'label' => $t->name])->values()->all(),
        ]);
    }

    public function add(Request $request, string $program): JsonResponse
    {
        $this->allow('merchandise.sample.manage');
        $row = SampleProgram::query()->whereKey($program)->firstOrFail();
        $data = $request->validate($this->lineRules(''), $this->lineMessages(''));
        $line = SampleLine::query()->create($this->values($data) + [
            'program_id' => $row->id, 'sample_type_id' => $data['sample_type_id'], 'status' => 'PLANNED',
            'sort_order' => 10 + (int) SampleLine::query()->where('program_id', $row->id)->max('sort_order'),
        ]);

        return ApiResponse::ok($this->present($row) + ['added' => ['qty' => $line->req_qty, 'type' => SampleType::query()->whereKey($line->sample_type_id)->value('name')]]);
    }

    public function save(Request $request, string $program): JsonResponse
    {
        $this->allow('merchandise.sample.manage');
        $row = SampleProgram::query()->whereKey($program)->firstOrFail();
        $data = $request->validate(['rows' => ['present', 'array', 'max:300'], 'rows.*.id' => ['required', 'string']] + $this->lineRules('rows.*.', withType: false), $this->lineMessages('rows.*.'));
        $existing = SampleLine::query()->where('program_id', $row->id)->get()->keyBy('id');
        $kept = collect($data['rows'])->pluck('id')->all();
        foreach ($kept as $id) {
            if (! $existing->has($id)) {
                throw ValidationException::withMessages(['rows' => __('merchandise.sample.line_gone')]);
            }
        }
        $batched = $existing->first(fn (SampleLine $l): bool => $l->inBatch() && ! in_array($l->id, $kept, true));
        if ($batched) {
            throw ValidationException::withMessages(['rows' => __('merchandise.sample.line_in_batch', ['batch' => $batched->batch_no])]);
        }

        DB::transaction(function () use ($row, $data, $existing, $kept): void {
            SampleLine::query()->where('program_id', $row->id)->whereNotIn('id', $kept)->delete();
            foreach ($data['rows'] as $r) {
                $line = $existing[$r['id']];
                // The batch owns a line once it is in one; what is sent for it is not taken.
                if (! $line->inBatch()) {
                    $line->update($this->values($r));
                }
            }
        });

        return ApiResponse::ok($this->present($row));
    }

    /** @return array<string, list<mixed>> */
    private function lineRules(string $prefix, bool $withType = true): array
    {
        return ($withType ? [$prefix.'sample_type_id' => ['required', Rule::exists('sample_types', 'id')->where('company_id', $this->caller()->companyId)]] : []) + [
            $prefix.'item' => ['nullable', 'string', 'max:96'],
            $prefix.'colour' => ['nullable', 'string', 'max:96'],
            $prefix.'size' => ['nullable', 'string', 'max:32'],
            $prefix.'req_qty' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            $prefix.'unit' => ['nullable', 'string', 'max:16'],
            $prefix.'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    private function lineMessages(string $prefix): array
    {
        return [
            $prefix.'sample_type_id.required' => __('merchandise.sample.type_required'),
            $prefix.'req_qty.required' => __('merchandise.sample.qty_required'),
            $prefix.'req_qty.gt' => __('merchandise.sample.qty_positive'),
        ];
    }

    /** @return array<string, mixed> */
    private function values(array $r): array
    {
        $text = fn (string $k): ?string => isset($r[$k]) && trim((string) $r[$k]) !== '' ? trim((string) $r[$k]) : null;

        return ['item' => $text('item'), 'colour' => $text('colour'), 'size' => $text('size'), 'req_qty' => $r['req_qty'], 'unit' => $text('unit'), 'remarks' => $text('remarks')];
    }

    /** @return array{program: array<string, mixed>, rows: list<array<string, mixed>>} */
    private function present(SampleProgram $row): array
    {
        $order = $row->order_id ? Order::query()->whereKey($row->order_id)->first(['order_no', 'job_no', 'work_style_no']) : null;
        $job = $row->work_style_no ?: ($order ? ($order->work_style_no ?: $order->job_no) : null);
        $lines = SampleLine::query()->where('program_id', $row->id)->orderBy('sort_order')->get();
        $types = SampleType::query()->whereIn('id', $lines->pluck('sample_type_id'))->pluck('name', 'id');

        return [
            // Brickland's Job# (Ord#): "529 (LPP-2026-000008)".
            'program' => ['id' => $row->id, 'program_no' => $row->program_no, 'job' => $job && $order ? "{$job} ({$order->order_no})" : ($job ?? $order?->order_no)],
            'rows' => $lines->map(fn (SampleLine $l): array => [
                'id' => $l->id, 'sample_type_id' => $l->sample_type_id, 'type' => $types[$l->sample_type_id] ?? null, 'item' => $l->item, 'colour' => $l->colour, 'size' => $l->size,
                'req_qty' => $l->req_qty, 'unit' => $l->unit, 'remarks' => $l->remarks, 'status' => $l->status, 'batch_no' => $l->batch_no, 'locked' => $l->inBatch(),
            ])->values()->all(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeBatch;
use App\Modules\DyeHouse\Models\DyeFinishingQc;
use App\Modules\DyeHouse\Models\DyeProduction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sample Finishing Quality Check: the shade, hand-feel and GSM verdict on a
 * finished sample batch — passed, failed, rework or still pending — with who
 * checked it. Only a batch that has run is checked, and one verdict is given
 * per batch per day: a batch re-checked after rework is checked on another
 * day, and both rows stay on file.
 */
class DyeSampleQcResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-sample-qcs';
    }

    public function model(): string
    {
        return DyeFinishingQc::class;
    }

    protected function scope(): string
    {
        return 'SAMPLE';
    }

    public function columns(): array
    {
        return ['batch_id', 'qc_date', 'result', 'model_combo', 'gsm', 'width_inch', 'checked_by', 'shade_note', 'remarks'];
    }

    public function query(): Builder
    {
        return DyeFinishingQc::query()->where('scope', $this->scope())->orderByDesc('qc_date');
    }

    public function rowQuery(): Builder
    {
        return DyeFinishingQc::query()->where('scope', $this->scope());
    }

    public function messages(): array
    {
        return [
            'batch_id.required' => __('dyehouse.batch.v.qc_batch'), 'qc_date.required' => __('dyehouse.batch.v.qc_date'), 'result.required' => __('dyehouse.batch.v.qc_result'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'batch_id' => [$required, Rule::exists('dye_batches', 'id')->where('company_id', $this->company())->where('scope', $this->scope())],
            'qc_date' => [$required, 'date'],
            'result' => [$required, Rule::in(DyeFinishingQc::RESULTS)],
            'model_combo' => ['nullable', 'string', 'max:120'],
            'gsm' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'width_inch' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'checked_by' => ['nullable', $this->own('hr_employees')],
            'shade_note' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $batch = $data['batch_id'] ?? $row?->getAttribute('batch_id');
        $date = $data['qc_date'] ?? $row?->getAttribute('qc_date')?->toDateString();
        if (isset($data['batch_id']) && ! DyeProduction::query()->where('batch_id', $batch)->exists()) {
            throw ValidationException::withMessages(['batch_id' => __('dyehouse.batch.v.qc_not_run', ['no' => DyeBatch::query()->whereKey($batch)->value('batch_no')])]);
        }
        $twin = DyeFinishingQc::query()->where('batch_id', $batch)->whereDate('qc_date', $date)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
        if ($twin) {
            throw ValidationException::withMessages(['qc_date' => __('dyehouse.batch.v.qc_twice', ['no' => DyeBatch::query()->whereKey($batch)->value('batch_no'), 'date' => $date])]);
        }
        if (! $row) {
            $data += ['scope' => $this->scope(), 'created_by' => $this->actingUserId()];
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $n = $this->names();
        $b = DyeBatch::query()->whereKey($row->getAttribute('batch_id'))->first(['batch_no', 'lot_no', 'card_qty', 'buyer_id', 'order_id', 'order_no', 'style_ref', 'colour_id']);
        $out['batch_no'] = $b?->batch_no;
        $out['lot_no'] = $b?->lot_no;
        $out['batch_qty'] = $b ? (float) $b->card_qty : null;
        $out['buyer_id'] = $b?->buyer_id;
        $out['order_id'] = $b?->order_id;
        $out['colour_id'] = $b?->colour_id;
        $out['buyer'] = $b ? ($n['buyers'][$b->buyer_id] ?? null) : null;
        $out['style'] = $b?->style_ref;
        $out['order_no'] = $b?->order_no;
        $out['colour'] = $b ? ($n['colours'][$b->colour_id] ?? null) : null;
        $out['checked'] = $row->getAttribute('checked_by') ? DB::table('hr_employees')->where('id', $row->getAttribute('checked_by'))->value('name') : null;

        return $out;
    }
}

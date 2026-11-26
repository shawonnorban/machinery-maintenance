<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Resources;

use App\Modules\DyeHouse\Models\DyeBatch;
use App\Modules\DyeHouse\Models\DyeCheckRollReturn;
use App\Modules\DyeHouse\Models\DyeChemRequisition;
use App\Modules\DyeHouse\Support\DyeBatches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dye Batch Re-Process: a check roll QC failed, returned for re-processing —
 * the re-process type, the batch and its shade, the weight and the defect,
 * and the day QC failed it, which the register reports the wait from.
 * Raising the return sends the roll nowhere; Back does, from the register,
 * and a roll sent back is no longer edited.
 */
class DyeCheckRollReturnResource extends DyeBatchResource
{
    public function key(): string
    {
        return 'dye-check-roll-returns';
    }

    public function model(): string
    {
        return DyeCheckRollReturn::class;
    }

    public function viewPermission(): string
    {
        return 'dyehouse.requisition.view';
    }

    public function managePermission(): string
    {
        return 'dyehouse.requisition.manage';
    }

    public function columns(): array
    {
        return ['return_no', 'req_date', 'not_ok_date', 'reprocess_type', 'party_id', 'store_id', 'batch_id', 'colour_id', 'dye_machine_id', 'qty_kg', 'defect', 'remarks'];
    }

    public function query(): Builder
    {
        return DyeCheckRollReturn::query()->orderByDesc('req_date')->orderByDesc('return_no');
    }

    public function messages(): array
    {
        return [
            'req_date.required' => __('dyehouse.req.v.req_date'), 'reprocess_type.required' => __('dyehouse.req.v.reprocess_type'),
            'qty_kg.required' => __('dyehouse.req.v.qty'), 'qty_kg.gt' => __('dyehouse.req.v.qty'), 'not_ok_date.before_or_equal' => __('dyehouse.req.v.not_ok_after'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'req_date' => [$required, 'date'],
            'not_ok_date' => ['nullable', 'date', 'before_or_equal:'.(request()->input('req_date') ?? $row?->getAttribute('req_date')?->toDateString() ?? '2999-12-31')],
            'reprocess_type' => [$required, Rule::in(DyeChemRequisition::PROCESS_TYPES)],
            'party_id' => ['nullable', Rule::in([$this->company()])],
            'store_id' => ['nullable', $this->own('stores')],
            'batch_id' => ['nullable', $this->own('dye_batches')],
            'colour_id' => ['nullable', $this->own('colours')],
            'dye_machine_id' => ['nullable', $this->own('dye_machines')],
            'qty_kg' => [$required, 'numeric', 'gt:0', 'max:99999999'],
            'defect' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if ($row?->getAttribute('sent_back_at')) {
            throw ValidationException::withMessages(['return_no' => __('dyehouse.req.v.sent_back', ['no' => $row->getAttribute('return_no')])]);
        }
        if (! empty($data['batch_id']) && empty($data['colour_id'] ?? $row?->getAttribute('colour_id'))) {
            $data['colour_id'] = DyeBatch::query()->whereKey($data['batch_id'])->value('colour_id');
        }
        if (! $row) {
            $data += ['return_no' => DyeBatches::nextNumber('CRR-', 'dye_check_roll_returns', 'return_no', $data['req_date'], 'Ym'), 'created_by' => $this->actingUserId()];
        }

        return $data;
    }

    public function beforeDelete(Model $row): void
    {
        if ($row->getAttribute('sent_back_at')) {
            throw ValidationException::withMessages(['return_no' => __('dyehouse.req.v.sent_back', ['no' => $row->getAttribute('return_no')])]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $n = $this->names();
        $req = $row->getAttribute('req_date');
        $notOk = $row->getAttribute('not_ok_date');
        $out['company'] = $n['companies'][$row->getAttribute('party_id') ?? $this->company()] ?? null;
        $out['store'] = $n['stores'][$row->getAttribute('store_id')] ?? null;
        $out['batch_no'] = $row->getAttribute('batch_id') ? DyeBatch::query()->whereKey($row->getAttribute('batch_id'))->value('batch_no') : null;
        $out['colour'] = $n['colours'][$row->getAttribute('colour_id')] ?? null;
        $out['machine'] = $n['machines'][$row->getAttribute('dye_machine_id')] ?? null;
        $out['qty_kg'] = (float) $row->getAttribute('qty_kg');
        // Waited: the days between QC failing the roll and the return being raised.
        $out['waited'] = $notOk ? (int) $notOk->diffInDays($req) : null;
        $out['sent_back'] = $row->getAttribute('sent_back_at') !== null;

        return $out;
    }
}

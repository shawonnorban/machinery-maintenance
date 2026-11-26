<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Resources;

use App\Modules\Finishing\Models\FinBatchReceipt;
use App\Modules\Finishing\Models\FinInspection;
use App\Modules\Finishing\Models\FinInspectionRoll;
use App\Modules\Finishing\Models\FinProcessDelivery;
use App\Modules\Finishing\Models\FinReprocessAdvice;
use App\Modules\Finishing\Support\FinFloor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Rejection & Re-Process Advice: the re-process the floor is advised to run
 * on what a posted inspection rejected — its rejected rolls and kilos counted
 * from the inspection (every roll, when the verdict rejected the batch and no
 * roll was marked), the process, and a machine mapped to it. One advice per
 * inspection; the held batch goes back to Awaiting Load to be run again.
 * Undone only while the batch has not moved since. Brickland left this screen
 * a scaffold; it is built as its other registers are.
 */
class FinReprocessAdviceResource extends FinQcResource
{
    public function key(): string
    {
        return 'fin-reprocess-advices';
    }

    public function model(): string
    {
        return FinReprocessAdvice::class;
    }

    public function columns(): array
    {
        return ['advice_no', 'advice_date', 'inspection_id', 'receipt_id', 'process_id', 'machine_id', 'rejected_rolls', 'rejected_kg', 'reason', 'remarks'];
    }

    public function query(): Builder
    {
        return FinReprocessAdvice::query()->orderByDesc('advice_date')->orderByDesc('advice_no');
    }

    public function messages(): array
    {
        return [
            'advice_date.required' => __('finishing.qc.v.advice_date'), 'inspection_id.required' => __('finishing.qc.v.inspection'), 'process_id.required' => __('finishing.qc.v.process'),
            'reason.required' => __('finishing.qc.v.reason'), 'remarks.required_if' => __('finishing.qc.v.other_reason'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'advice_date' => [$required, 'date'],
            'inspection_id' => [$required, $this->own('fin_inspections')],
            'process_id' => [$required, $this->own('fin_process_types')],
            'machine_id' => ['nullable', $this->own('fin_machines')],
            'reason' => [$required, Rule::in(FinReprocessAdvice::REASONS)],
            'remarks' => ['nullable', 'required_if:reason,OTHER', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if ($row && array_key_exists('inspection_id', $data) && $data['inspection_id'] !== $row->getAttribute('inspection_id')) {
            throw ValidationException::withMessages(['inspection_id' => __('finishing.qc.v.advice_inspection_fixed', ['no' => $row->getAttribute('advice_no')])]);
        }
        $get = fn (string $k) => array_key_exists($k, $data) ? $data[$k] : $row?->getAttribute($k);
        $insp = FinInspection::query()->findOrFail($get('inspection_id'));
        $rolls = FinInspectionRoll::query()->where('inspection_id', $insp->id)->get();
        $rejected = $rolls->where('result', 'REJECT');
        if ($rejected->isEmpty() && $insp->verdict === 'REJECT') {
            $rejected = $rolls;
        }
        if (! $row) {
            if ($insp->status !== 'POSTED' || $rejected->isEmpty()) {
                throw ValidationException::withMessages(['inspection_id' => __('finishing.qc.v.nothing_rejected', ['no' => $insp->ref_no])]);
            }
            if (FinReprocessAdvice::query()->where('inspection_id', $insp->id)->exists()) {
                throw ValidationException::withMessages(['inspection_id' => __('finishing.qc.v.advised_already', ['no' => $insp->ref_no])]);
            }
            $this->liveCard($insp->receipt_id, 'inspection_id');
        }
        if (substr((string) $get('advice_date'), 0, 10) < $insp->insp_date->toDateString()) {
            throw ValidationException::withMessages(['advice_date' => __('finishing.qc.v.advice_before', ['no' => $insp->ref_no])]);
        }
        $card = FinBatchReceipt::query()->findOrFail($insp->receipt_id);
        if ($get('machine_id')) {
            $lines = $card->dia_type === 'BOTH' ? ['OPEN', 'TUBE'] : [$card->dia_type];
            if (! collect($lines)->contains(fn ($l) => FinFloor::mapping((string) $get('machine_id'), (string) $get('process_id'), $l))) {
                throw ValidationException::withMessages(['machine_id' => __('finishing.qc.v.machine_not_mapped')]);
            }
        }
        $data['receipt_id'] = $card->id;
        $data['rejected_rolls'] = $rejected->count();
        $data['rejected_kg'] = round((float) $rejected->sum('weight_kg'), 3);
        if (! $row) {
            $data['advice_no'] = $this->number('FRA-', 'fin_reprocess_advices', 'advice_no', substr((string) $data['advice_date'], 0, 10));
            $data['created_by'] = $this->actingUserId();
        } else {
            unset($data['advice_no']);
        }

        return $data;
    }

    public function afterSave(Model $row, bool $created): void
    {
        if ($created) {
            $card = FinBatchReceipt::query()->lockForUpdate()->findOrFail($row->getAttribute('receipt_id'));
            FinFloor::move($card, 'AWAITING_LOAD', 'QC_ADV', now(), null, $row->getAttribute('advice_no'), $this->actingUserId());
        }
    }

    public function beforeDelete(Model $row): void
    {
        $card = FinBatchReceipt::query()->findOrFail($row->getAttribute('receipt_id'));
        $last = FinProcessDelivery::query()->where('receipt_id', $card->id)->orderByDesc('delivered_at')->orderByDesc('created_at')->orderByDesc('id')->first();
        if (! $last || $last->source !== 'QC_ADV' || $last->remarks !== $row->getAttribute('advice_no')) {
            throw ValidationException::withMessages(['advice_no' => __('finishing.qc.v.advice_acted', ['no' => $row->getAttribute('advice_no')])]);
        }
        FinFloor::move($card, $last->from_stage ?? 'ON_HOLD', 'QC_UNDO', now(), null, $row->getAttribute('advice_no'), $this->actingUserId());
    }

    public function present(Model $row): array
    {
        $out = parent::present($row) + $this->cardInfo($row->getAttribute('receipt_id'));
        [$machines, $processes] = FinFloor::machineAndProcessNames();
        $insp = FinInspection::query()->find($row->getAttribute('inspection_id'));
        $out['ref_no'] = $insp?->ref_no;
        $out['insp_date'] = $insp?->insp_date?->toDateString();
        $out['verdict'] = $insp?->verdict;
        $out['process'] = $processes[$row->getAttribute('process_id')] ?? null;
        $out['machine'] = $machines[$row->getAttribute('machine_id')] ?? null;
        $out['rejected_kg'] = $this->kg($row->getAttribute('rejected_kg'));

        return $out;
    }
}

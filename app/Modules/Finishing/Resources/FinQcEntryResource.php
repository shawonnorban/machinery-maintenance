<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Resources;

use App\Modules\Finishing\Models\FinBatchReceipt;
use App\Modules\Finishing\Models\FinProductionSheet;
use App\Modules\Finishing\Models\FinQcEntry;
use App\Modules\Finishing\Support\FinFloor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Finishing Quality Entry: one roll's reading taken as its batch comes off a
 * machine — weight, GSM, dia, shade, and whether it is faulty (a faulty roll
 * names its defect). Only a batch on the floor, not before it was received;
 * a roll number once per batch, kept upper case. Brickland left this screen
 * a scaffold; it is built as its other registers are.
 */
class FinQcEntryResource extends FinQcResource
{
    public function key(): string
    {
        return 'fin-qc-entries';
    }

    public function model(): string
    {
        return FinQcEntry::class;
    }

    public function columns(): array
    {
        return ['entry_date', 'receipt_id', 'machine_id', 'process_id', 'shift', 'roll_no', 'weight_kg', 'gsm', 'dia', 'shade', 'result', 'defect', 'remarks'];
    }

    public function query(): Builder
    {
        return FinQcEntry::query()->orderByDesc('entry_date')->orderByDesc('created_at');
    }

    public function messages(): array
    {
        return [
            'entry_date.required' => __('finishing.qc.v.entry_date'), 'receipt_id.required' => __('finishing.qc.v.batch'), 'shift.required' => __('finishing.qc.v.shift'),
            'roll_no.required' => __('finishing.qc.v.roll_no'), 'weight_kg.required' => __('finishing.qc.v.weight'), 'weight_kg.gt' => __('finishing.qc.v.weight'),
            'shade.required' => __('finishing.qc.v.shade'), 'result.required' => __('finishing.qc.v.result'), 'defect.required_if' => __('finishing.qc.v.defect'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'entry_date' => [$required, 'date'],
            'receipt_id' => [$required, $this->own('fin_batch_receipts')],
            'machine_id' => ['nullable', $this->own('fin_machines')],
            'process_id' => ['nullable', $this->own('fin_process_types')],
            'shift' => [$required, Rule::in(FinProductionSheet::SHIFTS)],
            'roll_no' => [$required, 'string', 'max:40'],
            'weight_kg' => [$required, 'numeric', 'gt:0', 'max:999999'],
            'gsm' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'dia' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'shade' => [$required, Rule::in(FinQcEntry::SHADES)],
            'result' => [$required, Rule::in(FinQcEntry::RESULTS)],
            'defect' => ['nullable', 'required_if:result,FAULT', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $get = fn (string $k) => array_key_exists($k, $data) ? $data[$k] : $row?->getAttribute($k);
        $card = ! $row || $row->getAttribute('receipt_id') !== $get('receipt_id') ? $this->liveCard((string) $get('receipt_id'), 'receipt_id') : FinBatchReceipt::query()->findOrFail($get('receipt_id'));
        if (substr((string) $get('entry_date'), 0, 10) < $card->receive_date->toDateString()) {
            throw ValidationException::withMessages(['entry_date' => __('finishing.qc.v.before_receipt', ['no' => $card->receipt_no])]);
        }
        $roll = mb_strtoupper(trim((string) $get('roll_no')));
        $data['roll_no'] = $roll;
        $twin = FinQcEntry::query()->where('receipt_id', $card->id)->where('roll_no', $roll)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
        if ($twin) {
            throw ValidationException::withMessages(['roll_no' => __('finishing.qc.v.roll_twice', ['roll' => $roll, 'batch' => FinFloor::batches([$card->batch_id])[$card->batch_id]->batch_no ?? ''])]);
        }
        if (! $row) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row) + $this->cardInfo($row->getAttribute('receipt_id'));
        [$machines, $processes] = FinFloor::machineAndProcessNames();
        $out['machine'] = $machines[$row->getAttribute('machine_id')] ?? null;
        $out['process'] = $processes[$row->getAttribute('process_id')] ?? null;
        $out['weight_kg'] = $this->kg($row->getAttribute('weight_kg'));
        foreach (['gsm', 'dia'] as $k) {
            $out[$k] = $row->getAttribute($k) === null ? null : (float) $row->getAttribute($k);
        }

        return $out;
    }
}

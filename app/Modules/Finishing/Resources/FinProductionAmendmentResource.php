<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Resources;

use App\Modules\Finishing\Models\FinBatchReceipt;
use App\Modules\Finishing\Models\FinProductionAmendment;
use App\Modules\Finishing\Models\FinProductionLine;
use App\Modules\Finishing\Models\FinProductionSheet;
use App\Modules\Finishing\Support\FinFloor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Finishing Production Amendment: a correction to a posted production line —
 * what went in, what came off, the machine or the process — kept auditable:
 * the line's old figures are written beside the new, with the reason. Only a
 * posted sheet's line (a draft is simply edited); the machine must still be
 * mapped to the process on the sheet's line; something must change. An
 * amendment stands as made — it is neither edited nor removed; a further
 * correction is a further amendment.
 */
class FinProductionAmendmentResource extends FinProductionResource
{
    public function key(): string
    {
        return 'fin-production-amendments';
    }

    public function model(): string
    {
        return FinProductionAmendment::class;
    }

    public function deletable(): bool
    {
        return false;
    }

    public function columns(): array
    {
        return ['amend_no', 'amend_date', 'line_id', 'old_input_kg', 'new_input_kg', 'old_output_kg', 'new_output_kg', 'old_machine_id', 'new_machine_id', 'old_process_id', 'new_process_id', 'reason'];
    }

    public function query(): Builder
    {
        return FinProductionAmendment::query()->orderByDesc('amend_date')->orderByDesc('amend_no');
    }

    public function messages(): array
    {
        return [
            'amend_date.required' => __('finishing.prod.v.amend_date'), 'line_id.required' => __('finishing.prod.v.amend_line'), 'reason.required' => __('finishing.prod.v.amend_reason'),
            'new_input_kg.required' => __('finishing.prod.v.input'), 'new_input_kg.gt' => __('finishing.prod.v.input'), 'new_output_kg.required' => __('finishing.prod.v.output'),
        ];
    }

    public function rules(?Model $row): array
    {
        return [
            'amend_date' => ['required', 'date'],
            'line_id' => ['required', $this->own('fin_production_lines')],
            'new_input_kg' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'new_output_kg' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'new_machine_id' => ['nullable', $this->own('fin_machines')],
            'new_process_id' => ['nullable', $this->own('fin_process_types')],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if ($row) {
            throw ValidationException::withMessages(['amend_no' => __('finishing.prod.v.amend_stands', ['no' => $row->getAttribute('amend_no')])]);
        }
        $line = FinProductionLine::query()->findOrFail($data['line_id']);
        $sheet = FinProductionSheet::query()->findOrFail($line->sheet_id);
        if ($sheet->status !== 'POSTED') {
            throw ValidationException::withMessages(['line_id' => __('finishing.prod.v.amend_draft', ['no' => $sheet->sheet_no])]);
        }
        if (substr((string) $data['amend_date'], 0, 10) < $sheet->production_date->toDateString()) {
            throw ValidationException::withMessages(['amend_date' => __('finishing.prod.v.amend_before', ['no' => $sheet->sheet_no])]);
        }
        $machine = $data['new_machine_id'] ?? null ?: $line->machine_id;
        $process = $data['new_process_id'] ?? null ?: $line->process_id;
        if ((float) $data['new_output_kg'] > (float) $data['new_input_kg']) {
            throw ValidationException::withMessages(['new_output_kg' => __('finishing.prod.v.output_over')]);
        }
        if (! FinFloor::mapping($machine, $process, $sheet->line)) {
            throw ValidationException::withMessages(['new_process_id' => __('finishing.prod.v.not_mapped_plain', ['line' => __("finishing.prod.opt.line.{$sheet->line}")])]);
        }
        $same = abs((float) $data['new_input_kg'] - (float) $line->input_kg) < 0.0005 && abs((float) $data['new_output_kg'] - (float) $line->output_kg) < 0.0005
            && $machine === $line->machine_id && $process === $line->process_id;
        if ($same) {
            throw ValidationException::withMessages(['new_input_kg' => __('finishing.prod.v.amend_nothing')]);
        }

        return array_merge($data, [
            'amend_no' => $this->number('FPA-', 'fin_production_amendments', 'amend_no', substr((string) $data['amend_date'], 0, 10)),
            'old_input_kg' => $line->input_kg, 'old_output_kg' => $line->output_kg, 'old_machine_id' => $line->machine_id, 'old_process_id' => $line->process_id,
            'new_machine_id' => $machine, 'new_process_id' => $process, 'created_by' => $this->actingUserId(),
        ]);
    }

    public function afterSave(Model $row, bool $created): void
    {
        FinProductionLine::query()->whereKey($row->getAttribute('line_id'))->update([
            'input_kg' => $row->getAttribute('new_input_kg'), 'output_kg' => $row->getAttribute('new_output_kg'),
            'machine_id' => $row->getAttribute('new_machine_id'), 'process_id' => $row->getAttribute('new_process_id'), 'updated_at' => now(),
        ]);
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $line = FinProductionLine::query()->find($row->getAttribute('line_id'));
        $sheet = $line ? FinProductionSheet::query()->find($line->sheet_id) : null;
        $card = $line ? FinBatchReceipt::query()->find($line->receipt_id) : null;
        [$machines, $processes] = FinFloor::machineAndProcessNames();
        $out['sheet_no'] = $sheet?->sheet_no;
        $out['production_date'] = $sheet?->production_date?->toDateString();
        $out['line'] = $sheet?->line;
        $out['batch_no'] = $card ? (FinFloor::batches([$card->batch_id])[$card->batch_id]->batch_no ?? null) : null;
        foreach (['old', 'new'] as $side) {
            $out["{$side}_machine"] = $machines[$row->getAttribute("{$side}_machine_id")] ?? null;
            $out["{$side}_process"] = $processes[$row->getAttribute("{$side}_process_id")] ?? null;
            foreach (['input', 'output'] as $k) {
                $out["{$side}_{$k}_kg"] = $this->kg($row->getAttribute("{$side}_{$k}_kg"));
            }
        }

        return $out;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Resources;

use App\Modules\Finishing\Models\FinMachine;
use App\Modules\Finishing\Models\FinMachineProcess;
use App\Modules\Finishing\Models\FinMachineTarget;
use App\Modules\Finishing\Models\FinProcessType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Machine-Wise Target Setup: a finishing machine's output per shift, for a
 * process (or every process) and dia, from the day it applies. One target per
 * machine, process, dia and effective date — revising a target is a new row,
 * so past months keep the target they were read against. Finishing Line →
 * Master Data (AnnotechERP 18/01).
 */
class MachineTargetResource extends FinishingResource
{
    public function key(): string
    {
        return 'fin-machine-targets';
    }

    public function model(): string
    {
        return FinMachineTarget::class;
    }

    public function columns(): array
    {
        return ['machine_id', 'process_id', 'dia_type', 'effective_from', 'target_qty', 'shift_hours', 'remarks', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    public function query(): Builder
    {
        return FinMachineTarget::query()->orderByDesc('effective_from');
    }

    public function messages(): array
    {
        return ['machine_id.required' => __('finishing.master.machine_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();

        return [
            'machine_id' => [$required, Rule::exists('fin_machines', 'id')->where('company_id', $company)],
            'process_id' => ['nullable', Rule::exists('fin_process_types', 'id')->where('company_id', $company)],
            'dia_type' => [$required, Rule::in(FinMachineProcess::DIAS)],
            'effective_from' => [$required, 'date'],
            'target_qty' => [$required, 'numeric', 'gt:0', 'max:9999999'],
            'shift_hours' => [$required, 'numeric', 'gt:0', 'max:24'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $p = array_merge($row ? ['machine_id' => $row->machine_id, 'process_id' => $row->process_id, 'dia_type' => $row->dia_type, 'effective_from' => $row->effective_from?->toDateString()] : [], $data);
        $taken = FinMachineTarget::query()->where('machine_id', $p['machine_id'])->where('dia_type', $p['dia_type'])->whereDate('effective_from', $p['effective_from'])
            ->where(fn ($q) => empty($p['process_id']) ? $q->whereNull('process_id') : $q->where('process_id', $p['process_id']))
            ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['effective_from' => __('finishing.master.target_taken')]);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $machine = FinMachine::query()->whereKey($row->getAttribute('machine_id'))->first(['machine_no', 'name']);
        $process = $row->getAttribute('process_id') ? FinProcessType::query()->whereKey($row->getAttribute('process_id'))->first(['name', 'short_name']) : null;
        $out['machine_no'] = $machine?->machine_no;
        $out['machine_name'] = $machine?->name;
        $out['process'] = $process ? "{$process->name} ({$process->short_name})" : null;
        $out['per_hour'] = round((float) $row->getAttribute('target_qty') / max(0.01, (float) $row->getAttribute('shift_hours')), 2);

        return $out;
    }
}

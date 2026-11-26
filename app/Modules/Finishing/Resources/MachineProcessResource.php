<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Resources;

use App\Modules\Finishing\Models\FinMachine;
use App\Modules\Finishing\Models\FinMachineProcess;
use App\Modules\Finishing\Models\FinProcessType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Machine-Process Mapping: which processes each finishing machine may run, per
 * dia, the order the floor works them in, and the stage a batch enters and
 * leaves at — which must differ. A machine is mapped to a process once per
 * dia; one default machine per process and dia. Finishing Line → Master Data
 * (AnnotechERP 18/01).
 */
class MachineProcessResource extends FinishingResource
{
    public function key(): string
    {
        return 'fin-machine-processes';
    }

    public function model(): string
    {
        return FinMachineProcess::class;
    }

    public function columns(): array
    {
        return ['dia_type', 'machine_id', 'process_id', 'sequence', 'batch_status', 'next_status', 'is_default', 'active'];
    }

    public function booleans(): array
    {
        return ['is_default', 'active'];
    }

    public function defaults(): array
    {
        return ['is_default' => false, 'active' => true];
    }

    public function query(): Builder
    {
        return FinMachineProcess::query()->orderBy('sequence')->orderBy('created_at');
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
            'dia_type' => [$required, Rule::in(FinMachineProcess::DIAS)],
            'process_id' => [$required, Rule::exists('fin_process_types', 'id')->where('company_id', $company)],
            'sequence' => [$required, 'integer', 'min:1', 'max:999'],
            'batch_status' => [$required, Rule::in(FinProcessType::STAGES)],
            'next_status' => [$required, Rule::in(FinProcessType::STAGES)],
            'is_default' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $p = array_merge($row?->only(['machine_id', 'process_id', 'dia_type', 'batch_status', 'next_status']) ?? [], $data);
        if ($p['batch_status'] === $p['next_status']) {
            throw ValidationException::withMessages(['next_status' => __('finishing.master.status_same')]);
        }
        $taken = FinMachineProcess::query()->where('machine_id', $p['machine_id'])->where('process_id', $p['process_id'])->where('dia_type', $p['dia_type'])
            ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['process_id' => __('finishing.master.mapping_taken')]);
        }

        return $data;
    }

    public function afterSave(Model $row, bool $created): void
    {
        if ($row->getAttribute('is_default')) {
            FinMachineProcess::query()->where('process_id', $row->process_id)->where('dia_type', $row->dia_type)->whereKeyNot($row->getKey())->update(['is_default' => false]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $machine = FinMachine::query()->whereKey($row->getAttribute('machine_id'))->first(['machine_no', 'name']);
        $process = FinProcessType::query()->whereKey($row->getAttribute('process_id'))->first(['name', 'short_name']);
        $out['machine_no'] = $machine?->machine_no;
        $out['machine_name'] = $machine?->name;
        $out['process'] = $process ? "{$process->name} ({$process->short_name})" : null;

        return $out;
    }
}

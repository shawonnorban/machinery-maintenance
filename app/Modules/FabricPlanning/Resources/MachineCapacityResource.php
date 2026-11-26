<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Resources;

use App\Modules\FabricPlanning\Models\FpMachineCapacity;
use App\Modules\FabricPlanning\Support\FpOps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Dye Machine Capacity Board → Records and New machine capacity: what a dye
 * machine can take in a planning week. Max load in kg follows from Capacity
 * 100% and Max Load %, and the batches planned are read from the plans —
 * neither is typed. One row per machine and week. Fabric Requirement →
 * Operations (AnnotechERP 13/02).
 */
class MachineCapacityResource extends FabricOpsResource
{
    public function key(): string
    {
        return 'fp-machine-capacities';
    }

    public function model(): string
    {
        return FpMachineCapacity::class;
    }

    public function columns(): array
    {
        return ['dye_machine_id', 'week_start', 'capacity_kg', 'working_days', 'holidays', 'max_load_pct', 'capacity_hours', 'avg_batch_qty'];
    }

    public function query(): Builder
    {
        return FpMachineCapacity::query()->orderByDesc('week_start');
    }

    public function messages(): array
    {
        $positive = __('fabricplanning.ops.v.capacity');

        return [
            'capacity_kg.required' => $positive, 'capacity_kg.gt' => $positive,
            'dye_machine_id.required' => __('fabricplanning.ops.v.machine'), 'week_start.required' => __('fabricplanning.ops.v.week'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'dye_machine_id' => [$required, Rule::exists('dye_machines', 'id')->where('company_id', $this->company())],
            'week_start' => [$required, 'date'],
            'capacity_kg' => [$required, 'numeric', 'gt:0', 'max:9999999'],
            'working_days' => [$required, 'integer', 'min:0', 'max:7'],
            'holidays' => [$required, 'integer', 'min:0', 'max:7'],
            'max_load_pct' => [$required, 'numeric', 'min:0', 'max:200'],
            'capacity_hours' => [$required, 'numeric', 'min:0', 'max:999'],
            'avg_batch_qty' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['week_start'])) {
            $data['week_start'] = FpOps::weekStart((string) $data['week_start'])->toDateString();
        }
        $n = array_merge(['dye_machine_id' => $row?->getAttribute('dye_machine_id'), 'week_start' => $row?->getAttribute('week_start')?->toDateString()], $data);
        $days = (int) ($data['working_days'] ?? $row?->getAttribute('working_days')) + (int) ($data['holidays'] ?? $row?->getAttribute('holidays'));
        if ($days > 7) {
            throw ValidationException::withMessages(['holidays' => __('fabricplanning.ops.v.week_days')]);
        }
        if (FpMachineCapacity::query()->where('dye_machine_id', $n['dye_machine_id'])->whereDate('week_start', $n['week_start'])->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
            throw ValidationException::withMessages(['week_start' => __('fabricplanning.ops.v.capacity_taken')]);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['week'] = FpOps::weekLabel($row->getAttribute('week_start')->toDateString());
        $out['max_load_kg'] = round((float) $row->getAttribute('capacity_kg') * (float) $row->getAttribute('max_load_pct') / 100, 2);

        return $out;
    }
}

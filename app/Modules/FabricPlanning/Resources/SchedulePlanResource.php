<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Resources;

use App\Modules\FabricPlanning\Models\FpScheduleBatch;
use App\Modules\FabricPlanning\Models\FpSchedulePlan;
use App\Modules\FabricPlanning\Support\FpOps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Batch Schedule Board → Records and New Plan: a scheduling board — its name,
 * the party it is for (blank: all), whether its columns run on shipment or
 * booking dates, its scale and cell width. The plan number (SP-00001) is
 * given on save. Fabric Requirement → Operations (AnnotechERP 13/02).
 */
class SchedulePlanResource extends FabricOpsResource
{
    public function key(): string
    {
        return 'fp-schedule-plans';
    }

    public function model(): string
    {
        return FpSchedulePlan::class;
    }

    public function columns(): array
    {
        return ['plan_no', 'name', 'party_id', 'basis', 'scale', 'cell_width', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    public function deletable(): bool
    {
        return false;
    }

    public function query(): Builder
    {
        return FpSchedulePlan::query()->orderBy('plan_no');
    }

    public function messages(): array
    {
        return ['name.required' => __('fabricplanning.ops.v.plan_name'), 'cell_width.gt' => __('fabricplanning.ops.v.cell_width'), 'cell_width.required' => __('fabricplanning.ops.v.cell_width')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:120'],
            'party_id' => ['nullable', Rule::in([$this->company()])],
            'basis' => [$required, Rule::in(['SHIPMENT', 'BOOKING'])],
            'scale' => [$required, Rule::in(['DAY', 'WEEK', 'MONTH'])],
            'cell_width' => [$required, 'numeric', 'gt:0', 'max:1000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        unset($data['plan_no']);
        if (! $row) {
            $data['plan_no'] = FpOps::nextNumber('fp_schedule_plans', 'plan_no', 'SP', $this->company());
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['batches'] = FpScheduleBatch::query()->where('schedule_plan_id', $row->getKey())->count();

        return $out;
    }
}

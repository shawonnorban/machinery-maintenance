<?php

declare(strict_types=1);

namespace App\Modules\Planning\Resources;

use App\Modules\Planning\Models\PlanningLineManpower;
use App\Modules\Planning\Support\PlanningLabels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Manpower Master: the operators, helpers and staff each planning line needs,
 * one row per role. Capacity Planning → Master Data (AnnotechERP 12/01).
 */
class LineManpowerResource extends PlanningMasterResource
{
    public function key(): string
    {
        return 'planning-line-manpower';
    }

    public function model(): string
    {
        return PlanningLineManpower::class;
    }

    public function columns(): array
    {
        return ['planning_line_id', 'role', 'required', 'remarks'];
    }

    public function query(): Builder
    {
        return PlanningLineManpower::query()->orderBy('planning_line_id')->orderBy('role');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $line = request()->input('planning_line_id', $row?->getAttribute('planning_line_id'));

        return [
            'planning_line_id' => [$required, Rule::exists('planning_lines', 'id')->where('company_id', $this->company())],
            // One row per role on a line.
            'role' => [$required, Rule::in(PlanningLineManpower::ROLES), Rule::unique('planning_line_manpower', 'role')->where('planning_line_id', $line)->ignore($row?->getKey())],
            'required' => [$required, 'integer', 'min:0', 'max:9999'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['role.unique' => __('planning.master.role_taken')];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $labels = PlanningLabels::forLine($row->getAttribute('planning_line_id'));
        $out['floor'] = $labels['floor'];
        $out['line'] = $labels['floor_line'];
        $out['planning_line'] = $labels['line'];

        return $out;
    }
}

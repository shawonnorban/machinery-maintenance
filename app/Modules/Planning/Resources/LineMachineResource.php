<?php

declare(strict_types=1);

namespace App\Modules\Planning\Resources;

use App\Modules\Planning\Models\PlanningLineMachine;
use App\Modules\Planning\Support\PlanningLabels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Machine Master: the machines installed on each planning line — type,
 * category, how many are installed, working and on standby, and their output
 * per hour. Capacity Planning → Master Data (AnnotechERP 12/01).
 */
class LineMachineResource extends PlanningMasterResource
{
    public function key(): string
    {
        return 'planning-line-machines';
    }

    public function model(): string
    {
        return PlanningLineMachine::class;
    }

    public function columns(): array
    {
        return ['planning_line_id', 'machine_type', 'name_code', 'category', 'installed', 'working', 'standby', 'capacity_per_hour', 'active'];
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
        return PlanningLineMachine::query()->orderBy('planning_line_id')->orderBy('machine_type');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'planning_line_id' => [$required, Rule::exists('planning_lines', 'id')->where('company_id', $this->company())],
            'machine_type' => [$required, 'string', 'max:255'],
            'name_code' => ['nullable', 'string', 'max:255'],
            'category' => [$required, Rule::in(PlanningLineMachine::CATEGORIES)],
            'installed' => [$required, 'integer', 'min:0', 'max:9999'],
            'working' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'standby' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'capacity_per_hour' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /** Working and standby machines are the installed ones, never more. */
    public function beforeSave(array $data, ?Model $row): array
    {
        foreach (['working', 'standby'] as $count) {
            if (array_key_exists($count, $data) && $data[$count] === null) {
                $data[$count] = 0;
            }
        }
        $m = array_merge($row?->only(['installed', 'working', 'standby']) ?? [], $data);
        if ((int) ($m['working'] ?? 0) + (int) ($m['standby'] ?? 0) > (int) ($m['installed'] ?? 0)) {
            throw ValidationException::withMessages(['working' => __('planning.master.machines_over')]);
        }

        return $data;
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

<?php

declare(strict_types=1);

namespace App\Modules\Planning\Resources;

use App\Modules\Planning\Models\PlanningLine;
use App\Modules\Planning\Support\PlanningLabels;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Production Line: each sewing line's standard manpower, machines, hours and
 * efficiency — what a master or daily plan starts from — and the floor line
 * its actual output is read from. Capacity Planning → Master Data
 * (AnnotechERP 12/01).
 */
class PlanningLineResource extends PlanningMasterResource
{
    public function key(): string
    {
        return 'planning-lines';
    }

    public function model(): string
    {
        return PlanningLine::class;
    }

    public function columns(): array
    {
        return ['floor_id', 'floor_line_id', 'code', 'name', 'std_manpower', 'std_machines', 'std_hours', 'std_efficiency', 'best_fit', 'display_order', 'under_maintenance', 'active'];
    }

    public function booleans(): array
    {
        return ['under_maintenance', 'active'];
    }

    public function defaults(): array
    {
        return ['active' => true, 'under_maintenance' => false, 'std_hours' => 10, 'std_efficiency' => 65];
    }

    public function query(): Builder
    {
        return PlanningLine::query()->orderBy('display_order')->orderBy('code');
    }

    public function messages(): array
    {
        return ['floor_id.required' => __('planning.master.floor_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();
        $floor = request()->input('floor_id', $row?->getAttribute('floor_id'));

        return [
            'floor_id' => [$required, Rule::exists('floors', 'id')->where('company_id', $company)],
            // Only a line on the same floor; its output is what Plan vs Actual reads.
            'floor_line_id' => ['nullable', Rule::exists('production_lines', 'id')->where('company_id', $company)->where('floor_id', $floor)],
            'code' => [$required, 'string', 'max:32', $this->uniqueOnFloor($row, $floor)],
            'name' => [$required, 'string', 'max:255'],
            'std_manpower' => [$required, 'integer', 'min:0', 'max:9999'],
            'std_machines' => [$required, 'integer', 'min:0', 'max:9999'],
            'std_hours' => [$required, 'numeric', 'min:0.5', 'max:24'],
            'std_efficiency' => [$required, 'numeric', 'min:0', 'max:150'],
            'best_fit' => ['nullable', 'string', 'max:255'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'under_maintenance' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    private function uniqueOnFloor(?Model $row, ?string $floor): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($row, $floor): void {
            $taken = PlanningLine::query()->where('floor_id', $floor)->where('code', strtoupper(trim((string) $value)))->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
            if ($taken) {
                $fail(__('planning.master.code_taken'));
            }
        };
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }
        if (array_key_exists('display_order', $data) && $data['display_order'] === null) {
            $data['display_order'] = 0;
        }

        return $data;
    }

    public function afterSave(Model $row, bool $created): void
    {
        PlanningLabels::forget();
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $labels = PlanningLabels::forLine($row->getKey());
        $out['unit'] = $labels['unit'];
        $out['floor'] = $labels['floor'];
        $out['floor_line'] = $labels['floor_line'];

        return $out;
    }
}

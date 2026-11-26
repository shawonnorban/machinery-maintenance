<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Planning\Support\PlanningLabels;
use App\Modules\WorkStudy\Models\WsLineProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Resource Capability Profile → line profile: a line's operators, ASOs and
 * machines before it is loaded — by machine type, or for the line overall
 * when the machine type is left blank. One profile per line and machine type.
 * Work Study → Master Data (AnnotechERP 14/01).
 */
class LineProfileResource extends WorkStudyResource
{
    public function key(): string
    {
        return 'ws-line-profiles';
    }

    public function model(): string
    {
        return WsLineProfile::class;
    }

    public function columns(): array
    {
        return ['planning_line_id', 'machine_type', 'operators', 'aso', 'machines'];
    }

    public function query(): Builder
    {
        return WsLineProfile::query()->orderBy('planning_line_id')->orderBy('machine_type');
    }

    public function messages(): array
    {
        return ['planning_line_id.required' => __('workstudy.master.line_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'planning_line_id' => [$required, Rule::exists('planning_lines', 'id')->where('company_id', $this->company())],
            'machine_type' => ['nullable', Rule::in(WsLineProfile::MACHINE_TYPES)],
            'operators' => [$required, 'integer', 'min:0', 'max:9999'],
            'aso' => [$required, 'integer', 'min:0', 'max:9999'],
            'machines' => [$required, 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $p = array_merge($row?->only(['planning_line_id', 'machine_type']) ?? [], $data);
        $taken = WsLineProfile::query()->where('planning_line_id', $p['planning_line_id'])
            ->where(fn ($q) => empty($p['machine_type']) ? $q->whereNull('machine_type') : $q->where('machine_type', $p['machine_type']))
            ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['machine_type' => __('workstudy.master.profile_taken')]);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $labels = PlanningLabels::forLine($row->getAttribute('planning_line_id'));
        $out['line'] = ($labels['floor'] ?? '?').'>>'.($labels['line'] ?? '?');

        return $out;
    }
}

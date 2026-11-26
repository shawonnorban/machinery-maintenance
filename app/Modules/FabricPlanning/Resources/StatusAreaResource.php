<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Resources;

use App\Modules\FabricPlanning\Models\FpStatusArea;
use App\Modules\FabricPlanning\Models\FpStatusGroup;
use App\Modules\FabricPlanning\Support\FpOps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Planning Constraint Ruleset → Records, New and Edit plan status area: what
 * a constraint family measures and how much it counts. The code is fixed
 * once made — the scoring engine reads an area by it. A parent, if any, is an
 * area of the same family. Fabric Requirement → Operations (AnnotechERP 13/02).
 */
class StatusAreaResource extends FabricOpsResource
{
    public function key(): string
    {
        return 'fp-status-areas';
    }

    public function model(): string
    {
        return FpStatusArea::class;
    }

    public function columns(): array
    {
        return ['group_id', 'parent_id', 'sl', 'code', 'name', 'wt_factor', 'active'];
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
        FpOps::ensureRuleset($this->company());

        return FpStatusArea::query()->join('fp_status_groups as g', 'g.id', '=', 'fp_status_areas.group_id')
            ->orderBy('g.sl')->orderBy('fp_status_areas.sl')->select('fp_status_areas.*');
    }

    public function messages(): array
    {
        return [
            'group_id.required' => __('fabricplanning.ops.v.group'),
            'code.unique' => __('fabricplanning.ops.v.code_taken'),
            'wt_factor.required' => __('fabricplanning.ops.v.wt'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'group_id' => [$required, Rule::exists('fp_status_groups', 'id')->where('company_id', $this->company())],
            'parent_id' => ['nullable', Rule::exists('fp_status_areas', 'id')->where('company_id', $this->company())],
            'sl' => [$required, 'integer', 'min:0', 'max:999'],
            'code' => [$required, 'string', 'max:24', Rule::unique('fp_status_areas', 'code')->where('company_id', $this->company())->ignore($row?->getKey())],
            'name' => [$required, 'string', 'max:160'],
            'wt_factor' => [$required, 'numeric', 'min:0', 'max:999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim((string) $data['code']));
            // The code is fixed once the area is made.
            if ($row && $data['code'] !== $row->getAttribute('code')) {
                throw ValidationException::withMessages(['code' => __('fabricplanning.ops.v.code_fixed')]);
            }
        }
        $group = $data['group_id'] ?? $row?->getAttribute('group_id');
        $parent = $data['parent_id'] ?? null;
        if ($parent !== null) {
            $p = FpStatusArea::query()->find($parent);
            if (! $p || $p->group_id !== $group || ($row && $p->id === $row->getKey())) {
                throw ValidationException::withMessages(['parent_id' => __('fabricplanning.ops.v.parent')]);
            }
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $group = FpStatusGroup::query()->find($row->getAttribute('group_id'));
        $out['group'] = $group ? "{$group->sl}. {$group->name}" : null;
        $out['group_sl'] = $group?->sl;
        $out['parent'] = $row->getAttribute('parent_id') ? FpStatusArea::query()->whereKey($row->getAttribute('parent_id'))->value('code') : null;

        return $out;
    }
}

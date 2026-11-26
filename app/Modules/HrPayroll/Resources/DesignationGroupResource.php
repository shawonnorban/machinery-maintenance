<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Resources;

use App\Modules\HrPayroll\Models\HrDesignationGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Employee Category: the categories designations are grouped under, in a
 * tree, and the manpower category (Operator, Quality, Helper, Supervisor,
 * Staff) the HR and Company dashboards count each as — empty means read from
 * the designation title. HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class DesignationGroupResource extends HrResource
{
    public function key(): string
    {
        return 'hr-designation-groups';
    }

    public function model(): string
    {
        return HrDesignationGroup::class;
    }

    public function columns(): array
    {
        return ['name', 'name_bn', 'short_name', 'parent_id', 'level', 'sort_order', 'manpower_category', 'is_leaf', 'active'];
    }

    public function booleans(): array
    {
        return ['is_leaf', 'active'];
    }

    public function defaults(): array
    {
        return ['is_leaf' => true, 'active' => true];
    }

    public function query(): Builder
    {
        $this->ensureDefaults();

        return HrDesignationGroup::query()->orderBy('sort_order')->orderBy('name');
    }

    public function messages(): array
    {
        return ['name.required' => __('hrpayroll.master.group_name_required')];
    }

    public function rules(?Model $row): array
    {
        // A list's defaults go in before the first row is checked against them.
        $this->ensureDefaults();
        $required = $row ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255', $this->uniqueText(HrDesignationGroup::class, 'name', $row, __('hrpayroll.master.group_taken'))],
            'name_bn' => [$required, 'string', 'max:255'],
            'short_name' => [$required, 'string', 'max:32'],
            'sort_order' => [$required, 'integer', 'min:0'],
            'parent_id' => ['nullable', $this->own('hr_designation_groups')],
            'manpower_category' => ['nullable', Rule::in(HrDesignationGroup::MANPOWER)],
            'is_leaf' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        return $this->placeInTree(HrDesignationGroup::class, $data, $row);
    }

    public function afterSave(Model $row, bool $created): void
    {
        $this->parentIsBranch(HrDesignationGroup::class, $row);
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['parent'] = $row->getAttribute('parent_id') ? HrDesignationGroup::query()->whereKey($row->getAttribute('parent_id'))->value('name') : null;

        return $out;
    }
}

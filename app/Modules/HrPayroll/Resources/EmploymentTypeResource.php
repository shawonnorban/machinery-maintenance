<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Resources;

use App\Modules\HrPayroll\Models\HrEmploymentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Employment Type: the types an employee is engaged on — Staff, Worker —
 * filed in a tree, each with its own entitlements. Type Level is derived from
 * the parent on save. HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class EmploymentTypeResource extends HrResource
{
    public function key(): string
    {
        return 'hr-employment-types';
    }

    public function model(): string
    {
        return HrEmploymentType::class;
    }

    public function columns(): array
    {
        return ['name', 'name_bn', 'short_name', 'parent_id', 'level', 'is_leaf', 'active'];
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

        return HrEmploymentType::query()->orderBy('level')->orderBy('name');
    }

    public function messages(): array
    {
        return ['name.required' => __('hrpayroll.master.type_name_required')];
    }

    public function rules(?Model $row): array
    {
        // A list's defaults go in before the first row is checked against them.
        $this->ensureDefaults();
        $required = $row ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255', $this->uniqueText(HrEmploymentType::class, 'name', $row, __('hrpayroll.master.type_taken'))],
            'name_bn' => [$required, 'string', 'max:255'],
            'short_name' => [$required, 'string', 'max:16'],
            'parent_id' => ['nullable', $this->own('hr_employment_types')],
            'is_leaf' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        return $this->placeInTree(HrEmploymentType::class, $data, $row);
    }

    public function afterSave(Model $row, bool $created): void
    {
        $this->parentIsBranch(HrEmploymentType::class, $row);
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['parent'] = $row->getAttribute('parent_id') ? HrEmploymentType::query()->whereKey($row->getAttribute('parent_id'))->value('name') : null;

        return $out;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Resources;

use App\Modules\Accounting\Models\AccCostCenterGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Cost Centre Grouping: the reporting groups cost centres roll into — floor,
 * unit and division. Finance & Ledger → Master Data (AnnotechERP 23/01).
 */
class CostCenterGroupResource extends AccountingResource
{
    public function key(): string
    {
        return 'acc-cost-center-groups';
    }

    public function model(): string
    {
        return AccCostCenterGroup::class;
    }

    public function columns(): array
    {
        return ['name', 'display_order', 'remarks', 'active'];
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
        return AccCostCenterGroup::query()->orderBy('display_order')->orderBy('name');
    }

    public function messages(): array
    {
        return ['name.required' => __('accounting.master.cost_center_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255', $this->uniqueText(AccCostCenterGroup::class, 'name', $row, __('accounting.master.group_taken'))],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (array_key_exists('display_order', $data) && $data['display_order'] === null) {
            $data['display_order'] = 0;
        }

        return $data;
    }
}

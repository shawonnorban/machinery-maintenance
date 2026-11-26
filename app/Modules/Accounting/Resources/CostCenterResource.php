<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Resources;

use App\Modules\Accounting\Models\AccCostCenter;
use App\Modules\Accounting\Models\AccCostCenterGroup;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Cost Centre Master: the units expenditure is charged to, from a sewing line
 * to a whole factory — its office, group and department or section. The
 * code is issued on save, in the register's three-digit form. Finance &
 * Ledger → Master Data (AnnotechERP 23/01).
 */
class CostCenterResource extends AccountingResource
{
    public function key(): string
    {
        return 'acc-cost-centers';
    }

    public function model(): string
    {
        return AccCostCenter::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'factory_id', 'group_id', 'dept_section', 'remarks', 'active'];
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
        return AccCostCenter::query()->orderBy('code');
    }

    public function messages(): array
    {
        return ['name.required' => __('accounting.master.name_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();

        return [
            'name' => [$required, 'string', 'max:255', $this->uniqueText(AccCostCenter::class, 'name', $row, __('accounting.master.cost_center_taken'))],
            'factory_id' => ['nullable', Rule::exists('factories', 'id')->where('company_id', $company)],
            'group_id' => ['nullable', Rule::exists('acc_cost_center_groups', 'id')->where('company_id', $company)],
            'dept_section' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (! $row) {
            $last = AccCostCenter::query()->pluck('code')->map(fn ($c) => (int) $c)->max() ?? 0;
            $data['code'] = str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['company'] = Company::query()->whereKey($row->getAttribute('company_id'))->value('name');
        $out['office'] = $row->getAttribute('factory_id') ? Factory::query()->whereKey($row->getAttribute('factory_id'))->value('name') : null;
        $out['group'] = $row->getAttribute('group_id') ? AccCostCenterGroup::query()->whereKey($row->getAttribute('group_id'))->value('name') : null;

        return $out;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Resources;

use App\Modules\HrPayroll\Models\HrGazetteLine;
use App\Modules\HrPayroll\Models\HrPayElement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Pay Element: the heads a salary is built from — basic, house rent,
 * medical, food, transport and the rest — with how many gazette structure
 * lines use each. HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class PayElementResource extends HrResource
{
    public function key(): string
    {
        return 'hr-pay-elements';
    }

    public function model(): string
    {
        return HrPayElement::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'active'];
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
        $this->ensureDefaults();

        return HrPayElement::query()->orderBy('code');
    }

    public function messages(): array
    {
        return ['code.required' => __('hrpayroll.master.element_code_required')];
    }

    public function rules(?Model $row): array
    {
        // A list's defaults go in before the first row is checked against them.
        $this->ensureDefaults();
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:16', 'regex:/^[A-Za-z0-9_-]+$/', $this->uniqueText(HrPayElement::class, 'code', $row, __('hrpayroll.master.element_taken'))],
            'name' => [$required, 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['in_structures'] = HrGazetteLine::query()->where('pay_element_id', $row->getKey())->count();
        // No ledger mapping exists for a pay element until Salary Processing posts to the books.
        $out['gl_mapped'] = 0;

        return $out;
    }
}

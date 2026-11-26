<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Resources;

use App\Modules\Accounting\Models\AccFiscalYear;
use App\Modules\HrPayroll\Models\HrAllowance;
use App\Modules\HrPayroll\Models\HrDeduction;
use App\Modules\HrPayroll\Models\HrPayElement;
use App\Modules\HrPayroll\Models\HrPayPeriod;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Payroll Settings → pay periods: a company's pay period in a fiscal year,
 * its dates and pay date, and where it is in its life (Initiated → Submitted
 * → Activated → Closed). The number is generated on save from the company,
 * year and month — it is not typed. HR & Payroll → Master Data (AnnotechERP
 * 24/01).
 */
class PayPeriodResource extends HrResource
{
    public function key(): string
    {
        return 'hr-pay-periods';
    }

    public function model(): string
    {
        return HrPayPeriod::class;
    }

    public function columns(): array
    {
        return ['period_no', 'period_type', 'fiscal_year_id', 'month', 'start_date', 'end_date', 'pay_date', 'status', 'pay_element_id', 'dept_type', 'remarks', 'show_for_report', 'closed'];
    }

    public function booleans(): array
    {
        return ['show_for_report', 'closed'];
    }

    public function defaults(): array
    {
        return ['show_for_report' => true, 'closed' => false, 'status' => 'INITIATED'];
    }

    public function query(): Builder
    {
        return HrPayPeriod::query()->orderByDesc('pay_date')->orderByDesc('period_no');
    }

    public function messages(): array
    {
        return ['period_type.required' => __('hrpayroll.master.period_type_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'period_type' => [$required, Rule::in(['MONTHLY', 'DAYS'])],
            'fiscal_year_id' => [$required, $this->own('acc_fiscal_years')],
            'month' => [$required, 'integer', 'min:1', 'max:12'],
            'start_date' => [$required, 'date'],
            'end_date' => [$required, 'date'],
            'pay_date' => [$required, 'date'],
            'status' => [$required, Rule::in(HrPayPeriod::STATUSES)],
            'pay_element_id' => ['nullable', $this->own('hr_pay_elements')],
            'dept_type' => ['nullable', Rule::in(['PRODUCTION', 'NON_PRODUCTION'])],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'show_for_report' => ['sometimes', 'boolean'],
            'closed' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        unset($data['period_no']);
        $v = fn (string $k) => self::date(array_key_exists($k, $data) ? $data[$k] : $row?->getAttribute($k));
        if ($v('end_date') < $v('start_date')) {
            throw ValidationException::withMessages(['end_date' => __('hrpayroll.master.period_order')]);
        }
        if (($data['status'] ?? null) === 'CLOSED') {
            $data['closed'] = true;
        }
        if (! $row) {
            $company = Company::query()->find($this->company(), ['code']);
            $year = AccFiscalYear::query()->whereKey($data['fiscal_year_id'])->value('name');
            $prefix = 'APP-'.strtoupper((string) $company?->code).$year.str_pad((string) $data['month'], 2, '0', STR_PAD_LEFT);
            $last = HrPayPeriod::query()->where('period_no', 'like', $prefix.'%')->pluck('period_no')->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;
            $data['period_no'] = $prefix.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $allowances = HrAllowance::query()->where('pay_period_id', $row->getKey());
        $out['company'] = Company::query()->whereKey($row->getAttribute('company_id'))->value('name');
        $out['fiscal_year'] = AccFiscalYear::query()->whereKey($row->getAttribute('fiscal_year_id'))->value('name');
        $out['pay_element'] = $row->getAttribute('pay_element_id') ? HrPayElement::query()->whereKey($row->getAttribute('pay_element_id'))->value('name') : null;
        $out['total_pays'] = (clone $allowances)->distinct()->count('user_id');
        $out['total_payable'] = round((float) $allowances->sum('amount') - (float) HrDeduction::query()->where('pay_period_id', $row->getKey())->sum('amount'), 2);

        return $out;
    }
}

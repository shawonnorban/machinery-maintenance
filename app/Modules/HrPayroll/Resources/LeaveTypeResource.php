<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Resources;

use App\Modules\HrPayroll\Models\HrLeaveType;
use App\Modules\HrPayroll\Models\HrPayElement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Leave Type: paid or unpaid, with its eligibility and balance rules. Leave
 * Group decides whether a day under the type counts as leave, present or
 * absent — attendance and payroll both branch on it. HR & Payroll → Master
 * Data (AnnotechERP 24/01).
 */
class LeaveTypeResource extends HrResource
{
    public function key(): string
    {
        return 'hr-leave-types';
    }

    public function model(): string
    {
        return HrLeaveType::class;
    }

    public function columns(): array
    {
        return ['code', 'color', 'name', 'name_bn', 'paid', 'pay_element_id', 'day_forward', 'forward_days', 'accrual', 'accrual_auto', 'offday_included', 'holiday_included', 'balance_check', 'leave_group', 'min_employment', 'min_unit', 'notify_days', 'max_days', 'min_work_days', 'applicable_for', 'encashable', 'active', 'remarks'];
    }

    public function booleans(): array
    {
        return ['paid', 'day_forward', 'accrual', 'accrual_auto', 'offday_included', 'holiday_included', 'balance_check', 'encashable', 'active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    public function query(): Builder
    {
        $this->ensureDefaults();

        return HrLeaveType::query()->orderBy('code');
    }

    public function messages(): array
    {
        return ['code.required' => __('hrpayroll.master.leave_code_required')];
    }

    public function rules(?Model $row): array
    {
        // A list's defaults go in before the first row is checked against them.
        $this->ensureDefaults();
        $required = $row ? 'sometimes' : 'required';
        $count = ['nullable', 'integer', 'min:0', 'max:3660'];

        return [
            'code' => [$required, 'string', 'max:8', $this->uniqueText(HrLeaveType::class, 'code', $row, __('hrpayroll.master.leave_taken'))],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'name' => [$required, 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'pay_element_id' => ['nullable', $this->own('hr_pay_elements')],
            'forward_days' => $count,
            'leave_group' => [$required, Rule::in(['LEAVE', 'PRESENT', 'ABSENT'])],
            'min_employment' => $count,
            'min_unit' => ['nullable', Rule::in(['DAY', 'MONTH', 'YEAR'])],
            'notify_days' => $count,
            'max_days' => $count,
            'min_work_days' => $count,
            'applicable_for' => [$required, Rule::in(['MALE', 'FEMALE', 'COMMON'])],
            'remarks' => ['nullable', 'string', 'max:1000'],
            ...array_fill_keys($this->booleans(), ['sometimes', 'boolean']),
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }
        if (array_key_exists('day_forward', $data) && ! $data['day_forward']) {
            $data['forward_days'] = null;
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['pay_element'] = $row->getAttribute('pay_element_id') ? HrPayElement::query()->whereKey($row->getAttribute('pay_element_id'))->value('name') : null;

        return $out;
    }
}

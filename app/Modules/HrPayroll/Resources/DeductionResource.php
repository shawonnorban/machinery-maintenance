<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Resources;

use App\Modules\HrPayroll\Models\HrDeduction;
use App\Modules\HrPayroll\Models\HrPayElement;
use App\Modules\HrPayroll\Models\HrPayPeriod;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Deduction: what is held back from each employee for a pay period, by deduction head — entered or uploaded. The same head cannot be deducted twice from one employee in one period. New rows come in through HrPayrollApiController::entries(); here an
 * amount is corrected or a row removed. HR & Payroll → Master Data
 * (AnnotechERP 24/01).
 */
class DeductionResource extends HrResource
{
    public function key(): string
    {
        return 'hr-deductions';
    }

    public function model(): string
    {
        return HrDeduction::class;
    }

    public function columns(): array
    {
        return ['pay_period_id', 'pay_element_id', 'user_id', 'amount'];
    }

    public function deletable(): bool
    {
        return true;
    }

    public function query(): Builder
    {
        return HrDeduction::query()->latest('updated_at');
    }

    public function rules(?Model $row): array
    {
        // Only the amount changes after entry; who, what and when are the row's identity.
        return ['amount' => [$row ? 'sometimes' : 'required', 'numeric', 'gt:0', 'max:9999999999']];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $period = HrPayPeriod::query()->find($row?->getAttribute('pay_period_id'));
        if ($period?->closed) {
            throw ValidationException::withMessages(['amount' => __('hrpayroll.master.period_closed')]);
        }

        return array_intersect_key($data, ['amount' => true]);
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $period = HrPayPeriod::query()->find($row->getAttribute('pay_period_id'), ['period_no', 'closed']);
        $element = HrPayElement::query()->find($row->getAttribute('pay_element_id'), ['code', 'name']);
        $user = User::query()->find($row->getAttribute('user_id'), ['name', 'employee_code']);
        $out['pay_period'] = $period?->period_no;
        $out['period_closed'] = (bool) $period?->closed;
        $out['pay_element'] = $element ? "{$element->name} ({$element->code})" : null;
        $out['employee_code'] = $user?->employee_code;
        $out['employee'] = $user?->name;

        return $out;
    }
}

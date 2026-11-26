<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Resources;

use App\Modules\HrPayroll\Models\HrBonusRule;
use App\Modules\HrPayroll\Models\HrBonusRuleSet;
use App\Modules\HrPayroll\Models\HrDesignationGroup;
use App\Modules\HrPayroll\Models\HrEmploymentType;
use App\Modules\HrPayroll\Models\HrShiftTeam;
use App\Modules\Tenancy\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Bonus Rule: a festival bonus by service length (months), salary band, the
 * wage it is based on, and a percentage or a fixed amount, for an employee
 * type — narrowed, if need be, to a department, designation group or shift
 * team. A high limit of 0 means no upper limit. HR & Payroll → Master Data
 * (AnnotechERP 24/01).
 */
class BonusRuleResource extends HrResource
{
    public function key(): string
    {
        return 'hr-bonus-rules';
    }

    public function model(): string
    {
        return HrBonusRule::class;
    }

    public function columns(): array
    {
        return ['rule_set_id', 'service_low', 'service_high', 'salary_low', 'salary_high', 'basis', 'pct', 'fixed', 'employment_type_id', 'department_id', 'designation_group_id', 'shift_team_id', 'active'];
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

        return HrBonusRule::query()->orderBy('created_at')->orderBy('id');
    }

    public function messages(): array
    {
        return ['service_low.required' => __('hrpayroll.master.service_low_required')];
    }

    public function rules(?Model $row): array
    {
        // A list's defaults go in before the first row is checked against them.
        $this->ensureDefaults();
        $required = $row ? 'sometimes' : 'required';

        return [
            'service_low' => [$required, 'integer', 'min:0', 'max:99999'],
            'rule_set_id' => [$required, $this->own('hr_bonus_rule_sets')],
            'service_high' => [$required, 'integer', 'min:0', 'max:99999'],
            'salary_low' => [$required, 'numeric', 'min:0', 'max:999999999'],
            'salary_high' => [$required, 'numeric', 'min:0', 'max:999999999'],
            'basis' => [$required, Rule::in(['BASIC', 'GROSS'])],
            'pct' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'fixed' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'employment_type_id' => [$required, $this->own('hr_employment_types')],
            'department_id' => ['nullable', $this->own('departments')],
            'designation_group_id' => ['nullable', $this->own('hr_designation_groups')],
            'shift_team_id' => ['nullable', $this->own('hr_shift_teams')],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $v = fn (string $k) => array_key_exists($k, $data) ? $data[$k] : $row?->getAttribute($k);
        foreach ([['service_low', 'service_high'], ['salary_low', 'salary_high']] as [$low, $high]) {
            if ((float) $v($high) !== 0.0 && (float) $v($high) < (float) $v($low)) {
                throw ValidationException::withMessages([$high => __('hrpayroll.master.band_order')]);
            }
        }
        if (($v('pct') === null || $v('pct') === '') && ($v('fixed') === null || $v('fixed') === '')) {
            throw ValidationException::withMessages(['pct' => __('hrpayroll.master.bonus_amount_required')]);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $name = fn (string $model, string $column, string $field = 'name') => $row->getAttribute($column) ? $model::query()->whereKey($row->getAttribute($column))->value($field) : null;
        $out['rule_set'] = $name(HrBonusRuleSet::class, 'rule_set_id');
        $out['employment_type'] = $name(HrEmploymentType::class, 'employment_type_id');
        $out['department'] = $name(Department::class, 'department_id');
        $out['designation_group'] = $name(HrDesignationGroup::class, 'designation_group_id');
        $out['shift_team'] = $name(HrShiftTeam::class, 'shift_team_id');
        $out['scope'] = implode(' · ', array_filter([$out['department'], $out['designation_group'], $out['shift_team']]));

        return $out;
    }
}

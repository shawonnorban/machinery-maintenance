<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Resources;

use App\Modules\HrPayroll\Models\HrEmploymentType;
use App\Modules\HrPayroll\Models\HrOtRule;
use App\Modules\Tenancy\Models\Grade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * OT Rule: how an OT hour is priced — wage basis ÷ divisor × multiplier — by
 * effective date, employee category and grade. When several apply on a day,
 * the one naming the grade wins, then the one naming the category, then the
 * general rule. HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class OtRuleResource extends HrResource
{
    public function key(): string
    {
        return 'hr-ot-rules';
    }

    public function model(): string
    {
        return HrOtRule::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'wage_basis', 'divisor', 'multiplier', 'effective_from', 'effective_to', 'employment_type_id', 'grade_id', 'legal_ref', 'remarks', 'active'];
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

        return HrOtRule::query()->orderByDesc('effective_from')->orderBy('code');
    }

    public function messages(): array
    {
        return ['effective_from.required' => __('hrpayroll.master.effective_from_required')];
    }

    public function rules(?Model $row): array
    {
        // A list's defaults go in before the first row is checked against them.
        $this->ensureDefaults();
        $required = $row ? 'sometimes' : 'required';

        return [
            'effective_from' => [$required, 'date'],
            'code' => [$required, 'string', 'max:16', $this->uniqueText(HrOtRule::class, 'code', $row, __('hrpayroll.master.ot_taken'))],
            'name' => [$required, 'string', 'max:255'],
            'wage_basis' => [$required, Rule::in(['BASIC', 'GROSS'])],
            'divisor' => [$required, 'numeric', 'gt:0', 'max:9999'],
            'multiplier' => [$required, 'numeric', 'gt:0', 'max:99'],
            'effective_to' => ['nullable', 'date'],
            'employment_type_id' => ['nullable', $this->own('hr_employment_types')],
            'grade_id' => ['nullable', $this->own('grades')],
            'legal_ref' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }
        $from = self::date($data['effective_from'] ?? $row?->getAttribute('effective_from'));
        $to = self::date(array_key_exists('effective_to', $data) ? $data['effective_to'] : $row?->getAttribute('effective_to'));
        if ($to && $to < $from) {
            throw ValidationException::withMessages(['effective_to' => __('hrpayroll.master.effective_order')]);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
        $out['formula'] = ucfirst(strtolower((string) $row->getAttribute('wage_basis'))).' ÷ '.$num($row->getAttribute('divisor')).' × '.$num($row->getAttribute('multiplier'));
        $out['category'] = $row->getAttribute('employment_type_id') ? HrEmploymentType::query()->whereKey($row->getAttribute('employment_type_id'))->value('name') : null;
        $out['grade'] = $row->getAttribute('grade_id') ? Grade::query()->whereKey($row->getAttribute('grade_id'))->value('name') : null;

        return $out;
    }
}

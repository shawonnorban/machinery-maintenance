<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Resources;

use App\Modules\HrPayroll\Models\HrEmploymentType;
use App\Modules\HrPayroll\Models\HrGazette;
use App\Modules\HrPayroll\Models\HrGazetteGradeWage;
use App\Modules\Tenancy\Models\Grade;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Gazette Grade Wage: what each gazette fixes per grade — Basic, House Rent
 * as a share of it, the allowances — and the Gross-A appointment letters
 * print. HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class GazetteGradeWageResource extends HrResource
{
    public function key(): string
    {
        return 'hr-gazette-grade-wages';
    }

    public function model(): string
    {
        return HrGazetteGradeWage::class;
    }

    public function columns(): array
    {
        return ['gazette_id', 'employment_type_id', 'grade_id', 'basic', 'house_rent_pct', 'medical', 'conveyance', 'food', 'other', 'active'];
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

        return HrGazetteGradeWage::query()->latest('created_at');
    }

    public function messages(): array
    {
        return ['gazette_id.required' => __('hrpayroll.master.gazette_required')];
    }

    public function rules(?Model $row): array
    {
        // A list's defaults go in before the first row is checked against them.
        $this->ensureDefaults();
        $required = $row ? 'sometimes' : 'required';
        $money = [$required, 'numeric', 'min:0', 'max:9999999999'];

        return [
            'gazette_id' => [$required, $this->own('hr_gazettes')],
            'employment_type_id' => [$required, $this->own('hr_employment_types')],
            'grade_id' => [$required, $this->own('grades')],
            'basic' => $money,
            'house_rent_pct' => [$required, 'numeric', 'min:0', 'max:500'],
            'medical' => $money,
            'conveyance' => $money,
            'food' => $money,
            'other' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $key = fn (string $k) => $data[$k] ?? $row?->getAttribute($k);
        $taken = HrGazetteGradeWage::query()->where('gazette_id', $key('gazette_id'))->where('employment_type_id', $key('employment_type_id'))->where('grade_id', $key('grade_id'))
            ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['grade_id' => __('hrpayroll.master.grade_wage_taken')]);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $gazette = HrGazette::query()->find($row->getAttribute('gazette_id'), ['name', 'year']);
        $basic = (float) $row->getAttribute('basic');
        $houseRent = round($basic * (float) $row->getAttribute('house_rent_pct') / 100, 2);
        $out['gazette'] = $gazette?->name;
        $out['year'] = $gazette?->year;
        $out['category'] = HrEmploymentType::query()->whereKey($row->getAttribute('employment_type_id'))->value('name');
        $out['grade'] = Grade::query()->whereKey($row->getAttribute('grade_id'))->value('name');
        $out['house_rent'] = $houseRent;
        $out['gross_a'] = round($basic + $houseRent + (float) $row->getAttribute('medical') + (float) $row->getAttribute('conveyance') + (float) $row->getAttribute('food') + (float) $row->getAttribute('other'), 2);

        return $out;
    }
}

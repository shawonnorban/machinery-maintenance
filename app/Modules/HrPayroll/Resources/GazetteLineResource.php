<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Resources;

use App\Modules\HrPayroll\Models\HrEmploymentType;
use App\Modules\HrPayroll\Models\HrGazette;
use App\Modules\HrPayroll\Models\HrGazetteLine;
use App\Modules\HrPayroll\Models\HrPayElement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Wage/Gazette Rule → salary structure lines: what a gazette sets for an
 * employee category and pay element. Basic is the multiplier the gross is
 * divided by, House Rent a percentage of basic, every other head an amount in
 * taka. HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class GazetteLineResource extends HrResource
{
    public function key(): string
    {
        return 'hr-gazette-lines';
    }

    public function model(): string
    {
        return HrGazetteLine::class;
    }

    public function columns(): array
    {
        return ['gazette_id', 'employment_type_id', 'pay_element_id', 'amount', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    public function deletable(): bool
    {
        return true;
    }

    public function query(): Builder
    {
        $this->ensureDefaults();

        return HrGazetteLine::query()
            ->join('hr_gazettes as g', 'g.id', '=', 'hr_gazette_lines.gazette_id')
            ->join('hr_employment_types as t', 't.id', '=', 'hr_gazette_lines.employment_type_id')
            ->orderByDesc('g.effective_date')->orderBy('g.name')->orderByDesc('t.name')->orderBy('hr_gazette_lines.created_at')
            ->select('hr_gazette_lines.*');
    }

    public function messages(): array
    {
        return ['amount.required' => __('hrpayroll.master.amount_required')];
    }

    public function rules(?Model $row): array
    {
        // A list's defaults go in before the first row is checked against them.
        $this->ensureDefaults();
        $required = $row ? 'sometimes' : 'required';

        return [
            'amount' => [$required, 'numeric', 'min:0', 'max:9999999999'],
            'gazette_id' => [$required, $this->own('hr_gazettes')],
            'employment_type_id' => [$required, $this->own('hr_employment_types')],
            'pay_element_id' => [$required, $this->own('hr_pay_elements')],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $key = fn (string $k) => $data[$k] ?? $row?->getAttribute($k);
        $taken = HrGazetteLine::query()->where('gazette_id', $key('gazette_id'))->where('employment_type_id', $key('employment_type_id'))->where('pay_element_id', $key('pay_element_id'))
            ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['pay_element_id' => __('hrpayroll.master.line_taken')]);
        }

        return $data;
    }

    /** What an amount means, by the head it is set for. */
    public static function means(?string $code, float $amount): string
    {
        $n = rtrim(rtrim(number_format($amount, 2, '.', ','), '0'), '.');

        return match ($code) {
            'BASIC' => "Gross ÷ {$n}",
            'HRENT' => "{$n}% of Basic",
            default => "৳{$n}",
        };
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $gazette = HrGazette::query()->find($row->getAttribute('gazette_id'), ['name', 'number', 'year', 'effective_date']);
        $element = HrPayElement::query()->find($row->getAttribute('pay_element_id'), ['code', 'name']);
        $out['gazette'] = $gazette?->name;
        $out['number_year'] = implode(' / ', array_filter([$gazette?->number, $gazette?->year]));
        $out['effective_date'] = $gazette?->effective_date?->format('Y-m-d');
        $out['category'] = HrEmploymentType::query()->whereKey($row->getAttribute('employment_type_id'))->value('name');
        $out['pay_element'] = $element?->name;
        $out['means'] = self::means($element?->code, (float) $row->getAttribute('amount'));

        return $out;
    }
}

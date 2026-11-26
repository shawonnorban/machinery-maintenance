<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Resources;

use App\Modules\Accounting\Models\AccFiscalYear;
use App\Modules\HrPayroll\Models\HrHoliday;
use App\Modules\HrPayroll\Support\HolidayCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Festival Calendar → Holidays: the holiday register attendance reads. A
 * dated holiday is put on the factory calendar straight away; days already on
 * the calendar are never overwritten, and a holiday that has put days there
 * is kept — deactivate it instead. HR & Payroll → Master Data (AnnotechERP
 * 24/01).
 */
class HolidayResource extends HrResource
{
    public function key(): string
    {
        return 'hr-holidays';
    }

    public function model(): string
    {
        return HrHoliday::class;
    }

    public function columns(): array
    {
        return ['fiscal_year_id', 'code', 'name', 'type', 'start_date', 'days', 'recurring', 'calendar_days', 'remarks', 'active'];
    }

    public function booleans(): array
    {
        return ['recurring', 'active'];
    }

    public function defaults(): array
    {
        return ['active' => true, 'days' => 1];
    }

    public function deletable(): bool
    {
        return true;
    }

    public function query(): Builder
    {
        $this->ensureDefaults();

        return HrHoliday::query()->orderByRaw('start_date is null')->orderBy('start_date')->orderBy('code');
    }

    public function messages(): array
    {
        return ['name.required' => __('hrpayroll.master.holiday_name_required')];
    }

    public function rules(?Model $row): array
    {
        // A list's defaults go in before the first row is checked against them.
        $this->ensureDefaults();
        $required = $row ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'code' => [$required, 'string', 'max:32', $this->uniqueText(HrHoliday::class, 'code', $row, __('hrpayroll.master.holiday_taken'))],
            'type' => [$required, Rule::in(HrHoliday::TYPES)],
            'fiscal_year_id' => [$required, $this->own('acc_fiscal_years')],
            'start_date' => ['nullable', 'date'],
            'days' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'recurring' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }
        unset($data['calendar_days']);
        $start = self::date(array_key_exists('start_date', $data) ? $data['start_date'] : $row?->getAttribute('start_date'));
        $year = AccFiscalYear::query()->find($data['fiscal_year_id'] ?? $row?->getAttribute('fiscal_year_id'));
        if ($start && $year && ($start < self::date($year->fy_start) || $start > self::date($year->fy_end))) {
            throw ValidationException::withMessages(['start_date' => __('hrpayroll.master.holiday_outside_year')]);
        }

        return $data;
    }

    public function afterSave(Model $row, bool $created): void
    {
        HolidayCalendar::file($row);
    }

    public function beforeDelete(Model $row): void
    {
        if ((int) $row->getAttribute('calendar_days') > 0) {
            throw ValidationException::withMessages(['id' => __('hrpayroll.master.holiday_on_calendar')]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $start = $row->getAttribute('start_date');
        $out['end_date'] = $start ? $start->copy()->addDays(max(1, (int) $row->getAttribute('days')) - 1)->format('Y-m-d') : null;
        $out['fiscal_year'] = AccFiscalYear::query()->whereKey($row->getAttribute('fiscal_year_id'))->value('name');

        return $out;
    }
}

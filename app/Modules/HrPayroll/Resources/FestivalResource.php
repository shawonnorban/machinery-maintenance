<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Resources;

use App\Modules\Accounting\Models\AccFiscalYear;
use App\Modules\HrPayroll\Models\HrBonusRuleSet;
use App\Modules\HrPayroll\Models\HrFestival;
use App\Modules\HrPayroll\Models\HrHoliday;
use App\Modules\HrPayroll\Support\HolidayCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Festival Calendar → Festivals: Eid-ul-Fitr, Eid-ul-Adha and the rest by
 * year — the date is set for each year, never assumed — the payroll month
 * the bonus is paid with, and the bonus rule set that prices it. "Also add
 * its holiday" files the day in the holiday register and on the attendance
 * calendar. HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class FestivalResource extends HrResource
{
    private bool $addHoliday = false;

    public function key(): string
    {
        return 'hr-festivals';
    }

    public function model(): string
    {
        return HrFestival::class;
    }

    public function columns(): array
    {
        return ['name', 'festival_date', 'year', 'payment_month', 'bonus_rule_set_id', 'bonus_type', 'min_service', 'active', 'eligibility', 'reference', 'holiday_id'];
    }

    public function booleans(): array
    {
        return ['active', 'add_holiday'];
    }

    /** A festival is removed, or marked Inactive once a bonus has been run against it. */
    public function deletable(): bool
    {
        return true;
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    public function query(): Builder
    {
        return HrFestival::query()->orderByDesc('festival_date');
    }

    public function messages(): array
    {
        return ['name.required' => __('hrpayroll.master.festival_name_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'festival_date' => [$required, 'date'],
            'year' => [$required, 'integer', 'min:2000', 'max:2100'],
            'payment_month' => [$required, 'date_format:Y-m'],
            'bonus_rule_set_id' => ['nullable', $this->own('hr_bonus_rule_sets')],
            'bonus_type' => ['nullable', Rule::in(HrFestival::BONUS_TYPES)],
            'min_service' => ['nullable', 'integer', 'min:0', 'max:600'],
            'active' => ['sometimes', 'boolean'],
            'eligibility' => ['nullable', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:2000'],
            'add_holiday' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $this->addHoliday = (bool) ($data['add_holiday'] ?? false);
        unset($data['add_holiday'], $data['holiday_id']);
        $date = self::date($data['festival_date'] ?? $row?->getAttribute('festival_date'));
        $year = (int) ($data['year'] ?? $row?->getAttribute('year'));
        $name = $data['name'] ?? $row?->getAttribute('name');
        if ((int) substr($date, 0, 4) !== $year) {
            throw ValidationException::withMessages(['year' => __('hrpayroll.master.festival_year')]);
        }
        $taken = HrFestival::query()->whereRaw('upper(trim(name)) = ?', [strtoupper(trim((string) $name))])->where('year', $year)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['name' => __('hrpayroll.master.festival_taken')]);
        }
        if ($this->addHoliday && ! ($row?->getAttribute('holiday_id')) && ! AccFiscalYear::query()->where('fy_start', '<=', $date)->where('fy_end', '>=', $date)->exists()) {
            throw ValidationException::withMessages(['add_holiday' => __('hrpayroll.master.festival_no_year')]);
        }

        return $data;
    }

    public function afterSave(Model $row, bool $created): void
    {
        if (! $this->addHoliday || $row->getAttribute('holiday_id')) {
            return;
        }
        $date = $row->getAttribute('festival_date')->format('Y-m-d');
        $base = 'HOL-'.strtoupper(Str::slug((string) $row->getAttribute('name'))).'-'.$row->getAttribute('year');
        $code = substr($base, 0, 32);
        for ($n = 2; HrHoliday::query()->where('code', $code)->exists(); $n++) {
            $code = substr($base, 0, 28).'-'.$n;
        }
        $holiday = HrHoliday::query()->create([
            'company_id' => $row->getAttribute('company_id'),
            'fiscal_year_id' => AccFiscalYear::query()->where('fy_start', '<=', $date)->where('fy_end', '>=', $date)->value('id'),
            'code' => $code,
            'name' => $row->getAttribute('name'),
            'type' => 'GOVT',
            'start_date' => $date,
            'days' => 1,
            'active' => true,
        ]);
        HolidayCalendar::file($holiday);
        $row->forceFill(['holiday_id' => $holiday->getKey()])->saveQuietly();
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['bonus_rule_set'] = $row->getAttribute('bonus_rule_set_id') ? HrBonusRuleSet::query()->whereKey($row->getAttribute('bonus_rule_set_id'))->value('name') : null;
        $out['holiday'] = $row->getAttribute('holiday_id') ? HrHoliday::query()->whereKey($row->getAttribute('holiday_id'))->value('code') : null;

        return $out;
    }
}

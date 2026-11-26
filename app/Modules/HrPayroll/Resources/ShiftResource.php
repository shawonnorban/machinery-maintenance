<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Resources;

use App\Modules\HrPayroll\Models\HrShift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Shift: start and regular end, break, working hours, grace, cross-day and
 * OT settings, effective-dated — what the attendance engine reads for every
 * punch. A shift whose end comes before its start crosses midnight; working
 * hours left blank are the span less the break. HR & Payroll → Master Data
 * (AnnotechERP 24/01).
 */
class ShiftResource extends HrResource
{
    public function key(): string
    {
        return 'hr-shifts';
    }

    public function model(): string
    {
        return HrShift::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'start_time', 'end_time', 'break_start', 'break_min', 'working_hours', 'grace_min', 'cross_day', 'ot_allowed', 'ot_approval', 'effective_date', 'active', 'remarks'];
    }

    public function booleans(): array
    {
        return ['cross_day', 'ot_allowed', 'ot_approval', 'active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    public function query(): Builder
    {
        $this->ensureDefaults();

        return HrShift::query()->orderBy('start_time');
    }

    public function messages(): array
    {
        return ['code.required' => __('hrpayroll.master.shift_code_required'), 'start_time.date_format' => __('hrpayroll.master.time_format'), 'end_time.date_format' => __('hrpayroll.master.time_format'), 'break_start.date_format' => __('hrpayroll.master.time_format')];
    }

    public function rules(?Model $row): array
    {
        // A list's defaults go in before the first row is checked against them.
        $this->ensureDefaults();
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:16', $this->uniqueText(HrShift::class, 'code', $row, __('hrpayroll.master.shift_taken'))],
            'name' => [$required, 'string', 'max:255'],
            'start_time' => [$required, 'date_format:H:i'],
            'end_time' => [$required, 'date_format:H:i'],
            'break_start' => ['nullable', 'date_format:H:i'],
            'break_min' => ['nullable', 'integer', 'min:0', 'max:720'],
            'working_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'grace_min' => ['nullable', 'integer', 'min:0', 'max:240'],
            'cross_day' => ['sometimes', 'boolean'],
            'ot_allowed' => ['sometimes', 'boolean'],
            'ot_approval' => ['sometimes', 'boolean'],
            'effective_date' => ['nullable', 'date'],
            'active' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }
        $start = $data['start_time'] ?? $row?->getAttribute('start_time');
        $end = $data['end_time'] ?? $row?->getAttribute('end_time');
        // Set automatically when the end is before the start (22:00 – 06:00).
        $data['cross_day'] = $end < $start;
        $break = (int) ($data['break_min'] ?? $row?->getAttribute('break_min') ?? 0);
        if ((array_key_exists('working_hours', $data) && $data['working_hours'] === null) || (! $row && ! isset($data['working_hours']))) {
            [$sh, $sm] = array_map('intval', explode(':', $start));
            [$eh, $em] = array_map('intval', explode(':', $end));
            $span = ($eh * 60 + $em) - ($sh * 60 + $sm);
            $span += $span <= 0 ? 1440 : 0;
            $data['working_hours'] = round(max(0, $span - $break) / 60, 2);
        }

        return $data;
    }
}

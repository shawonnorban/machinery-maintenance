<?php

declare(strict_types=1);

namespace App\Modules\Planning\Resources;

use App\Modules\HrPayroll\Models\HrShift;
use App\Modules\Planning\Models\PlanningLineShift;
use App\Modules\Planning\Support\PlanningLabels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Shift Master: the shifts each planning line runs — with its manpower and
 * target. The shift itself is HR & Payroll's (Shift Master, the one source of
 * shift times, user's decision of 2026-11-15): its name, time and break are
 * copied from there on every save. A shift that ends before it starts runs
 * past midnight. Capacity Planning → Master Data (AnnotechERP 12/01).
 */
class LineShiftResource extends PlanningMasterResource
{
    public function key(): string
    {
        return 'planning-line-shifts';
    }

    public function model(): string
    {
        return PlanningLineShift::class;
    }

    public function columns(): array
    {
        return ['planning_line_id', 'hr_shift_id', 'shift_name', 'start_time', 'end_time', 'break_minutes', 'manpower', 'target', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true, 'break_minutes' => 0];
    }

    public function query(): Builder
    {
        return PlanningLineShift::query()->orderBy('planning_line_id')->orderBy('start_time');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $line = request()->input('planning_line_id', $row?->getAttribute('planning_line_id'));

        return [
            'planning_line_id' => [$required, Rule::exists('planning_lines', 'id')->where('company_id', $this->company())],
            'hr_shift_id' => [$required, Rule::exists('hr_shifts', 'id')->where('company_id', $this->company())->where('active', true),
                Rule::unique('planning_line_shifts', 'hr_shift_id')->where('planning_line_id', $line)->ignore($row?->getKey())],
            'manpower' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'target' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        // The shift's own facts come from HR & Payroll, never from the form.
        unset($data['shift_name'], $data['start_time'], $data['end_time'], $data['break_minutes']);
        $shiftId = $data['hr_shift_id'] ?? $row?->getAttribute('hr_shift_id');
        if ($shiftId !== null) {
            $shift = HrShift::query()->findOrFail($shiftId);
            $data += [
                'shift_name' => $shift->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'break_minutes' => (int) ($shift->break_min ?? 0),
            ];
        }
        $s = array_merge($row ? ['start_time' => substr((string) $row->getAttribute('start_time'), 0, 5), 'end_time' => substr((string) $row->getAttribute('end_time'), 0, 5), 'break_minutes' => $row->getAttribute('break_minutes')] : [], $data);
        if (self::minutes($s['start_time'], $s['end_time']) - (int) ($s['break_minutes'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['break_minutes' => __('planning.master.shift_too_short')]);
        }

        return $data;
    }

    /** Minutes from start to end, past midnight when the end is earlier. */
    public static function minutes(string $start, string $end): int
    {
        [$sh, $sm] = array_map('intval', explode(':', substr($start, 0, 5)));
        [$eh, $em] = array_map('intval', explode(':', substr($end, 0, 5)));
        $span = ($eh * 60 + $em) - ($sh * 60 + $sm);

        return $span <= 0 ? $span + 1440 : $span;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $start = substr((string) $row->getAttribute('start_time'), 0, 5);
        $end = substr((string) $row->getAttribute('end_time'), 0, 5);
        $out['start_time'] = $start;
        $out['end_time'] = $end;
        $out['time'] = "{$start}–{$end}";
        $span = round((self::minutes($start, $end) - (int) $row->getAttribute('break_minutes')) / 60, 2);
        // HR's regular hours: the rest of a long night is overtime.
        $regular = $row->getAttribute('hr_shift_id') ? HrShift::query()->whereKey($row->getAttribute('hr_shift_id'))->value('working_hours') : null;
        $out['working_hours'] = $regular === null ? $span : min($span, (float) $regular);
        $labels = PlanningLabels::forLine($row->getAttribute('planning_line_id'));
        $out['floor'] = $labels['floor'];
        $out['line'] = $labels['floor_line'];
        $out['planning_line'] = $labels['line'];

        return $out;
    }
}

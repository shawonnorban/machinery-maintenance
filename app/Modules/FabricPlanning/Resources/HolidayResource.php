<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Resources;

use App\Modules\FabricPlanning\Models\FpHoliday;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Factory Calendar & Holidays → Records and New factory holiday: a day a unit
 * (Brickland's Office) does not run — for one section, or every section when
 * none is named. A working day is simply one with no row. The Company box is
 * the signed-in company, asked for as Brickland asks for it. Fabric
 * Requirement → Operations (AnnotechERP 13/02).
 */
class HolidayResource extends FabricOpsResource
{
    public function key(): string
    {
        return 'fp-holidays';
    }

    public function model(): string
    {
        return FpHoliday::class;
    }

    public function columns(): array
    {
        return ['section_id', 'factory_id', 'day', 'day_type', 'name'];
    }

    public function query(): Builder
    {
        return FpHoliday::query()->orderByDesc('day');
    }

    public function messages(): array
    {
        return [
            'company.required' => __('fabricplanning.ops.v.company'),
            'factory_id.required' => __('fabricplanning.ops.v.office'),
            'day.required' => __('fabricplanning.ops.v.day'),
            'day_type.required' => __('fabricplanning.ops.v.day_type'),
        ];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'company' => [$required, Rule::in([$this->company()])],
            'section_id' => ['nullable', Rule::exists('departments', 'id')->where('company_id', $this->company())],
            'factory_id' => [$required, Rule::exists('factories', 'id')->where('company_id', $this->company())],
            'day' => [$required, 'date'],
            'day_type' => [$required, Rule::in(FpHoliday::TYPES)],
            'name' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** One row per unit, section and day. */
    public function beforeSave(array $data, ?Model $row): array
    {
        unset($data['company']);
        $n = array_merge($row?->only(['section_id', 'factory_id']) ?? [], $data);
        $day = $data['day'] ?? $row?->getAttribute('day')?->toDateString();
        $taken = FpHoliday::query()->where('factory_id', $n['factory_id'])->whereDate('day', $day)
            ->where(fn ($q) => ($n['section_id'] ?? null) === null ? $q->whereNull('section_id') : $q->where('section_id', $n['section_id']))
            ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['day' => __('fabricplanning.ops.v.day_taken')]);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['company'] = $this->company();

        return $out;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Resources;

use App\Modules\Knitting\Models\KnitCapacityPlan;
use App\Modules\Knitting\Support\KnittingLabels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Machine Capacity Hour Plan: a machine's declared kilograms per running hour
 * and the hours, days and efficiency its loading is planned on, for a span of
 * days. Bands do not overlap — one machine has one plan for any given day; a
 * band with no end is the plan in force until another starts. Knit Production
 * → Master Data (AnnotechERP 15/01).
 */
class CapacityPlanResource extends KnittingResource
{
    public function key(): string
    {
        return 'knit-capacity-plans';
    }

    public function model(): string
    {
        return KnitCapacityPlan::class;
    }

    public function columns(): array
    {
        return ['machine_id', 'shift_id', 'effective_from', 'effective_to', 'plan_hours', 'running_days', 'efficiency', 'capacity_per_hour', 'remarks', 'active'];
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
        return KnitCapacityPlan::query()->orderByDesc('effective_from');
    }

    public function messages(): array
    {
        return ['effective_from.required' => __('knitting.master.effective_from_required'), 'machine_id.required' => __('knitting.master.machine_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();

        return [
            'machine_id' => [$required, Rule::exists('knit_machines', 'id')->where('company_id', $company)],
            'shift_id' => ['nullable', Rule::exists('hr_shifts', 'id')->where('company_id', $company)],
            'effective_from' => [$required, 'date'],
            'effective_to' => ['nullable', 'date'],
            'plan_hours' => [$required, 'numeric', 'gt:0', 'max:24'],
            'running_days' => [$required, 'integer', 'min:1', 'max:31'],
            'efficiency' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'capacity_per_hour' => [$required, 'numeric', 'gt:0', 'max:99999'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $p = array_merge($row ? ['machine_id' => $row->machine_id, 'effective_from' => $row->effective_from?->toDateString(), 'effective_to' => $row->effective_to?->toDateString(), 'active' => $row->active] : ['active' => true], $data);
        $p['effective_to'] ??= null;
        if ($p['effective_to'] && $p['effective_to'] < $p['effective_from']) {
            throw ValidationException::withMessages(['effective_to' => __('knitting.master.to_before_from')]);
        }
        if ($p['active'] ?? true) {
            $clash = KnitCapacityPlan::query()->where('machine_id', $p['machine_id'])->where('active', true)
                ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $p['effective_from']))
                ->when($p['effective_to'], fn ($q) => $q->where('effective_from', '<=', $p['effective_to']))
                ->exists();
            if ($clash) {
                throw ValidationException::withMessages(['effective_from' => __('knitting.master.band_overlaps')]);
            }
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $machine = KnittingLabels::machines()[$row->getAttribute('machine_id')] ?? null;
        $out['machine_no'] = $machine['no'] ?? null;
        $out['floor'] = $machine ? (KnittingLabels::floors()[$machine['floor_id']] ?? null) : null;
        $out['shift'] = KnittingLabels::shifts()[$row->getAttribute('shift_id')] ?? null;
        $efficiency = $row->getAttribute('efficiency') === null ? 100 : (float) $row->getAttribute('efficiency');
        $out['plan_qty_month'] = round((float) $row->getAttribute('plan_hours') * (int) $row->getAttribute('running_days') * (float) $row->getAttribute('capacity_per_hour') * $efficiency / 100, 2);

        return $out;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\FabricPlanning\Resources;

use App\Modules\DyeHouse\Models\DyeColourGroup;
use App\Modules\FabricPlanning\Models\FpBatchHour;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Standard Batch Hour Norms: the hours a dye batch is planned to take, by
 * fiber combination template, buyer category and dyeing method. One norm per
 * combination. Fabric Requirement → Master Data (AnnotechERP 13/01).
 */
class BatchHourResource extends FabricPlanningResource
{
    public function key(): string
    {
        return 'fp-batch-hours';
    }

    public function model(): string
    {
        return FpBatchHour::class;
    }

    public function columns(): array
    {
        return ['colour_group_id', 'fiber_template', 'buyer_category', 'dyeing_method', 'batch_hours'];
    }

    public function booleans(): array
    {
        return ['any_template', 'any_category'];
    }

    protected function anyBoxes(): array
    {
        return ['fiber_template' => 'any_template', 'buyer_category' => 'any_category'];
    }

    public function query(): Builder
    {
        return FpBatchHour::query()->orderBy('dyeing_method')->orderBy('fiber_template');
    }

    public function messages(): array
    {
        return ['batch_hours.gt' => __('fabricplanning.master.hours_positive'), 'batch_hours.required' => __('fabricplanning.master.hours_positive')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'colour_group_id' => [$required, Rule::exists('dye_colour_groups', 'id')->where('company_id', $this->company())],
            'fiber_template' => ['nullable', Rule::in(FpBatchHour::TEMPLATES)],
            'any_template' => ['sometimes', 'boolean'],
            'buyer_category' => ['nullable', Rule::in(FpBatchHour::BUYER_CATEGORIES)],
            'any_category' => ['sometimes', 'boolean'],
            'dyeing_method' => [$required, Rule::in(FpBatchHour::METHODS)],
            'batch_hours' => [$required, 'numeric', 'gt:0', 'max:999'],
        ];
    }

    /** One norm per template, category and method. */
    public function beforeSave(array $data, ?Model $row): array
    {
        $data = $this->applyAny($data, $row);
        $n = array_merge($row?->only(['fiber_template', 'buyer_category', 'dyeing_method']) ?? [], $data);
        $taken = FpBatchHour::query()
            ->where(fn ($q) => $n['fiber_template'] === null ? $q->whereNull('fiber_template') : $q->where('fiber_template', $n['fiber_template']))
            ->where(fn ($q) => $n['buyer_category'] === null ? $q->whereNull('buyer_category') : $q->where('buyer_category', $n['buyer_category']))
            ->where('dyeing_method', $n['dyeing_method'])
            ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages(['dyeing_method' => __('fabricplanning.master.norm_taken')]);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['colour_group'] = $row->getAttribute('colour_group_id') ? DyeColourGroup::query()->whereKey($row->getAttribute('colour_group_id'))->value('name') : null;

        return $out;
    }
}

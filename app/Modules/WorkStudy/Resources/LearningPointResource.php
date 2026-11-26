<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\WorkStudy\Models\WsLearningPoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Learning Curve Model: the efficiency a line is expected to reach on each day
 * of a style run, by fabric class and product criticality — one row per day,
 * so a fourteen-day curve needs no new columns. Work Study → Master Data
 * (AnnotechERP 14/01).
 */
class LearningPointResource extends WorkStudyResource
{
    public function key(): string
    {
        return 'ws-learning-points';
    }

    /** Brickland removes a row here; one still in use is refused (deactivate it instead). */
    public function deletable(): bool
    {
        return true;
    }

    public function model(): string
    {
        return WsLearningPoint::class;
    }

    public function columns(): array
    {
        return ['fabric_class', 'prod_type', 'day', 'efficiency'];
    }

    public function query(): Builder
    {
        return WsLearningPoint::query()->orderBy('fabric_class')->orderBy('prod_type')->orderBy('day');
    }

    public function messages(): array
    {
        return ['efficiency.required' => __('workstudy.master.efficiency_required'), 'day.unique' => __('workstudy.master.day_taken'), 'efficiency.max' => __('workstudy.master.efficiency_max')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $class = request()->input('fabric_class', $row?->getAttribute('fabric_class'));
        $type = request()->input('prod_type', $row?->getAttribute('prod_type'));

        return [
            'fabric_class' => [$required, Rule::in(WsLearningPoint::FABRIC_CLASSES)],
            'prod_type' => [$required, Rule::in(WsLearningPoint::PROD_TYPES)],
            'day' => [$required, 'integer', 'min:1', 'max:365', Rule::unique('ws_learning_points', 'day')->where('company_id', $this->company())->where('fabric_class', $class)->where('prod_type', $type)->ignore($row?->getKey())],
            'efficiency' => [$required, 'numeric', 'min:0', 'max:100'],
        ];
    }
}

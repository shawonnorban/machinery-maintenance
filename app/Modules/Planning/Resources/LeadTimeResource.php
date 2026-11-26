<?php

declare(strict_types=1);

namespace App\Modules\Planning\Resources;

use App\Modules\Planning\Models\PlanningLeadTime;
use App\Modules\Planning\Support\PlanningDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Other Setup: the default lead time of each activity a new master plan
 * starts from, and what it is counted from. The activities are fixed; only
 * their days, basis and remarks are edited. Capacity Planning → Master Data
 * (AnnotechERP 12/01).
 */
class LeadTimeResource extends PlanningMasterResource
{
    public function key(): string
    {
        return 'planning-lead-times';
    }

    public function model(): string
    {
        return PlanningLeadTime::class;
    }

    public function columns(): array
    {
        return ['activity', 'lead_days', 'counted_from', 'remarks', 'sort_order', 'changed'];
    }

    public function deletable(): bool
    {
        return false;
    }

    public function query(): Builder
    {
        PlanningDefaults::leadTimes($this->company());

        return PlanningLeadTime::query()->orderBy('sort_order');
    }

    public function rules(?Model $row): array
    {
        return [
            // The activities are the module's: a new row is refused outright,
            // and an edit cannot rename one.
            'activity' => $row ? ['prohibited'] : ['required', 'prohibited'],
            'lead_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'counted_from' => ['sometimes', Rule::in(PlanningLeadTime::COUNTED_FROM)],
            'remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $data['changed'] = true;

        return $data;
    }
}

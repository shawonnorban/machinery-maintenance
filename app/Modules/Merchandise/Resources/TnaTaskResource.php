<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\TnaTask;
use App\Modules\Merchandise\Models\TnaTaskGroup;
use App\Modules\Merchandise\Models\TnaTemplateTask;
use App\Modules\Merchandise\Support\MerchandiseDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Task & Action Register: the T&A milestones templates are built from. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class TnaTaskResource extends MasterResource
{
    public function key(): string
    {
        return 'tna-tasks';
    }

    public function model(): string
    {
        return TnaTask::class;
    }

    public function columns(): array
    {
        return ['tna_task_group_id', 'code', 'sort_order', 'name', 'name_bn', 'source_module', 'offset_days', 'is_sample', 'is_start', 'is_end', 'is_production', 'active'];
    }

    public function booleans(): array
    {
        return ['active', 'is_sample', 'is_start', 'is_end', 'is_production'];
    }

    public function query(): Builder
    {
        MerchandiseDefaults::ensureTna($this->company());

        return TnaTask::query()->orderBy('sort_order')->orderBy('name');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'tna_task_group_id' => ['nullable', Rule::exists('tna_task_groups', 'id')->where('company_id', $this->company())],
            'code' => [$required, 'string', 'max:16', $this->uniqueCode($row)],
            'sort_order' => [$required, 'integer', 'min:0', 'max:9999'],
            'name' => [$required, 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'source_module' => ['nullable', 'string', 'max:32'],
            'offset_days' => ['nullable', 'integer', 'min:-999', 'max:999'],
            'is_sample' => ['sometimes', 'boolean'],
            'is_start' => ['sometimes', 'boolean'],
            'is_end' => ['sometimes', 'boolean'],
            'is_production' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['group'] = $row->getAttribute('tna_task_group_id') ? TnaTaskGroup::query()->whereKey($row->getAttribute('tna_task_group_id'))->value('name') : null;

        return $out;
    }

    /** A task a template's calendar still uses stays; switch it off instead. */
    public function beforeDelete(Model $row): void
    {
        if (TnaTemplateTask::query()->where('tna_task_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['id' => __('merchandise.master.in_use')]);
        }
    }
}

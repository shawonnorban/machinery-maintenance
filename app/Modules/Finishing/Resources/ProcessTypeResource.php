<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Resources;

use App\Modules\Finishing\Models\FinProcessGroup;
use App\Modules\Finishing\Models\FinProcessSubgroup;
use App\Modules\Finishing\Models\FinProcessType;
use App\Modules\Finishing\Support\FinishingDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Finishing Process Types: the processes a batch may be booked against, the
 * group and sub-group each is filed under, the one it normally follows, its
 * rate and the loss it may book unexplained. A process cannot link to itself;
 * one in use is deactivated, never deleted. Finishing Line → Master Data
 * (AnnotechERP 18/01).
 */
class ProcessTypeResource extends FinishingResource
{
    public function key(): string
    {
        return 'fin-process-types';
    }

    /** Brickland removes a row here; one still in use is refused (deactivate it instead). */
    public function deletable(): bool
    {
        return true;
    }

    public function model(): string
    {
        return FinProcessType::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'short_name', 'group_id', 'subgroup_id', 'link_process_id', 'wip_stage', 'rate', 'loss_pct', 'display_order', 'active'];
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
        FinishingDefaults::ensure($this->company());

        return FinProcessType::query()->orderBy('display_order')->orderBy('code');
    }

    public function messages(): array
    {
        return ['group_id.required' => __('finishing.master.group_required'), 'code.regex' => __('finishing.master.code_format')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();

        return [
            'code' => [$required, 'string', 'max:24', 'regex:/^[A-Za-z0-9._-]+$/', $this->uniqueText(FinProcessType::class, 'code', $row, __('finishing.master.code_taken'))],
            'name' => [$required, 'string', 'max:255'],
            'short_name' => [$required, 'string', 'max:16'],
            'group_id' => [$required, Rule::exists('fin_process_groups', 'id')->where('company_id', $company)],
            'subgroup_id' => ['nullable', Rule::exists('fin_process_subgroups', 'id')->where('company_id', $company)],
            'link_process_id' => ['nullable', Rule::exists('fin_process_types', 'id')->where('company_id', $company)],
            'wip_stage' => [$required, Rule::in(FinProcessType::STAGES)],
            'rate' => [$required, 'numeric', 'min:0', 'max:99999999'],
            'loss_pct' => [$required, 'numeric', 'min:0', 'max:100'],
            'display_order' => [$required, 'integer', 'min:0', 'max:9999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }
        $p = array_merge($row?->only(['group_id', 'subgroup_id', 'link_process_id']) ?? [], $data);
        if (! empty($p['subgroup_id']) && FinProcessSubgroup::query()->whereKey($p['subgroup_id'])->value('group_id') !== $p['group_id']) {
            throw ValidationException::withMessages(['subgroup_id' => __('finishing.master.subgroup_other_group')]);
        }
        if ($row && ! empty($p['link_process_id']) && $p['link_process_id'] === $row->getKey()) {
            throw ValidationException::withMessages(['link_process_id' => __('finishing.master.link_self')]);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['group'] = FinProcessGroup::query()->whereKey($row->getAttribute('group_id'))->value('name');
        $out['subgroup'] = $row->getAttribute('subgroup_id') ? FinProcessSubgroup::query()->whereKey($row->getAttribute('subgroup_id'))->value('name') : null;
        $out['link_process'] = $row->getAttribute('link_process_id') ? FinProcessType::query()->whereKey($row->getAttribute('link_process_id'))->value('name') : null;

        return $out;
    }
}

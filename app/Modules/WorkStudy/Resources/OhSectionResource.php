<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\WorkStudy\Models\WsOhHead;
use App\Modules\WorkStudy\Models\WsOhSection;
use App\Modules\WorkStudy\Support\WsCosting;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Overhead Cost Allocation → Section Cost Heads → New Section: an overhead
 * section with its Bangla name and place, and whether money may be
 * re-arranged into it (an allocation target). A section with heads is not
 * removed. Work Study → Costing (AnnotechERP 14/05).
 */
class OhSectionResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'ws-oh-sections';
    }

    public function model(): string
    {
        return WsOhSection::class;
    }

    public function viewPermission(): string
    {
        return 'workstudy.costing.view';
    }

    public function managePermission(): string
    {
        return 'workstudy.costing.manage';
    }

    public function columns(): array
    {
        return ['name', 'name_bn', 'sort_order', 'is_target'];
    }

    public function booleans(): array
    {
        return ['is_target'];
    }

    public function query(): Builder
    {
        WsCosting::ensure(app(TenantContext::class)->companyId());

        return WsOhSection::query()->orderBy('sort_order')->orderBy('name');
    }

    public function messages(): array
    {
        return ['name.required' => __('workstudy.cost.v.section'), 'name.unique' => __('workstudy.cost.v.section_taken')];
    }

    public function rules(?Model $row): array
    {
        $company = app(TenantContext::class)->companyId();

        return [
            'name' => [$row ? 'sometimes' : 'required', 'string', 'max:80', Rule::unique('ws_oh_sections', 'name')->where('company_id', $company)->ignore($row?->getKey())],
            'name_bn' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_target' => ['boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (isset($data['name'])) {
            $data['name'] = trim((string) $data['name']);
        }
        if (! $row && empty($data['sort_order'])) {
            $data['sort_order'] = (int) WsOhSection::query()->max('sort_order') + 1;
        }

        return $data;
    }

    public function beforeDelete(Model $row): void
    {
        if (WsOhHead::query()->where('section_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['id' => __('workstudy.cost.v.section_in_use')]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['heads'] = WsOhHead::query()->where('section_id', $row->getKey())->count();

        return $out;
    }
}

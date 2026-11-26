<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\WorkStudy\Models\WsGarmentItem;
use App\Modules\WorkStudy\Models\WsItemGroup;
use App\Modules\WorkStudy\Support\WsSpecs;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Garment Item Specification → Itm Group & Category → New Itm Group: a
 * garment item group, its code made from its name (GRP-KIDS). Renaming a group
 * carries its categories along; a group with categories is not removed.
 * Work Study → Item Specification (AnnotechERP 14/04).
 */
class ItemGroupResource extends ResourceDefinition
{
    /** The name a group had before an edit renames it. */
    private ?string $renamedFrom = null;

    public function key(): string
    {
        return 'ws-item-groups';
    }

    public function model(): string
    {
        return WsItemGroup::class;
    }

    public function viewPermission(): string
    {
        return 'workstudy.spec.view';
    }

    public function managePermission(): string
    {
        return 'workstudy.spec.manage';
    }

    public function columns(): array
    {
        return ['name', 'code', 'sort_order'];
    }

    public function query(): Builder
    {
        WsSpecs::ensure(app(TenantContext::class)->companyId());

        return WsItemGroup::query()->orderBy('sort_order')->orderBy('name');
    }

    public function messages(): array
    {
        return ['name.required' => __('workstudy.spec.v.group_name'), 'name.unique' => __('workstudy.spec.v.group_taken')];
    }

    public function rules(?Model $row): array
    {
        $company = app(TenantContext::class)->companyId();

        return [
            'name' => [$row ? 'sometimes' : 'required', 'string', 'max:80', Rule::unique('ws_item_groups', 'name')->where('company_id', $company)->ignore($row?->getKey())],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        unset($data['code']);
        if (isset($data['name'])) {
            $data['name'] = trim((string) $data['name']);
        }
        $this->renamedFrom = $row && isset($data['name']) && $data['name'] !== $row->getAttribute('name') ? $row->getAttribute('name') : null;
        if (! $row) {
            $data['code'] = WsSpecs::uniqueCode('GRP', $data['name'], fn ($c) => WsItemGroup::query()->where('code', $c)->exists());
            $data['sort_order'] ??= (int) WsItemGroup::query()->max('sort_order') + 10;
        }

        return $data;
    }

    /** A renamed group takes its categories with it. */
    public function afterSave(Model $row, bool $created): void
    {
        if (! $created && $this->renamedFrom !== null) {
            WsGarmentItem::query()->where('item_group', $this->renamedFrom)->update(['item_group' => $row->getAttribute('name')]);
        }
    }

    public function beforeDelete(Model $row): void
    {
        if (WsGarmentItem::query()->where('item_group', $row->getAttribute('name'))->exists()) {
            throw ValidationException::withMessages(['id' => __('workstudy.spec.v.group_in_use')]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['categories'] = WsGarmentItem::query()->where('item_group', $row->getAttribute('name'))->count();

        return $out;
    }
}

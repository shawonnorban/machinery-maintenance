<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Resources;

use App\Modules\GeneralStore\Models\GsCategory;
use App\Modules\GeneralStore\Models\GsItem;
use App\Modules\GeneralStore\Support\GeneralStoreDefaults;
use App\Modules\ItemMaster\Models\ItemCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Item Category: stationery, electrical, hardware, safety, cleaning … with how
 * many items sit in each. General Store → Master Data (AnnotechERP 10/01).
 */
class StoreCategoryResource extends StoreMasterResource
{
    public function key(): string
    {
        return 'store-categories';
    }

    public function model(): string
    {
        return GsCategory::class;
    }

    public function columns(): array
    {
        return ['parent_id', 'code', 'name', 'name_bn', 'description', 'active'];
    }

    public function query(): Builder
    {
        GeneralStoreDefaults::categories($this->company());

        return parent::query();
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:32', $this->uniqueCode($row)],
            // The one category tree (user, 2026-11-15): category › sub-category › detail.
            'parent_id' => ['nullable', Rule::exists('item_categories', 'id')->where('company_id', $this->company())],
            'name' => [$required, 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /** Three levels and no loops, as the item forms pick category, sub-category and detail. */
    public function beforeSave(array $data, ?Model $row): array
    {
        $parentId = $data['parent_id'] ?? null;
        if ($parentId === null || $parentId === '') {
            return $data;
        }
        if ($row !== null && $parentId === $row->getKey()) {
            throw ValidationException::withMessages(['parent_id' => __('masterdata.self_parent')]);
        }
        $parent = ItemCategory::query()->find($parentId);
        $self = $row !== null ? ItemCategory::query()->find($row->getKey()) : null;
        if ($parent !== null && $self !== null && in_array($self->getKey(), $parent->ancestorIds(), true)) {
            throw ValidationException::withMessages(['parent_id' => __('masterdata.hierarchy_cycle')]);
        }
        if ($parent !== null && $parent->depth() + ($self?->subtreeDepth() ?? 1) > ItemCategory::MAX_DEPTH) {
            throw ValidationException::withMessages(['parent_id' => __('masterdata.hierarchy_too_deep', ['levels' => ItemCategory::MAX_DEPTH])]);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['items'] = GsItem::query()->where('gs_category_id', $row->getKey())->count();
        $out['parent'] = $row->getAttribute('parent_id') ? GsCategory::query()->whereKey($row->getAttribute('parent_id'))->value('name') : null;

        return $out;
    }
}

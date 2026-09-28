<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemCategory;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ItemCategoryData extends MasterDataType
{
    public function key(): string
    {
        return 'item-categories';
    }

    public function model(): string
    {
        return ItemCategory::class;
    }

    public function group(): string
    {
        return 'inventory';
    }

    /** A company's own store layout; the platform seeds none. */
    public function sharedWithPlatform(): bool
    {
        return false;
    }

    public function fields(): array
    {
        return [
            Field::reference('parent_id', 'item-categories', required: false),
            Field::text('name'),
            Field::text('name_bn', required: false, inList: false),
            Field::code(),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [
            Item::class => ['category_id', 'sub_category_id', 'detail_category_id'],
            ItemCategory::class => 'parent_id',
        ];
    }

    /**
     * Three levels and no loops: the item form shows category, sub-category
     * and detail category as three cascading pickers, and a fourth level or a
     * parent that is its own descendant has nowhere to go.
     */
    public function validateRow(array $data, ?Model $existing): void
    {
        $parentId = $data['parent_id'] ?? null;

        if ($parentId === null) {
            return;
        }

        if ($existing !== null && $parentId === $existing->getKey()) {
            throw ValidationException::withMessages(['parent_id' => __('masterdata.self_parent')]);
        }

        /** @var ItemCategory|null $parent */
        $parent = ItemCategory::query()->find($parentId);

        if ($parent === null) {
            return;
        }

        if ($existing !== null && in_array($existing->getKey(), $parent->ancestorIds(), true)) {
            throw ValidationException::withMessages(['parent_id' => __('masterdata.hierarchy_cycle')]);
        }

        // The row's own subtree keeps its depth when re-parented, so the
        // deepest descendant is what has to fit under the new parent.
        $subtreeDepth = $existing instanceof ItemCategory ? $existing->subtreeDepth() : 1;

        if ($parent->depth() + $subtreeDepth > ItemCategory::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'parent_id' => __('masterdata.hierarchy_too_deep', ['levels' => ItemCategory::MAX_DEPTH]),
            ]);
        }
    }
}

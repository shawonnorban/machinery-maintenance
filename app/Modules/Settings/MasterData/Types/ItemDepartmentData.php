<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;

class ItemDepartmentData extends MasterDataType
{
    public function key(): string
    {
        return 'item-departments';
    }

    public function model(): string
    {
        return ItemDepartment::class;
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
            Field::text('name'),
            Field::text('name_bn', required: false, inList: false),
            Field::code(32),
            // What a new item filed under this department defaults to.
            Field::enum('default_item_type', Item::ITEM_TYPES, required: false),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [Item::class => 'item_department_id'];
    }
}

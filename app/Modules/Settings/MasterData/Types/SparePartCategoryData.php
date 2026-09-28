<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\SparePartCategory;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;

class SparePartCategoryData extends MasterDataType
{
    public function key(): string
    {
        return 'spare-part-categories';
    }

    public function model(): string
    {
        return SparePartCategory::class;
    }

    public function group(): string
    {
        return 'inventory';
    }

    public function fields(): array
    {
        return [
            Field::text('name'),
            Field::text('name_bn', required: false, inList: false),
            Field::code(),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [SparePart::class => 'category_id'];
    }
}

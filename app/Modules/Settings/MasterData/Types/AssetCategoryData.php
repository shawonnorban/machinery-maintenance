<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;

class AssetCategoryData extends MasterDataType
{
    public function key(): string
    {
        return 'asset-categories';
    }

    public function model(): string
    {
        return AssetCategory::class;
    }

    public function group(): string
    {
        return 'asset';
    }

    public function fields(): array
    {
        return [
            Field::reference('asset_type_id', 'asset-types'),
            Field::text('name'),
            Field::code(),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [Asset::class => 'asset_category_id'];
    }
}

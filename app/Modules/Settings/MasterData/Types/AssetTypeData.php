<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetModel;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;

class AssetTypeData extends MasterDataType
{
    public function key(): string
    {
        return 'asset-types';
    }

    public function model(): string
    {
        return AssetType::class;
    }

    public function group(): string
    {
        return 'asset';
    }

    public function fields(): array
    {
        return [
            Field::text('name'),
            Field::code(),
            // The default a new asset of this type starts at. A sewing machine
            // and a boiler are not equally urgent when they stop.
            Field::enum('default_criticality', Asset::CRITICALITIES),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [
            Asset::class => 'asset_type_id',
            AssetCategory::class => 'asset_type_id',
            AssetModel::class => 'asset_type_id',
        ];
    }
}

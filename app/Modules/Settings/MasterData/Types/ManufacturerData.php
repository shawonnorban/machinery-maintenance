<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetModel;
use App\Modules\Asset\Models\Manufacturer;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;

class ManufacturerData extends MasterDataType
{
    public function key(): string
    {
        return 'manufacturers';
    }

    public function model(): string
    {
        return Manufacturer::class;
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
            Field::text('country', required: false, max: 2),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [
            Asset::class => 'manufacturer_id',
            AssetModel::class => 'manufacturer_id',
        ];
    }
}

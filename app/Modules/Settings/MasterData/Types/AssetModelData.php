<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetModel;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;

class AssetModelData extends MasterDataType
{
    public function key(): string
    {
        return 'asset-models';
    }

    public function model(): string
    {
        return AssetModel::class;
    }

    public function group(): string
    {
        return 'asset';
    }

    /** The nameplate says "model", so the screen does too. */
    public function displayColumn(): string
    {
        return 'model';
    }

    public function fields(): array
    {
        return [
            Field::reference('manufacturer_id', 'manufacturers'),
            Field::reference('asset_type_id', 'asset-types'),
            Field::text('model'),
            Field::code(64),
            Field::boolean('active'),
        ];
    }

    public function usedBy(): array
    {
        return [Asset::class => 'asset_model_id'];
    }
}

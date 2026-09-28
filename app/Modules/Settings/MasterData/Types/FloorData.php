<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use App\Modules\Tenancy\Models\Floor;

class FloorData extends MasterDataType
{
    public function key(): string
    {
        return 'floors';
    }

    public function model(): string
    {
        return Floor::class;
    }

    public function group(): string
    {
        return 'organisation';
    }

    public function sharedWithPlatform(): bool
    {
        return false;
    }

    public function supportsActive(): bool
    {
        return false;
    }

    public function fields(): array
    {
        return [
            Field::reference('building_id', 'buildings'),
            Field::text('name'),
            Field::code(),
        ];
    }

    public function usedBy(): array
    {
        return [
            AssetLocation::class => 'floor_id',
        ];
    }
}

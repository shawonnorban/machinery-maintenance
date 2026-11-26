<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\Tenancy\Models\Section;

/**
 * Company Management's Floor tab (AnnotechERP 01/02). A floor needs its
 * building ("Building is required."); capacity is the headcount it holds.
 */
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
            Field::code(auto: true),
            Field::text('name'),
            Field::number('capacity', required: false),
            Field::status(),
        ];
    }

    public function autoCodePrefix(): ?string
    {
        return 'FLR';
    }

    public function usedBy(): array
    {
        return [
            AssetLocation::class => 'floor_id',
            Section::class => 'floor_id',
            ProductionLine::class => 'floor_id',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\Floor;

/**
 * The organisation levels a location can name (ADR-052).
 *
 * Company-owned rather than platform-seeded: nobody else's factory has this
 * company's buildings in it. And no active flag — a building is there or it is
 * not, so the way out of one that should never have existed is to remove it,
 * which the delete rule already allows while nothing points at it.
 */
class BuildingData extends MasterDataType
{
    public function key(): string
    {
        return 'buildings';
    }

    public function model(): string
    {
        return Building::class;
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
            Field::belongsTo('factory_id', Factory::class),
            Field::text('name'),
            Field::code(),
        ];
    }

    public function usedBy(): array
    {
        return [
            Floor::class => 'building_id',
            AssetLocation::class => 'building_id',
        ];
    }
}

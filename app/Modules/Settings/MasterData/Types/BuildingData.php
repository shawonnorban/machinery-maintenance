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
 * The organisation levels a location can name (ADR-052), and Company
 * Management's Building tab (AnnotechERP 01/02).
 *
 * Company-owned rather than platform-seeded: nobody else's factory has this
 * company's buildings in it. A building still holding floors or machines is
 * not deleted — the delete rule refuses and names what is in the way — it is
 * set Inactive.
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
            Field::code(auto: true),
            Field::text('name'),
            // Declared; the floors actually set up are counted, not stored.
            Field::number('number_of_floors', required: false),
            Field::status(),
            Field::textarea('remarks'),
        ];
    }

    public function autoCodePrefix(): ?string
    {
        return 'BLD';
    }

    public function computedColumns(): array
    {
        return ['floors_registered' => __('masterdata.fields.floors_registered')];
    }

    public function computedValues(iterable $rows): array
    {
        $ids = collect($rows)->map(fn ($row): string => (string) $row->getKey())->all();
        $counts = $this->countBy(Floor::class, 'building_id', $ids);

        return collect($ids)
            ->mapWithKeys(fn (string $id): array => [$id => ['floors_registered' => $counts[$id] ?? 0]])
            ->all();
    }

    public function usedBy(): array
    {
        return [
            Floor::class => 'building_id',
            AssetLocation::class => 'building_id',
        ];
    }
}

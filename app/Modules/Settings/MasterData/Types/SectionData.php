<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\Tenancy\Models\Section;

/**
 * Company Management's Section tab (AnnotechERP 01/03). A section belongs to
 * a department ("Department is required.") and may sit on one floor.
 */
class SectionData extends MasterDataType
{
    public function key(): string
    {
        return 'sections';
    }

    public function model(): string
    {
        return Section::class;
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
            Field::reference('department_id', 'departments'),
            Field::code(auto: true),
            Field::text('name'),
            Field::reference('floor_id', 'floors', required: false),
            Field::status(),
        ];
    }

    public function autoCodePrefix(): ?string
    {
        return 'SEC';
    }

    public function usedBy(): array
    {
        return [
            ProductionLine::class => 'section_id',
            AssetLocation::class => 'section_id',
        ];
    }
}

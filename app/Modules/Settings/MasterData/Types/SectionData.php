<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\Tenancy\Models\Section;

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
            Field::text('name'),
            Field::code(),
        ];
    }

    public function usedBy(): array
    {
        return [
            ProductionLine::class => 'section_id',
            AssetLocation::class => 'section_id',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use App\Modules\Tenancy\Models\Workstation;

class WorkstationData extends MasterDataType
{
    public function key(): string
    {
        return 'workstations';
    }

    public function model(): string
    {
        return Workstation::class;
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
            Field::reference('production_line_id', 'production-lines'),
            Field::text('name'),
            Field::code(),
        ];
    }

    public function usedBy(): array
    {
        return [
            AssetLocation::class => 'workstation_id',
        ];
    }
}

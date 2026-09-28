<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\Tenancy\Models\Workstation;

/**
 * The line a machine stands on — the unit a floor supervisor actually thinks
 * in, and the one that appears on most location names.
 */
class ProductionLineData extends MasterDataType
{
    public function key(): string
    {
        return 'production-lines';
    }

    public function model(): string
    {
        return ProductionLine::class;
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
            // Optional: a mill that does not divide its departments into
            // sections still has lines.
            Field::reference('section_id', 'sections', required: false),
            Field::text('name'),
            Field::code(),
        ];
    }

    public function usedBy(): array
    {
        return [
            Workstation::class => 'production_line_id',
            AssetLocation::class => 'production_line_id',
        ];
    }
}

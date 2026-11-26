<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\Tenancy\Models\Workstation;

/**
 * The line a machine stands on — the unit a floor supervisor actually thinks
 * in, and the one that appears on most location names. Also Company
 * Management's Line tab (AnnotechERP 01/03), where every line carries a type.
 */
class ProductionLineData extends MasterDataType
{
    /** Asset states that take a machine out of the line's working count. */
    private const DOWN_STATES = ['UNDER_MAINTENANCE', 'BREAKDOWN', 'UNDER_REPAIR'];

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
            Field::reference('floor_id', 'floors', required: false),
            Field::code(auto: true),
            Field::text('name'),
            Field::enum('line_type', ProductionLine::TYPES),
            Field::number('manpower', required: false),
            Field::number('capacity_per_day', required: false),
            Field::number('target_per_day', required: false),
            Field::status(),
        ];
    }

    public function autoCodePrefix(): ?string
    {
        return 'LN';
    }

    /**
     * The machines standing on the line, read straight from machinery
     * maintenance's asset locations so the two cannot disagree.
     */
    public function computedColumns(): array
    {
        return [
            'machines' => __('masterdata.fields.machines'),
            'machines_down' => __('masterdata.fields.machines_down'),
        ];
    }

    public function computedValues(iterable $rows): array
    {
        $ids = collect($rows)->map(fn ($row): string => (string) $row->getKey())->all();

        if ($ids === []) {
            return [];
        }

        $assets = Asset::query()
            ->join('asset_locations', 'asset_locations.id', '=', 'assets.asset_location_id')
            ->whereIn('asset_locations.production_line_id', $ids)
            ->get(['asset_locations.production_line_id as line_id', 'assets.status']);

        return collect($ids)->mapWithKeys(function (string $id) use ($assets): array {
            $onLine = $assets->where('line_id', $id);

            return [$id => [
                'machines' => $onLine->count(),
                'machines_down' => $onLine->whereIn('status', self::DOWN_STATES)->count(),
            ]];
        })->all();
    }

    public function usedBy(): array
    {
        return [
            Workstation::class => 'production_line_id',
            AssetLocation::class => 'production_line_id',
        ];
    }
}

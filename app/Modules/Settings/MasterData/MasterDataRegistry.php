<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData;

use App\Modules\Settings\MasterData\Types\AssetCategoryData;
use App\Modules\Settings\MasterData\Types\AssetModelData;
use App\Modules\Settings\MasterData\Types\AssetTypeData;
use App\Modules\Settings\MasterData\Types\BuildingData;
use App\Modules\Settings\MasterData\Types\CostCategoryData;
use App\Modules\Settings\MasterData\Types\DepartmentData;
use App\Modules\Settings\MasterData\Types\DowntimeReasonCodeData;
use App\Modules\Settings\MasterData\Types\FailureCategoryData;
use App\Modules\Settings\MasterData\Types\FailureCodeData;
use App\Modules\Settings\MasterData\Types\FloorData;
use App\Modules\Settings\MasterData\Types\MaintenanceTypeData;
use App\Modules\Settings\MasterData\Types\ManufacturerData;
use App\Modules\Settings\MasterData\Types\MeterTypeData;
use App\Modules\Settings\MasterData\Types\ProductionLineData;
use App\Modules\Settings\MasterData\Types\RootCauseData;
use App\Modules\Settings\MasterData\Types\SectionData;
use App\Modules\Settings\MasterData\Types\SparePartCategoryData;
use App\Modules\Settings\MasterData\Types\WorkstationData;

/**
 * Every reference list a company can maintain (SRS 6).
 *
 * A registry rather than a controller per list. There are a dozen of these and
 * they differ only in their columns; twelve near-identical controllers is
 * twelve places for the tenant check to be forgotten in one of them.
 */
class MasterDataRegistry
{
    /** One permission covers the lot: this is all the same job. */
    public const PERMISSION = 'masterdata.manage';

    /**
     * Order matters on the screen: a parent list comes before the list that
     * points at it, because you cannot add a failure code before its category.
     */
    public const GROUPS = ['organisation', 'asset', 'breakdown', 'maintenance', 'inventory', 'cost'];

    /** @var array<string, MasterDataType>|null */
    private ?array $types = null;

    /**
     * @return array<string, MasterDataType>
     */
    public function all(): array
    {
        return $this->types ??= collect([
            // Organisation first, and parents before children: you cannot add a
            // floor before its building.
            new BuildingData,
            new FloorData,
            new DepartmentData,
            new SectionData,
            new ProductionLineData,
            new WorkstationData,

            new AssetTypeData,
            new AssetCategoryData,
            new ManufacturerData,
            new AssetModelData,
            new FailureCategoryData,
            new FailureCodeData,
            new RootCauseData,
            new DowntimeReasonCodeData,
            new MaintenanceTypeData,
            new MeterTypeData,
            new SparePartCategoryData,
            new CostCategoryData,
        ])->keyBy(fn (MasterDataType $type) => $type->key())->all();
    }

    public function find(string $key): ?MasterDataType
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @return array<string, list<MasterDataType>>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach (self::GROUPS as $group) {
            $grouped[$group] = [];
        }

        foreach ($this->all() as $type) {
            $grouped[$type->group()][] = $type;
        }

        return array_filter($grouped);
    }
}

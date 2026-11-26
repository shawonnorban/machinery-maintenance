<?php

declare(strict_types=1);

namespace App\Modules\Settings\MasterData\Types;

use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Settings\MasterData\Field;
use App\Modules\Settings\MasterData\MasterDataType;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Designation;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\Tenancy\Models\Section;

/**
 * Company Management's Department tab (AnnotechERP 01/03). The type splits
 * production from support departments; "Sections" is counted, not stored.
 */
class DepartmentData extends MasterDataType
{
    public function key(): string
    {
        return 'departments';
    }

    public function model(): string
    {
        return Department::class;
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
            Field::enum('department_type', Department::TYPES, required: false),
            Field::status(),
        ];
    }

    public function autoCodePrefix(): ?string
    {
        return 'DPT';
    }

    public function computedColumns(): array
    {
        return ['sections_count' => __('masterdata.fields.sections_count')];
    }

    public function computedValues(iterable $rows): array
    {
        $ids = collect($rows)->map(fn ($row): string => (string) $row->getKey())->all();
        $counts = $this->countBy(Section::class, 'department_id', $ids);

        return collect($ids)
            ->mapWithKeys(fn (string $id): array => [$id => ['sections_count' => $counts[$id] ?? 0]])
            ->all();
    }

    public function usedBy(): array
    {
        return [
            Section::class => 'department_id',
            ProductionLine::class => 'department_id',
            AssetLocation::class => 'department_id',
            Designation::class => 'department_id',
        ];
    }
}

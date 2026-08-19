<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Imports\Types;

use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Reporting\Imports\ImportColumn;
use App\Modules\Reporting\Imports\ImportContext;
use App\Modules\Reporting\Imports\Importer;
use App\Modules\Reporting\Imports\ImportOutcome;
use App\Modules\Reporting\Imports\PreparedRow;
use App\Modules\Reporting\Imports\RowContext;
use App\Modules\Tenancy\Models\Building;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\Tenancy\Models\ProductionLine;
use Throwable;

/**
 * Where machines live (SRS 33, ADR-052).
 *
 * Imported before assets, because an asset row names its location by code and
 * a file full of unknown location codes fails every row for one missing import.
 * The screens say so; this class cannot enforce an order across two separate
 * uploads.
 *
 * The optional building, department and line columns are matched by code and
 * skipped when absent, so a factory that tracks locations as a flat list of
 * codes is not forced to model a hierarchy it does not use.
 */
class LocationImporter extends Importer
{
    public function type(): string
    {
        return 'locations';
    }

    public function permission(): string
    {
        return 'masterdata.manage';
    }

    public function columns(): array
    {
        return [
            'code' => new ImportColumn('import.columns.location_code', true, 'DHK-L3-01'),
            'name' => new ImportColumn('import.columns.name', true, 'Line 3, Station 1'),
            'factory_code' => new ImportColumn('import.columns.factory_code', true, 'DHK'),
            'building_code' => new ImportColumn('import.columns.building_code', false, 'B1'),
            'department_code' => new ImportColumn('import.columns.department_code', false, 'SEWING'),
            'production_line_code' => new ImportColumn('import.columns.production_line_code', false, 'L3'),
            'status' => new ImportColumn('import.columns.status', false, 'ACTIVE', 'ACTIVE, INACTIVE'),
        ];
    }

    public function prepare(array $row, RowContext $context): PreparedRow
    {
        $errors = [];

        foreach (['code', 'name', 'factory_code'] as $required) {
            if (($row[$required] ?? null) === null) {
                $errors[] = ['field' => $required, 'error' => __('import.errors.required'), 'value' => null];
            }
        }

        if ($errors !== []) {
            return PreparedRow::invalid($context->rowNumber, $errors, $row);
        }

        $factory = $context->remember("factory:{$row['factory_code']}", fn () => Factory::query()
            ->where('code', $row['factory_code'])->first());

        if ($factory === null) {
            return PreparedRow::invalid($context->rowNumber, [[
                'field' => 'factory_code',
                'error' => __('import.errors.unknown_reference'),
                'value' => $row['factory_code'],
            ]], $row);
        }

        $status = $row['status'] !== null ? strtoupper($row['status']) : 'ACTIVE';

        if (! in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            $errors[] = [
                'field' => 'status',
                'error' => __('import.errors.one_of', ['values' => 'ACTIVE, INACTIVE']),
                'value' => $row['status'],
            ];
        }

        $building = $this->lookup($context, Building::class, 'building', $row['building_code'], $factory->id);
        $department = $this->lookup($context, Department::class, 'department', $row['department_code'], $factory->id);

        // A production line hangs off a department rather than a factory
        // directly, so it is found by code and checked through its department.
        $line = $row['production_line_code'] === null ? null : $context->remember(
            "line:{$row['production_line_code']}",
            fn () => ProductionLine::query()->where('code', $row['production_line_code'])->first(),
        );

        if ($line !== null && $department !== null && $line->department_id !== $department->id) {
            $errors[] = [
                'field' => 'production_line_code',
                'error' => __('import.errors.line_department_mismatch'),
                'value' => $row['production_line_code'],
            ];
        }

        foreach ([
            ['building_code', $row['building_code'], $building],
            ['department_code', $row['department_code'], $department],
            ['production_line_code', $row['production_line_code'], $line],
        ] as [$field, $code, $found]) {
            if ($code !== null && $found === null) {
                // Named but not found is an error; absent is fine. A typo that
                // silently becomes "no building" is a location nobody can find.
                $errors[] = [
                    'field' => $field,
                    'error' => __('import.errors.unknown_reference'),
                    'value' => $code,
                ];
            }
        }

        if ($errors !== []) {
            return PreparedRow::invalid($context->rowNumber, $errors, $row);
        }

        return PreparedRow::valid($context->rowNumber, [
            'code' => $row['code'],
            'name' => $row['name'],
            'factory_id' => $factory->id,
            'building_id' => $building?->id,
            'department_id' => $department?->id,
            'production_line_id' => $line?->id,
            'status' => $status,
        ], $row);
    }

    public function write(PreparedRow $row, ImportContext $context): ImportOutcome
    {
        try {
            $existing = AssetLocation::where('code', $row->values['code'])->first();

            if ($existing !== null) {
                $existing->update($row->values);

                return ImportOutcome::updated();
            }

            AssetLocation::create($row->values);

            return ImportOutcome::created();
        } catch (Throwable $e) {
            return ImportOutcome::failed($e->getMessage());
        }
    }

    public function supportsExport(): bool
    {
        return true;
    }

    public function exportRows(): iterable
    {
        $locations = AssetLocation::query()
            ->with(['factory', 'building', 'department', 'productionLine'])
            ->orderBy('code')
            ->lazy();

        foreach ($locations as $location) {
            yield [
                'code' => $location->code,
                'name' => $location->name,
                'factory_code' => $location->factory?->code,
                'building_code' => $location->building?->code,
                'department_code' => $location->department?->code,
                'production_line_code' => $location->productionLine?->code,
                'status' => $location->status,
            ];
        }
    }

    private function lookup(RowContext $context, string $model, string $prefix, ?string $code, string $factoryId): mixed
    {
        if ($code === null) {
            return null;
        }

        return $context->remember("{$prefix}:{$code}", fn () => $model::query()
            ->where('code', $code)
            ->where('factory_id', $factoryId)
            ->first());
    }
}

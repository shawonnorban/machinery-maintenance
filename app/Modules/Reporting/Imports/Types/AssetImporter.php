<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Imports\Types;

use App\Modules\Asset\Actions\CreateAsset;
use App\Modules\Asset\Actions\UpdateAsset;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetCategory;
use App\Modules\Asset\Models\AssetLocation;
use App\Modules\Asset\Models\AssetModel;
use App\Modules\Asset\Models\AssetType;
use App\Modules\Asset\Models\Manufacturer;
use App\Modules\Reporting\Imports\ImportColumn;
use App\Modules\Reporting\Imports\ImportContext;
use App\Modules\Reporting\Imports\Importer;
use App\Modules\Reporting\Imports\ImportOutcome;
use App\Modules\Reporting\Imports\PreparedRow;
use App\Modules\Reporting\Imports\RowContext;
use App\Modules\Tenancy\Models\Factory;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The installed machine list (SRS 33).
 *
 * The import a factory actually needs on day one: a few thousand machines that
 * somebody has been tracking in a spreadsheet for years.
 *
 * Rows are written through CreateAsset and UpdateAsset, the same actions the
 * screens use (ADR-066). An importer with its own inserts is an importer that
 * creates assets the product's own rules would have refused — a category from
 * the wrong type, a location in another factory — and those only surface months
 * later as a report that will not balance.
 *
 * A code that already exists updates rather than duplicates. Factories re-send
 * a corrected file, and an import that answers a re-send with a second copy of
 * every machine is an import nobody uses twice.
 */
class AssetImporter extends Importer
{
    public function __construct(
        private readonly CreateAsset $create,
        private readonly UpdateAsset $update,
    ) {}

    public function type(): string
    {
        return 'assets';
    }

    public function permission(): string
    {
        return 'asset.asset.create';
    }

    public function columns(): array
    {
        return [
            'asset_code' => new ImportColumn('import.columns.asset_code', true, 'SEW-DHK-00412'),
            'name' => new ImportColumn('import.columns.name', true, 'Juki DDL-9000C'),
            'asset_type_code' => new ImportColumn('import.columns.asset_type_code', true, 'SEWING'),
            'asset_category_code' => new ImportColumn('import.columns.asset_category_code', true, 'LOCKSTITCH'),
            'factory_code' => new ImportColumn('import.columns.factory_code', true, 'DHK'),
            'location_code' => new ImportColumn('import.columns.location_code', true, 'DHK-L3-01'),
            'manufacturer_code' => new ImportColumn('import.columns.manufacturer_code', false, 'JUKI'),
            'model_code' => new ImportColumn('import.columns.model_code', false, 'DDL9000C'),
            'serial_number' => new ImportColumn('import.columns.serial_number', false, 'A1B2C3'),
            'criticality' => new ImportColumn('import.columns.criticality', false, 'HIGH', 'CRITICAL, HIGH, MEDIUM, LOW'),
            'status' => new ImportColumn('import.columns.status', false, 'INSTALLED', 'DRAFT, PURCHASED, INSTALLED'),
            'purchase_date' => new ImportColumn('import.columns.purchase_date', false, '2024-03-15'),
            'installation_date' => new ImportColumn('import.columns.installation_date', false, '2024-04-01'),
            'acquisition_cost' => new ImportColumn('import.columns.acquisition_cost', false, '285000'),
            'currency' => new ImportColumn('import.columns.currency', false, 'BDT'),
            'warranty_start' => new ImportColumn('import.columns.warranty_start', false, '2024-04-01'),
            'warranty_end' => new ImportColumn('import.columns.warranty_end', false, '2026-04-01'),
            'useful_life_months' => new ImportColumn('import.columns.useful_life_months', false, '120'),
            'notes' => new ImportColumn('import.columns.notes', false, ''),
        ];
    }

    public function prepare(array $row, RowContext $context): PreparedRow
    {
        $errors = [];
        $values = [];

        foreach (['asset_code', 'name', 'asset_type_code', 'asset_category_code', 'factory_code', 'location_code'] as $required) {
            if (($row[$required] ?? null) === null) {
                $errors[] = ['field' => $required, 'error' => __('import.errors.required'), 'value' => null];
            }
        }

        if ($errors !== []) {
            return PreparedRow::invalid($context->rowNumber, $errors, $row);
        }

        $type = $context->remember("type:{$row['asset_type_code']}", fn () => AssetType::query()
            ->where('code', $row['asset_type_code'])->first());

        if ($type === null) {
            $errors[] = [
                'field' => 'asset_type_code',
                'error' => __('import.errors.unknown_reference'),
                'value' => $row['asset_type_code'],
            ];
        }

        $category = $context->remember("category:{$row['asset_category_code']}", fn () => AssetCategory::query()
            ->where('code', $row['asset_category_code'])->first());

        if ($category === null) {
            $errors[] = [
                'field' => 'asset_category_code',
                'error' => __('import.errors.unknown_reference'),
                'value' => $row['asset_category_code'],
            ];
        }

        $factory = $context->remember("factory:{$row['factory_code']}", fn () => Factory::query()
            ->where('code', $row['factory_code'])->first());

        if ($factory === null) {
            $errors[] = [
                'field' => 'factory_code',
                'error' => __('import.errors.unknown_reference'),
                'value' => $row['factory_code'],
            ];
        }

        $location = $context->remember("location:{$row['location_code']}", fn () => AssetLocation::query()
            ->where('code', $row['location_code'])->first());

        if ($location === null) {
            $errors[] = [
                'field' => 'location_code',
                'error' => __('import.errors.unknown_reference'),
                'value' => $row['location_code'],
            ];
        } elseif ($factory !== null && $location->factory_id !== $factory->id) {
            // Caught here as well as in the action, so the person sees it in
            // the preview rather than as a failed row after confirming.
            $errors[] = [
                'field' => 'location_code',
                'error' => __('import.errors.location_factory_mismatch'),
                'value' => $row['location_code'],
            ];
        }

        if ($category !== null && $type !== null && $category->asset_type_id !== $type->id) {
            $errors[] = [
                'field' => 'asset_category_code',
                'error' => __('import.errors.category_type_mismatch'),
                'value' => $row['asset_category_code'],
            ];
        }

        $criticality = $row['criticality'] !== null ? strtoupper($row['criticality']) : null;

        if ($criticality !== null && ! in_array($criticality, Asset::CRITICALITIES, true)) {
            $errors[] = [
                'field' => 'criticality',
                'error' => __('import.errors.one_of', ['values' => implode(', ', Asset::CRITICALITIES)]),
                'value' => $row['criticality'],
            ];
        }

        $status = $row['status'] !== null ? strtoupper($row['status']) : 'DRAFT';

        if (! in_array($status, Asset::CREATABLE_STATUSES, true)) {
            // Later states are reached through an audited status change, so a
            // file cannot declare a machine RUNNING on arrival.
            $errors[] = [
                'field' => 'status',
                'error' => __('import.errors.one_of', ['values' => implode(', ', Asset::CREATABLE_STATUSES)]),
                'value' => $row['status'],
            ];
        }

        foreach (['purchase_date', 'installation_date', 'warranty_start', 'warranty_end'] as $dateField) {
            if ($row[$dateField] !== null && ! $this->isDate($row[$dateField])) {
                $errors[] = [
                    'field' => $dateField,
                    'error' => __('import.errors.date_format'),
                    'value' => $row[$dateField],
                ];
            }
        }

        foreach (['acquisition_cost', 'useful_life_months'] as $numericField) {
            if ($row[$numericField] !== null && ! is_numeric(str_replace(',', '', $row[$numericField]))) {
                $errors[] = [
                    'field' => $numericField,
                    'error' => __('import.errors.numeric'),
                    'value' => $row[$numericField],
                ];
            }
        }

        if ($errors !== []) {
            return PreparedRow::invalid($context->rowNumber, $errors, $row);
        }

        $manufacturer = $row['manufacturer_code'] === null ? null : Manufacturer::query()
            ->where('code', $row['manufacturer_code'])->first();

        $model = $row['model_code'] === null ? null : AssetModel::query()
            ->where('code', $row['model_code'])->first();

        $values = [
            'asset_code' => $row['asset_code'],
            'name' => $row['name'],
            'asset_type_id' => $type->id,
            'asset_category_id' => $category->id,
            'manufacturer_id' => $manufacturer?->id,
            'asset_model_id' => $model?->id,
            'serial_number' => $row['serial_number'],
            'current_factory_id' => $factory->id,
            'asset_location_id' => $location->id,
            'criticality' => $criticality ?? $type->default_criticality ?? 'MEDIUM',
            'status' => $status,
            'purchase_date' => $row['purchase_date'],
            'installation_date' => $row['installation_date'],
            'acquisition_cost' => $this->number($row['acquisition_cost']),
            'currency' => $row['currency'] ?? 'BDT',
            'warranty_start' => $row['warranty_start'],
            'warranty_end' => $row['warranty_end'],
            'useful_life_months' => $this->number($row['useful_life_months']),
            'notes' => $row['notes'],
        ];

        return PreparedRow::valid($context->rowNumber, $values, $row);
    }

    public function write(PreparedRow $row, ImportContext $context): ImportOutcome
    {
        $existing = Asset::where('asset_code', $row->values['asset_code'])->first();

        try {
            if ($existing !== null) {
                $values = $row->values;
                // Status is not overwritten on update: a machine that has been
                // running for six months is not returned to INSTALLED because a
                // stale spreadsheet says so.
                unset($values['status']);
                $values['version'] = $existing->version;

                $this->update->handle($existing, $values, $context->userId);

                return ImportOutcome::updated();
            }

            $this->create->handle([
                ...$row->values,
                'is_imported' => true,
                'imported_batch_id' => $context->batchId,
            ], $context->userId);

            return ImportOutcome::created();
        } catch (ValidationException $e) {
            return ImportOutcome::failed(implode(' ', $e->validator->errors()->all()));
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
        $assets = Asset::query()
            ->with(['type', 'category', 'manufacturer', 'model', 'factory', 'location'])
            ->orderBy('asset_code')
            ->lazy();

        foreach ($assets as $asset) {
            yield [
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'asset_type_code' => $asset->type?->code,
                'asset_category_code' => $asset->category?->code,
                'factory_code' => $asset->factory?->code,
                'location_code' => $asset->location?->code,
                'manufacturer_code' => $asset->manufacturer?->code,
                'model_code' => $asset->model?->code,
                'serial_number' => $asset->serial_number,
                'criticality' => $asset->criticality,
                'status' => $asset->status,
                'purchase_date' => $asset->purchase_date?->format('Y-m-d'),
                'installation_date' => $asset->installation_date?->format('Y-m-d'),
                'acquisition_cost' => $asset->acquisition_cost,
                'currency' => $asset->currency,
                'warranty_start' => $asset->warranty_start?->format('Y-m-d'),
                'warranty_end' => $asset->warranty_end?->format('Y-m-d'),
                'useful_life_months' => $asset->useful_life_months,
                'notes' => $asset->notes,
            ];
        }
    }

    private function isDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }

    private function number(?string $value): ?string
    {
        return $value === null ? null : str_replace(',', '', $value);
    }
}

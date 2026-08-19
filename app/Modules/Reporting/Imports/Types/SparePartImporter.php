<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Imports\Types;

use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\SparePartCategory;
use App\Modules\Reporting\Imports\ImportColumn;
use App\Modules\Reporting\Imports\ImportContext;
use App\Modules\Reporting\Imports\Importer;
use App\Modules\Reporting\Imports\ImportOutcome;
use App\Modules\Reporting\Imports\PreparedRow;
use App\Modules\Reporting\Imports\RowContext;
use Throwable;

/**
 * The spare parts catalogue (SRS 33).
 *
 * The catalogue only: what a part is, what it costs to buy and when to reorder
 * it. Opening stock is deliberately not importable here.
 *
 * Stock arrives through the ledger, as a receipt with a quantity, a cost and a
 * bin (SRS 23). Letting a spreadsheet set quantity_on_hand directly would write
 * a balance with no transaction behind it, and the first time anybody replayed
 * the ledger to check a valuation, the numbers would not meet. An importer that
 * quietly breaks the audit trail is worse than one that asks for a second step.
 */
class SparePartImporter extends Importer
{
    public function type(): string
    {
        return 'spare_parts';
    }

    public function permission(): string
    {
        return 'inventory.part.create';
    }

    public function columns(): array
    {
        return [
            'part_number' => new ImportColumn('import.columns.part_number', true, 'JK-DDL9000-HOOK'),
            'name' => new ImportColumn('import.columns.name', true, 'Rotary hook, Juki DDL-9000C'),
            'category_code' => new ImportColumn('import.columns.category_code', true, 'SEWING_PARTS'),
            'unit' => new ImportColumn('import.columns.unit', true, 'PCS'),
            'brand' => new ImportColumn('import.columns.brand', false, 'Juki'),
            'manufacturer' => new ImportColumn('import.columns.manufacturer', false, 'Juki'),
            'minimum_stock' => new ImportColumn('import.columns.minimum_stock', false, '2'),
            'reorder_level' => new ImportColumn('import.columns.reorder_level', false, '4'),
            'unit_cost' => new ImportColumn('import.columns.unit_cost', false, '2450'),
            'currency' => new ImportColumn('import.columns.currency', false, 'BDT'),
            'lead_time_days' => new ImportColumn('import.columns.lead_time_days', false, '21'),
            'is_critical_spare' => new ImportColumn('import.columns.is_critical_spare', false, 'yes', 'yes / no'),
            'shelf_life_days' => new ImportColumn('import.columns.shelf_life_days', false, ''),
            'hazardous' => new ImportColumn('import.columns.hazardous', false, 'no', 'yes / no'),
            'notes' => new ImportColumn('import.columns.notes', false, ''),
        ];
    }

    public function prepare(array $row, RowContext $context): PreparedRow
    {
        $errors = [];

        foreach (['part_number', 'name', 'category_code', 'unit'] as $required) {
            if (($row[$required] ?? null) === null) {
                $errors[] = ['field' => $required, 'error' => __('import.errors.required'), 'value' => null];
            }
        }

        if ($errors !== []) {
            return PreparedRow::invalid($context->rowNumber, $errors, $row);
        }

        $category = $context->remember("category:{$row['category_code']}", fn () => SparePartCategory::query()
            ->where('code', $row['category_code'])->first());

        if ($category === null) {
            $errors[] = [
                'field' => 'category_code',
                'error' => __('import.errors.unknown_reference'),
                'value' => $row['category_code'],
            ];
        }

        foreach (['minimum_stock', 'reorder_level', 'unit_cost', 'lead_time_days', 'shelf_life_days'] as $numeric) {
            if ($row[$numeric] !== null && ! is_numeric(str_replace(',', '', $row[$numeric]))) {
                $errors[] = [
                    'field' => $numeric,
                    'error' => __('import.errors.numeric'),
                    'value' => $row[$numeric],
                ];
            }
        }

        $critical = $this->boolean($row['is_critical_spare'] ?? null);
        $hazardous = $this->boolean($row['hazardous'] ?? null);

        foreach ([['is_critical_spare', $critical], ['hazardous', $hazardous]] as [$field, $parsed]) {
            if ($row[$field] !== null && $parsed === null) {
                $errors[] = [
                    'field' => $field,
                    'error' => __('import.errors.boolean'),
                    'value' => $row[$field],
                ];
            }
        }

        if ($errors !== []) {
            return PreparedRow::invalid($context->rowNumber, $errors, $row);
        }

        return PreparedRow::valid($context->rowNumber, [
            'part_number' => $row['part_number'],
            'name' => $row['name'],
            'category_id' => $category->id,
            'unit' => $row['unit'],
            'brand' => $row['brand'],
            'manufacturer' => $row['manufacturer'],
            'minimum_stock' => $this->number($row['minimum_stock']) ?? '0',
            'reorder_level' => $this->number($row['reorder_level']) ?? '0',
            'unit_cost' => $this->number($row['unit_cost']) ?? '0',
            'currency' => $row['currency'] ?? 'BDT',
            'lead_time_days' => $this->number($row['lead_time_days']),
            'is_critical_spare' => $critical ?? false,
            'shelf_life_days' => $this->number($row['shelf_life_days']),
            'hazardous' => $hazardous ?? false,
            'notes' => $row['notes'],
            'active' => true,
        ], $row);
    }

    public function write(PreparedRow $row, ImportContext $context): ImportOutcome
    {
        try {
            $existing = SparePart::where('part_number', $row->values['part_number'])->first();

            if ($existing !== null) {
                $existing->update($row->values);

                return ImportOutcome::updated();
            }

            SparePart::create($row->values);

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
        $parts = SparePart::query()->with('category')->orderBy('part_number')->lazy();

        foreach ($parts as $part) {
            yield [
                'part_number' => $part->part_number,
                'name' => $part->name,
                'category_code' => $part->category?->code,
                'unit' => $part->unit,
                'brand' => $part->brand,
                'manufacturer' => $part->manufacturer,
                'minimum_stock' => $part->minimum_stock,
                'reorder_level' => $part->reorder_level,
                'unit_cost' => $part->unit_cost,
                'currency' => $part->currency,
                'lead_time_days' => $part->lead_time_days,
                'is_critical_spare' => $part->is_critical_spare ? 'yes' : 'no',
                'shelf_life_days' => $part->shelf_life_days,
                'hazardous' => $part->hazardous ? 'yes' : 'no',
                'notes' => $part->notes,
            ];
        }
    }

    /**
     * Accepts what people actually type. A file that fails on "Yes" because the
     * parser wanted "true" is a file that gets fixed by hand.
     */
    private function boolean(?string $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower(trim($value))) {
            'yes', 'y', 'true', '1' => true,
            'no', 'n', 'false', '0' => false,
            default => null,
        };
    }

    private function number(?string $value): ?string
    {
        return $value === null ? null : str_replace(',', '', $value);
    }
}

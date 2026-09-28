<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Imports\Types;

use App\Modules\Reporting\Imports\ImportColumn;
use App\Modules\Reporting\Imports\ImportContext;
use App\Modules\Reporting\Imports\Importer;
use App\Modules\Reporting\Imports\ImportOutcome;
use App\Modules\Reporting\Imports\PreparedRow;
use App\Modules\Reporting\Imports\RowContext;
use App\Modules\Vendor\Models\Vendor;
use Throwable;

/**
 * The supplier list (SRS 33).
 *
 * A factory's vendor list arrives as a contacts spreadsheet somebody in
 * purchasing has kept for years, and typing three hundred of them in by hand is
 * how a vendor field ends up empty on every cost entry.
 */
class VendorImporter extends Importer
{
    public function type(): string
    {
        return 'vendors';
    }

    public function permission(): string
    {
        return 'vendor.vendor.create';
    }

    public function columns(): array
    {
        return [
            'code' => new ImportColumn('import.columns.code', true, 'JUKI-BD'),
            'name' => new ImportColumn('import.columns.name', true, 'Juki Bangladesh Ltd'),
            'vendor_type' => new ImportColumn('import.columns.vendor_type', false, 'BOTH', 'SUPPLIER, SERVICE, BOTH'),
            'contact_name' => new ImportColumn('import.columns.contact_name', false, 'Rafiqul Islam'),
            'phone' => new ImportColumn('import.columns.phone', false, '+8801700000000'),
            'email' => new ImportColumn('import.columns.email', false, 'service@example.com'),
            'address' => new ImportColumn('import.columns.address', false, 'Tejgaon, Dhaka'),
            'tax_reference' => new ImportColumn('import.columns.tax_reference', false, ''),
            'status' => new ImportColumn('import.columns.status', false, 'ACTIVE', 'ACTIVE, INACTIVE, BLACKLISTED'),
            'notes' => new ImportColumn('import.columns.notes', false, ''),
        ];
    }

    public function prepare(array $row, RowContext $context): PreparedRow
    {
        $errors = [];

        foreach (['code', 'name'] as $required) {
            if (($row[$required] ?? null) === null) {
                $errors[] = ['field' => $required, 'error' => __('import.errors.required'), 'value' => null];
            }
        }

        if ($errors !== []) {
            return PreparedRow::invalid($context->rowNumber, $errors, $row);
        }

        $type = $row['vendor_type'] !== null ? strtoupper($row['vendor_type']) : 'SUPPLIER';

        if (! in_array($type, Vendor::TYPES, true)) {
            $errors[] = [
                'field' => 'vendor_type',
                'error' => __('import.errors.one_of', ['values' => implode(', ', Vendor::TYPES)]),
                'value' => $row['vendor_type'],
            ];
        }

        $status = $row['status'] !== null ? strtoupper($row['status']) : 'ACTIVE';

        if (! in_array($status, Vendor::STATUSES, true)) {
            $errors[] = [
                'field' => 'status',
                'error' => __('import.errors.one_of', ['values' => implode(', ', Vendor::STATUSES)]),
                'value' => $row['status'],
            ];
        }

        if ($row['email'] !== null && filter_var($row['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = [
                'field' => 'email',
                'error' => __('import.errors.email'),
                'value' => $row['email'],
            ];
        }

        if ($errors !== []) {
            return PreparedRow::invalid($context->rowNumber, $errors, $row);
        }

        return PreparedRow::valid($context->rowNumber, [
            'code' => $row['code'],
            'name' => $row['name'],
            'vendor_type' => $type,
            'contact_name' => $row['contact_name'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'address' => $row['address'],
            'tax_reference' => $row['tax_reference'],
            'status' => $status,
            'notes' => $row['notes'],
        ], $row);
    }

    public function write(PreparedRow $row, ImportContext $context): ImportOutcome
    {
        try {
            $existing = Vendor::where('code', $row->values['code'])->first();

            if ($existing !== null) {
                $existing->update($row->values);

                return ImportOutcome::updated();
            }

            Vendor::create([...$row->values, 'created_by' => $context->userId]);

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
        foreach (Vendor::query()->orderBy('code')->lazy() as $vendor) {
            yield [
                'code' => $vendor->code,
                'name' => $vendor->name,
                'vendor_type' => $vendor->vendor_type,
                'contact_name' => $vendor->contact_name,
                'phone' => $vendor->phone,
                'email' => $vendor->email,
                'address' => $vendor->address,
                'tax_reference' => $vendor->tax_reference,
                'status' => $vendor->status,
                'notes' => $vendor->notes,
            ];
        }
    }
}

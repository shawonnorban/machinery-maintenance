<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Imports;

use App\Modules\Identity\Models\User;

/**
 * One kind of thing that can be imported (SRS 33, ADR-031).
 *
 * Every importer describes its columns rather than parsing positionally.
 * Factories send files with columns in whatever order the person who built the
 * spreadsheet chose, and matching by position turns a serial number into a
 * purchase price without anybody noticing.
 *
 * References are given by code, never by id. A spreadsheet cannot contain a
 * ULID, and asking a factory to paste internal identifiers into their asset
 * list is asking them not to use the import at all.
 */
abstract class Importer
{
    /** Stable identifier, stored on the job row and used in URLs. */
    abstract public function type(): string;

    abstract public function permission(): string;

    /**
     * The columns this importer reads.
     *
     * @return array<string, ImportColumn>
     */
    abstract public function columns(): array;

    /**
     * Turn one file row into the values that will be written, or record why it
     * cannot be.
     *
     * Validation and resolution happen together deliberately: "asset type
     * SEWING does not exist" is a validation error, and finding out requires
     * the lookup. Splitting them would mean doing every lookup twice.
     *
     * @param  array<string, string|null>  $row
     */
    abstract public function prepare(array $row, RowContext $context): PreparedRow;

    /**
     * Write one prepared row.
     *
     * Goes through the same Action the screens use wherever one exists
     * (ADR-066). An import that writes rows directly is an import that creates
     * records the product's own rules would have refused.
     */
    abstract public function write(PreparedRow $row, ImportContext $context): ImportOutcome;

    public function title(): string
    {
        return __("import.types.{$this->type()}.title");
    }

    public function description(): string
    {
        return __("import.types.{$this->type()}.description");
    }

    /**
     * A row a person can look at to understand the format.
     *
     * @return array<string, string>
     */
    public function exampleRow(): array
    {
        $example = [];

        foreach ($this->columns() as $name => $column) {
            $example[$name] = $column->example;
        }

        return $example;
    }

    /**
     * Whether current data can be exported in this importer's own format.
     *
     * Export and import live in the same class on purpose. The round trip is
     * the point — pull the asset register out, fix two hundred rows in a
     * spreadsheet, upload it again — and two separate classes owning the same
     * column mapping is how the exported file stops being importable.
     */
    public function supportsExport(): bool
    {
        return false;
    }

    /**
     * Current data, keyed by this importer's own column names.
     *
     * @return iterable<int, array<string, scalar|null>>
     */
    public function exportRows(): iterable
    {
        return [];
    }

    public function allows(User $user): bool
    {
        return $user->can('import.job.create') && $user->can($this->permission());
    }
}

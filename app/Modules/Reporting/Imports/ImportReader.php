<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Imports;

use Generator;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Reads an uploaded file into rows keyed by column name (SRS 33).
 *
 * CSV and XLSX through the same interface, because a factory sends whichever
 * their office happens to use and neither is worth refusing.
 *
 * Headers are matched case-insensitively with surrounding whitespace stripped.
 * "Asset Code" and "asset_code " are the same column to everyone except a
 * parser, and a file rejected over a capital letter is a file that gets emailed
 * to support instead.
 */
class ImportReader
{
    /** Guards against a file that would take the request down before it is read. */
    public const MAX_ROWS = 20000;

    /**
     * @return Generator<int, array<string, string|null>> Row number => values.
     */
    public function rows(string $path, string $extension): Generator
    {
        $reader = $this->readerFor($path, $extension);
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $header = null;
                // Counting the header as row 1, so a reported row number is the
                // row a person sees in their spreadsheet.
                $rowNumber = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $values = array_map(
                        fn ($value) => $this->stringify($value),
                        $row->toArray(),
                    );

                    if ($header === null) {
                        $header = $this->normaliseHeader($values);

                        continue;
                    }

                    if ($this->isBlank($values)) {
                        // Trailing empty rows are a spreadsheet artefact, not a
                        // failed import.
                        continue;
                    }

                    yield $rowNumber => $this->combine($header, $values);

                    if ($rowNumber > self::MAX_ROWS) {
                        throw ValidationException::withMessages([
                            'file' => __('import.too_many_rows', ['max' => self::MAX_ROWS]),
                        ]);
                    }
                }

                // One sheet only. A workbook with three sheets is ambiguous,
                // and guessing which one holds the data is how the wrong three
                // hundred rows get imported.
                break;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * The header names actually present in the file.
     *
     * @return list<string>
     */
    public function headers(string $path, string $extension): array
    {
        $reader = $this->readerFor($path, $extension);
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    return $this->normaliseHeader(array_map(
                        fn ($value) => $this->stringify($value),
                        $row->toArray(),
                    ));
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return [];
    }

    private function readerFor(string $path, string $extension): CsvReader|XlsxReader
    {
        return match (strtolower($extension)) {
            'csv', 'txt' => new CsvReader,
            'xlsx' => new XlsxReader,
            default => throw ValidationException::withMessages([
                'file' => __('import.unsupported_file', ['extension' => $extension]),
            ]),
        };
    }

    /**
     * @param  list<string|null>  $values
     * @return list<string>
     */
    private function normaliseHeader(array $values): array
    {
        return array_map(
            fn (?string $value) => strtolower(trim((string) $value)),
            $values,
        );
    }

    /**
     * @param  list<string>  $header
     * @param  list<string|null>  $values
     * @return array<string, string|null>
     */
    private function combine(array $header, array $values): array
    {
        $row = [];

        foreach ($header as $index => $name) {
            if ($name === '') {
                continue;
            }

            $value = $values[$index] ?? null;

            // An empty cell is null, not "". Otherwise every optional column
            // arrives as an empty string and validation has to unpick which
            // blanks were meant.
            $row[$name] = ($value === null || trim($value) === '')
                ? null
                : $this->unescape(trim($value));
        }

        return $row;
    }

    /**
     * @param  list<string|null>  $values
     */
    private function isBlank(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Undoes the apostrophe the exporter adds in front of a value a spreadsheet
     * would otherwise treat as a formula, so an export and a re-import round
     * trip to the same data.
     */
    private function unescape(string $value): string
    {
        return preg_match("/^'[=+\-@]/", $value) === 1 ? substr($value, 1) : $value;
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            // A spreadsheet date arrives as a date object; anything downstream
            // wants the text the person typed.
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}

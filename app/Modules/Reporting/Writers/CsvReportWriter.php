<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Writers;

use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use RuntimeException;

/**
 * CSV, written row by row (SRS 32).
 *
 * Starts with a UTF-8 byte order mark. Without it Excel reads the file in the
 * system codepage and every Bengali column arrives as mojibake — which looks
 * like a data problem to the person who opened it, not an encoding one.
 */
class CsvReportWriter implements ReportWriter
{
    private const BOM = "\xEF\xBB\xBF";

    public function format(): string
    {
        return 'CSV';
    }

    public function extension(): string
    {
        return 'csv';
    }

    public function mimeType(): string
    {
        return 'text/csv';
    }

    public function write(string $path, Report $report, ReportQuery $query, array $meta = []): int
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Could not open [{$path}] for writing.");
        }

        try {
            fwrite($handle, self::BOM);

            // The parameters travel with the file. A spreadsheet emailed on
            // without its period and scope is a set of numbers nobody can
            // check (SRS 44).
            foreach ($meta as $label => $value) {
                fputcsv($handle, [$label, $value]);
            }

            if ($meta !== []) {
                fputcsv($handle, []);
            }

            $columns = $report->columns();

            fputcsv($handle, array_map(fn (array $c) => __($c['label']), $columns));

            $count = 0;

            foreach ($report->rows($query) as $row) {
                fputcsv($handle, array_map(
                    fn (string $key) => $this->cell($row[$key] ?? null),
                    array_keys($columns),
                ));

                $count++;
            }

            return $count;
        } finally {
            fclose($handle);
        }
    }

    private function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}

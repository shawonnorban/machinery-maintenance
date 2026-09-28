<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Writers;

use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Excel, written row by row (SRS 32).
 *
 * Through a streaming writer rather than an in-memory spreadsheet library. A
 * report of a fleet's history is hundreds of thousands of rows, and the common
 * libraries hold every cell as an object until the file is saved — which turns
 * a large export into an out-of-memory error on the machine that had the least
 * to spare.
 *
 * Numbers are written as numbers. A column of totals that arrives as text
 * cannot be summed in the spreadsheet, and the first thing anybody does with an
 * exported cost column is sum it.
 */
class XlsxReportWriter implements ReportWriter
{
    public function format(): string
    {
        return 'XLSX';
    }

    public function extension(): string
    {
        return 'xlsx';
    }

    public function mimeType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function write(string $path, Report $report, ReportQuery $query, array $meta = []): int
    {
        $writer = new Writer;
        $writer->openToFile($path);

        $bold = (new Style)->withFontBold(true);

        try {
            foreach ($meta as $label => $value) {
                $writer->addRow(Row::fromValues([$label, $value]));
            }

            if ($meta !== []) {
                $writer->addRow(Row::fromValues([]));
            }

            $columns = $report->columns();

            $writer->addRow(Row::fromValuesWithStyle(
                array_map(fn (array $c) => __($c['label']), $columns),
                $bold,
            ));

            $count = 0;

            foreach ($report->rows($query) as $row) {
                $values = [];

                foreach ($columns as $key => $meta_) {
                    $values[] = $this->cell($row[$key] ?? null, $meta_['numeric'] ?? false);
                }

                $writer->addRow(Row::fromValues($values));

                $count++;
            }

            return $count;
        } finally {
            $writer->close();
        }
    }

    private function cell(mixed $value, bool $numeric): string|int|float
    {
        if ($value === null) {
            return '';
        }

        if ($numeric && is_numeric($value)) {
            // Decimal columns arrive as strings from the database, and a money
            // string in a spreadsheet is a cell you cannot add up.
            return str_contains((string) $value, '.') ? (float) $value : (int) $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return (string) $value;
    }
}

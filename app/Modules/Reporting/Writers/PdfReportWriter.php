<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Writers;

use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Shared\Pdf\TablePdf;

/**
 * PDF, through the shared table PDF (SRS 32): the report's title, the
 * parameters it was run with, its columns and rows on landscape A4, Bangla
 * shaped. Past `TablePdf::MAX_ROWS` it stops and says so — a spreadsheet is
 * the format for a report that size.
 */
class PdfReportWriter implements ReportWriter
{
    public function format(): string
    {
        return 'PDF';
    }

    public function extension(): string
    {
        return 'pdf';
    }

    public function mimeType(): string
    {
        return 'application/pdf';
    }

    public function write(string $path, Report $report, ReportQuery $query, array $meta = []): int
    {
        $columns = $report->columns();
        $rows = (function () use ($report, $query, $columns): \Generator {
            foreach ($report->rows($query) as $row) {
                yield array_map(fn (string $key) => $row[$key] ?? null, array_keys($columns));
            }
        })();

        return (new TablePdf(
            $report->title(),
            array_values(array_map(fn (array $c): array => ['label' => __($c['label']), 'numeric' => $c['numeric'] ?? false], $columns)),
            $rows,
            $meta,
        ))->save($path);
    }
}

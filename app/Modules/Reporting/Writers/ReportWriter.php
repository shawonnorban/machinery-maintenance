<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Writers;

use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;

/**
 * Turns a report's rows into a file (SRS 32).
 *
 * Writers take a path rather than returning a string. A year of downtime does
 * not fit in memory twice, and the difference between writing as you go and
 * building the whole document first is the difference between a large export
 * and a fatal error.
 */
interface ReportWriter
{
    /** CSV | XLSX | PDF, as stored on the job row. */
    public function format(): string;

    public function extension(): string;

    public function mimeType(): string;

    /**
     * Write every row to the given path and return how many there were.
     *
     * @param  array<string, string>  $meta  Title and parameter lines for a header block.
     */
    public function write(string $path, Report $report, ReportQuery $query, array $meta = []): int;
}

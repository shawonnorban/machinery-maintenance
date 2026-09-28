<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports;

use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;

/**
 * One report (SRS 32).
 *
 * Rows are yielded lazily and never collected. A year of downtime across a
 * fleet is hundreds of thousands of rows, and the difference between streaming
 * them and building an array is the difference between a slow download and an
 * exhausted worker.
 *
 * Every query here runs through Eloquent so the tenant scope applies without
 * each report having to remember it. A report is the easiest place in a system
 * to leak another company's data: it is read-only, it aggregates, and nobody
 * looks at it twice.
 */
abstract class Report
{
    /** Stable identifier, stored on the job row and used in URLs. */
    abstract public function key(): string;

    /** Reports are grouped on the index by the thing they are about. */
    abstract public function group(): string;

    abstract public function permission(): string;

    /**
     * Column key => meta. `label` is a translation key; `numeric` right-aligns
     * and marks the value as a figure rather than text.
     *
     * @return array<string, array{label: string, numeric?: bool}>
     */
    abstract public function columns(): array;

    /**
     * @return iterable<int, array<string, scalar|null>>
     */
    abstract public function rows(ReportQuery $query): iterable;

    /**
     * Which filter inputs the run form shows.
     *
     * @return list<string>
     */
    public function filters(): array
    {
        return ['period', 'factory'];
    }

    /**
     * How many rows this would produce.
     *
     * Used to decide between answering now and queueing (ADR-032), so it must
     * be cheap: a count, never a materialised result.
     */
    public function estimatedRows(ReportQuery $query): int
    {
        $countable = $this->countable($query);

        return $countable?->count() ?? 0;
    }

    /**
     * The query behind estimatedRows(), where one exists.
     *
     * Aggregate reports override estimatedRows() instead: counting the rows of
     * a grouped query means running the grouping, which is the work itself.
     */
    protected function countable(ReportQuery $query): Builder|QueryBuilder|null
    {
        return null;
    }

    /** Translated title, for the index, the run screen and the file name. */
    public function title(): string
    {
        return __("report.{$this->key()}.title");
    }

    public function description(): string
    {
        return __("report.{$this->key()}.description");
    }
}

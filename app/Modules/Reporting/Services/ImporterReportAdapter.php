<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Modules\Reporting\Imports\Importer;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;

/**
 * Lets an importer be written by the report writers.
 *
 * The CSV and XLSX writers already stream rows, escape formulas and handle
 * encoding. A second pair of writers for raw exports would be the same code
 * with the same bugs to fix twice, so an importer is presented to them as a
 * report instead.
 *
 * Deliberately not registered in the report registry: this is a file format,
 * not a report somebody should find on the reports screen.
 */
class ImporterReportAdapter extends Report
{
    public function __construct(private readonly Importer $importer) {}

    public function key(): string
    {
        return $this->importer->type();
    }

    public function group(): string
    {
        return 'export';
    }

    public function permission(): string
    {
        return $this->importer->permission();
    }

    public function columns(): array
    {
        $columns = [];

        // Keyed by the importer's own column names, because the file has to be
        // readable by the importer that produced its shape.
        foreach ($this->importer->columns() as $name => $column) {
            $columns[$name] = ['label' => $name];
        }

        return $columns;
    }

    public function rows(ReportQuery $query): iterable
    {
        return $this->importer->exportRows();
    }

    public function title(): string
    {
        return $this->importer->title();
    }

    public function description(): string
    {
        return $this->importer->description();
    }
}

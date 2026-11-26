<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Reports;

use App\Modules\QualityTally\Support\QtBook;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;

/**
 * Quality Tally's reports on the Reporting module's runner (PDF, Excel, CSV;
 * a big one queued): the period's dates read as PRODUCTION dates on the
 * factory's clock, and `section` narrows to Cutting, Sewing or Quality.
 * qtally.view — refused, with the rest of the module, while the platform has
 * not given it to the company.
 */
abstract class QtReport extends Report
{
    public function group(): string
    {
        return 'quality';
    }

    public function permission(): string
    {
        return 'qtally.view';
    }

    public function filters(): array
    {
        return ['period', 'section'];
    }

    /** @return array{0: string, 1: string} the production dates the period covers */
    protected function days(ReportQuery $query): array
    {
        return [$query->from->setTimezone(QtBook::TZ)->toDateString(), $query->to->setTimezone(QtBook::TZ)->toDateString()];
    }

    protected function section(ReportQuery $query): ?string
    {
        $s = $query->get('section');

        return in_array($s, QtBook::SECTIONS, true) ? $s : null;
    }

    protected function sectionName(string $section): string
    {
        return (string) __("qtally.section.{$section}");
    }

    /** "SECTION:id" => "LINE01 — Line 01" for the report's company. */
    protected function lineNames(string $companyId): array
    {
        return QtBook::allLines($companyId)->map(fn ($l) => QtBook::label($l))->all();
    }
}

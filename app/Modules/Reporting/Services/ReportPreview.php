<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Modules\Asset\Models\Asset;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Support\TenantTimezone;

/**
 * The on-screen view of a report, and the header block that travels with its
 * file.
 *
 * The preview is capped. A screen showing forty thousand rows is not a report,
 * it is a browser tab that stops responding — and the export exists precisely
 * for the case where every row is wanted.
 */
class ReportPreview
{
    public const PREVIEW_ROWS = 200;

    public function __construct(private readonly TenantTimezone $timezone) {}

    /**
     * @return array{rows: list<array<string, scalar|null>>, truncated: bool, shown: int}
     */
    public function rows(Report $report, ReportQuery $query, int $limit = self::PREVIEW_ROWS): array
    {
        $rows = [];
        $truncated = false;

        foreach ($report->rows($query) as $row) {
            if (count($rows) >= $limit) {
                // One row past the limit is enough to know there are more, and
                // stops the generator right there rather than draining it.
                $truncated = true;
                break;
            }

            $rows[] = $row;
        }

        return ['rows' => $rows, 'truncated' => $truncated, 'shown' => count($rows)];
    }

    /**
     * What the report was asked for, in words.
     *
     * Written into every export. A spreadsheet forwarded without its period and
     * scope is a set of numbers nobody can check (SRS 44).
     *
     * @return array<string, string>
     */
    public function metaFor(Report $report, ReportQuery $query): array
    {
        $meta = [
            __('report.meta.report') => $report->title(),
            __('report.meta.generated_at') => $this->timezone->format(now()).' ('.$this->timezone->current().')',
        ];

        if (in_array('period', $report->filters(), true)) {
            $meta[__('report.meta.period')] = $this->timezone->format($query->from, 'Y-m-d')
                .' — '.$this->timezone->format($query->to, 'Y-m-d');
        }

        $meta[__('report.meta.factory')] = $query->factoryId === null
            ? __('report.meta.all_factories')
            : (Factory::find($query->factoryId)?->name ?? __('common.not_available'));

        if ($query->assetId !== null) {
            $meta[__('report.meta.asset')] = Asset::find($query->assetId)?->asset_code
                ?? __('common.not_available');
        }

        return $meta;
    }
}

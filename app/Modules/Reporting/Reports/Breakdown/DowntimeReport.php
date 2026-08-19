<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Breakdown;

use App\Modules\Breakdown\Models\DowntimeRecord;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Shared\Support\TenantTimezone;
use Illuminate\Database\Eloquent\Builder;

/**
 * Measured downtime, stoppage by stoppage (SRS 32, SRS 17).
 *
 * Response, repair and total minutes are shown separately because they are
 * different problems with different owners: slow response is a dispatch
 * problem, slow repair is a skills or parts problem, and a large gap between
 * repair finishing and production resuming is neither.
 *
 * Only the current calculation version of each stoppage appears. A
 * recalculation writes a new row beside the old one, and showing both would
 * double the factory's downtime overnight.
 */
class DowntimeReport extends Report
{
    public function __construct(private readonly TenantTimezone $timezone) {}

    public function key(): string
    {
        return 'downtime';
    }

    public function group(): string
    {
        return 'breakdown';
    }

    public function permission(): string
    {
        return 'breakdown.breakdown.view_any';
    }

    public function filters(): array
    {
        return ['period', 'factory', 'asset'];
    }

    public function columns(): array
    {
        return [
            'asset_code' => ['label' => 'report.columns.asset_code'],
            'failure_at' => ['label' => 'report.columns.failure_at'],
            'production_resumed_at' => ['label' => 'report.columns.production_resumed_at'],
            'downtime_class' => ['label' => 'report.columns.downtime_class'],
            'reason' => ['label' => 'report.columns.downtime_reason'],
            'response_minutes' => ['label' => 'report.columns.response_minutes', 'numeric' => true],
            'repair_minutes' => ['label' => 'report.columns.repair_minutes', 'numeric' => true],
            'hold_minutes' => ['label' => 'report.columns.hold_minutes', 'numeric' => true],
            'total_minutes' => ['label' => 'report.columns.total_downtime', 'numeric' => true],
            'counts_against_availability' => ['label' => 'report.columns.counts_against_availability'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $record) {
            yield [
                'asset_code' => $record->asset?->asset_code,
                'failure_at' => $this->timezone->format($record->failure_at),
                'production_resumed_at' => $this->timezone->format($record->production_resumed_at),
                'downtime_class' => __('breakdown.class_'.strtolower($record->downtime_class)),
                'reason' => $record->reasonCode?->label(),
                'response_minutes' => $record->response_minutes,
                'repair_minutes' => $record->repair_minutes,
                'hold_minutes' => $record->hold_minutes,
                'total_minutes' => $record->total_downtime_minutes,
                'counts_against_availability' => $record->counts_against_availability
                    ? __('common.yes')
                    : __('common.no'),
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        return DowntimeRecord::query()
            ->with(['asset', 'reasonCode'])
            ->whereBetween('failure_at', [$query->from, $query->to])
            ->whereRaw('calculation_version = (
                select max(v.calculation_version) from downtime_records v
                where v.breakdown_id = downtime_records.breakdown_id
            )')
            ->when($query->factoryId !== null, fn ($q) => $q->where('factory_id', $query->factoryId))
            ->when($query->assetId !== null, fn ($q) => $q->where('asset_id', $query->assetId))
            ->orderBy('failure_at');
    }
}

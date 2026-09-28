<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Maintenance;

use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Support\TenantTimezone;
use Illuminate\Database\Eloquent\Builder;

/**
 * What was done to a machine, and what it cost (SRS 32).
 *
 * The report an inspector asks for against a specific machine, so it reads as a
 * chronology rather than a summary. Cancelled work orders are included and
 * labelled: a cancelled job is part of the history of a machine, and a history
 * that only shows completed work cannot explain a gap.
 */
class MaintenanceHistoryReport extends Report
{
    public function __construct(private readonly TenantTimezone $timezone) {}

    public function key(): string
    {
        return 'maintenance_history';
    }

    public function group(): string
    {
        return 'maintenance';
    }

    public function permission(): string
    {
        return 'work_order.work_order.view_any';
    }

    public function filters(): array
    {
        return ['period', 'factory', 'asset'];
    }

    public function columns(): array
    {
        return [
            'work_order_number' => ['label' => 'report.columns.work_order'],
            'asset_code' => ['label' => 'report.columns.asset_code'],
            'title' => ['label' => 'report.columns.title'],
            'maintenance_type' => ['label' => 'report.columns.maintenance_type'],
            'source' => ['label' => 'report.columns.source'],
            'status' => ['label' => 'report.columns.status'],
            'scheduled_start' => ['label' => 'report.columns.scheduled_start'],
            'actual_start' => ['label' => 'report.columns.actual_start'],
            'completed_at' => ['label' => 'report.columns.completed_at'],
            'parts_cost' => ['label' => 'report.columns.parts_cost', 'numeric' => true],
            'total_cost' => ['label' => 'report.columns.total_cost', 'numeric' => true],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $order) {
            yield [
                'work_order_number' => $order->work_order_number,
                'asset_code' => $order->asset?->asset_code,
                'title' => $order->title,
                'maintenance_type' => $order->maintenanceType?->name,
                'source' => __('work_order.source_'.strtolower($order->source)),
                'status' => __('work_order.status_'.strtolower($order->status)),
                'scheduled_start' => $this->timezone->format($order->scheduled_start),
                'actual_start' => $this->timezone->format($order->actual_start),
                'completed_at' => $this->timezone->format($order->completed_at),
                'parts_cost' => $order->actual_parts_cost,
                'total_cost' => $order->actual_cost,
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        return WorkOrder::query()
            ->with(['asset', 'maintenanceType'])
            // Created in the window, not completed in it: a job opened in March
            // and finished in April belongs to March's history, and filtering
            // on completion silently drops everything still open.
            ->whereBetween('created_at', [$query->from, $query->to])
            ->when($query->factoryId !== null, fn ($q) => $q->where('factory_id', $query->factoryId))
            ->when($query->assetId !== null, fn ($q) => $q->where('asset_id', $query->assetId))
            ->orderBy('created_at');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Maintenance;

use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Shared\Support\TenantTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * What is late right now (SRS 32).
 *
 * A worklist rather than a history, so it ignores the period filter and looks
 * at the present. A report of what was overdue last month tells nobody what to
 * do this morning.
 *
 * Late means past the grace period, not past the due date. A plan with two days
 * of grace is not late on day one, and reporting it as such trains people to
 * ignore the list.
 */
class OverdueMaintenanceReport extends Report
{
    public function __construct(private readonly TenantTimezone $timezone) {}

    public function key(): string
    {
        return 'overdue_maintenance';
    }

    public function group(): string
    {
        return 'maintenance';
    }

    public function permission(): string
    {
        return 'maintenance.plan.view_any';
    }

    public function filters(): array
    {
        return ['factory'];
    }

    public function columns(): array
    {
        return [
            'asset_code' => ['label' => 'report.columns.asset_code'],
            'asset_name' => ['label' => 'report.columns.name'],
            'plan' => ['label' => 'report.columns.plan'],
            'due_at' => ['label' => 'report.columns.due_at'],
            'grace_until' => ['label' => 'report.columns.grace_until'],
            'days_late' => ['label' => 'report.columns.days_late', 'numeric' => true],
            'status' => ['label' => 'report.columns.status'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $now = CarbonImmutable::now();

        foreach ($this->base($query)->lazy() as $schedule) {
            $deadline = $schedule->grace_until ?? $schedule->due_at;

            yield [
                'asset_code' => $schedule->asset?->asset_code,
                'asset_name' => $schedule->asset?->name,
                'plan' => $schedule->plan?->name,
                'due_at' => $this->timezone->format($schedule->due_at),
                'grace_until' => $this->timezone->format($schedule->grace_until),
                'days_late' => (int) $deadline->diffInDays($now, absolute: true),
                'status' => __('maintenance.status_'.strtolower($schedule->status)),
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        $now = CarbonImmutable::now();

        return MaintenanceSchedule::query()
            ->with(['asset', 'plan'])
            ->whereIn('status', MaintenanceSchedule::OPEN_STATUSES)
            // Grace where it exists, the due date where it does not.
            ->where(fn ($q) => $q
                ->where(fn ($inner) => $inner->whereNotNull('grace_until')->where('grace_until', '<', $now))
                ->orWhere(fn ($inner) => $inner->whereNull('grace_until')->where('due_at', '<', $now)))
            ->when(
                $query->factoryId !== null,
                fn ($q) => $q->whereHas('asset', fn ($a) => $a->where('current_factory_id', $query->factoryId)),
            )
            ->orderBy('due_at');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\People;

use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderLaborEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Work completed per technician (SRS 32, SRS 21).
 *
 * Deliberately about work, not about people. This system holds no salary,
 * attendance or disciplinary data, so the report shows jobs finished and hours
 * booked and stops there. A maintenance system that quietly becomes an HR file
 * is a different product with different obligations.
 *
 * Labour hours come from booked entries rather than from work order duration:
 * a job left open over a weekend did not take sixty hours of anybody's time.
 */
class TechnicianPerformanceReport extends Report
{
    public function key(): string
    {
        return 'technician_performance';
    }

    public function group(): string
    {
        return 'people';
    }

    public function permission(): string
    {
        return 'technician.performance.view';
    }

    public function filters(): array
    {
        return ['period', 'factory'];
    }

    public function columns(): array
    {
        return [
            'employee_id' => ['label' => 'report.columns.employee_id'],
            'name' => ['label' => 'report.columns.technician'],
            'factory' => ['label' => 'report.columns.factory'],
            'grade' => ['label' => 'report.columns.grade'],
            'assigned' => ['label' => 'report.columns.jobs_assigned', 'numeric' => true],
            'completed' => ['label' => 'report.columns.jobs_completed', 'numeric' => true],
            'labor_hours' => ['label' => 'report.columns.labor_hours', 'numeric' => true],
            'labor_cost' => ['label' => 'report.columns.labor_cost', 'numeric' => true],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $technician) {
            $assignedIds = DB::table('work_order_assignments')
                ->where('technician_id', $technician->id)
                ->whereBetween('assigned_at', [$query->from, $query->to])
                ->pluck('work_order_id');

            $completed = WorkOrder::query()
                ->whereIn('id', $assignedIds)
                ->whereIn('status', ['COMPLETED', 'VERIFIED', 'CLOSED'])
                ->count();

            $labor = WorkOrderLaborEntry::query()
                ->where('technician_id', $technician->id)
                ->whereBetween('started_at', [$query->from, $query->to])
                ->selectRaw('coalesce(sum(minutes), 0) as minutes, coalesce(sum(base_amount), 0) as amount')
                ->first();

            yield [
                'employee_id' => $technician->employee_id,
                'name' => $technician->name,
                'factory' => $technician->factory?->name,
                'grade' => $technician->grade?->name,
                'assigned' => $assignedIds->count(),
                'completed' => $completed,
                'labor_hours' => round(((int) $labor->minutes) / 60, 1),
                'labor_cost' => $labor->amount,
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        return Technician::query()
            ->with(['factory', 'grade'])
            ->when($query->factoryId !== null, fn ($q) => $q->where('factory_id', $query->factoryId))
            ->orderBy('name');
    }
}

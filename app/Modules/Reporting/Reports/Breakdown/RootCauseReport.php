<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Breakdown;

use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Support\Facades\DB;

/**
 * Failures grouped by why they happened (SRS 32).
 *
 * Unclassified breakdowns get their own row rather than being filtered out.
 * A taxonomy report that hides what nobody classified reads as a clean factory,
 * when what it actually shows is a closing process people are skipping — which
 * is the more useful finding.
 */
class RootCauseReport extends Report
{
    public function key(): string
    {
        return 'root_cause';
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
            'root_cause' => ['label' => 'report.columns.root_cause'],
            'failure_code' => ['label' => 'report.columns.failure_code'],
            'failures' => ['label' => 'report.columns.failures', 'numeric' => true],
            'downtime_minutes' => ['label' => 'report.columns.downtime_minutes', 'numeric' => true],
            'assets_affected' => ['label' => 'report.columns.assets_affected', 'numeric' => true],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $rows = Breakdown::query()
            ->leftJoin('root_causes', 'breakdowns.root_cause_id', '=', 'root_causes.id')
            ->leftJoin('failure_codes', 'breakdowns.failure_code_id', '=', 'failure_codes.id')
            ->leftJoin('downtime_records', function ($join): void {
                $join->on('downtime_records.breakdown_id', '=', 'breakdowns.id')
                    // Explicit, even though breakdown ids are ULIDs and cannot
                    // collide across tenants. A join is the one place the
                    // global scope does not reach, and a report is the easiest
                    // place in a system to leak another company's data.
                    ->on('downtime_records.company_id', '=', 'breakdowns.company_id')
                    // Latest version only: a recalculation writes a new row
                    // beside the old one, and joining all of them counts the
                    // same stoppage twice (SRS 17.3).
                    ->whereRaw('downtime_records.calculation_version = (
                        select max(v.calculation_version) from downtime_records v
                        where v.breakdown_id = breakdowns.id
                    )');
            })
            ->whereBetween('breakdowns.failure_at', [$query->from, $query->to])
            ->whereNotIn('breakdowns.status', ['CANCELLED'])
            ->when($query->factoryId !== null, fn ($q) => $q->where('breakdowns.factory_id', $query->factoryId))
            ->when($query->assetId !== null, fn ($q) => $q->where('breakdowns.asset_id', $query->assetId))
            ->groupBy('root_causes.name', 'failure_codes.name')
            ->orderByDesc(DB::raw('count(*)'))
            ->select([
                DB::raw('root_causes.name as root_cause'),
                DB::raw('failure_codes.name as failure_code'),
                DB::raw('count(*) as failures'),
                DB::raw('coalesce(sum(downtime_records.total_downtime_minutes), 0) as downtime_minutes'),
                DB::raw('count(distinct breakdowns.asset_id) as assets_affected'),
            ])
            ->get();

        foreach ($rows as $row) {
            yield [
                'root_cause' => $row->root_cause ?? __('report.unclassified'),
                'failure_code' => $row->failure_code ?? __('report.unclassified'),
                'failures' => (int) $row->failures,
                'downtime_minutes' => (int) $row->downtime_minutes,
                'assets_affected' => (int) $row->assets_affected,
            ];
        }
    }

    public function estimatedRows(ReportQuery $query): int
    {
        // Bounded by the taxonomy, not by the number of failures: the output is
        // one row per cause and code pairing that actually occurred, so this
        // report never needs queueing.
        return Breakdown::query()
            ->whereBetween('failure_at', [$query->from, $query->to])
            ->distinct()
            ->count('root_cause_id');
    }
}

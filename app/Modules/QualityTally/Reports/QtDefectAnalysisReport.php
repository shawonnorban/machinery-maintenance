<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Reports;

use App\Modules\Reporting\Reports\ReportQuery;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/** Each defect over the period: how often it was found, its share of the section's defects, and on how many lines and jobs. */
class QtDefectAnalysisReport extends QtReport
{
    public function key(): string
    {
        return 'qt_defect_analysis';
    }

    public function columns(): array
    {
        return [
            'section' => ['label' => 'report.columns.qt_section'], 'code' => ['label' => 'report.columns.qt_defect_code'], 'defect' => ['label' => 'report.columns.qt_defect_name'],
            'found' => ['label' => 'report.columns.qt_found', 'numeric' => true], 'share' => ['label' => 'report.columns.qt_share', 'numeric' => true],
            'lines' => ['label' => 'report.columns.qt_lines', 'numeric' => true], 'jobs' => ['label' => 'report.columns.qt_jobs', 'numeric' => true],
        ];
    }

    public function estimatedRows(ReportQuery $query): int
    {
        return (int) DB::table('qt_defect_types')->where('company_id', app(TenantContext::class)->companyId())->count();
    }

    public function rows(ReportQuery $query): iterable
    {
        [$from, $to] = $this->days($query);
        $section = $this->section($query);
        $rows = DB::table('qt_entry_defects as x')->join('qt_entries as e', 'e.id', '=', 'x.entry_id')->join('qt_defect_types as t', 't.id', '=', 'x.defect_type_id')
            ->where('e.company_id', app(TenantContext::class)->companyId())->whereBetween('e.production_date', [$from, $to])
            ->when($section, fn ($w, $s) => $w->where('e.section', $s))
            ->groupBy('t.section', 't.code', 't.name')
            ->select('t.section', 't.code', 't.name', DB::raw('count(*) as found'), DB::raw('count(distinct e.line_id) as lines'), DB::raw('count(distinct e.job_id) as jobs'))
            ->orderBy('t.section')->orderByDesc('found')->get();
        $totals = $rows->groupBy('section')->map(fn ($g) => (int) $g->sum('found'));

        foreach ($rows as $r) {
            yield [
                'section' => $this->sectionName($r->section), 'code' => $r->code, 'defect' => $r->name, 'found' => (int) $r->found,
                'share' => $totals[$r->section] ? round($r->found / $totals[$r->section] * 100, 2) : null, 'lines' => (int) $r->lines, 'jobs' => (int) $r->jobs,
            ];
        }
    }
}

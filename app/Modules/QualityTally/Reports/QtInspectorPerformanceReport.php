<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Reports;

use App\Modules\QualityTally\Models\QtEntry;
use App\Modules\QualityTally\Support\QtBook;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/** Each QC over the period: days tapped, taps, garments checked, defects found and the DHU they recorded. */
class QtInspectorPerformanceReport extends QtReport
{
    public function key(): string
    {
        return 'qt_inspector_performance';
    }

    public function columns(): array
    {
        return [
            'qc' => ['label' => 'report.columns.qt_qc'], 'employee_code' => ['label' => 'report.columns.qt_employee_code'], 'days' => ['label' => 'report.columns.qt_days', 'numeric' => true],
            'taps' => ['label' => 'report.columns.qt_taps', 'numeric' => true], 'checked' => ['label' => 'report.columns.qt_checked', 'numeric' => true],
            'defect' => ['label' => 'report.columns.qt_defect', 'numeric' => true], 'reject' => ['label' => 'report.columns.qt_reject', 'numeric' => true],
            'found' => ['label' => 'report.columns.qt_found', 'numeric' => true], 'dhu' => ['label' => 'report.columns.qt_dhu', 'numeric' => true],
            'per_day' => ['label' => 'report.columns.qt_checked_per_day', 'numeric' => true],
        ];
    }

    public function estimatedRows(ReportQuery $query): int
    {
        [$from, $to] = $this->days($query);

        return (int) QtEntry::query()->whereBetween('production_date', [$from, $to])->distinct()->count('user_id');
    }

    public function rows(ReportQuery $query): iterable
    {
        [$from, $to] = $this->days($query);
        $section = $this->section($query);
        $companyId = app(TenantContext::class)->companyId();
        $counts = QtEntry::query()->whereBetween('production_date', [$from, $to])->when($section, fn ($w, $s) => $w->where('section', $s))
            ->select('user_id', 'action', DB::raw('count(*) as n'))->groupBy('user_id', 'action')->get()->groupBy('user_id');
        $days = QtEntry::query()->whereBetween('production_date', [$from, $to])->when($section, fn ($w, $s) => $w->where('section', $s))
            ->select('user_id', DB::raw('count(distinct production_date) as d'))->groupBy('user_id')->pluck('d', 'user_id');
        $found = DB::table('qt_entry_defects as x')->join('qt_entries as e', 'e.id', '=', 'x.entry_id')->where('e.company_id', $companyId)
            ->whereBetween('e.production_date', [$from, $to])->when($section, fn ($w, $s) => $w->where('e.section', $s))
            ->select('e.user_id', DB::raw('count(*) as n'))->groupBy('e.user_id')->pluck('n', 'user_id');
        $users = DB::table('users')->whereIn('id', $counts->keys())->pluck('name', 'id');
        $codes = DB::table('qt_inspectors as i')->join('hr_employees as h', 'h.id', '=', 'i.hr_employee_id')->where('i.company_id', $companyId)
            ->pluck('h.employee_code', 'i.user_id');

        foreach ($counts->sortBy(fn ($g, $id) => mb_strtolower((string) ($users[$id] ?? ''))) as $userId => $g) {
            $m = QtBook::metrics($g->pluck('n', 'action')->map(fn ($n) => (int) $n)->all(), (int) ($found[$userId] ?? 0));
            $d = (int) ($days[$userId] ?? 0);

            yield [
                'qc' => $users[$userId] ?? '—', 'employee_code' => $codes[$userId] ?? null, 'days' => $d, 'taps' => (int) $g->sum('n'),
                'checked' => $m['checked'], 'defect' => $m['defect'], 'reject' => $m['reject'], 'found' => $m['defect_tags'], 'dhu' => $m['dhu'],
                'per_day' => $d ? (int) round($m['checked'] / $d) : null,
            ];
        }
    }
}

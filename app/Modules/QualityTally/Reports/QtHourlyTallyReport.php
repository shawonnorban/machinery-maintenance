<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Reports;

use App\Modules\QualityTally\Models\QtEntry;
use App\Modules\QualityTally\Support\QtBook;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/** The tally sheet for many lines at once: day, line and hour, with its four counts, pass and DHU. */
class QtHourlyTallyReport extends QtReport
{
    public function key(): string
    {
        return 'qt_hourly_tally';
    }

    public function columns(): array
    {
        return [
            'date' => ['label' => 'report.columns.date'], 'section' => ['label' => 'report.columns.qt_section'], 'line' => ['label' => 'report.columns.qt_line'],
            'hour' => ['label' => 'report.columns.qt_hour'], 'rft' => ['label' => 'report.columns.qt_rft', 'numeric' => true],
            'defect' => ['label' => 'report.columns.qt_defect', 'numeric' => true], 'rectify' => ['label' => 'report.columns.qt_rectify', 'numeric' => true],
            'reject' => ['label' => 'report.columns.qt_reject', 'numeric' => true], 'checked' => ['label' => 'report.columns.qt_checked', 'numeric' => true],
            'pass' => ['label' => 'report.columns.qt_pass', 'numeric' => true], 'dhu' => ['label' => 'report.columns.qt_dhu', 'numeric' => true],
        ];
    }

    public function estimatedRows(ReportQuery $query): int
    {
        [$from, $to] = $this->days($query);

        return (int) QtEntry::query()->whereBetween('production_date', [$from, $to])->when($this->section($query), fn ($w, $s) => $w->where('section', $s))
            ->distinct()->count(DB::raw("production_date || section || line_id || hour"));
    }

    public function rows(ReportQuery $query): iterable
    {
        [$from, $to] = $this->days($query);
        $section = $this->section($query);
        $companyId = app(TenantContext::class)->companyId();
        $lines = $this->lineNames($companyId);
        $key = fn ($r) => $r->d.'|'.$r->section.'|'.$r->line_id.'|'.str_pad((string) $r->hour, 2, '0', STR_PAD_LEFT);
        $counts = QtEntry::query()->whereBetween('production_date', [$from, $to])->when($section, fn ($w, $s) => $w->where('section', $s))
            ->select(DB::raw('production_date::text as d'), 'section', 'line_id', 'hour', 'action', DB::raw('count(*) as n'))
            ->groupBy('production_date', 'section', 'line_id', 'hour', 'action')->get()->groupBy($key);
        $tags = DB::table('qt_entry_defects as x')->join('qt_entries as e', 'e.id', '=', 'x.entry_id')->where('e.company_id', $companyId)
            ->whereBetween('e.production_date', [$from, $to])->when($section, fn ($w, $s) => $w->where('e.section', $s))
            ->select(DB::raw('e.production_date::text as d'), 'e.section', 'e.line_id', 'e.hour', DB::raw('count(*) as n'))
            ->groupBy('e.production_date', 'e.section', 'e.line_id', 'e.hour')->get()->mapWithKeys(fn ($r) => [$key($r) => (int) $r->n]);

        foreach ($counts->sortKeys() as $k => $g) {
            [$d, $s, $lineId, $h] = explode('|', $k);
            $m = QtBook::metrics($g->pluck('n', 'action')->map(fn ($n) => (int) $n)->all(), $tags[$k] ?? 0);

            yield [
                'date' => $d, 'section' => $this->sectionName($s), 'line' => $lines[$s.':'.$lineId] ?? '—', 'hour' => sprintf('%s:00–%02d:00', $h, ((int) $h + 1) % 24),
                'rft' => $m['rft'], 'defect' => $m['defect'], 'rectify' => $m['rectify'], 'reject' => $m['reject'], 'checked' => $m['checked'], 'pass' => $m['pass'], 'dhu' => $m['dhu'],
            ];
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Reports;

use App\Modules\QualityTally\Models\QtEntry;
use App\Modules\QualityTally\Models\QtJob;
use App\Modules\QualityTally\Models\QtLineTarget;
use App\Modules\QualityTally\Support\QtBook;
use App\Modules\Reporting\Reports\ReportQuery;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/** Day by day, every line and job: target, the four counts, pass, DHU, RFT %, achievement and efficiency. */
class QtDailySummaryReport extends QtReport
{
    public function key(): string
    {
        return 'qt_daily_summary';
    }

    public function columns(): array
    {
        return [
            'date' => ['label' => 'report.columns.date'], 'section' => ['label' => 'report.columns.qt_section'], 'line' => ['label' => 'report.columns.qt_line'],
            'job_no' => ['label' => 'report.columns.qt_job_no'], 'buyer' => ['label' => 'report.columns.qt_buyer'], 'style' => ['label' => 'report.columns.qt_style'],
            'target' => ['label' => 'report.columns.qt_target', 'numeric' => true], 'checked' => ['label' => 'report.columns.qt_checked', 'numeric' => true],
            'rft' => ['label' => 'report.columns.qt_rft', 'numeric' => true], 'defect' => ['label' => 'report.columns.qt_defect', 'numeric' => true],
            'rectify' => ['label' => 'report.columns.qt_rectify', 'numeric' => true], 'reject' => ['label' => 'report.columns.qt_reject', 'numeric' => true],
            'pass' => ['label' => 'report.columns.qt_pass', 'numeric' => true], 'dhu' => ['label' => 'report.columns.qt_dhu', 'numeric' => true],
            'rft_pct' => ['label' => 'report.columns.qt_rft_pct', 'numeric' => true], 'achievement' => ['label' => 'report.columns.qt_achievement', 'numeric' => true],
            'efficiency' => ['label' => 'report.columns.qt_efficiency', 'numeric' => true],
        ];
    }

    public function estimatedRows(ReportQuery $query): int
    {
        [$from, $to] = $this->days($query);

        return (int) QtEntry::query()->whereBetween('production_date', [$from, $to])->when($this->section($query), fn ($w, $s) => $w->where('section', $s))
            ->distinct()->count(DB::raw("production_date || section || line_id || job_id"));
    }

    public function rows(ReportQuery $query): iterable
    {
        [$from, $to] = $this->days($query);
        $section = $this->section($query);
        $companyId = app(TenantContext::class)->companyId();
        $lines = $this->lineNames($companyId);
        $key = fn ($r) => $r->d.'|'.$r->section.'|'.$r->line_id.'|'.$r->job_id;

        $counts = QtEntry::query()->whereBetween('production_date', [$from, $to])->when($section, fn ($w, $s) => $w->where('section', $s))
            ->select(DB::raw('production_date::text as d'), 'section', 'line_id', 'job_id', 'action', DB::raw('count(*) as n'))
            ->groupBy('production_date', 'section', 'line_id', 'job_id', 'action')->get()->groupBy($key);
        $tags = DB::table('qt_entry_defects as x')->join('qt_entries as e', 'e.id', '=', 'x.entry_id')->where('e.company_id', $companyId)
            ->whereBetween('e.production_date', [$from, $to])->when($section, fn ($w, $s) => $w->where('e.section', $s))
            ->select(DB::raw('e.production_date::text as d'), 'e.section', 'e.line_id', 'e.job_id', DB::raw('count(*) as n'))
            ->groupBy('e.production_date', 'e.section', 'e.line_id', 'e.job_id')->get()->mapWithKeys(fn ($r) => [$key($r) => (int) $r->n]);
        $targets = QtLineTarget::query()->whereBetween('target_date', [$from, $to])->when($section, fn ($w, $s) => $w->where('section', $s))->get()
            ->keyBy(fn ($t) => $t->target_date->toDateString().'|'.$t->section.'|'.$t->line_id.'|'.$t->job_id);
        $keys = $counts->keys()->merge($targets->keys())->unique()->sort()->values();
        $jobs = QtJob::query()->whereIn('id', $keys->map(fn ($k) => explode('|', $k)[3])->unique())->get()->keyBy('id');

        foreach ($keys as $k) {
            [$d, $s, $lineId, $jobId] = explode('|', $k);
            $t = $targets->get($k);
            $job = $jobs->get($jobId);
            $m = QtBook::metrics(($counts[$k] ?? collect())->pluck('n', 'action')->map(fn ($n) => (int) $n)->all(), $tags[$k] ?? 0, $t?->target_qty);
            $man = QtBook::manMinutes($companyId, $d, $s, $lineId, $t?->manpower, $t ? (float) $t->working_hours : null);

            yield [
                'date' => $d, 'section' => $this->sectionName($s), 'line' => $lines[$s.':'.$lineId] ?? '—', 'job_no' => $job?->job_no, 'buyer' => $job?->buyer_name, 'style' => $job?->style,
                'target' => $m['target'], 'checked' => $m['checked'], 'rft' => $m['rft'], 'defect' => $m['defect'], 'rectify' => $m['rectify'], 'reject' => $m['reject'],
                'pass' => $m['pass'], 'dhu' => $m['dhu'], 'rft_pct' => $m['rft_pct'], 'achievement' => $m['achievement'],
                'efficiency' => QtBook::efficiency($m['pass'], $job?->smv === null ? null : (float) $job->smv, $man),
            ];
        }
    }
}

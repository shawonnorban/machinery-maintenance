<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Http\Controllers\Api;

use App\Modules\QualityTally\Http\Controllers\Concerns\GuardsSections;
use App\Modules\QualityTally\Models\QtEntry;
use App\Modules\QualityTally\Models\QtJob;
use App\Modules\QualityTally\Models\QtLineTarget;
use App\Modules\QualityTally\Support\QtBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Quality Tally's outputs (AnnotechERP 36): the day's dashboard (every line
 * and job with its target, pass, DHU and RFT %), the hourly Tally Sheet of one
 * line, and Tally History (every tap, who and when).
 */
class QtReportApiController extends ApiController
{
    use GuardsSections;

    public function dashboard(Request $request): JsonResponse
    {
        $this->allowView($request->filled('section') ? (string) $request->input('section') : null);
        $q = $request->validate(['date' => ['nullable', 'date'], 'section' => ['nullable', Rule::in(QtBook::SECTIONS)]]);
        $c = $this->caller()->companyId;
        $date = CarbonImmutable::parse($q['date'] ?? QtBook::today())->toDateString();
        $section = $q['section'] ?? null;
        $lines = QtBook::allLines($c);

        $counts = QtEntry::query()->whereDate('production_date', $date)->when($section, fn ($w, $s) => $w->where('section', $s))
            ->select('section', 'line_id', 'job_id', 'action', DB::raw('count(*) as n'))->groupBy('section', 'line_id', 'job_id', 'action')->get();
        $tags = DB::table('qt_entry_defects as d')->join('qt_entries as e', 'e.id', '=', 'd.entry_id')
            ->where('e.company_id', $c)->whereDate('e.production_date', $date)->when($section, fn ($w, $s) => $w->where('e.section', $s))
            ->select('e.section', 'e.line_id', 'e.job_id', DB::raw('count(*) as n'))->groupBy('e.section', 'e.line_id', 'e.job_id')->get()
            ->keyBy(fn ($r) => $r->section.'|'.$r->line_id.'|'.$r->job_id);
        $targets = QtLineTarget::query()->whereDate('target_date', $date)->when($section, fn ($w, $s) => $w->where('section', $s))->get()
            ->keyBy(fn ($t) => $t->section.'|'.$t->line_id.'|'.$t->job_id);

        $keys = $counts->map(fn ($r) => $r->section.'|'.$r->line_id.'|'.$r->job_id)->merge($targets->keys())->unique()->values();
        $grouped = $counts->groupBy(fn ($r) => $r->section.'|'.$r->line_id.'|'.$r->job_id);
        $jobs = QtJob::query()->whereIn('id', $keys->map(fn ($k) => explode('|', $k)[2])->unique())->get()->keyBy('id');

        $rows = $keys->map(function (string $k) use ($grouped, $tags, $targets, $lines, $jobs) {
            [$s, $lineId, $jobId] = explode('|', $k);
            $t = $targets->get($k);
            $line = $lines->get($s.':'.$lineId);
            $job = $jobs->get($jobId);

            return [
                'key' => $k, 'section' => $s, 'line_id' => $lineId, 'line' => QtBook::label($line) ?? '—', 'line_code' => $line?->code,
                'job_id' => $jobId, 'job_no' => $job?->job_no, 'buyer_name' => $job?->buyer_name, 'style' => $job?->style, 'color' => $job?->color,
                'hourly_target' => $t && (float) $t->working_hours > 0 ? (int) round($t->target_qty / (float) $t->working_hours) : null,
            ] + QtBook::metrics(($grouped[$k] ?? collect())->pluck('n', 'action')->map(fn ($n) => (int) $n)->all(), (int) ($tags[$k]->n ?? 0), $t?->target_qty);
        })->sortBy(fn ($r) => array_search($r['section'], QtBook::SECTIONS, true).'|'.$r['line_code'].'|'.$r['job_no'])->values();

        // Each line's efficiency: earned minutes of all its jobs over its man-minutes.
        $smv = $jobs->map(fn ($j) => $j->smv === null ? null : (float) $j->smv);
        $byLine = $rows->groupBy(fn ($r) => $r['section'].'|'.$r['line_id']);
        $lineEff = $byLine->map(function ($g) use ($c, $date, $targets, $smv) {
            $first = $g->first();
            if ($g->contains(fn ($r) => ($smv[$r['job_id']] ?? null) === null)) {
                return null;
            }
            $lineTargets = $targets->filter(fn ($t) => $t->section === $first['section'] && $t->line_id === $first['line_id']);
            $man = QtBook::manMinutes($c, $date, $first['section'], $first['line_id'], $lineTargets->max('manpower') ?: null, $lineTargets->isEmpty() ? null : (float) $lineTargets->max('working_hours'));
            $earned = $g->reduce(fn ($a, $r) => $a + $r['pass'] * $smv[$r['job_id']], 0.0);

            return $man ? round($earned / $man * 100, 2) : null;
        });
        $rows = $rows->map(fn ($r) => $r + ['efficiency' => $lineEff[$r['section'].'|'.$r['line_id']] ?? null])->values();

        $sections = [];
        foreach (QtBook::SECTIONS as $s) {
            if ($section && $s !== $section) {
                continue;
            }
            $sections[$s] = $this->total($rows->where('section', $s));
        }

        $top = DB::table('qt_entry_defects as d')->join('qt_entries as e', 'e.id', '=', 'd.entry_id')->join('qt_defect_types as t', 't.id', '=', 'd.defect_type_id')
            ->where('e.company_id', $c)->whereDate('e.production_date', $date)->when($section, fn ($w, $s) => $w->where('e.section', $s))
            ->select('t.section', 't.code', 't.name', 't.name_bn', DB::raw('count(*) as n'))->groupBy('t.section', 't.code', 't.name', 't.name_bn')->orderByDesc('n')->limit(10)->get()
            ->map(fn ($r) => ['section' => $r->section, 'code' => $r->code, 'name' => $r->name, 'name_bn' => $r->name_bn, 'count' => (int) $r->n])->all();

        $hourTags = DB::table('qt_entry_defects as d')->join('qt_entries as e', 'e.id', '=', 'd.entry_id')
            ->where('e.company_id', $c)->whereDate('e.production_date', $date)->when($section, fn ($w, $s) => $w->where('e.section', $s))
            ->select('e.section', 'e.hour', DB::raw('count(*) as n'))->groupBy('e.section', 'e.hour')->get()
            ->mapWithKeys(fn ($r) => [$r->section.'|'.$r->hour => (int) $r->n]);
        $hourly = QtEntry::query()->whereDate('production_date', $date)->when($section, fn ($w, $s) => $w->where('section', $s))
            ->select('section', 'hour', 'action', DB::raw('count(*) as n'))->groupBy('section', 'hour', 'action')->get()
            ->groupBy(fn ($r) => $r->section.'|'.$r->hour)
            ->map(function ($g, $k) use ($hourTags) {
                [$s, $h] = explode('|', $k);

                return ['section' => $s, 'hour' => (int) $h] + QtBook::metrics($g->pluck('n', 'action')->map(fn ($n) => (int) $n)->all(), $hourTags[$k] ?? 0);
            })->sortBy(fn ($r) => sprintf('%s|%02d', $r['section'], $r['hour']))->values()->all();

        // Cutting is tapped panel by panel: the day's panels checked and rejected, by lot (Cut Panel Inspection).
        $panels = null;
        if ($section === null || $section === 'CUTTING') {
            $cut = fn () => DB::table('qt_entries as e')->where('e.company_id', $c)->where('e.section', 'CUTTING')->whereNotNull('e.panel_id')->whereDate('e.production_date', $date);
            $lots = $cut()->groupBy('e.line_id', 'e.job_id', 'e.cutting_no', 'e.batch_no')
                ->select('e.line_id', 'e.job_id', 'e.cutting_no', 'e.batch_no', DB::raw("sum(case when e.action in ('RFT', 'DEFECT') then 1 else 0 end) as checked"), DB::raw("sum(case when e.action = 'REJECT' then 1 else 0 end) as reject"), DB::raw("sum(case when e.action = 'RECTIFY' then 1 else 0 end) as rectify"))->get();
            $cutJobs = QtJob::query()->whereIn('id', $lots->pluck('job_id'))->get()->keyBy('id');
            $cutTop = $cut()->join('qt_entry_defects as x', 'x.entry_id', '=', 'e.id')->join('qt_defect_types as t', 't.id', '=', 'x.defect_type_id')
                ->select('t.code', 't.name', 't.name_bn', DB::raw('count(*) as n'))->groupBy('t.code', 't.name', 't.name_bn')->orderByDesc('n')->limit(5)->get();
            $checked = (int) $lots->sum('checked');
            $reject = (int) $lots->sum('reject');
            $panels = [
                'checked' => $checked, 'reject' => $reject, 'reject_pct' => $checked ? round($reject / $checked * 100, 2) : null, 'reports' => $lots->count(),
                'rows' => $lots->map(fn ($r) => [
                    'line' => QtBook::label($lines->get('CUTTING:'.$r->line_id)) ?? '—', 'job_no' => $cutJobs[$r->job_id]->job_no ?? null, 'cutting_no' => $r->cutting_no, 'batch_no' => $r->batch_no,
                    'checked' => (int) $r->checked, 'reject' => (int) $r->reject, 'reject_pct' => $r->checked ? round($r->reject / $r->checked * 100, 2) : null,
                ])->values()->all(),
                'top_defects' => $cutTop->map(fn ($t) => ['code' => $t->code, 'name' => $t->name, 'name_bn' => $t->name_bn, 'count' => (int) $t->n])->all(),
            ];
        }

        return ApiResponse::ok([
            'date' => $date, 'section' => $section, 'rows' => $rows->all(), 'sections' => $sections, 'totals' => $this->total($rows), 'panels' => $panels,
            'top_defects' => $top, 'hourly' => $hourly, 'refreshed_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * One line on one day, hour by hour (8 → last hour tapped): RFT, defect,
     * rectify, reject, checked, pass and the hourly target, and which defect
     * was found in which hour — the paper tally sheet a QC used to keep.
     */
    public function sheet(Request $request): JsonResponse
    {
        $this->allowView($request->filled('section') ? (string) $request->input('section') : null);
        $q = $request->validate([
            'date' => ['nullable', 'date'],
            'section' => ['required', Rule::in(QtBook::SECTIONS)],
            'line_id' => ['required', 'string', 'size:26'],
            'job_id' => ['nullable', 'string', 'size:26'],
        ]);
        $c = $this->caller()->companyId;
        $date = CarbonImmutable::parse($q['date'] ?? QtBook::today())->toDateString();
        $line = QtBook::lines($c, $q['section'])->get($q['line_id']);
        $scope = fn ($w) => $w->where('e.company_id', $c)->where('e.section', $q['section'])->where('e.line_id', $q['line_id'])->whereDate('e.production_date', $date)
            ->when($q['job_id'] ?? null, fn ($x, $j) => $x->where('e.job_id', $j));

        $counts = DB::table('qt_entries as e')->where($scope)->select('e.hour', 'e.action', DB::raw('count(*) as n'))->groupBy('e.hour', 'e.action')->get()->groupBy('hour');
        $tagRows = DB::table('qt_entry_defects as d')->join('qt_entries as e', 'e.id', '=', 'd.entry_id')->join('qt_defect_types as t', 't.id', '=', 'd.defect_type_id')
            ->where($scope)->select('t.id', 't.code', 't.name', 't.name_bn', 't.sort_order', 'e.hour', DB::raw('count(*) as n'))->groupBy('t.id', 't.code', 't.name', 't.name_bn', 't.sort_order', 'e.hour')->get();
        $targets = QtLineTarget::query()->whereDate('target_date', $date)->where('section', $q['section'])->where('line_id', $q['line_id'])
            ->when($q['job_id'] ?? null, fn ($x, $j) => $x->where('job_id', $j))->get();
        $target = $targets->isEmpty() ? null : (int) $targets->sum('target_qty');
        $hoursWorked = $targets->isEmpty() ? null : (float) $targets->max('working_hours');
        $hourlyTarget = $target && $hoursWorked ? (int) round($target / $hoursWorked) : null;

        $tagsByHour = $tagRows->groupBy('hour')->map(fn ($g) => (int) $g->sum('n'));
        $tapped = $counts->keys()->map(fn ($h) => (int) $h);
        $first = min(8, $tapped->min() ?? 8);
        $last = max($first + (int) ceil($hoursWorked ?? 8) - 1, $tapped->max() ?? 0);
        $hours = [];
        $cumulative = 0;
        for ($h = $first; $h <= min($last, 23); $h++) {
            $m = QtBook::metrics(($counts[$h] ?? collect())->pluck('n', 'action')->map(fn ($n) => (int) $n)->all(), (int) ($tagsByHour[$h] ?? 0), $hourlyTarget);
            $cumulative += $m['pass'];
            $hours[] = ['hour' => $h, 'label' => Clock::range(sprintf('%02d:00', $h), sprintf('%02d:00', ($h + 1) % 24)), 'cumulative_pass' => $cumulative] + $m;
        }

        $defects = $tagRows->groupBy('id')->map(function ($g) {
            $f = $g->first();

            return ['id' => $f->id, 'code' => $f->code, 'name' => $f->name, 'name_bn' => $f->name_bn, 'sort' => (int) $f->sort_order,
                'by_hour' => $g->mapWithKeys(fn ($r) => [(int) $r->hour => (int) $r->n])->all(), 'total' => (int) $g->sum('n')];
        })->sortByDesc('total')->values()->all();

        $all = DB::table('qt_entries as e')->where($scope)->select('e.action', DB::raw('count(*) as n'))->groupBy('e.action')->pluck('n', 'action')->map(fn ($n) => (int) $n)->all();
        $inspectors = DB::table('qt_entries as e')->join('users as u', 'u.id', '=', 'e.user_id')->where($scope)
            ->select('u.name', DB::raw('count(*) as n'))->groupBy('u.name')->orderByDesc('n')->get()->map(fn ($r) => ['name' => $r->name, 'taps' => (int) $r->n])->all();
        $jobIds = DB::table('qt_entries as e')->where($scope)->distinct()->pluck('e.job_id')->merge($targets->pluck('job_id'))->unique();
        $jobs = QtJob::query()->whereIn('id', $jobIds)->get()->map(fn (QtJob $j) => ['id' => $j->id, 'job_no' => $j->job_no, 'buyer_name' => $j->buyer_name, 'style' => $j->style, 'color' => $j->color, 'po_no' => $j->po_no, 'smv' => $j->smv === null ? null : (float) $j->smv])->values()->all();
        $manpower = $targets->isEmpty() ? null : ((int) $targets->max('manpower') ?: null);
        $totals = QtBook::metrics($all, (int) $tagRows->sum('n'), $target);
        // Earned minutes job by job (each job its own SMV) over the line's man-minutes
        // (attendance first, else the target's manpower × hours).
        $smvOf = collect($jobs)->pluck('smv', 'id');
        $passByJob = DB::table('qt_entries as e')->where($scope)->select('e.job_id', 'e.action', DB::raw('count(*) as n'))->groupBy('e.job_id', 'e.action')->get()->groupBy('job_id')
            ->map(fn ($g) => QtBook::metrics($g->pluck('n', 'action')->map(fn ($n) => (int) $n)->all(), 0)['pass']);
        $earned = $passByJob->every(fn ($n, $id) => $smvOf[$id] ?? null) ? $passByJob->reduce(fn ($a, $n, $id) => $a + $n * (float) $smvOf[$id], 0.0) : null;
        $manMinutes = QtBook::manMinutes($c, $date, $q['section'], $q['line_id'], $manpower, $hoursWorked);
        $attendance = (int) DB::table('qt_line_attendances')->where('company_id', $c)->whereDate('att_date', $date)->where('section', $q['section'])->where('line_id', $q['line_id'])->sum('attendance');

        return ApiResponse::ok([
            'date' => $date, 'section' => $q['section'], 'line_id' => $q['line_id'], 'line' => QtBook::label($line), 'job_id' => $q['job_id'] ?? null,
            'jobs' => $jobs, 'target' => $target, 'hourly_target' => $hourlyTarget, 'working_hours' => $hoursWorked,
            'manpower' => $attendance ?: $manpower, 'attendance_recorded' => $attendance > 0, 'man_minutes' => $manMinutes,
            // Earned minutes ÷ man-minutes — null, never 0, when an SMV or the manpower is missing.
            'efficiency' => $earned !== null && $earned > 0 && $manMinutes ? round($earned / $manMinutes * 100, 2) : ($earned === 0.0 && $manMinutes ? 0.0 : null),
            'hours' => $hours, 'defects' => $defects, 'totals' => $totals, 'inspectors' => $inspectors,
        ]);
    }

    /**
     * Quality Analytics (phase B): a date range (at most 90 days — wider is a
     * report, not a screen) — each day's checked, pass against target, DHU and
     * RFT %; each line; the defects that cost most; each QC. Across sections
     * target, pass and achievement are not added (the same garment is counted
     * at each), so a section is asked for, Sewing by default.
     */
    public function analytics(Request $request): JsonResponse
    {
        $this->allowView(null);
        $q = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'section' => ['nullable', Rule::in(QtBook::SECTIONS)]]);
        $c = $this->caller()->companyId;
        $to = CarbonImmutable::parse($q['to'] ?? QtBook::today());
        $from = CarbonImmutable::parse($q['from'] ?? $to->subDays(13)->toDateString());
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 89) {
            $from = $to->subDays(89);
        }
        $section = $q['section'] ?? 'SEWING';
        [$f, $t] = [$from->toDateString(), $to->toDateString()];
        $lines = QtBook::lines($c, $section);

        $base = fn () => QtEntry::query()->where('section', $section)->whereBetween('production_date', [$f, $t]);
        $tagBase = fn () => DB::table('qt_entry_defects as x')->join('qt_entries as e', 'e.id', '=', 'x.entry_id')
            ->where('e.company_id', $c)->where('e.section', $section)->whereBetween('e.production_date', [$f, $t]);
        $targets = QtLineTarget::query()->where('section', $section)->whereBetween('target_date', [$f, $t])->get();

        // Per day: pass is capped per line and job, then summed.
        $cells = $base()->select(DB::raw('production_date::text as d'), 'line_id', 'job_id', 'action', DB::raw('count(*) as n'))
            ->groupBy('production_date', 'line_id', 'job_id', 'action')->get()->groupBy(fn ($r) => $r->d.'|'.$r->line_id.'|'.$r->job_id);
        $cellMetrics = $cells->map(fn ($g) => QtBook::metrics($g->pluck('n', 'action')->map(fn ($n) => (int) $n)->all(), 0));
        $tagsByDay = $tagBase()->select(DB::raw('e.production_date::text as d'), DB::raw('count(*) as n'))->groupBy('e.production_date')->pluck('n', 'd');
        $targetByDay = $targets->groupBy(fn ($x) => $x->target_date->toDateString())->map(fn ($g) => (int) $g->sum('target_qty'));

        $days = [];
        for ($d = $from; $d->lessThanOrEqualTo($to); $d = $d->addDay()) {
            $key = $d->toDateString();
            $mine = $cellMetrics->filter(fn ($m, $k) => str_starts_with($k, $key.'|'));
            $checked = (int) $mine->sum('checked');
            $rft = (int) $mine->sum('rft');
            $pass = (int) $mine->sum('pass');
            $tags = (int) ($tagsByDay[$key] ?? 0);
            $target = $targetByDay[$key] ?? null;
            $days[] = [
                'date' => $key, 'checked' => $checked, 'pass' => $pass, 'reject' => (int) $mine->sum('reject'), 'target' => $target,
                'dhu' => $checked ? round($tags / $checked * 100, 2) : null, 'rft_pct' => $checked ? round($rft / $checked * 100, 2) : null,
                'achievement' => $target ? round($pass / $target * 100, 2) : null,
            ];
        }

        $byLine = $cellMetrics->groupBy(fn ($m, $k) => explode('|', $k)[1], true);
        $tagsByLine = $tagBase()->select('e.line_id', DB::raw('count(*) as n'))->groupBy('e.line_id')->pluck('n', 'line_id');
        $targetByLine = $targets->groupBy('line_id')->map(fn ($g) => (int) $g->sum('target_qty'));
        $lineRows = collect($byLine)->keys()->merge($targetByLine->keys())->unique()->map(function ($lineId) use ($byLine, $tagsByLine, $targetByLine, $lines) {
            $g = collect($byLine[$lineId] ?? []);
            $checked = (int) $g->sum('checked');
            $pass = (int) $g->sum('pass');
            $target = $targetByLine[$lineId] ?? null;

            return [
                'line_id' => $lineId, 'line' => QtBook::label($lines->get($lineId)) ?? '—', 'code' => $lines->get($lineId)?->code,
                'checked' => $checked, 'pass' => $pass, 'reject' => (int) $g->sum('reject'), 'target' => $target,
                'dhu' => $checked ? round(((int) ($tagsByLine[$lineId] ?? 0)) / $checked * 100, 2) : null,
                'rft_pct' => $checked ? round(((int) $g->sum('rft')) / $checked * 100, 2) : null,
                'achievement' => $target ? round($pass / $target * 100, 2) : null,
            ];
        })->sortBy('code')->values();

        $top = $tagBase()->join('qt_defect_types as dt', 'dt.id', '=', 'x.defect_type_id')
            ->select('dt.code', 'dt.name', 'dt.name_bn', DB::raw('count(*) as n'))->groupBy('dt.code', 'dt.name', 'dt.name_bn')->orderByDesc('n')->limit(10)->get();
        $totalTags = (int) $tagBase()->count();

        $qc = $base()->select('user_id', 'action', DB::raw('count(*) as n'))->groupBy('user_id', 'action')->get()->groupBy('user_id');
        $qcTags = $tagBase()->select('e.user_id', DB::raw('count(*) as n'))->groupBy('e.user_id')->pluck('n', 'user_id');
        $names = DB::table('users')->whereIn('id', $qc->keys())->pluck('name', 'id');

        $checkedAll = (int) collect($days)->sum('checked');
        $passAll = (int) collect($days)->sum('pass');
        $targetAll = (int) $targets->sum('target_qty');

        return ApiResponse::ok([
            'from' => $f, 'to' => $t, 'section' => $section,
            'totals' => [
                'checked' => $checkedAll, 'pass' => $passAll, 'target' => $targetAll ?: null, 'reject' => (int) collect($days)->sum('reject'),
                'achievement' => $targetAll ? round($passAll / $targetAll * 100, 2) : null,
                'dhu' => $checkedAll ? round($totalTags / $checkedAll * 100, 2) : null,
                'rft_pct' => $checkedAll ? round(((int) $cellMetrics->sum('rft')) / $checkedAll * 100, 2) : null,
                'days_worked' => collect($days)->where('checked', '>', 0)->count(),
            ],
            'days' => $days,
            'lines' => $lineRows->all(),
            'top_defects' => $top->map(fn ($r) => ['code' => $r->code, 'name' => $r->name, 'name_bn' => $r->name_bn, 'count' => (int) $r->n, 'share' => $totalTags ? round($r->n / $totalTags * 100, 1) : null])->all(),
            'inspectors' => $qc->map(function ($g, $userId) use ($qcTags, $names) {
                $m = QtBook::metrics($g->pluck('n', 'action')->map(fn ($n) => (int) $n)->all(), (int) ($qcTags[$userId] ?? 0));

                return ['name' => $names[$userId] ?? '—', 'checked' => $m['checked'], 'defect' => $m['defect'], 'reject' => $m['reject'], 'dhu' => $m['dhu']];
            })->sortByDesc('checked')->values()->all(),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $this->allowView($request->filled('section') ? (string) $request->input('section') : null);
        $q = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
            'section' => ['nullable', Rule::in(QtBook::SECTIONS)], 'line_id' => ['nullable', 'string', 'size:26'],
            'job_id' => ['nullable', 'string', 'size:26'], 'action' => ['nullable', Rule::in(QtBook::ACTIONS)], 'user_id' => ['nullable', 'string', 'size:26'],
            'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
        ]);
        $c = $this->caller()->companyId;
        $from = $q['from'] ?? QtBook::today();
        $to = $q['to'] ?? $from;
        $page = QtEntry::query()->with('defects')
            ->whereDate('production_date', '>=', $from)->whereDate('production_date', '<=', $to)
            ->when($q['section'] ?? null, fn ($w, $v) => $w->where('section', $v))->when($q['line_id'] ?? null, fn ($w, $v) => $w->where('line_id', $v))
            ->when($q['job_id'] ?? null, fn ($w, $v) => $w->where('job_id', $v))->when($q['action'] ?? null, fn ($w, $v) => $w->where('action', $v))
            ->when($q['user_id'] ?? null, fn ($w, $v) => $w->where('user_id', $v))
            ->orderByDesc('occurred_at')->orderByDesc('id')->paginate((int) ($q['per_page'] ?? 50), ['*'], 'page', (int) ($q['page'] ?? 1));

        $items = collect($page->items());
        $lines = QtBook::allLines($c);
        $jobs = QtJob::query()->whereIn('id', $items->pluck('job_id')->unique())->get()->keyBy('id');
        $users = DB::table('users')->whereIn('id', $items->pluck('user_id')->unique())->pluck('name', 'id');
        $defectNames = DB::table('qt_defect_types')->whereIn('id', $items->flatMap(fn ($e) => $e->defects->pluck('defect_type_id'))->unique())->pluck('name', 'id');
        $tz = QtBook::TZ;

        return ApiResponse::ok([
            'rows' => $items->map(fn (QtEntry $e) => [
                'id' => $e->id, 'occurred_at' => $e->occurred_at->toIso8601String(), 'time' => $e->occurred_at->copy()->setTimezone($tz)->format('H:i:s'),
                'production_date' => $e->production_date->toDateString(), 'hour' => $e->hour, 'section' => $e->section,
                'line' => QtBook::label($lines->get($e->section.':'.$e->line_id)) ?? '—', 'job_no' => $jobs[$e->job_id]->job_no ?? null,
                'style' => $jobs[$e->job_id]->style ?? null, 'action' => $e->action, 'user' => $users[$e->user_id] ?? null,
                'defects' => $e->defects->map(fn ($d) => $defectNames[$d->defect_type_id] ?? '—')->values()->all(), 'synced_late' => $e->synced_late, 'review' => $e->review,
            ])->all(),
            'total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'from' => $from, 'to' => $to,
            'users' => DB::table('qt_entries as e')->join('users as u', 'u.id', '=', 'e.user_id')->where('e.company_id', $c)->when($q['section'] ?? null, fn ($w, $v) => $w->where('e.section', $v))->distinct()->orderBy('u.name')->get(['u.id', 'u.name'])
                ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all(),
        ]);
    }

    /** @return array<string, int|float|null> */
    private function total(Collection $rows): array
    {
        $target = $rows->whereNotNull('target')->sum('target');
        $m = QtBook::metrics(['RFT' => $rows->sum('rft'), 'DEFECT' => $rows->sum('defect'), 'RECTIFY' => $rows->sum('rectify'), 'REJECT' => $rows->sum('reject')], (int) $rows->sum('defect_tags'), $target ?: null);
        // Pass is capped per line and job, so the total is the sum of the capped passes.
        $m['pass'] = (int) $rows->sum('pass');
        $m['achievement'] = $target ? round($m['pass'] / $target * 100, 2) : null;
        $m['balance'] = $target ? $target - $m['pass'] : null;
        $m['lines'] = $rows->pluck('section')->zip($rows->pluck('line_id'))->map(fn ($p) => implode(':', $p->all()))->unique()->count();
        // The same garment is counted at cutting, sewing and quality: across sections a target, its
        // achievement and a pass total would add one piece two or three times — so there are none.
        $m['mixed'] = $rows->pluck('section')->unique()->count() > 1;
        if ($m['mixed']) {
            $m['target'] = $m['achievement'] = $m['balance'] = $m['pass'] = null;
        }

        return $m;
    }
}

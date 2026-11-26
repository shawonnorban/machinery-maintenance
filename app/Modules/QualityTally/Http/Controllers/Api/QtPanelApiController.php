<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Http\Controllers\Api;

use App\Modules\QualityTally\Http\Controllers\Concerns\GuardsSections;
use App\Modules\QualityTally\Models\QtDefectType;
use App\Modules\QualityTally\Models\QtCutBundle;
use App\Modules\QualityTally\Models\QtJob;
use App\Modules\QualityTally\Models\QtPanel;
use App\Modules\QualityTally\Support\QtBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Quality Tally → Cut Panel Inspection (user, 2026-11-25): cutting is checked
 * panel by panel, bundle by bundle, as on Norban Comtex's "100% Cut Panel
 * Inspection Report" — and Setup → Panels, each garment type's panels.
 *
 * Who may write a report: a QC given that cutting table (QC Assignment), or a
 * manager who keeps the jobs (qtally.job.manage) entering a paper sheet.
 */
class QtPanelApiController extends ApiController
{
    use GuardsSections;

    /* ── Panels (Setup) ─────────────────────────────────────────────── */

    public function panels(): JsonResponse
    {
        if (! $this->caller()->can('qtally.defect.manage')) {
            $this->allowViewOrTap('CUTTING');
        }
        $used = \App\Modules\QualityTally\Models\QtEntry::query()->whereNotNull('panel_id')->select('panel_id', DB::raw('count(*) as n'))->groupBy('panel_id')->pluck('n', 'panel_id');

        return ApiResponse::ok(QtPanel::query()->orderBy('garment_type')->orderBy('sort_order')->orderBy('name')->get()->map(fn (QtPanel $x) => [
            'id' => $x->id, 'garment_type' => $x->garment_type, 'code' => $x->code, 'name' => $x->name, 'per_piece' => $x->per_piece, 'sort_order' => $x->sort_order,
            'active' => $x->active, 'used' => (int) ($used[$x->id] ?? 0),
        ])->all());
    }

    public function savePanel(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('qtally.defect.manage');
        $row = $id ? QtPanel::query()->findOrFail($id) : null;
        $type = trim((string) $request->input('garment_type', $row?->garment_type));
        $request->merge(['garment_type' => $type]);
        $d = $request->validate([
            'garment_type' => ['required', 'string', 'max:40'],
            'code' => ['nullable', 'string', 'max:12'],
            'name' => ['required', 'string', 'max:80', Rule::unique('qt_panels', 'name')->where('company_id', $this->caller()->companyId)->where('garment_type', $type)->ignore($row?->id)],
            'per_piece' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['sometimes', 'boolean'],
        ], ['name.unique' => __('qtally.v.panel_unique')], __('qtally.attrs'));
        $values = ['garment_type' => $d['garment_type'], 'code' => strtoupper(trim((string) ($d['code'] ?? ''))), 'name' => trim($d['name']), 'per_piece' => $d['per_piece'] ?? $row?->per_piece ?? 1, 'sort_order' => $d['sort_order'] ?? 0, 'active' => $d['active'] ?? true];
        $saved = $row ? tap($row)->update($values) : QtPanel::query()->create($values);

        return $id ? ApiResponse::ok(['id' => $saved->id]) : ApiResponse::created(['id' => $saved->id]);
    }

    public function deletePanel(string $id): JsonResponse
    {
        $this->allow('qtally.defect.manage');
        $row = QtPanel::query()->findOrFail($id);
        if (\App\Modules\QualityTally\Models\QtEntry::query()->where('panel_id', $row->id)->exists()) {
            throw ValidationException::withMessages(['id' => __('qtally.v.panel_used')]);
        }
        $row->delete();

        return ApiResponse::ok(['id' => $id]);
    }

    /** Adds the standard panels (every garment type, or one), skipping names already there. */
    public function standardPanels(Request $request): JsonResponse
    {
        $this->allow('qtally.defect.manage');
        $only = $request->input('garment_type');
        $added = 0;
        foreach (QtBook::standardPanels() as $type => $list) {
            if ($only && $only !== $type) {
                continue;
            }
            $have = QtPanel::query()->where('garment_type', $type)->pluck('name')->all();
            foreach ($list as $i => [$code, $name, $per]) {
                if (! in_array($name, $have, true)) {
                    QtPanel::query()->create(['garment_type' => $type, 'code' => $code, 'name' => $name, 'per_piece' => $per, 'sort_order' => ($i + 1) * 10, 'active' => true]);
                    $added++;
                }
            }
        }

        return ApiResponse::ok(['added' => $added]);
    }

    /* ── Cut Panel Inspection — the report built from the cutting taps ── */

    /** The report's columns: the cutting defects (B … Y1), in their order. */
    public function form(): JsonResponse
    {
        $this->allowViewOrTap('CUTTING');
        // A job already cut is one garment.
        $garments = QtCutBundle::query()->distinct()->pluck('garment_type', 'job_id');

        return ApiResponse::ok([
            'today' => QtBook::today(),
            'lines' => QtBook::lines($this->caller()->companyId, 'CUTTING')->where('active', true)->map(fn ($l) => ['value' => $l->id, 'label' => QtBook::label($l)])->values()->all(),
            'jobs' => QtJob::query()->where('status', 'OPEN')->orderBy('job_no')->get()->map(fn (QtJob $j) => ['value' => $j->id, 'label' => trim($j->job_no.' · '.$j->buyer_name.' · '.$j->style.($j->color !== '' ? ' · '.$j->color : '')), 'garment_type' => $garments[$j->id] ?? null])->all(),
            'garment_types' => QtPanel::query()->where('active', true)->orderBy('garment_type')->orderBy('sort_order')->orderBy('name')->get()
                ->groupBy('garment_type')->map(fn ($g, $type) => ['name' => $type, 'panels' => $g->map(fn ($x) => ['id' => $x->id, 'name' => $x->name, 'per_piece' => $x->per_piece])->values()->all()])->values()->all(),
            'defects' => QtDefectType::query()->where('section', 'CUTTING')->where('active', true)->orderBy('sort_order')->orderBy('code')->get()
                ->map(fn (QtDefectType $d) => ['id' => $d->id, 'code' => $d->code, 'name' => $d->name, 'name_bn' => $d->name_bn])->all(),
        ]);
    }

    /**
     * One inspection per lot: a day, a cutting table, a job, its cutting and
     * batch numbers — every panel the QCs tapped for it.
     */
    public function index(Request $request): JsonResponse
    {
        $this->allowViewOrTap('CUTTING');
        $q = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'search' => ['nullable', 'string', 'max:60']]);
        $c = $this->caller()->companyId;
        $to = $q['to'] ?? QtBook::today();
        $from = $q['from'] ?? CarbonImmutable::parse($to)->subDays(6)->toDateString();
        $rows = $this->taps($c)->leftJoin('qt_jobs as j', 'j.id', '=', 'e.job_id')->whereBetween('e.production_date', [$from, $to])
            ->when($q['search'] ?? null, fn ($w, $v) => $w->where(fn ($x) => $x->where('e.cutting_no', 'ilike', "%{$v}%")->orWhere('e.batch_no', 'ilike', "%{$v}%")->orWhere('j.job_no', 'ilike', "%{$v}%")))
            ->groupBy('e.production_date', 'e.line_id', 'e.job_id', 'e.cutting_no', 'e.batch_no', 'e.garment_type', 'j.job_no', 'j.buyer_name', 'j.style', 'j.color')
            ->select(
                DB::raw('e.production_date::text as d'), 'e.line_id', 'e.job_id', 'e.cutting_no', 'e.batch_no', 'e.garment_type', 'j.job_no', 'j.buyer_name', 'j.style', 'j.color',
                DB::raw("sum(case when e.action in ('RFT', 'DEFECT') then 1 else 0 end) as checked"), DB::raw("sum(case when e.action = 'REJECT' then 1 else 0 end) as reject"), DB::raw("sum(case when e.action = 'RECTIFY' then 1 else 0 end) as rectify"),
                DB::raw('count(distinct e.bundle_no) as bundles'), DB::raw('max(e.cut_qty) as cut_qty'), DB::raw('count(distinct e.user_id) as qcs'),
            )
            ->orderByDesc('e.production_date')->orderByDesc(DB::raw('max(e.occurred_at)'))->get();
        $lines = QtBook::lines($c, 'CUTTING');
        $given = $this->givenQty($rows->pluck('job_id')->unique()->all());

        return ApiResponse::ok([
            'from' => $from, 'to' => $to,
            'rows' => $rows->map(fn ($r) => [
                'key' => ['date' => $r->d, 'line_id' => $r->line_id, 'job_id' => $r->job_id, 'cutting_no' => $r->cutting_no, 'batch_no' => $r->batch_no, 'garment_type' => $r->garment_type],
                'date' => $r->d, 'line' => QtBook::label($lines->get($r->line_id)) ?? '—', 'job_no' => $r->job_no, 'buyer' => $r->buyer_name, 'style' => $r->style, 'color' => $r->color,
                'garment_type' => $r->garment_type, 'cutting_no' => $r->cutting_no, 'batch_no' => $r->batch_no, 'cut_qty' => $r->cut_qty !== null ? (int) $r->cut_qty : $given([$r->job_id, $r->cutting_no, $r->batch_no]),
                'bundles' => (int) $r->bundles, 'checked' => (int) $r->checked, 'reject' => (int) $r->reject,
                'reject_pct' => $r->checked ? round($r->reject / $r->checked * 100, 2) : null, 'qcs' => (int) $r->qcs,
            ])->all(),
        ]);
    }

    /** One lot as the paper sheet: bundle by bundle and panel by panel, each code's count, the reject. */
    public function show(Request $request): JsonResponse
    {
        $this->allowViewOrTap('CUTTING');
        $k = $request->validate([
            'date' => ['required', 'date'], 'line_id' => ['required', 'string', 'size:26'], 'job_id' => ['required', 'string', 'size:26'],
            'cutting_no' => ['nullable', 'string', 'max:40'], 'batch_no' => ['nullable', 'string', 'max:40'], 'garment_type' => ['nullable', 'string', 'max:40'],
        ]);
        $c = $this->caller()->companyId;
        $lot = fn () => $this->taps($c)->whereDate('e.production_date', $k['date'])->where('e.line_id', $k['line_id'])->where('e.job_id', $k['job_id'])
            ->where(fn ($w) => ($k['cutting_no'] ?? null) ? $w->where('e.cutting_no', $k['cutting_no']) : $w->whereNull('e.cutting_no'))
            ->where(fn ($w) => ($k['batch_no'] ?? null) ? $w->where('e.batch_no', $k['batch_no']) : $w->whereNull('e.batch_no'))
            ->when($k['garment_type'] ?? null, fn ($w, $v) => $w->where('e.garment_type', $v));

        $cells = $lot()->groupBy('e.bundle_no', 'e.bundle_serial', 'e.bundle_qty', 'e.size', 'e.panel_id')
            ->select('e.bundle_no', 'e.bundle_serial', 'e.bundle_qty', 'e.size', 'e.panel_id', DB::raw("sum(case when e.action in ('RFT', 'DEFECT') then 1 else 0 end) as checked"), DB::raw("sum(case when e.action = 'REJECT' then 1 else 0 end) as reject"), DB::raw("sum(case when e.action = 'RECTIFY' then 1 else 0 end) as rectify"))
            ->orderBy(DB::raw('min(e.occurred_at)'))->get();
        if ($cells->isEmpty()) {
            abort(404);
        }
        $cellKey = fn ($r) => implode('|', [$r->bundle_no, $r->bundle_serial, $r->bundle_qty, $r->size, $r->panel_id]);
        $tags = $lot()->join('qt_entry_defects as x', 'x.entry_id', '=', 'e.id')
            ->groupBy('e.bundle_no', 'e.bundle_serial', 'e.bundle_qty', 'e.size', 'e.panel_id', 'x.defect_type_id')
            ->select('e.bundle_no', 'e.bundle_serial', 'e.bundle_qty', 'e.size', 'e.panel_id', 'x.defect_type_id', DB::raw('count(*) as n'))->get()
            ->groupBy($cellKey);
        $panels = QtPanel::query()->whereIn('id', $cells->pluck('panel_id'))->get()->keyBy('id');
        $job = QtJob::query()->find($k['job_id']);
        $qcs = $lot()->join('users as u', 'u.id', '=', 'e.user_id')->select('u.name', DB::raw('count(*) as n'))->groupBy('u.name')->orderByDesc('n')->get();
        $cutQty = $lot()->max('e.cut_qty') ?? $this->givenQty([$k['job_id']])([$k['job_id'], $k['cutting_no'] ?? null, $k['batch_no'] ?? null]);

        // Bundle by bundle, in the order they were opened.
        $rows = $cells->groupBy('bundle_no')->flatten(1)->values()->map(fn ($r, $i) => [
            'sl' => $i + 1, 'bundle_no' => $r->bundle_no, 'bundle_serial' => $r->bundle_serial, 'bundle_qty' => $r->bundle_qty === null ? null : (int) $r->bundle_qty, 'size' => $r->size,
            'panel' => $panels[$r->panel_id]->name ?? '—', 'checked' => (int) $r->checked, 'rectify' => (int) $r->rectify, 'reject' => (int) $r->reject,
            'defects' => ($tags[$cellKey($r)] ?? collect())->mapWithKeys(fn ($t) => [$t->defect_type_id => (int) $t->n])->all(),
        ]);
        $byDefect = [];
        foreach ($rows as $r) {
            foreach ($r['defects'] as $id => $n) {
                $byDefect[$id] = ($byDefect[$id] ?? 0) + $n;
            }
        }
        $checked = (int) $rows->sum('checked');
        $reject = (int) $rows->sum('reject');

        return ApiResponse::ok([
            'key' => $k, 'date' => $k['date'], 'line' => QtBook::label(QtBook::lines($c, 'CUTTING')->get($k['line_id'])),
            'job_no' => $job?->job_no, 'buyer' => $job?->buyer_name, 'style' => $job?->style, 'color' => $job?->color,
            'garment_type' => $k['garment_type'] ?? null, 'cutting_no' => $k['cutting_no'] ?? null, 'batch_no' => $k['batch_no'] ?? null, 'cut_qty' => $cutQty === null ? null : (int) $cutQty,
            'qcs' => $qcs->map(fn ($q) => ['name' => $q->name, 'taps' => (int) $q->n])->all(),
            'totals' => ['checked' => $checked, 'rectify' => (int) $rows->sum('rectify'), 'reject' => $reject, 'reject_pct' => $checked ? round($reject / $checked * 100, 2) : null, 'bundles' => $rows->pluck('bundle_no')->unique()->count(), 'by_defect' => $byDefect],
            'rows' => $rows->all(),
        ]);
    }

    /**
     * A lot's cut pieces: what its bundle cards hold — a card of every panel counts once, a card of one
     * panel beside its own, so the panel given the most says how many pieces the lot has.
     *
     * @param  list<string>  $jobIds
     * @return callable(array{0: ?string, 1: ?string, 2: ?string}): ?int
     */
    private function givenQty(array $jobIds): callable
    {
        $key = fn (?string ...$p): string => implode('|', array_map(fn ($x) => (string) $x, $p));
        $lots = QtCutBundle::query()->whereIn('job_id', $jobIds)->get(['job_id', 'cutting_no', 'batch_no', 'panel_id', 'qty'])
            ->groupBy(fn (QtCutBundle $b) => $key($b->job_id, $b->cutting_no, $b->batch_no))
            ->map(fn ($g) => (int) $g->whereNull('panel_id')->sum('qty') + (int) $g->whereNotNull('panel_id')->groupBy('panel_id')->map(fn ($x) => $x->sum('qty'))->max());

        return fn (array $lot): ?int => $lots[$key(...$lot)] ?? null;
    }

    /** The company's cutting taps (one panel each), aliased `e`. */
    private function taps(string $companyId): \Illuminate\Database\Query\Builder
    {
        return DB::table('qt_entries as e')->where('e.company_id', $companyId)->where('e.section', 'CUTTING')->whereNotNull('e.panel_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\ProductionTracking\Http\Controllers\Api;

use App\Modules\ProductionTracking\Models\PtEntry;
use App\Modules\ProductionTracking\Models\PtLineCapacity;
use App\Modules\ProductionTracking\Support\ProductionBoard;
use App\Modules\ProductionTracking\Support\PtBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Production Tracking (AnnotechERP 30): what its screens pick from, the
 * dashboards and order progress, the Daily Production Report, Settings, Line
 * Capacity Master and Production Master Data. Daily Production Plan and the
 * TV screens have their own controllers.
 */
class ProductionTrackingApiController extends ApiController
{
    private const VIEW = 'productiontracking.view';

    /* ── Lookups ────────────────────────────────────────────────────── */

    public function lookups(): JsonResponse
    {
        $this->allow(self::VIEW);
        $c = $this->caller()->companyId;
        $settings = PtBook::settings($c);
        $line = fn (string $section) => PtBook::lines($c, $section)->where('active', true)->map(fn ($l) => ['value' => $l->id, 'label' => PtBook::label($l), 'section' => $section, 'code' => $l->code])->values()->all();

        return ApiResponse::ok([
            'today' => PtBook::today()->toDateString(),
            'sewing_lines' => $line('SEWING'),
            'finishing_lines' => $line('FINISHING'),
            'buyers' => DB::table('buyers')->where('company_id', $c)->orderBy('name')->pluck('name')->unique()->values()->all(),
            'orders' => DB::table('merch_orders as o')->leftJoin('buyers as b', 'b.id', '=', 'o.buyer_id')->where('o.company_id', $c)
                ->whereNotIn('o.status', ['CANCELLED'])->orderByDesc('o.ship_date')->limit(400)
                ->get(['o.id', 'o.po_no', 'o.order_no', 'o.style_no', 'o.item', 'b.name as buyer'])
                ->map(fn ($o) => ['value' => $o->id, 'label' => trim(($o->po_no ?: $o->order_no).' — '.$o->buyer.' · '.$o->style_no), 'po' => $o->po_no ?: $o->order_no, 'buyer' => $o->buyer, 'style' => $o->style_no])->all(),
            'styles' => DB::table('merch_orders')->where('company_id', $c)->whereNotNull('style_no')->distinct()->orderBy('style_no')->pluck('style_no')->all(),
            'settings' => $this->settingsBody($c),
        ]);
    }

    /* ── Dashboards, order progress, report ─────────────────────────── */

    public function board(Request $request, string $view): JsonResponse
    {
        $this->allow(self::VIEW);
        $q = $request->validate([
            'range' => ['nullable', Rule::in(['TODAY', '3D', '7D', 'CUSTOM'])], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
            'line' => ['nullable', 'string', 'max:26'], 'buyer' => ['nullable', 'string', 'max:255'], 'search' => ['nullable', 'string', 'max:120'],
            'barcode' => ['nullable', 'string', 'max:60'], 'section' => ['nullable', Rule::in(PtBook::SECTIONS)],
        ]);
        $c = $this->caller()->companyId;
        [$from, $to] = PtBook::period($q['range'] ?? null, $q['from'] ?? null, $q['to'] ?? null);
        $board = new ProductionBoard($c, $from, $to);
        $filters = ['line' => $q['line'] ?? null, 'buyer' => $q['buyer'] ?? null, 'search' => $q['search'] ?? null];
        $settings = PtBook::settings($c);
        $meta = ['from' => $from, 'to' => $to, 'refreshed_at' => now()->toIso8601String(), 'refresh_seconds' => $settings->refresh_seconds,
            'stale_seconds' => $settings->stale_seconds, 'on_track_pct' => $settings->on_track_pct, 'below_pct' => $settings->below_pct];

        $body = match ($view) {
            'sewing', 'finishing' => (fn ($s) => $board->lines($s, $filters) + [
                'buyer' => $board->by($s, 'buyer', $filters), 'style' => $board->by($s, 'style', $filters), 'po' => $board->by($s, 'po', $filters),
            ])(strtoupper($view)),
            'cutting' => $board->cutting($q['barcode'] ?? null),
            'all' => (function () use ($board, $filters) {
                $sewing = $board->lines('SEWING', $filters);
                $finishing = $board->lines('FINISHING', $filters);
                $cutting = $board->cutting();

                return ['sewing' => $sewing['totals'], 'finishing' => $finishing['totals'], 'cutting' => $cutting['kpi'],
                    'missing' => ['sewing' => count($sewing['missing']), 'finishing' => count($finishing['missing'])],
                    'trend' => $board->trend(), 'buyer' => array_slice($board->by('SEWING', 'buyer', $filters), 0, 8),
                    'style' => array_slice($board->by('SEWING', 'style', $filters), 0, 10), 'po' => array_slice($board->by('SEWING', 'po', $filters), 0, 10),
                    'finishing_by' => ['buyer' => $board->by('FINISHING', 'buyer', $filters)]];
            })(),
            'buyer', 'style', 'po' => ['rows' => $this->progress($board, $view, $filters)],
            'report' => ['rows' => $board->report($filters['line'], $filters['buyer'])],
            default => throw ValidationException::withMessages(['view' => __('productiontracking.v.view')]),
        };

        return ApiResponse::ok($body + ['meta' => $meta]);
    }

    /** Order progress: sewing and finishing side by side, by buyer, style or PO. */
    private function progress(ProductionBoard $board, string $dimension, array $filters): array
    {
        $sewing = collect($board->by('SEWING', $dimension, $filters))->keyBy('key');
        $finishing = collect($board->by('FINISHING', $dimension, $filters))->keyBy('key');

        return $sewing->keys()->merge($finishing->keys())->unique()->map(function ($k) use ($sewing, $finishing) {
            $s = $sewing[$k] ?? null;
            $f = $finishing[$k] ?? null;
            $first = $s ?? $f;

            return ['key' => $k, 'buyer' => $first['buyer'], 'style' => $first['style'], 'po' => $first['po'],
                'sewing_target' => $s['target'] ?? null, 'sewing_actual' => $s['actual'] ?? 0, 'sewing_pct' => $s['pct'] ?? null, 'sewing_band' => $s['band'] ?? 'NO_TARGET',
                'finishing_target' => $f['target'] ?? null, 'finishing_actual' => $f['actual'] ?? 0, 'finishing_pct' => $f['pct'] ?? null,
                'reject' => ($s['reject'] ?? 0) + ($f['reject'] ?? 0), 'rework' => ($s['rework'] ?? 0) + ($f['rework'] ?? 0)];
        })->sortByDesc('sewing_actual')->values()->all();
    }

    /* ── Settings ───────────────────────────────────────────────────── */

    public function settings(): JsonResponse
    {
        $this->allow(self::VIEW);

        return ApiResponse::ok($this->settingsBody($this->caller()->companyId));
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $this->allow('productiontracking.settings.manage');
        $d = $request->validate([
            'refresh_seconds' => ['required', 'integer', 'min:15', 'max:600'],
            'stale_seconds' => ['required', 'integer', 'max:3600'],
            'on_track_pct' => ['required', 'integer', 'min:1', 'max:100'],
            'below_pct' => ['required', 'integer', 'min:0', 'lt:on_track_pct'],
            'approval_workflow' => ['required', 'boolean'],
            'smv_method' => ['required', 'boolean'],
        ], [], __('productiontracking.attrs'));
        if ($d['stale_seconds'] < 2 * $d['refresh_seconds']) {
            throw ValidationException::withMessages(['stale_seconds' => __('productiontracking.v.stale')]);
        }
        $c = $this->caller()->companyId;
        PtBook::settings($c)->update($d + ['updated_by' => $this->caller()->auditUserId()]);

        return ApiResponse::ok($this->settingsBody($c));
    }

    private function settingsBody(string $c): array
    {
        $s = PtBook::settings($c);
        $today = PtBook::today()->toDateString();

        return $s->only(['refresh_seconds', 'stale_seconds', 'on_track_pct', 'below_pct', 'approval_workflow', 'smv_method']) + [
            'updated_at' => $s->updated_at?->toIso8601String(),
            'updated_by' => $s->updated_by ? DB::table('users')->where('id', $s->updated_by)->value('name') : null,
            'configured' => [
                'sewing_lines' => PtBook::lines($c, 'SEWING')->where('active', true)->count(),
                'finishing_lines' => PtBook::lines($c, 'FINISHING')->where('active', true)->count(),
                'capacities' => PtLineCapacity::query()->where('active', true)->whereDate('effective_from', '<=', $today)
                    ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))->count(),
                'plans_today' => DB::table('pt_daily_plans')->where('company_id', $c)->where('current', true)->whereDate('plan_date', $today)->whereNotIn('status', ['CANCELLED'])->count(),
                'tv_screens' => DB::table('pt_tv_screens')->where('company_id', $c)->where('active', true)->count(),
            ],
        ];
    }

    /* ── Line Capacity Master ───────────────────────────────────────── */

    public function capacities(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);
        $c = $this->caller()->companyId;
        $today = PtBook::today()->toDateString();
        $lines = PtBook::lines($c, 'SEWING')->merge(PtBook::lines($c, 'FINISHING'));
        $rows = PtLineCapacity::query()->orderBy('section')->orderByDesc('effective_from')->get();
        $users = DB::table('users')->whereIn('id', $rows->pluck('updated_by')->merge($rows->pluck('created_by'))->filter()->unique())->pluck('name', 'id');
        $current = fn (PtLineCapacity $r) => $r->active && $r->effective_from->toDateString() <= $today && ($r->effective_to === null || $r->effective_to->toDateString() >= $today);

        $without = $lines->where('active', true)->filter(fn ($l) => ! $rows->contains(fn ($r) => $r->line_id === $l->id && $current($r)))
            ->map(fn ($l) => ['id' => $l->id, 'label' => PtBook::label($l), 'section' => $l->section])->values()->all();

        return ApiResponse::ok([
            'rows' => $rows->map(fn (PtLineCapacity $r) => [
                'id' => $r->id, 'section' => $r->section, 'line_id' => $r->line_id, 'line' => PtBook::label($lines->get($r->line_id)), 'line_code' => $lines->get($r->line_id)?->code,
                'capacity_type' => $r->capacity_type, 'standard_capacity' => (float) $r->standard_capacity, 'default_efficiency' => $r->default_efficiency === null ? null : (float) $r->default_efficiency,
                'includes_efficiency' => $r->includes_efficiency, 'effective_from' => $r->effective_from->toDateString(), 'effective_to' => $r->effective_to?->toDateString(),
                'active' => $r->active, 'current' => $current($r), 'remarks' => $r->remarks,
                'updated_by' => $users[$r->updated_by ?? $r->created_by] ?? null, 'updated_at' => $r->updated_at?->toIso8601String(),
            ])->all(),
            'without' => $without,
        ]);
    }

    public function saveCapacity(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('productiontracking.capacity.manage');
        $c = $this->caller()->companyId;
        $row = $id ? PtLineCapacity::query()->findOrFail($id) : null;
        $d = $request->validate([
            'section' => ['required', Rule::in(PtBook::SECTIONS)],
            'line_id' => ['required', 'string', 'size:26'],
            'capacity_type' => ['required', Rule::in(['PER_HOUR', 'PER_DAY'])],
            'standard_capacity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'default_efficiency' => ['nullable', 'numeric', 'gt:0', 'max:200'],
            'includes_efficiency' => ['sometimes', 'boolean'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'active' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], ['line_id.required' => __('productiontracking.v.line')], __('productiontracking.attrs'));
        if (! PtBook::lines($c, $d['section'])->has($d['line_id'])) {
            throw ValidationException::withMessages(['line_id' => __('productiontracking.v.line')]);
        }
        // One capacity in force per line at a time: periods may not overlap.
        $overlap = PtLineCapacity::query()->where('section', $d['section'])->where('line_id', $d['line_id'])->where('active', true)
            ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $d['effective_from']))
            ->when($d['effective_to'] ?? null, fn ($q, $to) => $q->whereDate('effective_from', '<=', $to))
            ->exists();
        if ($overlap && ($d['active'] ?? true)) {
            throw ValidationException::withMessages(['effective_from' => __('productiontracking.v.capacity_overlap')]);
        }
        $me = $this->caller()->auditUserId();
        $values = $d + ['includes_efficiency' => $d['includes_efficiency'] ?? false, 'active' => $d['active'] ?? true, 'updated_by' => $me];
        $saved = $row ? tap($row)->update($values) : PtLineCapacity::query()->create($values + ['created_by' => $me]);

        return $id ? ApiResponse::ok(['id' => $saved->id]) : ApiResponse::created(['id' => $saved->id]);
    }

    /* ── Production Master Data ─────────────────────────────────────── */

    public function entries(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);
        $q = $request->validate(['date' => ['nullable', 'date'], 'section' => ['nullable', Rule::in(PtBook::ENTRY_SECTIONS)]]);
        $c = $this->caller()->companyId;
        $date = CarbonImmutable::parse($q['date'] ?? PtBook::today())->toDateString();
        $lines = PtBook::lines($c, 'SEWING')->merge(PtBook::lines($c, 'FINISHING'));
        $rows = PtEntry::query()->whereDate('entry_date', $date)->when($q['section'] ?? null, fn ($w, $s) => $w->where('section', $s))->orderBy('section')->orderBy('created_at')->get();
        // The same target the dashboards show: a line's approved plan, else its entries' target.
        $board = new ProductionBoard($c, $date, $date);
        $targetOf = fn (string $s) => $s === 'CUTTING'
            ? ((int) $rows->where('section', $s)->sum('target') ?: null)
            : ($board->lines($s)['totals']['target'] ?: null);

        return ApiResponse::ok([
            'date' => $date,
            'rows' => $rows->map(fn (PtEntry $e) => $e->toArray() + ['entry_date' => $e->entry_date->toDateString(), 'line' => PtBook::label($lines->get((string) $e->line_id))])->all(),
            'totals' => collect(PtBook::ENTRY_SECTIONS)->mapWithKeys(fn ($s) => [$s => [
                'pcs' => (int) $rows->where('section', $s)->sum('production'),
                'entries' => $rows->where('section', $s)->count(),
                'target' => $targetOf($s),
            ]])->all(),
        ]);
    }

    public function saveEntry(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('productiontracking.entry.manage');
        $c = $this->caller()->companyId;
        $row = $id ? PtEntry::query()->findOrFail($id) : null;
        $n = ['nullable', 'integer', 'min:0', 'max:99999999'];
        $d = $request->validate([
            'section' => ['required', Rule::in(PtBook::ENTRY_SECTIONS)],
            'entry_date' => ['required', 'date'],
            'line_id' => ['nullable', 'string', 'size:26', Rule::requiredIf(fn () => $request->input('section') !== 'CUTTING')],
            'buyer_name' => ['nullable', 'string', 'max:255'], 'style' => ['nullable', 'string', 'max:120'], 'po_no' => ['nullable', 'string', 'max:64'], 'item' => ['nullable', 'string', 'max:120'],
            'order_qty' => $n, 'target' => $n, 'production' => $n, 'bundles_ready' => $n, 'bundles_issued' => $n, 'reject' => $n, 'rework' => $n,
            'smv' => ['nullable', 'numeric', 'min:0', 'max:9999'], 'manpower' => $n, 'available_hours' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'plan_hours' => ['nullable', 'numeric', 'min:0', 'max:24'], 'run_day' => $n, 'total_produced' => $n, 'remarks' => ['nullable', 'string', 'max:1000'],
        ], ['line_id.required' => __('productiontracking.v.line')], __('productiontracking.attrs'));
        if ($d['section'] !== 'CUTTING' && ! PtBook::lines($c, $d['section'])->has((string) $d['line_id'])) {
            throw ValidationException::withMessages(['line_id' => __('productiontracking.v.line')]);
        }
        if ($d['section'] === 'CUTTING') {
            $d['line_id'] = null;
        }
        // Available hour: man power × working hours, when left blank.
        if (($d['available_hours'] ?? null) === null && ($d['manpower'] ?? null) && ($d['plan_hours'] ?? null)) {
            $d['available_hours'] = round($d['manpower'] * $d['plan_hours'], 2);
        }
        $saved = $row ? tap($row)->update($d) : PtEntry::query()->create($d + ['created_by' => $this->caller()->auditUserId()]);

        return $id ? ApiResponse::ok(['id' => $saved->id]) : ApiResponse::created(['id' => $saved->id]);
    }

    public function deleteEntry(string $id): JsonResponse
    {
        $this->allow('productiontracking.entry.manage');
        PtEntry::query()->findOrFail($id)->delete();

        return ApiResponse::ok(['deleted' => true]);
    }
}

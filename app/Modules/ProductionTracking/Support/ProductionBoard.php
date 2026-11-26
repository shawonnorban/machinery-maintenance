<?php

declare(strict_types=1);

namespace App\Modules\ProductionTracking\Support;

use App\Modules\ProductionTracking\Models\PtSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What Production Tracking's dashboards, order progress, TV screens and
 * report show (AnnotechERP 30), read from the floor as it is:
 *
 * - **Sewing actual** = Assembly Floor's Sewing Bundle OUT, by its line and
 *   OUT date; **finishing actual** = Finishing Bundle OUT's good pieces.
 * - **Reject / Rework** = what Quality inspection found, charged to the sewing
 *   line that sewed the bundle (finishing: what Finishing OUT marked). Never
 *   added to Actual.
 * - **Target** = the line's approved Daily Production Plan of the day (a
 *   pending one too when the approval workflow is off).
 * - **Production Master Data** entries add their production, target, reject
 *   and rework to the same line and day — for figures no scan records.
 * - **Cutting**: a bundle is Ready from its lay being cut until Sewing Bundle
 *   IN receives it; a cancelled bundle never counts. The ERP keeps no count of
 *   planned bundles, so Planned / Remaining / Ready % read N/A unless an entry
 *   gives a target.
 */
final class ProductionBoard
{
    public function __construct(private readonly string $companyId, private readonly string $from, private readonly string $to) {}

    /* ── Sewing and finishing lines ─────────────────────────────────── */

    /**
     * Each line's target, actual, reject and rework over the period, with
     * its band, style and the time of its last output.
     *
     * @param  array{line?: ?string, buyer?: ?string, search?: ?string}  $filters
     * @return array{lines: list<array>, totals: array, missing: list<string>}
     */
    public function lines(string $section, array $filters = []): array
    {
        $settings = PtBook::settings($this->companyId);
        $lines = PtBook::lines($this->companyId, $section)->where('active', true);
        $facts = $this->facts($section)->when($filters['buyer'] ?? null, fn ($c, $b) => $c->where('buyer', $b));
        $targets = $this->targets($section, $settings);
        $entries = $this->entries($section)->when($filters['buyer'] ?? null, fn ($c, $b) => $c->where('buyer_name', $b));

        $rows = $lines->map(function ($line) use ($facts, $targets, $entries, $settings) {
            $mine = $facts->where('line_id', $line->id);
            $mineEntries = $entries->where('line_id', $line->id);
            $plan = $targets->where('line_id', $line->id);
            $target = $plan->isNotEmpty() ? (int) $plan->sum('target') : ($mineEntries->whereNotNull('target')->isNotEmpty() ? (int) $mineEntries->sum('target') : null);
            $actual = (int) $mine->sum('actual') + (int) $mineEntries->sum('production');
            $pct = $target ? round($actual / $target * 100, 1) : null;

            return [
                'id' => $line->id, 'code' => $line->code, 'name' => $line->name, 'label' => PtBook::label($line), 'floor' => $line->floor,
                'style' => $plan->pluck('style')->filter()->first() ?? $mine->pluck('style')->filter()->first() ?? $mineEntries->pluck('style')->filter()->first(),
                'target' => $target, 'actual' => $actual, 'balance' => $target !== null ? $target - $actual : null,
                'reject' => (int) $mine->sum('reject') + (int) $mineEntries->sum('reject'),
                'rework' => (int) $mine->sum('rework') + (int) $mineEntries->sum('rework'),
                'pct' => $pct, 'band' => PtBook::band($pct, $settings),
                'updated_at' => $mine->max('at'),
            ];
        })
            ->when($filters['line'] ?? null, fn ($c, $id) => $c->where('id', $id))
            ->when($filters['search'] ?? null, fn ($c, $s) => $c->filter(fn ($r) => str_contains(mb_strtolower($r['code'].' '.$r['name'].' '.($r['style'] ?? '')), mb_strtolower($s))))
            ->values();

        $withTarget = $rows->whereNotNull('target');
        $target = (int) $withTarget->sum('target');
        $actual = (int) $rows->sum('actual');

        return [
            'lines' => $rows->all(),
            'totals' => [
                'target' => $target, 'actual' => $actual, 'reject' => (int) $rows->sum('reject'), 'rework' => (int) $rows->sum('rework'),
                'pct' => $target ? round($actual / $target * 100, 1) : null, 'lines' => $rows->count(), 'lines_with_target' => $withTarget->count(),
                'producing' => $rows->where('actual', '>', 0)->count(),
            ],
            'missing' => $rows->whereNull('target')->pluck('code')->values()->all(),
        ];
    }

    /**
     * Output by buyer, style or PO of a section.
     *
     * @return list<array{key: string, buyer: ?string, style: ?string, po: ?string, target: ?int, actual: int, reject: int, rework: int, pct: ?float}>
     */
    public function by(string $section, string $dimension, array $filters = []): array
    {
        $settings = PtBook::settings($this->companyId);
        $facts = $this->facts($section);
        $entries = $this->entries($section)->map(fn ($e) => ['buyer' => $e->buyer_name, 'style' => $e->style, 'po' => $e->po_no, 'actual' => (int) $e->production, 'reject' => (int) $e->reject, 'rework' => (int) $e->rework]);
        $targets = $this->targets($section, $settings);
        $key = fn ($r) => match ($dimension) {
            'buyer' => (string) ($r['buyer'] ?? '—'),
            'style' => ($r['buyer'] ?? '—').'|'.($r['style'] ?? '—'),
            default => ($r['buyer'] ?? '—').'|'.($r['style'] ?? '—').'|'.($r['po'] ?? '—'),
        };
        $all = $facts->map(fn ($f) => (array) $f)->concat($entries);
        $planned = $targets->map(fn ($t) => ['buyer' => $t['buyer'], 'style' => $t['style'], 'po' => $t['po'], 'target' => $t['target']]);

        $keys = $all->map($key)->concat($planned->map($key))->unique();

        return $keys->map(function ($k) use ($all, $planned, $key, $settings) {
            $rows = $all->filter(fn ($r) => $key($r) === $k);
            $plan = $planned->filter(fn ($r) => $key($r) === $k);
            $first = $rows->first() ?? $plan->first();
            $target = $plan->isNotEmpty() ? (int) $plan->sum('target') : null;
            $actual = (int) $rows->sum('actual');
            $pct = $target ? round($actual / $target * 100, 1) : null;

            return ['key' => $k, 'buyer' => $first['buyer'] ?? null, 'style' => $first['style'] ?? null, 'po' => $first['po'] ?? null,
                'target' => $target, 'actual' => $actual, 'reject' => (int) $rows->sum('reject'), 'rework' => (int) $rows->sum('rework'),
                'pct' => $pct, 'band' => PtBook::band($pct, $settings)];
        })
            ->when($filters['buyer'] ?? null, fn ($c, $b) => $c->where('buyer', $b))
            ->when($filters['search'] ?? null, fn ($c, $s) => $c->filter(fn ($r) => str_contains(mb_strtolower(implode(' ', [$r['buyer'], $r['style'], $r['po']])), mb_strtolower($s))))
            ->sortByDesc('actual')->values()->all();
    }

    /**
     * Cumulative sewing output by hour of the period's last day, against the
     * day's approved target spread evenly over 08:00–17:00.
     *
     * @return list<array{hour: string, actual: int, target: int}>
     */
    public function trend(): array
    {
        $day = $this->to;
        $outs = DB::table('asm_bundle_outs as o')->join('asm_bundle_ins as i', 'i.id', '=', 'o.bundle_in_id')
            ->where('o.company_id', $this->companyId)->whereDate('o.out_date', $day)->get(['o.output_qty', 'o.created_at']);
        $target = (int) (new self($this->companyId, $day, $day))->lines('SEWING')['totals']['target'];
        // Production Master Data counts at the hour it was recorded; one recorded before 08:00 or after 20:00 at the nearest end.
        $entries = (new self($this->companyId, $day, $day))->entries('SEWING');
        $hourOf = fn ($at) => max(8, min(20, (int) \Carbon\CarbonImmutable::parse($at)->setTimezone(PtBook::TZ)->format('G')));
        $out = [];
        $sum = 0;
        for ($h = 8; $h <= 20; $h++) {
            $sum += (int) $outs->filter(fn ($o) => (int) \Carbon\CarbonImmutable::parse($o->created_at)->setTimezone(PtBook::TZ)->format('G') === $h)->sum('output_qty')
                + (int) $entries->filter(fn ($e) => $hourOf($e->created_at) === $h)->sum('production');
            $out[] = ['hour' => sprintf('%02d:00', $h), 'actual' => $sum, 'target' => (int) round($target * min(1, max(0, ($h - 7) / 9)))];
        }

        return $out;
    }

    /* ── Cutting ────────────────────────────────────────────────────── */

    /** Bundles cut in the period, ready for sewing or already received, with Production Master Data's cutting figures. */
    public function cutting(?string $barcode = null): array
    {
        $bundles = DB::table('cut_bundles as b')->leftJoin('buyers as y', 'y.id', '=', 'b.buyer_id')
            ->where('b.company_id', $this->companyId)->where('b.status', '!=', 'CANCELLED')
            ->whereBetween('b.cut_date', [$this->from, $this->to])
            ->when($barcode, fn ($q, $c) => $q->where('b.bundle_no', 'ilike', '%'.$c.'%'))
            ->orderByDesc('b.cut_date')->orderBy('b.bundle_no')
            ->get(['b.id', 'b.bundle_no', 'b.cut_date', 'b.po_no', 'b.style', 'b.colour', 'b.size', 'b.qty', 'y.name as buyer']);
        $received = DB::table('asm_bundle_ins')->whereIn('bundle_id', $bundles->pluck('id'))->pluck('receive_date', 'bundle_id');
        $rows = $bundles->map(fn ($b) => [
            'bundle_no' => $b->bundle_no, 'cut_date' => substr((string) $b->cut_date, 0, 10), 'buyer' => $b->buyer, 'style' => $b->style, 'po' => $b->po_no,
            'colour' => $b->colour, 'size' => $b->size, 'qty' => (int) $b->qty, 'status' => isset($received[$b->id]) ? 'ISSUED' : 'READY',
            'received_on' => isset($received[$b->id]) ? substr((string) $received[$b->id], 0, 10) : null,
        ]);
        $entries = $this->entries('CUTTING');
        $ready = $rows->where('status', 'READY');
        $planned = $entries->whereNotNull('target')->isNotEmpty() ? (int) $entries->sum('target') : null;
        $readyPcs = (int) $ready->sum('qty') + (int) $entries->sum('production');
        $group = fn (callable $by) => $rows->groupBy($by)->map(fn ($g, $k) => ['key' => $k ?: '—', 'bundles' => $g->count(), 'pcs' => (int) $g->sum('qty'),
            'ready' => (int) $g->where('status', 'READY')->sum('qty'), 'issued' => (int) $g->where('status', 'ISSUED')->sum('qty')])->values()->all();

        return [
            'kpi' => [
                'planned' => $planned, 'cut_bundles' => $rows->count() + (int) $entries->sum('bundles_ready'),
                'ready_pcs' => $readyPcs, 'issued_bundles' => $rows->where('status', 'ISSUED')->count() + (int) $entries->sum('bundles_issued'),
                'remaining' => $planned !== null ? max(0, $planned - $readyPcs) : null,
                'ready_pct' => $planned ? round($readyPcs / $planned * 100, 1) : null,
            ],
            'bundles' => $rows->values()->all(),
            'buyer' => $group(fn ($r) => $r['buyer']),
            'style' => $group(fn ($r) => $r['style']),
            'po' => $group(fn ($r) => $r['po']),
            'date' => $group(fn ($r) => $r['cut_date']),
        ];
    }

    /* ── Daily production report ────────────────────────────────────── */

    /**
     * Line by line and day by day: target, production, reject, rework and
     * achievement, sewing and finishing.
     *
     * @return list<array>
     */
    public function report(?string $lineId = null, ?string $buyer = null): array
    {
        $out = [];
        $settings = PtBook::settings($this->companyId);
        for ($d = \Carbon\CarbonImmutable::parse($this->from); $d->lte(\Carbon\CarbonImmutable::parse($this->to)); $d = $d->addDay()) {
            $day = new self($this->companyId, $d->toDateString(), $d->toDateString());
            foreach (PtBook::SECTIONS as $section) {
                foreach ($day->lines($section, ['line' => $lineId, 'buyer' => $buyer])['lines'] as $row) {
                    if ($row['target'] === null && $row['actual'] === 0 && $row['reject'] === 0 && $row['rework'] === 0) {
                        continue;
                    }
                    $out[] = ['date' => $d->toDateString(), 'section' => $section] + $row;
                }
            }
        }

        return $out;
    }

    /* ── The facts underneath ───────────────────────────────────────── */

    /**
     * Scanned output and defects of a section in the period, one row per
     * bundle event: line, buyer, style, PO, actual, reject, rework, when.
     *
     * @return Collection<int, object>
     */
    public function facts(string $section): Collection
    {
        if ($section === 'FINISHING') {
            return DB::table('asm_finishing_outs as o')->join('asm_finishing_ins as i', 'i.id', '=', 'o.finishing_in_id')
                ->join('cut_bundles as b', 'b.id', '=', 'i.bundle_id')->leftJoin('buyers as y', 'y.id', '=', 'b.buyer_id')
                ->where('o.company_id', $this->companyId)->whereBetween('o.out_date', [$this->from, $this->to])
                ->get(['i.line_id', 'y.name as buyer', 'b.style', 'b.po_no as po', 'o.good_qty as actual', 'o.reject_qty as reject', 'o.rework_qty as rework', 'o.created_at as at'])
                ->map(fn ($r) => (object) ['line_id' => $r->line_id, 'buyer' => $r->buyer, 'style' => $r->style, 'po' => $r->po, 'actual' => (int) $r->actual, 'reject' => (int) $r->reject, 'rework' => (int) $r->rework, 'at' => $r->at]);
        }

        $output = DB::table('asm_bundle_outs as o')->join('asm_bundle_ins as i', 'i.id', '=', 'o.bundle_in_id')
            ->join('cut_bundles as b', 'b.id', '=', 'i.bundle_id')->leftJoin('buyers as y', 'y.id', '=', 'b.buyer_id')
            ->where('o.company_id', $this->companyId)->whereBetween('o.out_date', [$this->from, $this->to])
            ->get(['i.line_id', 'y.name as buyer', 'b.style', 'b.po_no as po', 'o.output_qty as actual', 'o.created_at as at'])
            ->map(fn ($r) => (object) ['line_id' => $r->line_id, 'buyer' => $r->buyer, 'style' => $r->style, 'po' => $r->po, 'actual' => (int) $r->actual, 'reject' => 0, 'rework' => 0, 'at' => $r->at]);

        // Quality's findings, charged to the sewing line that sewed the bundle.
        $defects = DB::table('asm_quality_inspections as q')->join('asm_quality_ins as qi', 'qi.id', '=', 'q.quality_in_id')
            ->join('asm_bundle_ins as i', 'i.bundle_id', '=', 'qi.bundle_id')
            ->join('cut_bundles as b', 'b.id', '=', 'qi.bundle_id')->leftJoin('buyers as y', 'y.id', '=', 'b.buyer_id')
            ->where('q.company_id', $this->companyId)->whereBetween('q.inspected_on', [$this->from, $this->to])
            ->get(['i.line_id', 'y.name as buyer', 'b.style', 'b.po_no as po', 'q.reject_qty as reject', 'q.rework_qty as rework', 'q.created_at as at'])
            ->map(fn ($r) => (object) ['line_id' => $r->line_id, 'buyer' => $r->buyer, 'style' => $r->style, 'po' => $r->po, 'actual' => 0, 'reject' => (int) $r->reject, 'rework' => (int) $r->rework, 'at' => null]);

        return $output->concat($defects)->values();
    }

    /**
     * The day targets that reach the dashboards: current approved plans (and
     * pending ones when the approval workflow is off).
     *
     * @return Collection<int, array{line_id: string, target: int, buyer: ?string, style: ?string, po: ?string}>
     */
    public function targets(string $section, PtSetting $settings): Collection
    {
        $statuses = $settings->approval_workflow ? ['APPROVED'] : ['APPROVED', 'PENDING_APPROVAL'];

        return DB::table('pt_daily_plans')->where('company_id', $this->companyId)->where('section', $section)->where('current', true)
            ->whereIn('status', $statuses)->whereBetween('plan_date', [$this->from, $this->to])
            ->get(['line_id', 'approved_target', 'proposed_target', 'buyer_name', 'style', 'po_no'])
            ->map(fn ($p) => ['line_id' => $p->line_id, 'target' => (int) ($p->approved_target ?? $p->proposed_target), 'buyer' => $p->buyer_name, 'style' => $p->style, 'po' => $p->po_no]);
    }

    /** Production Master Data rows of a section in the period. */
    public function entries(string $section): Collection
    {
        return DB::table('pt_entries')->where('company_id', $this->companyId)->where('section', $section)
            ->whereBetween('entry_date', [$this->from, $this->to])->get();
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Http\Controllers\Api;

use App\Modules\Assembly\Support\FinishingFloor;
use App\Modules\Assembly\Support\PackingBook;
use App\Modules\HrPayroll\Support\HolidayCalendar;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Assembly Floor → Reports (AnnotechERP 20/09): Brickland's Apparel Assembly
 * Reports — one screen, a report picked from the list, only the parameters
 * it reads (production date, a date range, buyer, order, line, store, item,
 * unit), Preview (or Export to Excel). Each report reads the floor's own
 * books: cutting, sewing in and out, finishing, quality, packing, finished
 * goods and shipment, the trims store, the line machines and plans, and —
 * for the cross-floor ones — knitting, dyeing and sample programmes.
 * Sew S/C Production waits for a sewing sub-contract source.
 */
class AssemblyReportApiController extends ApiController
{
    /** Each report and the parameters it reads, in Brickland's order. */
    public const REPORTS = [
        'mc_spec' => ['line'],
        'hourly_production' => ['order', 'from', 'to'],
        'hourly_target' => ['date'],
        'endline_qc' => ['date'],
        'line_defect' => ['qline', 'date'],
        'order_closing' => ['buyer', 'order'],
        'end_table' => ['date'],
        'floor_defect_monthly' => ['from', 'to'],
        'end_table_detail' => ['date'],
        'hourly_defect' => ['date'],
        'order_closing_statement' => ['buyer', 'order'],
        'sewing_by_floor' => ['date'],
        'trims_ledger' => ['store', 'item', 'from', 'to'],
        'final_inspection' => ['date'],
        'finishing_export' => ['buyer', 'order', 'from', 'to'],
        'actual_sewing_done' => ['buyer', 'from', 'to'],
        'fin_manpower' => ['date'],
        'sewing_done' => ['buyer', 'from', 'to'],
        'booking_capacity' => ['buyer', 'unit', 'from', 'to'],
        'nature_production' => ['from', 'to'],
        'actual_booking_capacity' => ['buyer', 'unit', 'from', 'to'],
        'knit_dye_done' => ['buyer', 'from', 'to'],
        'sample_booking' => ['buyer', 'from', 'to'],
        'yarn_to_fabric' => ['buyer', 'from', 'to'],
        'company_sewing' => ['unit', 'from', 'to'],
        'store_receive_input' => ['date'],
        'colour_store_receive' => ['date'],
        'line_input' => ['line', 'date'],
        'colour_input' => ['date'],
    ];

    private const MAX_RANGE_DAYS = 366;

    private ?PackingBook $book = null;

    private array $memo = [];

    private array $q = [];

    /** What the parameter boxes offer: buyers, orders, sewing and quality lines, units, stores, trims items — and the report list. */
    public function options(): JsonResponse
    {
        $this->allow('assembly.report.view');
        $c = $this->caller()->companyId;

        return ApiResponse::ok([
            'today' => PackingBook::today()->toDateString(),
            'reports' => collect(self::REPORTS)->map(fn ($params, $kind) => ['kind' => $kind, 'params' => $params])->values()->all(),
            'buyers' => DB::table('buyers')->where('company_id', $c)->orderBy('name')->get(['id', 'name', 'short_name'])->map(fn ($b) => ['value' => $b->id, 'label' => $b->short_name ?: $b->name])->all(),
            'orders' => DB::table('merch_orders')->where('company_id', $c)->orderByDesc('order_no')->get(['id', 'order_no', 'style_no', 'buyer_id'])
                ->map(fn ($o) => ['value' => $o->id, 'label' => trim(($o->style_no ?: '—').' — '.$o->order_no.' ['.($o->style_no ?: '—').']'), 'buyer_id' => $o->buyer_id])->all(),
            'lines' => $this->sewingLines()->map(fn ($l) => ['value' => $l->id, 'label' => $l->label])->values()->all(),
            'qlines' => FinishingFloor::lines($c, 'QUALITY')->map(fn ($l) => ['value' => $l->id, 'label' => $l->label])->values()->all(),
            'units' => DB::table('factories')->where('company_id', $c)->orderBy('name')->get(['id', 'name'])->map(fn ($f) => ['value' => $f->id, 'label' => $f->name])->all(),
            'stores' => DB::table('stores')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name.($s->code ? ' ('.$s->code.')' : '')])->all(),
            'items' => DB::table('mp_items')->where('company_id', $c)->where('kind', 'TRIMS')->orderBy('name')->get(['id', 'item_code', 'name'])->map(fn ($i) => ['value' => $i->id, 'label' => trim($i->item_code.' — '.$i->name)])->all(),
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $this->allow('assembly.report.view');
        $kind = $request->validate(['kind' => ['required', Rule::in(array_keys(self::REPORTS))]])['kind'];
        $need = self::REPORTS[$kind];
        $c = $this->caller()->companyId;
        $rules = [
            'date' => [in_array('date', $need, true) ? 'required' : 'nullable', 'date'],
            'from' => [in_array('from', $need, true) ? 'required' : 'nullable', 'date'], 'to' => [in_array('to', $need, true) ? 'required' : 'nullable', 'date'],
            'buyer' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $c)], 'order' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $c)],
            'line' => ['nullable', Rule::exists('planning_lines', 'id')->where('company_id', $c)], 'qline' => ['nullable', Rule::exists('production_lines', 'id')->where('company_id', $c)],
            'unit' => ['nullable', Rule::exists('factories', 'id')->where('company_id', $c)], 'store' => [$kind === 'trims_ledger' ? 'required' : 'nullable', Rule::exists('stores', 'id')->where('company_id', $c)],
            'item' => ['nullable', Rule::exists('mp_items', 'id')->where('company_id', $c)],
        ];
        $q = $request->validate($rules, ['date.required' => __('assembly.v.ar_date'), 'from.required' => __('assembly.v.ar_from'), 'to.required' => __('assembly.v.ar_to'), 'store.required' => __('assembly.v.ar_store')]);
        if (! empty($q['from']) && ! empty($q['to'])) {
            if ($q['to'] < $q['from']) {
                throw ValidationException::withMessages(['to' => __('assembly.v.range_order')]);
            }
            if (CarbonImmutable::parse($q['from'])->diffInDays(CarbonImmutable::parse($q['to'])) + 1 > self::MAX_RANGE_DAYS) {
                throw ValidationException::withMessages(['to' => __('assembly.v.range_max', ['days' => self::MAX_RANGE_DAYS])]);
            }
        }
        $this->q = array_intersect_key($q, array_flip($need));
        [$columns, $rows] = $this->{'r'.str_replace('_', '', ucwords($kind, '_'))}();
        $columns = array_map(fn ($c) => is_array($c) ? $c : ['key' => rtrim($c, '#'), 'numeric' => str_ends_with($c, '#'), 'label' => null], $columns);
        $totals = [];
        foreach ($columns as $col) {
            if ($col['numeric'] && ! str_ends_with($col['key'], '_pct') && $col['key'] !== 'smv' && $col['key'] !== 'capacity' && $col['key'] !== 'hourly_target' && $col['key'] !== 'per_person' && $col['key'] !== 'balance') {
                $totals[$col['key']] = round(array_sum(array_map(fn ($r) => (float) ($r[$col['key']] ?? 0), $rows)), 2);
            }
        }

        return ApiResponse::ok(['kind' => $kind, 'params' => $this->q, 'columns' => $columns, 'rows' => array_values($rows), 'totals' => $totals]);
    }

    /* ───────── Machines, lines, hourly ───────── */

    private function rMcSpec(): array
    {
        $rows = DB::table('planning_line_machines')->where('company_id', $this->caller()->companyId)->where('active', true)
            ->when($this->q['line'] ?? null, fn ($w, $v) => $w->where('planning_line_id', $v))->orderBy('planning_line_id')->orderBy('machine_type')->get()
            ->map(fn ($m) => ['line' => $this->sewingLines()->get($m->planning_line_id)?->label, 'machine_type' => $m->machine_type, 'name_code' => $m->name_code, 'category' => $m->category,
                'installed' => (int) $m->installed, 'working' => (int) $m->working, 'standby' => (int) $m->standby, 'capacity' => (int) $m->capacity_per_hour])
            ->sortBy(fn ($r) => [$r['line'] ?? '', $r['machine_type'] ?? ''])->values()->all();

        return [['line', 'machine_type', 'name_code', 'category', 'installed#', 'working#', 'standby#', 'capacity#'], $rows];
    }

    private function rHourlyProduction(): array
    {
        $outs = $this->sewingOuts($this->q['from'], $this->q['to'])->filter(fn ($o) => empty($this->q['order']) || $o->order_id === $this->q['order']);
        $hours = $outs->map(fn ($o) => $o->hour)->unique()->sort()->values();
        $rows = $outs->groupBy(fn ($o) => $o->order_id.'|'.$o->colour.'|'.$o->size)->map(function ($g) use ($hours) {
            $o = $g->first();
            $row = ['order' => $this->orderLabel($o->order_id), 'colour' => $o->colour, 'size' => $o->size];
            foreach ($hours as $h) {
                $row['h'.$h] = (int) $g->where('hour', $h)->sum('output_qty');
            }

            return $row + ['total' => (int) $g->sum('output_qty')];
        })->sortBy(fn ($r) => [$r['order'], $r['colour'], PackingBook::sizeRank((string) $r['size'])])->values()->all();

        return [['order', 'colour', 'size', ...$this->hourCols($hours), 'total#'], $rows];
    }

    private function rHourlyTarget(): array
    {
        $d = $this->q['date'];
        $outs = $this->sewingOuts($d, $d);
        $targets = DB::table('ws_sewing_targets')->where('company_id', $this->caller()->companyId)->whereDate('target_date', $d)->where('is_off', false)->get()->groupBy('planning_line_id');
        $hours = $outs->map(fn ($o) => $o->hour)->unique()->sort()->values();
        $lineIds = $outs->pluck('line_id')->merge($targets->keys())->unique();
        $rows = $lineIds->map(function ($id) use ($outs, $targets, $hours) {
            $t = $targets->get($id, collect());
            $target = (int) $t->sum(fn ($x) => (int) $x->target_gen + (int) ($x->target_ot ?? 0));
            $wh = (float) ($t->first()->wh ?? 0);
            $mine = $outs->where('line_id', $id);
            $row = ['line' => $this->sewingLines()->get($id)?->label, 'target' => $target, 'hourly_target' => $wh > 0 ? round($target / $wh) : null];
            foreach ($hours as $h) {
                $row['h'.$h] = (int) $mine->where('hour', $h)->sum('output_qty');
            }
            $total = (int) $mine->sum('output_qty');

            return $row + ['total' => $total, 'achv_pct' => $target ? round($total / $target * 100, 1) : null];
        })->sortBy('line')->values()->all();

        return [['line', 'target#', 'hourly_target#', ...$this->hourCols($hours), 'total#', 'achv_pct#'], $rows];
    }

    /* ───────── Quality ───────── */

    private function rEndlineQc(): array
    {
        $rows = $this->inspections($this->q['date'], $this->q['date'])->groupBy('line_id')->map(function ($g) {
            $insp = (int) $g->sum('inspected_qty');
            $bad = (int) $g->sum('reject_qty') + (int) $g->sum('rework_qty');

            return ['line' => $this->qualityLines()->get($g->first()->line_id)?->label, 'bundles' => $g->pluck('quality_in_id')->unique()->count(), 'inspected' => $insp,
                'pass' => (int) $g->sum('pass_qty'), 'reject' => (int) $g->sum('reject_qty'), 'rework' => (int) $g->sum('rework_qty'),
                'pass_pct' => $insp ? round($g->sum('pass_qty') / $insp * 100, 1) : null, 'dhu_pct' => $insp ? round($bad / $insp * 100, 1) : null];
        })->sortBy('line')->values()->all();

        return [['line', 'bundles#', 'inspected#', 'pass#', 'reject#', 'rework#', 'pass_pct#', 'dhu_pct#'], $rows];
    }

    private function rLineDefect(): array
    {
        $insp = $this->inspections($this->q['date'], $this->q['date'])->filter(fn ($r) => empty($this->q['qline']) || $r->line_id === $this->q['qline'])->whereNotNull('defect_type');
        $rows = $insp->groupBy(fn ($r) => $r->line_id.'|'.$r->defect_type)->map(function ($g) use ($insp) {
            $r = $g->first();
            $qty = (int) $g->sum(fn ($x) => $x->reject_qty + $x->rework_qty);
            $lineTotal = (int) $insp->where('line_id', $r->line_id)->sum(fn ($x) => $x->reject_qty + $x->rework_qty);

            return ['line' => $this->qualityLines()->get($r->line_id)?->label, 'defect' => __('assembly.defect.'.$r->defect_type), 'reject' => (int) $g->sum('reject_qty'),
                'rework' => (int) $g->sum('rework_qty'), 'qty' => $qty, 'share_pct' => $lineTotal ? round($qty / $lineTotal * 100, 1) : null];
        })->sortBy(fn ($r) => [$r['line'], -$r['qty']])->values()->all();

        return [['line', 'defect', 'reject#', 'rework#', 'qty#', 'share_pct#'], $rows];
    }

    private function rFloorDefectMonthly(): array
    {
        $rows = $this->inspections($this->q['from'], $this->q['to'])->whereNotNull('defect_type')
            ->groupBy(fn ($r) => substr($r->inspected_on, 0, 7).'|'.($this->qualityLines()->get($r->line_id)?->floor ?? '—').'|'.$r->defect_type)->map(function ($g) {
                $r = $g->first();

                return ['month' => substr($r->inspected_on, 0, 7), 'floor' => $this->qualityLines()->get($r->line_id)?->floor, 'defect' => __('assembly.defect.'.$r->defect_type),
                    'reject' => (int) $g->sum('reject_qty'), 'rework' => (int) $g->sum('rework_qty'), 'qty' => (int) $g->sum(fn ($x) => $x->reject_qty + $x->rework_qty)];
            })->sortBy(fn ($r) => [$r['month'], $r['floor'] ?? '', -$r['qty']])->values()->all();

        return [['month', 'floor', 'defect', 'reject#', 'rework#', 'qty#'], $rows];
    }

    private function rHourlyDefect(): array
    {
        $insp = $this->inspections($this->q['date'], $this->q['date'])->whereNotNull('defect_type');
        $hours = $insp->map(fn ($r) => $r->hour)->unique()->sort()->values();
        $rows = $insp->groupBy('defect_type')->map(function ($g, $defect) use ($hours) {
            $row = ['defect' => __('assembly.defect.'.$defect)];
            foreach ($hours as $h) {
                $row['h'.$h] = (int) $g->where('hour', $h)->sum(fn ($x) => $x->reject_qty + $x->rework_qty);
            }

            return $row + ['total' => (int) $g->sum(fn ($x) => $x->reject_qty + $x->rework_qty)];
        })->sortByDesc('total')->values()->all();

        return [['defect', ...$this->hourCols($hours), 'total#'], $rows];
    }

    private function rFinalInspection(): array
    {
        $rows = $this->inspections($this->q['date'], $this->q['date'])->sortBy('created_at')->map(fn ($r) => $this->bundleCols($r->bundle_id) + [
            'time' => PackingBook::at(CarbonImmutable::parse($r->created_at)), 'line' => $this->qualityLines()->get($r->line_id)?->label, 'round' => (int) $r->round,
            'inspected' => (int) $r->inspected_qty, 'pass' => (int) $r->pass_qty, 'reject' => (int) $r->reject_qty, 'rework' => (int) $r->rework_qty,
            'defect' => $r->defect_type ? __('assembly.defect.'.$r->defect_type) : null,
        ])->values()->all();

        return [['time', 'line', 'bundle_no', 'order', 'colour', 'size', 'round#', 'inspected#', 'pass#', 'reject#', 'rework#', 'defect'], $rows];
    }

    /* ───────── Finishing ───────── */

    private function rEndTable(): array
    {
        $rows = $this->finishingOuts($this->q['date'], $this->q['date'])->groupBy('line_id')->map(function ($g) {
            $qty = (int) $g->sum('in_qty');

            return ['line' => $this->finishingLines()->get($g->first()->line_id)?->label, 'bundles' => $g->count(), 'received' => $qty, 'good' => (int) $g->sum('good_qty'),
                'reject' => (int) $g->sum('reject_qty'), 'rework' => (int) $g->sum('rework_qty'), 'good_pct' => $qty ? round($g->sum('good_qty') / $qty * 100, 1) : null];
        })->sortBy('line')->values()->all();

        return [['line', 'bundles#', 'received#', 'good#', 'reject#', 'rework#', 'good_pct#'], $rows];
    }

    private function rEndTableDetail(): array
    {
        $rows = $this->finishingOuts($this->q['date'], $this->q['date'])->sortBy('created_at')->map(fn ($o) => $this->bundleCols($o->bundle_id) + [
            'time' => PackingBook::at(CarbonImmutable::parse($o->created_at)), 'line' => $this->finishingLines()->get($o->line_id)?->label, 'received' => (int) $o->in_qty,
            'good' => (int) $o->good_qty, 'reject' => (int) $o->reject_qty, 'rework' => (int) $o->rework_qty, 'rework_status' => $o->rework_status ? __('assembly.rework.'.$o->rework_status) : null,
        ])->values()->all();

        return [['time', 'line', 'bundle_no', 'order', 'colour', 'size', 'received#', 'good#', 'reject#', 'rework#', 'rework_status'], $rows];
    }

    private function rFinManpower(): array
    {
        $d = $this->q['date'];
        $outs = $this->finishingOuts($d, $d);
        $rows = $this->finishingLines()->where('active', true)->map(function ($l) use ($outs) {
            $good = (int) $outs->where('line_id', $l->id)->sum('good_qty');
            $mp = (int) $l->manpower;
            $target = (int) $l->target_per_day;

            return ['line' => $l->label, 'manpower' => $mp, 'target' => $target, 'output' => $good, 'per_person' => $mp ? round($good / $mp, 1) : null,
                'achv_pct' => $target ? round($good / $target * 100, 1) : null];
        })->filter(fn ($r) => $r['output'] > 0 || $r['manpower'] > 0)->sortBy('line')->values()->all();

        return [['line', 'manpower#', 'target#', 'output#', 'per_person#', 'achv_pct#'], $rows];
    }

    /* ───────── Orders: closing, finishing & export, done / undone ───────── */

    private function rOrderClosing(): array
    {
        $rows = $this->orders()->map(function ($o) {
            $f = $this->flow($o->id);
            $orderQty = (int) $o->total_qty;

            return ['buyer' => $this->buyer($o->buyer_id), 'order' => $this->orderLabel($o->id), 'ship_date' => $o->ship_date, 'order_qty' => $orderQty, 'cut' => $f['cut'],
                'sewing_in' => $f['sew_in'], 'sewing_out' => $f['sew_out'], 'finishing' => $f['fin_good'], 'qc_pass' => $f['qc_pass'], 'packed' => $f['packed'], 'shipped' => $f['shipped'],
                'balance' => $orderQty - $f['shipped'], 'excess_pct' => $orderQty ? round(($f['shipped'] - $orderQty) / $orderQty * 100, 1) : null];
        })->values()->all();

        return [['buyer', 'order', 'ship_date', 'order_qty#', 'cut#', 'sewing_in#', 'sewing_out#', 'finishing#', 'qc_pass#', 'packed#', 'shipped#', 'balance#', 'excess_pct#'], $rows];
    }

    private function rOrderClosingStatement(): array
    {
        $rows = [];
        foreach ($this->orders() as $o) {
            $sizes = DB::table('merch_order_sizes')->where('order_id', $o->id)->get();
            $cards = $this->cards()->where('order_id', $o->id);
            $keys = $sizes->map(fn ($s) => $s->colour.'|'.$s->size)->merge($cards->map(fn ($c) => $c->colour.'|'.$c->size))->unique();
            $ledger = DB::table('asm_fg_ledger')->where('order_id', $o->id)->get();
            foreach ($keys as $k) {
                [$colour, $size] = explode('|', $k, 2);
                $ids = $cards->where('colour', $colour)->where('size', $size)->pluck('id')->all();
                $f = $this->flowOfBundles($ids);
                $l = $ledger->where('colour', $colour)->where('size', $size);
                $orderQty = (int) $sizes->where('colour', $colour)->where('size', $size)->sum('qty');
                $packed = (int) $l->where('doc_type', 'PACKING_OUT')->sum('qty_in');
                $shipped = (int) $l->where('doc_type', '!=', 'PACKING_OUT')->sum(fn ($x) => $x->qty_out - $x->qty_in);
                $rows[] = ['buyer' => $this->buyer($o->buyer_id), 'order' => $this->orderLabel($o->id), 'colour' => $colour, 'size' => $size, 'order_qty' => $orderQty, 'cut' => $f['cut'],
                    'sewing_out' => $f['sew_out'], 'finishing' => $f['fin_good'], 'qc_pass' => $f['qc_pass'], 'packed' => $packed, 'shipped' => $shipped, 'balance' => $orderQty - $shipped,
                    'excess_pct' => $orderQty ? round(($shipped - $orderQty) / $orderQty * 100, 1) : null];
            }
        }
        usort($rows, fn ($a, $b) => [$a['order'], $a['colour'], PackingBook::sizeRank($a['size'])] <=> [$b['order'], $b['colour'], PackingBook::sizeRank($b['size'])]);

        return [['buyer', 'order', 'colour', 'size', 'order_qty#', 'cut#', 'sewing_out#', 'finishing#', 'qc_pass#', 'packed#', 'shipped#', 'balance#', 'excess_pct#'], $rows];
    }

    private function rFinishingExport(): array
    {
        [$f, $t] = [$this->q['from'], $this->q['to']];
        $fin = $this->finishingOuts($f, $t);
        $qc = DB::table('asm_quality_outs as o')->join('asm_quality_ins as i', 'i.id', '=', 'o.quality_in_id')->whereBetween('o.out_date', [$f, $t])->where('o.company_id', $this->caller()->companyId)->get(['i.bundle_id', 'o.pass_qty']);
        $packed = DB::table('asm_packing_batches')->where('company_id', $this->caller()->companyId)->where('status', 'PACKED_OUT')->whereBetween('out_date', [$f, $t])->get(['order_id', 'final_packed_qty']);
        $shipped = DB::table('asm_shipments')->where('company_id', $this->caller()->companyId)->where('status', 'SHIPPED')->get()->filter(fn ($s) => ($d = PackingBook::day(CarbonImmutable::parse($s->shipped_at))) >= $f && $d <= $t);
        $rows = $this->orders()->map(function ($o) use ($fin, $qc, $packed, $shipped) {
            $ids = $this->cards()->where('order_id', $o->id)->pluck('id')->all();

            return ['buyer' => $this->buyer($o->buyer_id), 'order' => $this->orderLabel($o->id), 'order_qty' => (int) $o->total_qty, 'finishing' => (int) $fin->whereIn('bundle_id', $ids)->sum('good_qty'),
                'qc_pass' => (int) $qc->whereIn('bundle_id', $ids)->sum('pass_qty'), 'packed' => (int) $packed->where('order_id', $o->id)->sum('final_packed_qty'),
                'shipped' => (int) $shipped->where('order_id', $o->id)->sum('qty'), 'cartons' => (int) $shipped->where('order_id', $o->id)->sum('carton_qty')];
        })->filter(fn ($r) => $r['finishing'] + $r['qc_pass'] + $r['packed'] + $r['shipped'] > 0)->values()->all();

        return [['buyer', 'order', 'order_qty#', 'finishing#', 'qc_pass#', 'packed#', 'shipped#', 'cartons#'], $rows];
    }

    private function rActualSewingDone(): array
    {
        $sewn = $this->sewingOuts(null, null)->groupBy('order_id')->map(fn ($g) => (int) $g->sum('output_qty'));
        $rows = $this->ordersShipping()->map(function ($o) use ($sewn) {
            $booked = (int) $o->total_qty;
            $done = (int) ($sewn[$o->id] ?? 0);

            return ['buyer' => $this->buyer($o->buyer_id), 'order' => $this->orderLabel($o->id), 'ship_date' => $o->ship_date, 'booked' => $booked, 'sewn' => $done,
                'undone' => max(0, $booked - $done), 'done_pct' => $booked ? round($done / $booked * 100, 1) : null];
        })->sortBy(fn ($r) => [$r['buyer'] ?? '', $r['ship_date']])->values()->all();

        return [['buyer', 'order', 'ship_date', 'booked#', 'sewn#', 'undone#', 'done_pct#'], $rows];
    }

    private function rSewingDone(): array
    {
        $sewn = $this->sewingOuts($this->q['from'], $this->q['to'])->groupBy('order_id')->map(fn ($g) => (int) $g->sum('output_qty'));
        $rows = $this->ordersShipping()->groupBy('buyer_id')->map(function ($g, $buyer) use ($sewn) {
            $booked = (int) $g->sum('total_qty');
            $done = (int) $g->sum(fn ($o) => (int) ($sewn[$o->id] ?? 0));

            return ['buyer' => $this->buyer($buyer), 'orders' => $g->count(), 'booked' => $booked, 'sewn' => $done, 'undone' => max(0, $booked - $done), 'done_pct' => $booked ? round($done / $booked * 100, 1) : null];
        })->sortBy('buyer')->values()->all();

        return [['buyer', 'orders#', 'booked#', 'sewn#', 'undone#', 'done_pct#'], $rows];
    }

    /* ───────── Capacity, nature, company ───────── */

    private function rBookingCapacity(): array
    {
        return $this->capacity(false);
    }

    private function rActualBookingCapacity(): array
    {
        return $this->capacity(true);
    }

    /** Booked (or actually sewn) minutes per buyer against the sewing lines' capacity over the range. */
    private function capacity(bool $actual): array
    {
        [$f, $t] = [$this->q['from'], $this->q['to']];
        $lines = $this->sewingLines()->filter(fn ($l) => $l->active && (empty($this->q['unit']) || $l->unit_id === $this->q['unit']));
        $days = $this->workingDays($f, $t);
        $capacity = (int) round($lines->sum(fn ($l) => (float) $l->std_manpower * (float) $l->std_hours * 60 * ((float) $l->std_efficiency ?: 100) / 100) * $days);
        $smv = DB::table('planning_style_smvs')->where('company_id', $this->caller()->companyId)->where('active', true)->pluck('smv', 'style_no');
        $sewn = $actual ? $this->sewingOuts($f, $t)->filter(fn ($o) => $lines->has($o->line_id))->groupBy('order_id')->map(fn ($g) => (int) $g->sum('output_qty')) : collect();
        $orders = $actual ? $this->orders()->whereIn('id', $sewn->keys()) : $this->ordersShipping();
        $rows = $orders->groupBy('buyer_id')->map(function ($g, $buyer) use ($smv, $sewn, $actual, $capacity) {
            $qty = (int) $g->sum(fn ($o) => $actual ? (int) ($sewn[$o->id] ?? 0) : (int) $o->total_qty);
            $min = (int) round($g->sum(fn ($o) => ($actual ? (int) ($sewn[$o->id] ?? 0) : (int) $o->total_qty) * (float) ($smv[$o->style_no] ?? 0)));

            return ['buyer' => $this->buyer($buyer), 'orders' => $g->count(), 'qty' => $qty, 'smv' => $qty ? round($min / $qty, 2) : null, 'minutes' => $min, 'capacity' => $capacity,
                'share_pct' => $capacity ? round($min / $capacity * 100, 1) : null];
        })->sortBy('buyer')->values()->all();

        return [['buyer', 'orders#', 'qty#', 'smv#', 'minutes#', ['key' => 'capacity', 'numeric' => true, 'label' => null], 'share_pct#'], $rows];
    }

    private function rNatureProduction(): array
    {
        $ins = $this->sewingIns($this->q['from'], $this->q['to']);
        $outs = $this->sewingOuts($this->q['from'], $this->q['to']);
        $nature = fn ($orderId) => ($o = $this->orderRow($orderId)) ? trim(($o->item ?: '—').' · '.($o->gmt_type ?: $o->order_type ?: '—')) : '—';
        $rows = $ins->map(fn ($i) => $nature($i->order_id))->merge($outs->map(fn ($o) => $nature($o->order_id)))->unique()->map(fn ($n) => [
            'nature' => $n, 'orders' => $outs->filter(fn ($o) => $nature($o->order_id) === $n)->pluck('order_id')->merge($ins->filter(fn ($i) => $nature($i->order_id) === $n)->pluck('order_id'))->unique()->count(),
            'input' => (int) $ins->filter(fn ($i) => $nature($i->order_id) === $n)->sum('qty'), 'output' => (int) $outs->filter(fn ($o) => $nature($o->order_id) === $n)->sum('output_qty'),
        ])->sortBy('nature')->values()->all();

        return [['nature', 'orders#', 'input#', 'output#'], $rows];
    }

    private function rCompanySewing(): array
    {
        $ins = $this->sewingIns($this->q['from'], $this->q['to']);
        $outs = $this->sewingOuts($this->q['from'], $this->q['to']);
        $unitOf = fn ($lineId) => $this->sewingLines()->get($lineId);
        $keys = $ins->map(fn ($i) => ($unitOf($i->line_id)?->unit_id ?? '').'|'.$i->receive_date)->merge($outs->map(fn ($o) => ($unitOf($o->line_id)?->unit_id ?? '').'|'.$o->out_date))->unique();
        $rows = $keys->map(function ($k) use ($ins, $outs, $unitOf) {
            [$unit, $day] = explode('|', $k, 2);

            return ['unit' => $this->units()[$unit] ?? '—', 'date' => $day, 'input' => (int) $ins->filter(fn ($i) => ($unitOf($i->line_id)?->unit_id ?? '') === $unit && $i->receive_date === $day)->sum('qty'),
                'output' => (int) $outs->filter(fn ($o) => ($unitOf($o->line_id)?->unit_id ?? '') === $unit && $o->out_date === $day)->sum('output_qty'), '_unit' => $unit];
        })->filter(fn ($r) => empty($this->q['unit']) || $r['_unit'] === $this->q['unit'])->map(fn ($r) => collect($r)->except('_unit')->all())->sortBy(fn ($r) => [$r['unit'], $r['date']])->values()->all();

        return [['unit', 'date', 'input#', 'output#'], $rows];
    }

    private function rSewingByFloor(): array
    {
        $d = $this->q['date'];
        $ins = $this->sewingIns($d, $d);
        $outs = $this->sewingOuts($d, $d);
        $targets = DB::table('ws_sewing_targets')->where('company_id', $this->caller()->companyId)->whereDate('target_date', $d)->where('is_off', false)->get()->groupBy('planning_line_id');
        $rows = $ins->pluck('line_id')->merge($outs->pluck('line_id'))->unique()->map(function ($id) use ($ins, $outs, $targets) {
            $l = $this->sewingLines()->get($id);
            $target = (int) $targets->get($id, collect())->sum(fn ($x) => (int) $x->target_gen + (int) ($x->target_ot ?? 0));
            $out = (int) $outs->where('line_id', $id)->sum('output_qty');

            return ['floor' => $l?->floor, 'line' => $l?->label, 'input' => (int) $ins->where('line_id', $id)->sum('qty'), 'output' => $out, 'target' => $target, 'achv_pct' => $target ? round($out / $target * 100, 1) : null];
        })->sortBy(fn ($r) => [$r['floor'] ?? '', $r['line'] ?? ''])->values()->all();

        return [['floor', 'line', 'input#', 'output#', 'target#', 'achv_pct#'], $rows];
    }

    /* ───────── Cutting store & sewing input ───────── */

    private function rStoreReceiveInput(): array
    {
        $d = $this->q['date'];
        $cut = $this->cards()->filter(fn ($c) => $c->cut_date === $d);
        $ins = $this->sewingIns($d, $d);
        $rows = $cut->map(fn ($c) => $c->order_id.'|'.$c->colour)->merge($ins->map(fn ($i) => $i->order_id.'|'.$i->colour))->unique()->map(function ($k) use ($cut, $ins) {
            [$order, $colour] = explode('|', $k, 2);

            return ['order' => $this->orderLabel($order ?: null), 'colour' => $colour, 'received' => (int) $cut->where('order_id', $order)->where('colour', $colour)->sum('qty'),
                'input' => (int) $ins->where('order_id', $order)->where('colour', $colour)->sum('qty')];
        })->sortBy(fn ($r) => [$r['order'], $r['colour']])->values()->all();

        return [['order', 'colour', 'received#', 'input#'], $rows];
    }

    private function rColourStoreReceive(): array
    {
        $rows = $this->cards()->filter(fn ($c) => $c->cut_date === $this->q['date'])->groupBy(fn ($c) => $c->order_id.'|'.$c->colour)->map(fn ($g) => [
            'order' => $this->orderLabel($g->first()->order_id), 'colour' => $g->first()->colour, 'sizes' => $g->groupBy('size')->sortBy(fn ($s, $size) => [PackingBook::sizeRank((string) $size), (string) $size])->map(fn ($s, $size) => $size.':'.$s->sum('qty'))->implode(' '),
            'bundles' => $g->count(), 'qty' => (int) $g->sum('qty'),
        ])->sortBy(fn ($r) => [$r['order'], $r['colour']])->values()->all();

        return [['order', 'colour', 'sizes', 'bundles#', 'qty#'], $rows];
    }

    private function rLineInput(): array
    {
        $rows = $this->sewingIns($this->q['date'], $this->q['date'])->filter(fn ($i) => empty($this->q['line']) || $i->line_id === $this->q['line'])->sortBy('created_at')
            ->map(fn ($i) => ['line' => $this->sewingLines()->get($i->line_id)?->label] + $this->bundleCols($i->bundle_id) + ['time' => PackingBook::at(CarbonImmutable::parse($i->created_at)),
                'bundle_qty' => (int) $i->bundle_qty, 'short' => (int) $i->short_qty, 'qty' => (int) $i->qty])->values()->all();

        return [['line', 'time', 'bundle_no', 'order', 'colour', 'size', 'bundle_qty#', 'short#', 'qty#'], $rows];
    }

    private function rColourInput(): array
    {
        $rows = $this->sewingIns($this->q['date'], $this->q['date'])->groupBy(fn ($i) => $i->order_id.'|'.$i->colour)->map(fn ($g) => [
            'order' => $this->orderLabel($g->first()->order_id), 'colour' => $g->first()->colour, 'lines' => $g->pluck('line_id')->unique()->map(fn ($id) => $this->sewingLines()->get($id)?->code)->implode(', '),
            'bundles' => $g->count(), 'qty' => (int) $g->sum('qty'),
        ])->sortBy(fn ($r) => [$r['order'], $r['colour']])->values()->all();

        return [['order', 'colour', 'lines', 'bundles#', 'qty#'], $rows];
    }

    /* ───────── Trims store ───────── */

    private function rTrimsLedger(): array
    {
        [$f, $t, $store] = [$this->q['from'], $this->q['to'], $this->q['store']];
        $moves = DB::table('asm_trims_moves')->where('company_id', $this->caller()->companyId)->where('store_id', $store)->when($this->q['item'] ?? null, fn ($w, $v) => $w->where('item_id', $v))
            ->whereDate('move_date', '<=', $t)->orderBy('move_date')->orderBy('created_at')->orderBy('id')->get();
        $items = DB::table('mp_items')->whereIn('id', $moves->pluck('item_id')->unique())->get()->keyBy('id');
        $rows = [];
        foreach ($moves->groupBy('item_id') as $itemId => $g) {
            $name = trim(($items[$itemId]->item_code ?? '').' — '.($items[$itemId]->name ?? ''));
            $bal = (float) $g->filter(fn ($m) => $m->move_date < $f)->sum('qty');
            $rows[] = ['date' => $f, 'item' => $name, 'doc_no' => __('assembly.ar.opening'), 'kind' => null, 'in' => null, 'out' => null, 'balance' => round($bal, 3)];
            foreach ($g->filter(fn ($m) => $m->move_date >= $f) as $m) {
                $bal += (float) $m->qty;
                $rows[] = ['date' => substr($m->move_date, 0, 10), 'item' => $name, 'doc_no' => $m->doc_no, 'kind' => __('assembly.ar.move.'.$m->kind),
                    'in' => $m->qty > 0 ? round((float) $m->qty, 3) : null, 'out' => $m->qty < 0 ? round(-(float) $m->qty, 3) : null, 'balance' => round($bal, 3)];
            }
        }

        return [['date', 'item', 'doc_no', 'kind', 'in#', 'out#', 'balance#'], $rows];
    }

    /* ───────── Knitting, dyeing, yarn, samples ───────── */

    private function rKnitDyeDone(): array
    {
        $c = $this->caller()->companyId;
        $orders = $this->ordersShipping();
        $po = DB::table('knit_prod_orders')->where('company_id', $c)->whereIn('order_id', $orders->pluck('id'))->get();
        $knit = DB::table('knit_rolls as r')->join('knit_cards as k', 'k.id', '=', 'r.card_id')->whereIn('k.prod_order_id', $po->pluck('id'))->groupBy('k.prod_order_id')->selectRaw('k.prod_order_id, sum(r.weight) as kg')->pluck('kg', 'prod_order_id');
        $dyed = DB::table('dye_batches')->where('company_id', $c)->whereIn('order_id', $orders->pluck('id'))->where('status', '!=', 'REPLACED')->groupBy('order_id')->selectRaw('order_id, sum(coalesce(card_qty, plan_qty)) as kg')->pluck('kg', 'order_id');
        $rows = $orders->map(function ($o) use ($po, $knit, $dyed) {
            $mine = $po->where('order_id', $o->id);
            $req = (float) $mine->sum(fn ($p) => (float) ($p->adjusted_qty ?: $p->req_qty));
            $k = (float) $mine->sum(fn ($p) => (float) ($knit[$p->id] ?? 0));
            $d = (float) ($dyed[$o->id] ?? 0);

            return ['buyer' => $this->buyer($o->buyer_id), 'order' => $this->orderLabel($o->id), 'ship_date' => $o->ship_date, 'fabric_req' => round($req, 2), 'knitted' => round($k, 2),
                'knit_undone' => round(max(0, $req - $k), 2), 'dyed' => round($d, 2), 'dye_undone' => round(max(0, $req - $d), 2), 'knit_pct' => $req ? round($k / $req * 100, 1) : null, 'dye_pct' => $req ? round($d / $req * 100, 1) : null];
        })->filter(fn ($r) => $r['fabric_req'] + $r['knitted'] + $r['dyed'] > 0)->sortBy(fn ($r) => [$r['buyer'] ?? '', $r['ship_date']])->values()->all();

        return [['buyer', 'order', 'ship_date', 'fabric_req#', 'knitted#', 'knit_undone#', 'dyed#', 'dye_undone#', 'knit_pct#', 'dye_pct#'], $rows];
    }

    private function rYarnToFabric(): array
    {
        [$f, $t] = [$this->q['from'], $this->q['to']];
        $c = $this->caller()->companyId;
        $yarn = DB::table('knit_yarn_issue_reqs as r')->join('knit_yarn_issue_req_lines as l', 'l.req_id', '=', 'r.id')->where('r.company_id', $c)->whereBetween('r.req_date', [$f, $t])
            ->when($this->q['buyer'] ?? null, fn ($w, $v) => $w->where('r.buyer_id', $v))->whereNotNull('r.order_id')->groupBy('r.order_id', 'r.buyer_id')->selectRaw('r.order_id, r.buyer_id, sum(l.issued_qty) as kg')->get()->keyBy('order_id');
        $fabric = DB::table('knit_rolls as r')->join('knit_cards as k', 'k.id', '=', 'r.card_id')->join('knit_prod_orders as p', 'p.id', '=', 'k.prod_order_id')->where('r.company_id', $c)
            ->whereBetween(DB::raw('cast(r.roll_at as date)'), [$f, $t])->whereNotNull('p.order_id')->groupBy('p.order_id', 'p.buyer_id')->selectRaw('p.order_id, p.buyer_id, sum(r.weight) as kg')->get()->keyBy('order_id');
        $rows = $yarn->keys()->merge($fabric->keys())->unique()->map(function ($id) use ($yarn, $fabric) {
            $y = (float) ($yarn[$id]->kg ?? 0);
            $fb = (float) ($fabric[$id]->kg ?? 0);
            $buyer = $yarn[$id]->buyer_id ?? $fabric[$id]->buyer_id ?? $this->orderRow($id)?->buyer_id;

            return ['buyer' => $this->buyer($buyer), 'order' => $this->orderLabel($id), 'yarn_issued' => round($y, 2), 'grey_fabric' => round($fb, 2), 'balance' => round($y - $fb, 2),
                'loss_pct' => $y ? round(($y - $fb) / $y * 100, 1) : null, '_buyer' => $buyer];
        })->filter(fn ($r) => empty($this->q['buyer']) || $r['_buyer'] === $this->q['buyer'])->map(fn ($r) => collect($r)->except('_buyer')->all())->sortBy(fn ($r) => [$r['buyer'] ?? '', $r['order']])->values()->all();

        return [['buyer', 'order', 'yarn_issued#', 'grey_fabric#', 'balance#', 'loss_pct#'], $rows];
    }

    private function rSampleBooking(): array
    {
        $c = $this->caller()->companyId;
        $types = DB::table('sample_types')->pluck('name', 'id');
        $rows = DB::table('merch_sample_programs as p')->join('merch_sample_lines as l', 'l.program_id', '=', 'p.id')->leftJoin('merch_orders as o', 'o.id', '=', 'p.order_id')
            ->where('p.company_id', $c)->whereBetween('p.program_date', [$this->q['from'], $this->q['to']])->when($this->q['buyer'] ?? null, fn ($w, $v) => $w->where('o.buyer_id', $v))
            ->orderBy('p.program_date')->orderBy('p.program_no')->orderBy('l.sort_order')->get(['p.program_no', 'p.program_date', 'p.order_id', 'o.buyer_id', 'l.sample_type_id', 'l.item', 'l.colour', 'l.size', 'l.req_qty', 'l.sewn_qty', 'l.delivered_qty', 'l.rejected_qty', 'l.status'])
            ->map(fn ($r) => ['program_no' => $r->program_no, 'date' => substr((string) $r->program_date, 0, 10), 'buyer' => $this->buyer($r->buyer_id), 'order' => $this->orderLabel($r->order_id),
                'sample_type' => $types[$r->sample_type_id] ?? null, 'item' => $r->item, 'colour' => $r->colour, 'size' => $r->size, 'req' => (float) $r->req_qty, 'sewn' => (float) $r->sewn_qty,
                'delivered' => (float) $r->delivered_qty, 'rejected' => (float) $r->rejected_qty, 'balance' => (float) $r->req_qty - (float) $r->delivered_qty, 'status' => $r->status])->all();

        return [['program_no', 'date', 'buyer', 'order', 'sample_type', 'item', 'colour', 'size', 'req#', 'sewn#', 'delivered#', 'rejected#', 'balance#', 'status'], $rows];
    }

    /* ───────── Sources ───────── */

    /** Sewing outputs (with the card and the line), optionally for a range of out dates; `hour` on the Dhaka clock. */
    private function sewingOuts(?string $f, ?string $t): Collection
    {
        return $this->memo['outs'][$f.'|'.$t] ??= DB::table('asm_bundle_outs as o')->join('asm_bundle_ins as i', 'i.id', '=', 'o.bundle_in_id')->join('cut_bundles as b', 'b.id', '=', 'i.bundle_id')
            ->where('o.company_id', $this->caller()->companyId)->when($f, fn ($w) => $w->whereBetween('o.out_date', [$f, $t]))
            ->get(['o.output_qty', 'o.out_date', 'o.created_at', 'i.line_id', 'i.bundle_id', 'b.order_id', 'b.colour', 'b.size'])
            ->map(function ($o) {
                $o->out_date = substr((string) $o->out_date, 0, 10);
                $o->hour = CarbonImmutable::parse($o->created_at, 'UTC')->setTimezone('Asia/Dhaka')->format('H');

                return $o;
            });
    }

    private function sewingIns(string $f, string $t): Collection
    {
        return $this->memo['ins'][$f.'|'.$t] ??= DB::table('asm_bundle_ins as i')->join('cut_bundles as b', 'b.id', '=', 'i.bundle_id')->where('i.company_id', $this->caller()->companyId)
            ->whereBetween('i.receive_date', [$f, $t])->get(['i.bundle_id', 'i.receive_date', 'i.line_id', 'i.bundle_qty', 'i.short_qty', 'i.qty', 'i.created_at', 'b.order_id', 'b.colour', 'b.size'])
            ->map(function ($i) {
                $i->receive_date = substr((string) $i->receive_date, 0, 10);

                return $i;
            });
    }

    private function finishingOuts(string $f, string $t): Collection
    {
        return DB::table('asm_finishing_outs as o')->join('asm_finishing_ins as i', 'i.id', '=', 'o.finishing_in_id')->where('o.company_id', $this->caller()->companyId)
            ->whereBetween('o.out_date', [$f, $t])->get(['o.good_qty', 'o.reject_qty', 'o.rework_qty', 'o.rework_status', 'o.created_at', 'i.qty as in_qty', 'i.line_id', 'i.bundle_id']);
    }

    /** Quality inspections in a range, with the line and the card; `hour` on the Dhaka clock. */
    private function inspections(string $f, string $t): Collection
    {
        return DB::table('asm_quality_inspections as r')->join('asm_quality_ins as i', 'i.id', '=', 'r.quality_in_id')->where('r.company_id', $this->caller()->companyId)
            ->whereBetween('r.inspected_on', [$f, $t])->get(['r.quality_in_id', 'r.round', 'r.inspected_on', 'r.inspected_qty', 'r.pass_qty', 'r.reject_qty', 'r.rework_qty', 'r.defect_type', 'r.created_at', 'i.line_id', 'i.bundle_id'])
            ->map(function ($r) {
                $r->inspected_on = substr((string) $r->inspected_on, 0, 10);
                $r->hour = CarbonImmutable::parse($r->created_at, 'UTC')->setTimezone('Asia/Dhaka')->format('H');

                return $r;
            });
    }

    /** Cut → sewn → finished → QC pass → packed → shipped for one order. */
    private function flow(string $orderId): array
    {
        $f = $this->flowOfBundles($this->cards()->where('order_id', $orderId)->pluck('id')->all());
        $c = $this->caller()->companyId;
        $f['packed'] = (int) DB::table('asm_packing_batches')->where('company_id', $c)->where('order_id', $orderId)->where('status', 'PACKED_OUT')->sum('final_packed_qty');
        $f['shipped'] = (int) DB::table('asm_fg_ledger')->where('company_id', $c)->where('order_id', $orderId)->where('doc_type', '!=', 'PACKING_OUT')->get()->sum(fn ($l) => $l->qty_out - $l->qty_in);

        return $f;
    }

    private function flowOfBundles(array $ids): array
    {
        if (! $ids) {
            return ['cut' => 0, 'sew_in' => 0, 'sew_out' => 0, 'fin_good' => 0, 'qc_pass' => 0];
        }

        return [
            'cut' => (int) $this->cards()->whereIn('id', $ids)->sum('qty'),
            'sew_in' => (int) DB::table('asm_bundle_ins')->whereIn('bundle_id', $ids)->sum('qty'),
            'sew_out' => (int) DB::table('asm_bundle_outs as o')->join('asm_bundle_ins as i', 'i.id', '=', 'o.bundle_in_id')->whereIn('i.bundle_id', $ids)->sum('o.output_qty'),
            'fin_good' => (int) DB::table('asm_finishing_outs as o')->join('asm_finishing_ins as i', 'i.id', '=', 'o.finishing_in_id')->whereIn('i.bundle_id', $ids)->sum('o.good_qty'),
            'qc_pass' => (int) DB::table('asm_quality_outs as o')->join('asm_quality_ins as i', 'i.id', '=', 'o.quality_in_id')->whereIn('i.bundle_id', $ids)->sum('o.pass_qty'),
        ];
    }

    private function cards(): Collection
    {
        return $this->memo['cards'] ??= DB::table('cut_bundles')->where('company_id', $this->caller()->companyId)->where('status', '!=', 'CANCELLED')
            ->get(['id', 'bundle_no', 'order_id', 'colour', 'size', 'qty', 'cut_date'])->map(function ($b) {
                $b->cut_date = substr((string) $b->cut_date, 0, 10);

                return $b;
            })->keyBy('id');
    }

    private function bundleCols(string $bundleId): array
    {
        $b = $this->cards()->get($bundleId);

        return ['bundle_no' => $b?->bundle_no, 'order' => $this->orderLabel($b?->order_id), 'colour' => $b?->colour, 'size' => $b?->size];
    }

    /** The orders the buyer / order boxes narrow to. */
    private function orders(): Collection
    {
        return DB::table('merch_orders')->where('company_id', $this->caller()->companyId)->when($this->q['buyer'] ?? null, fn ($w, $v) => $w->where('buyer_id', $v))
            ->when($this->q['order'] ?? null, fn ($w, $v) => $w->where('id', $v))->orderBy('ship_date')->orderBy('order_no')->get();
    }

    /** Orders shipping in the range (and of the buyer, when picked). */
    private function ordersShipping(): Collection
    {
        return $this->orders()->filter(fn ($o) => $o->ship_date && $o->ship_date >= $this->q['from'] && $o->ship_date <= $this->q['to'])->values();
    }

    private function orderRow(?string $id): ?object
    {
        if (! $id) {
            return null;
        }

        return $this->memo['order'][$id] ??= DB::table('merch_orders')->where('id', $id)->first();
    }

    private function orderLabel(?string $id): ?string
    {
        $o = $this->orderRow($id);

        return $o ? trim(($o->style_no ?: '—').' — '.$o->order_no.' ['.($o->style_no ?: '—').']') : null;
    }

    private function buyer(?string $id): ?string
    {
        return $this->book()->buyer($id)[0];
    }

    /** Sewing lines by id: label, floor, unit and their standard capacity. */
    private function sewingLines(): Collection
    {
        return $this->memo['lines'] ??= DB::table('planning_lines as l')->leftJoin('floors as f', 'f.id', '=', 'l.floor_id')->leftJoin('buildings as b', 'b.id', '=', 'f.building_id')
            ->where('l.company_id', $this->caller()->companyId)->orderBy('f.name')->orderBy('l.display_order')->orderBy('l.code')
            ->get(['l.id', 'l.code', 'l.name', 'l.active', 'l.std_manpower', 'l.std_hours', 'l.std_efficiency', 'f.name as floor', 'b.factory_id as unit_id'])
            ->map(function ($l) {
                $l->label = trim($l->code.' · '.$l->name);

                return $l;
            })->keyBy('id');
    }

    private function qualityLines(): Collection
    {
        return $this->memo['qlines'] ??= FinishingFloor::lines($this->caller()->companyId, 'QUALITY');
    }

    private function finishingLines(): Collection
    {
        return $this->memo['flines'] ??= FinishingFloor::lines($this->caller()->companyId);
    }

    private function units(): array
    {
        return $this->memo['units'] ??= DB::table('factories')->where('company_id', $this->caller()->companyId)->pluck('name', 'id')->all();
    }

    /** Working days in a range: every day but Friday and HR & Payroll's holidays (the one factory calendar). */
    private function workingDays(string $f, string $t): int
    {
        $holidays = HolidayCalendar::dates($this->caller()->companyId, $f, $t);
        $n = 0;
        for ($d = CarbonImmutable::parse($f); $d->toDateString() <= $t; $d = $d->addDay()) {
            $n += (int) (! $d->isFriday() && ! isset($holidays[$d->toDateString()]));
        }

        return $n;
    }

    /** @return list<array{key: string, numeric: bool, label: string}> */
    private function hourCols(Collection $hours): array
    {
        return $hours->map(fn ($h) => ['key' => 'h'.$h, 'numeric' => true, 'label' => $h.'-'.str_pad((string) ((int) $h + 1), 2, '0', STR_PAD_LEFT)])->all();
    }

    private function book(): PackingBook
    {
        return $this->book ??= new PackingBook($this->caller()->companyId);
    }
}

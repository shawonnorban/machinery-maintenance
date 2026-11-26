<?php

declare(strict_types=1);

namespace App\Modules\StoreMaterial\Http\Controllers\Api;

use App\Modules\HrPayroll\Models\HrEmployee;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\MaterialProcurement\Models\PurchaseRequisition;
use App\Modules\MaterialProcurement\Models\PurchaseRequisitionLine;
use App\Modules\MaterialProcurement\Support\Procurement;
use App\Modules\StoreMaterial\Models\Grn;
use App\Modules\StoreMaterial\Models\GrnLine;
use App\Modules\StoreMaterial\Models\LedgerEntry;
use App\Modules\StoreMaterial\Models\MaterialOut;
use App\Modules\StoreMaterial\Models\MaterialReturn;
use App\Modules\StoreMaterial\Models\MinLevel;
use App\Modules\StoreMaterial\Models\Roll;
use App\Modules\StoreMaterial\Models\RollMove;
use App\Modules\StoreMaterial\Models\Stock;
use App\Modules\StoreMaterial\Support\StoreBook;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Floor;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\Tenancy\Models\Section;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Pdf\TablePdf;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Store & Material → Traceability and Reports (AnnotechERP 09/01), as Brickland
 * keeps them. Traceability: the full chain — PR → GRN → warehouse → Material
 * Out → floor / line → receiver → return → current stock — from a PR, a GRN,
 * a Material Out or a roll (that roll's own chain). Reports: Stock Summary, GRN, Material Out, Material
 * Return, Stock Ledger, Traceability, Fabric Roll and Low Stock, by buyer,
 * style, material and dates, viewed here or taken as an Excel workbook.
 */
class TraceApiController extends ApiController
{
    public const REPORTS = ['stock-summary', 'grn', 'material-out', 'material-return', 'stock-ledger', 'traceability', 'fabric-roll', 'low-stock'];

    /** The columns of every report, in Brickland's order; `n` marks a figure. */
    private const COLUMNS = [
        'stock-summary' => ['material', 'unit', 'pr_qty:n', 'in_house:n', 'out:n', 'returned:n', 'current:n'],
        'grn' => ['grn_no', 'date', 'pr_no', 'merchandiser', 'buyer', 'po', 'style', 'material', 'supplier', 'warehouse', 'unit', 'pr_qty:n', 'received:n', 'pending:n', 'status'],
        'material-out' => ['mo_no', 'date', 'purpose', 'material', 'roll', 'buyer', 'style', 'floor', 'section', 'line', 'receiver', 'unit', 'out:n', 'returned:n', 'status'],
        'material-return' => ['mr_no', 'date', 'mo_no', 'material', 'reason', 'floor', 'section', 'line', 'returned_by', 'received_by', 'unit', 'returned:n', 'status'],
        'stock-ledger' => ['date', 'reference', 'type', 'buyer', 'style', 'material', 'unit', 'in:n', 'out:n', 'returned:n', 'adj:n', 'balance:n'],
        'traceability' => ['pr_no', 'grn_no', 'merchandiser', 'buyer', 'po', 'style', 'material', 'unit', 'in_house:n', 'out:n', 'returned:n', 'current:n'],
        'fabric-roll' => ['roll_no', 'grn_no', 'buyer', 'style', 'fabric', 'colour', 'dye_lot', 'gsm', 'width', 'unit', 'received:n', 'out:n', 'returned:n', 'available:n', 'status'],
        'low-stock' => ['material', 'type', 'unit', 'minimum:n', 'available:n', 'shortfall:n', 'status'],
    ];

    /** Traceability: from a PR, a GRN, a Material Out or a roll. */
    public function trace(Request $request): JsonResponse
    {
        $this->allow('storematerial.view');
        $data = $request->validate(['by' => ['required', Rule::in(['PR', 'GRN', 'MO', 'ROLL'])], 'q' => ['required', 'string', 'max:40']], ['q.required' => __('storematerial.tr.q_required')]);
        $q = strtoupper(trim($data['q']));
        $roll = $data['by'] === 'ROLL' ? (StoreBook::rollNamed($q) ?? throw ValidationException::withMessages(['q' => __('storematerial.tr.no_roll', ['roll' => $q])])) : null;
        [$pr, $stocks] = match ($data['by']) {
            'PR' => $this->fromPr($q),
            'GRN' => $this->fromGrn($q),
            'MO' => $this->fromOut($q),
            'ROLL' => $this->fromGrn((string) Grn::query()->whereKey($roll->grn_id)->value('grn_no'), $roll->stock_id),
        };
        $names = $pr ? Procurement::names([$pr->created_by]) : [];

        return ApiResponse::ok([
            'pr' => $pr ? [
                'pr_no' => $pr->pr_no, 'merchandiser' => $names[$pr->created_by] ?? null, 'date' => $pr->req_date->toDateString(),
                'supplier' => MaterialSupplier::query()->whereIn('id', PurchaseRequisitionLine::query()->where('requisition_id', $pr->id)->pluck('approved_supplier_id')->filter())->pluck('name')->implode(', ') ?: null,
            ] : null,
            // A roll's GSM and width as entered, else the fabric master's — as Roll Wise and the Fabric Roll report show them.
            'roll' => $roll ? ['roll_no' => $roll->roll_no, 'dye_lot' => $roll->dye_lot] + array_map(
                fn (string $k) => $roll->{$k} ?? MaterialItem::query()->whereKey($roll->item_id)->value($k), ['gsm' => 'gsm', 'width' => 'width'],
            ) : null,
            'chains' => $stocks->map(fn (Stock $s) => $this->chain($s, $pr, $roll))->values()->all(),
        ]);
    }

    /** One report, as rows. */
    public function report(Request $request, string $report): JsonResponse
    {
        $this->allow('storematerial.view');
        abort_unless(in_array($report, self::REPORTS, true), 404);

        return ApiResponse::ok(['report' => $report, 'columns' => $this->columnsOf($report), 'rows' => $this->rows($report, $this->filters($request))]);
    }

    /** Export PDF: the same rows on landscape A4, with the company, the filters and — on the return report — the total. */
    public function pdf(Request $request, string $report): Response
    {
        $this->allow('storematerial.view');
        abort_unless(in_array($report, self::REPORTS, true), 404);
        $f = $this->filters($request);
        $columns = $this->columnsOf($report);
        $rows = $this->rows($report, $f);
        $values = fn (array $r): array => array_map(fn (array $c) => $c['key'] === 'status' && $r['status'] ? __("storematerial.rp.status.{$r['status']}") : ($r[$c['key']] ?? null), $columns);
        $meta = array_filter([
            __('storematerial.f.buyer') => $f['buyer'], __('storematerial.f.style') => $f['style'],
            __('storematerial.f.material') => $f['material'] ? MaterialItem::query()->whereKey($f['material'])->value('name') : null,
            __('storematerial.f.from') => $f['from'], __('storematerial.f.to') => $f['to'],
        ], fn ($v) => $v !== null && $v !== '');
        $total = $report === 'material-return' && $rows !== []
            ? array_map(fn (array $c, int $i) => $i === 0 ? __('storematerial.rp.total') : ($c['numeric'] ? array_sum(array_column($rows, $c['key'])) : null), $columns, array_keys($columns))
            : null;
        $body = (new TablePdf(
            __("storematerial.rp.heading.{$report}"),
            array_map(fn (array $c): array => ['label' => __("storematerial.rp.col.{$c['key']}"), 'numeric' => $c['numeric']], $columns),
            array_map($values, $rows),
            $meta,
            $total,
            (string) Company::query()->whereKey(app(TenantContext::class)->companyId())->value('name'),
        ))->output();

        return response($body, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="store-'.$report.'-'.now()->format('Ymd-His').'.pdf"',
        ]);
    }

    /** Export Excel: the same rows as a workbook. */
    public function excel(Request $request, string $report): StreamedResponse
    {
        $this->allow('storematerial.view');
        abort_unless(in_array($report, self::REPORTS, true), 404);
        $columns = $this->columnsOf($report);
        $rows = $this->rows($report, $this->filters($request));
        $name = 'store-'.$report.'-'.now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($columns, $rows): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(array_map(fn (array $c) => __("storematerial.rp.col.{$c['key']}"), $columns)));
            foreach ($rows as $r) {
                $writer->addRow(Row::fromValues(array_map(fn (array $c) => $r[$c['key']] ?? null, $columns)));
            }
            $writer->close();
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @return list<array{key: string, numeric: bool}> */
    private function columnsOf(string $report): array
    {
        return array_map(fn (string $c) => ['key' => explode(':', $c)[0], 'numeric' => str_ends_with($c, ':n')], self::COLUMNS[$report]);
    }

    /** @return array{buyer: ?string, style: ?string, material: ?string, from: ?string, to: ?string} */
    private function filters(Request $request): array
    {
        $f = $request->validate(['buyer' => ['nullable', 'string'], 'style' => ['nullable', 'string'], 'material' => ['nullable', 'string'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return ['buyer' => $f['buyer'] ?? null, 'style' => $f['style'] ?? null, 'material' => $f['material'] ?? null, 'from' => $f['from'] ?? null, 'to' => $f['to'] ?? null];
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $report, array $f): array
    {
        $stocks = Stock::query()->when($f['buyer'], fn ($q, $v) => $q->where('buyer', $v))->when($f['style'], fn ($q, $v) => $q->where('style', $v))
            ->when($f['material'], fn ($q, $v) => $q->where('item_id', $v))->get()->keyBy('id');
        $items = MaterialItem::query()->whereIn('id', $stocks->pluck('item_id'))->get()->keyBy('id');
        $inRange = fn ($q, string $col) => $q->when($f['from'], fn ($w, $v) => $w->where($col, '>=', $v))->when($f['to'], fn ($w, $v) => $w->where($col, '<=', $v));
        $material = fn (string $stockId): string => StoreBook::material($items[$stocks[$stockId]->item_id]);

        return match ($report) {
            'stock-summary' => $this->summary($stocks, $items),
            'grn' => $this->grnRows($stocks, $material, $inRange),
            'material-out' => $this->outRows($stocks, $material, $inRange),
            'material-return' => $inRange(MaterialReturn::query()->whereIn('material_out_id', MaterialOut::query()->whereIn('stock_id', $stocks->keys())->select('id')), 'mr_date')->orderByDesc('mr_no')->get()
                ->map(function (MaterialReturn $r) use ($stocks, $material): array {
                    $o = MaterialOut::query()->findOrFail($r->material_out_id);

                    return [
                        'mr_no' => $r->mr_no, 'date' => $r->mr_date->toDateString(), 'mo_no' => $o->mo_no, 'material' => $material($o->stock_id), 'reason' => __('storematerial.return_reason.'.$r->reason),
                        'floor' => Floor::query()->whereKey($o->floor_id)->value('name'), 'section' => Section::query()->whereKey($o->section_id)->value('name'), 'line' => $r->line_name,
                        'returned_by' => StoreBook::person($r->returned_by ? HrEmployee::query()->find($r->returned_by) : null), 'received_by' => StoreBook::person($r->received_by ? HrEmployee::query()->find($r->received_by) : null),
                        'unit' => $stocks[$o->stock_id]->uom, 'returned' => (float) $r->qty, 'status' => 'CONFIRMED',
                    ];
                })->values()->all(),
            'stock-ledger' => $this->ledgerRows($stocks, $material, $f),
            'traceability' => $this->traceRows($stocks, $material),
            'fabric-roll' => $this->rollRows($stocks, $items, $f),
            'low-stock' => $this->lowStock($stocks, $items),
        };
    }

    /** @return list<array<string, mixed>> */
    private function outRows(Collection $stocks, \Closure $material, \Closure $inRange): array
    {
        $outs = $inRange(MaterialOut::query()->whereIn('stock_id', $stocks->keys()), 'mo_date')->orderByDesc('mo_no')->get();
        // The rolls each Material Out took, by number.
        $moves = RollMove::query()->where('type', 'MATERIAL_OUT')->whereIn('source_id', $outs->pluck('id'))->get();
        $rollNos = Roll::query()->whereIn('id', $moves->pluck('roll_id'))->pluck('roll_no', 'id');
        $rollsOf = $moves->groupBy('source_id')->map(fn ($set) => $set->map(fn (RollMove $m) => $rollNos[$m->roll_id] ?? null)->filter()->unique()->sort()->implode(', '));

        return $outs->map(function (MaterialOut $o) use ($stocks, $material, $rollsOf): array {
            $s = $stocks[$o->stock_id];

            return [
                'mo_no' => $o->mo_no, 'date' => $o->mo_date->toDateString(), 'purpose' => __('storematerial.purpose.'.$o->purpose), 'material' => $material($o->stock_id), 'roll' => ($rollsOf[$o->id] ?? '') ?: null,
                'buyer' => $s->buyer ?: null, 'style' => $s->style ?: null, 'floor' => Floor::query()->whereKey($o->floor_id)->value('name'),
                'section' => Section::query()->whereKey($o->section_id)->value('name'), 'line' => ProductionLine::query()->whereKey($o->line_id)->value('name'),
                'receiver' => StoreBook::person(HrEmployee::query()->find($o->receiver_id)), 'unit' => $s->uom, 'out' => (float) $o->qty,
                'returned' => round((float) MaterialReturn::query()->where('material_out_id', $o->id)->sum('qty'), 3), 'status' => 'CONFIRMED',
            ];
        })->values()->all();
    }

    /**
     * Fabric Roll: every roll in the filtered stock — its GRN, whom it is
     * held for, the fabric, dye lot, GSM and width — with what it received,
     * what went out and came back, and what it has left.
     *
     * @return list<array<string, mixed>>
     */
    private function rollRows(Collection $stocks, Collection $items, array $f): array
    {
        $grns = Grn::query()->when($f['from'], fn ($q, $v) => $q->where('grn_date', '>=', $v))->when($f['to'], fn ($q, $v) => $q->where('grn_date', '<=', $v))->get()->keyBy('id');
        $rolls = Roll::query()->whereIn('stock_id', $stocks->keys())->whereIn('grn_id', $grns->keys())->get()
            ->sortBy(fn (Roll $r) => [$grns[$r->grn_id]->grn_no, $r->sort_order])->values();
        $left = StoreBook::rollTotals($rolls->pluck('id'));

        return $rolls->filter(fn (Roll $r) => isset($items[$r->item_id]))->map(function (Roll $r) use ($grns, $stocks, $items, $left): array {
            $t = $left[$r->id] ?? ['recv' => 0.0, 'out' => 0.0, 'returned' => 0.0, 'available' => 0.0];
            $grn = $grns[$r->grn_id];
            $status = match (true) {
                $grn->status === 'REVERSED' => 'REVERSED',
                $t['available'] <= 0 => 'ALL_OUT',
                $t['out'] > 0 => 'PARTLY_OUT',
                default => 'IN_STOCK',
            };

            return [
                'roll_no' => $r->roll_no, 'grn_no' => $grn->grn_no, 'buyer' => $stocks[$r->stock_id]->buyer ?: null, 'style' => $stocks[$r->stock_id]->style ?: null,
                'fabric' => $items[$r->item_id]->name, 'colour' => $items[$r->item_id]->colour, 'dye_lot' => $r->dye_lot, 'gsm' => $r->gsm ?? $items[$r->item_id]->gsm,
                'width' => $r->width ?? $items[$r->item_id]->width, 'unit' => $r->uom, 'received' => (float) $r->qty, 'out' => $t['out'], 'returned' => $t['returned'],
                'available' => max(0.0, $t['available']), 'status' => $status,
            ];
        })->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function summary(Collection $stocks, Collection $items): array
    {
        $entries = LedgerEntry::query()->whereIn('stock_id', $stocks->keys())->get()->groupBy('stock_id');
        $prQty = PurchaseRequisitionLine::query()->whereIn('item_id', $items->keys())->whereIn('status', PurchaseRequisitionLine::DELIVERY)
            ->whereIn('requisition_id', PurchaseRequisition::query()->where('status', 'APPROVED')->select('id'))->get()->groupBy('item_id')->map(fn ($set) => (float) $set->sum('req_qty'));

        return $stocks->groupBy(fn (Stock $s) => $s->item_id.'|'.$s->uom)->map(function ($set) use ($entries, $items, $prQty): array {
            $all = $set->flatMap(fn (Stock $s) => $entries[$s->id] ?? collect());
            $in = (float) $all->where('type', 'GRN')->sum('in_qty') - (float) $all->where('type', 'REVERSAL')->sum('out_qty');
            $first = $set->first();

            return [
                'material' => StoreBook::material($items[$first->item_id]), 'unit' => $first->uom, 'pr_qty' => round($prQty[$first->item_id] ?? 0, 3), 'in_house' => round($in, 3),
                'out' => round((float) $all->where('type', 'MATERIAL_OUT')->sum('out_qty'), 3), 'returned' => round((float) $all->sum('return_qty'), 3),
                'current' => round((float) $all->sum(fn ($e) => (float) $e->in_qty + (float) $e->return_qty - (float) $e->out_qty), 3),
            ];
        })->sortBy('material')->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function grnRows(Collection $stocks, \Closure $material, \Closure $inRange): array
    {
        $lines = GrnLine::query()->whereIn('stock_id', $stocks->keys())->get();
        $grns = $inRange(Grn::query()->whereIn('id', $lines->pluck('grn_id')), 'grn_date')->orderByDesc('grn_no')->get()->keyBy('id');
        $prs = PurchaseRequisition::query()->whereIn('id', $grns->pluck('requisition_id')->filter())->get()->keyBy('id');
        $prLines = PurchaseRequisitionLine::query()->whereIn('id', $lines->pluck('requisition_line_id')->filter())->get()->keyBy('id');
        $names = Procurement::names($prs->pluck('created_by')->all());
        $received = GrnLine::query()->join('sm_grns', 'sm_grns.id', '=', 'sm_grn_lines.grn_id')->where('sm_grns.status', 'CONFIRMED')
            ->whereIn('requisition_line_id', $prLines->keys())->get(['sm_grn_lines.requisition_line_id', 'sm_grn_lines.qty'])->groupBy('requisition_line_id')->map(fn ($s) => (float) $s->sum('qty'));

        return $lines->filter(fn (GrnLine $l) => isset($grns[$l->grn_id]))->sortByDesc(fn (GrnLine $l) => $grns[$l->grn_id]->grn_no)->values()->map(function (GrnLine $l) use ($grns, $prs, $prLines, $names, $received, $material): array {
            $g = $grns[$l->grn_id];
            $pr = $g->requisition_id ? $prs[$g->requisition_id] ?? null : null;
            $prLine = $l->requisition_line_id ? $prLines[$l->requisition_line_id] ?? null : null;

            return [
                'grn_no' => $g->grn_no, 'date' => $g->grn_date->toDateString(), 'pr_no' => $pr?->pr_no, 'merchandiser' => $pr ? ($names[$pr->created_by] ?? null) : null,
                'buyer' => $g->buyer ?: null, 'po' => $g->po_number ?: null, 'style' => $g->style ?: null, 'material' => $material($l->stock_id),
                'supplier' => $g->supplier_name ?? MaterialSupplier::query()->whereKey($g->supplier_id)->value('name'), 'warehouse' => __('storematerial.warehouse.'.$l->kind),
                'unit' => $l->uom, 'pr_qty' => $prLine ? (float) $prLine->req_qty : null, 'received' => (float) $l->qty,
                'pending' => $prLine ? round(max(0, (float) $prLine->req_qty - ($received[$prLine->id] ?? 0)), 3) : null, 'status' => $g->status,
            ];
        })->all();
    }

    /** @return list<array<string, mixed>> */
    private function ledgerRows(Collection $stocks, \Closure $material, array $f): array
    {
        $running = [];

        return LedgerEntry::query()->whereIn('stock_id', $stocks->keys())->orderBy('entry_date')->orderBy('created_at')->get()
            ->map(function (LedgerEntry $e) use (&$running, $stocks, $material): array {
                $running[$e->stock_id] = round(($running[$e->stock_id] ?? 0) + (float) $e->in_qty + (float) $e->return_qty - (float) $e->out_qty, 3);
                $s = $stocks[$e->stock_id];
                $adj = $e->type === 'ADJUSTMENT' ? round((float) $e->in_qty - (float) $e->out_qty, 3) : 0.0;

                return [
                    'date' => $e->entry_date->toDateString(), 'reference' => $e->reference, 'type' => __('storematerial.type.'.$e->type), 'buyer' => $s->buyer ?: null, 'style' => $s->style ?: null,
                    'material' => $material($e->stock_id), 'unit' => $s->uom, 'in' => $e->type === 'ADJUSTMENT' ? 0.0 : (float) $e->in_qty, 'out' => $e->type === 'ADJUSTMENT' ? 0.0 : (float) $e->out_qty,
                    'returned' => (float) $e->return_qty, 'adj' => $adj, 'balance' => $running[$e->stock_id],
                ];
                // The balance runs over every entry; the dates only choose which are shown.
            })->filter(fn (array $r) => (! $f['from'] || $r['date'] >= $f['from']) && (! $f['to'] || $r['date'] <= $f['to']))->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function traceRows(Collection $stocks, \Closure $material): array
    {
        $lines = GrnLine::query()->whereIn('stock_id', $stocks->keys())->get();
        $grns = Grn::query()->whereIn('id', $lines->pluck('grn_id'))->where('status', 'CONFIRMED')->whereNotNull('requisition_id')->get()->keyBy('id');
        $prs = PurchaseRequisition::query()->whereIn('id', $grns->pluck('requisition_id'))->get()->keyBy('id');
        $names = Procurement::names($prs->pluck('created_by')->all());

        return $lines->filter(fn (GrnLine $l) => isset($grns[$l->grn_id]))->groupBy(fn (GrnLine $l) => $grns[$l->grn_id]->requisition_id.'|'.$l->stock_id)->map(function ($set) use ($grns, $prs, $names, $stocks, $material): array {
            $first = $set->first();
            $pr = $prs[$grns[$first->grn_id]->requisition_id];
            $s = $stocks[$first->stock_id];
            $chain = $this->chain($s, $pr);

            return [
                'pr_no' => $pr->pr_no, 'grn_no' => $set->map(fn ($l) => $grns[$l->grn_id]->grn_no)->unique()->sort()->implode(', '), 'merchandiser' => $names[$pr->created_by] ?? null,
                'buyer' => $s->buyer ?: null, 'po' => $s->po_number ?: null, 'style' => $s->style ?: null, 'material' => $material($s->id), 'unit' => $s->uom,
                'in_house' => $chain['in_house'], 'out' => $chain['out'], 'returned' => $chain['returned'], 'current' => $chain['available'],
            ];
        })->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function lowStock(Collection $stocks, Collection $items): array
    {
        $balances = StoreBook::balances($stocks->keys());

        return MinLevel::query()->whereIn('item_id', $items->keys())->get()->map(function (MinLevel $m) use ($stocks, $items, $balances): ?array {
            $available = round($stocks->where('item_id', $m->item_id)->sum(fn (Stock $s) => $balances[$s->id] ?? 0), 3);
            $short = round((float) $m->min_qty - $available, 3);

            return $short > 0 ? [
                'material' => $items[$m->item_id]->name, 'type' => __('storematerial.kind.'.$items[$m->item_id]->kind), 'unit' => $items[$m->item_id]->uom,
                'minimum' => (float) $m->min_qty, 'available' => $available, 'shortfall' => $short, 'status' => $available <= 0 ? 'OUT' : 'LOW',
            ] : null;
        })->filter()->values()->all();
    }

    /**
     * One material's chain: the GRNs it came in on, the Material Outs it went
     * out on, the returns and what is left — or, for a roll, that roll's own:
     * its GRN, the Material Outs that took from it and how much, what came
     * back on it and what it has left.
     *
     * @return array<string, mixed>
     */
    private function chain(Stock $stock, ?PurchaseRequisition $pr, ?Roll $roll = null): array
    {
        if ($roll) {
            return $this->rollChain($stock, $roll);
        }
        $item = MaterialItem::query()->findOrFail($stock->item_id);
        $grnLines = GrnLine::query()->where('stock_id', $stock->id)->get();
        $grns = Grn::query()->whereIn('id', $grnLines->pluck('grn_id'))->where('status', 'CONFIRMED')->when($pr, fn ($q) => $q->where('requisition_id', $pr->id))->orderBy('grn_no')->get();
        $outs = MaterialOut::query()->where('stock_id', $stock->id)->orderBy('mo_no')->get();
        $returns = MaterialReturn::query()->whereIn('material_out_id', $outs->pluck('id'))->orderBy('mr_no')->get();
        $in = round((float) $grnLines->whereIn('grn_id', $grns->pluck('id'))->sum('qty'), 3);

        return [
            'material' => StoreBook::material($item), 'warehouse' => __('storematerial.warehouse.'.$item->kind), 'unit' => $stock->uom,
            'buyer' => $stock->buyer ?: null, 'po' => $stock->po_number ?: null, 'style' => $stock->style ?: null, 'order_no' => $stock->order_no ?: null,
            'grns' => $grns->pluck('grn_no')->all(), 'in_house' => $in,
            'outs' => $outs->map(fn (MaterialOut $o) => [
                'mo_no' => $o->mo_no, 'qty' => (float) $o->qty, 'purpose' => $o->purpose, 'floor' => Floor::query()->whereKey($o->floor_id)->value('name'),
                'section' => Section::query()->whereKey($o->section_id)->value('name'), 'line' => ProductionLine::query()->whereKey($o->line_id)->value('name'),
                'receiver' => StoreBook::person(HrEmployee::query()->find($o->receiver_id)),
            ])->values()->all(),
            'out' => round((float) $outs->sum('qty'), 3),
            'returns' => $returns->map(fn (MaterialReturn $r) => ['mr_no' => $r->mr_no, 'qty' => (float) $r->qty, 'reason' => $r->reason])->values()->all(),
            'returned' => round((float) $returns->sum('qty'), 3),
            'available' => StoreBook::balance($stock->id),
        ];
    }

    /** @return array<string, mixed> */
    private function rollChain(Stock $stock, Roll $roll): array
    {
        $item = MaterialItem::query()->findOrFail($stock->item_id);
        $grn = Grn::query()->findOrFail($roll->grn_id);
        $moves = RollMove::query()->where('roll_id', $roll->id)->get();
        $outs = MaterialOut::query()->whereIn('id', $moves->where('type', 'MATERIAL_OUT')->pluck('source_id'))->orderBy('mo_no')->get();
        $took = $moves->where('type', 'MATERIAL_OUT')->groupBy('source_id')->map(fn ($set) => round((float) $set->sum('out_qty'), 3));
        $returns = MaterialReturn::query()->where('roll_id', $roll->id)->orderBy('mr_no')->get();
        $left = StoreBook::rollTotals(collect([$roll->id]))[$roll->id] ?? ['available' => 0.0];

        return [
            'material' => StoreBook::material($item), 'warehouse' => __('storematerial.warehouse.'.$item->kind), 'unit' => $roll->uom,
            'buyer' => $stock->buyer ?: null, 'po' => $stock->po_number ?: null, 'style' => $stock->style ?: null, 'order_no' => $stock->order_no ?: null,
            'grns' => $grn->status === 'CONFIRMED' ? [$grn->grn_no] : [], 'in_house' => $grn->status === 'CONFIRMED' ? (float) $roll->qty : 0.0,
            'outs' => $outs->map(fn (MaterialOut $o) => [
                'mo_no' => $o->mo_no, 'qty' => $took[$o->id] ?? 0.0, 'purpose' => $o->purpose, 'floor' => Floor::query()->whereKey($o->floor_id)->value('name'),
                'section' => Section::query()->whereKey($o->section_id)->value('name'), 'line' => ProductionLine::query()->whereKey($o->line_id)->value('name'),
                'receiver' => StoreBook::person(HrEmployee::query()->find($o->receiver_id)),
            ])->values()->all(),
            'out' => round((float) $took->sum(), 3),
            'returns' => $returns->map(fn (MaterialReturn $r) => ['mr_no' => $r->mr_no, 'qty' => (float) $r->qty, 'reason' => $r->reason])->values()->all(),
            'returned' => round((float) $returns->sum('qty'), 3),
            'available' => max(0.0, (float) $left['available']),
        ];
    }

    /** @return array{0: PurchaseRequisition, 1: Collection<int, Stock>} */
    private function fromPr(string $no): array
    {
        $pr = PurchaseRequisition::query()->where('pr_no', $no)->first() ?? throw ValidationException::withMessages(['q' => __('storematerial.grn.pr_missing', ['pr' => $no])]);
        $stockIds = GrnLine::query()->whereIn('grn_id', Grn::query()->where('requisition_id', $pr->id)->select('id'))->pluck('stock_id')->unique();
        if ($stockIds->isEmpty()) {
            throw ValidationException::withMessages(['q' => __('storematerial.tr.nothing_received', ['ref' => $no])]);
        }

        return [$pr, Stock::query()->whereIn('id', $stockIds)->get()];
    }

    /** @return array{0: ?PurchaseRequisition, 1: Collection<int, Stock>} */
    private function fromGrn(string $no, ?string $stockId = null): array
    {
        $grn = Grn::query()->where('grn_no', $no)->first() ?? throw ValidationException::withMessages(['q' => __('storematerial.tr.grn_missing', ['grn' => $no])]);
        $stocks = $stockId ? collect([$stockId]) : GrnLine::query()->where('grn_id', $grn->id)->pluck('stock_id');

        return [$grn->requisition_id ? PurchaseRequisition::query()->find($grn->requisition_id) : null, Stock::query()->whereIn('id', $stocks)->get()];
    }

    /** @return array{0: ?PurchaseRequisition, 1: Collection<int, Stock>} */
    private function fromOut(string $no): array
    {
        $out = MaterialOut::query()->where('mo_no', $no)->first() ?? throw ValidationException::withMessages(['q' => __('storematerial.mr.mo_missing', ['mo' => $no])]);
        $prId = Grn::query()->whereIn('id', GrnLine::query()->where('stock_id', $out->stock_id)->select('grn_id'))->whereNotNull('requisition_id')->orderBy('grn_no')->value('requisition_id');

        return [$prId ? PurchaseRequisition::query()->find($prId) : null, Stock::query()->whereKey($out->stock_id)->get()];
    }
}

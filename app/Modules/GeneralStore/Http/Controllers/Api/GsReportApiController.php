<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Http\Controllers\Api;

use App\Modules\GeneralStore\Models\GsCategory;
use App\Modules\GeneralStore\Models\GsDepartment;
use App\Modules\GeneralStore\Models\GsDocument;
use App\Modules\GeneralStore\Models\GsDocumentLine;
use App\Modules\GeneralStore\Models\GsItem;
use App\Modules\GeneralStore\Models\GsRequisition;
use App\Modules\GeneralStore\Models\GsStockMovement;
use App\Modules\GeneralStore\Models\GsStore;
use App\Modules\GeneralStore\Models\GsSupplier;
use App\Modules\GeneralStore\Models\GsUnit;
use App\Modules\GeneralStore\Support\GsBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Pdf\TablePdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * General Store → Reports (AnnotechERP 10/06), the thirteen Brickland keeps:
 * the item-wise Stock Report (opening, received, returned, transferred,
 * issued, adjusted and current), the Receive, Issue, Return, Adjustment and
 * Transfer line reports, Department-wise and Item-wise Consumption,
 * Category-wise Stock, Stock Valuation, Low Stock, the Monthly receive /
 * issue / consumption and the Transaction History with the store balance —
 * each with its filters, a total row, Excel and a PDF.
 *
 * A cancelled document is left out unless the Cancelled status is asked for;
 * movement figures net a cancellation against what it reversed. Values are
 * quantity × price where a price is known.
 */
class GsReportApiController extends ApiController
{
    /** report => columns (`:n` a figure that totals, `:f` a figure that does not). */
    public const REPORTS = [
        'stock' => ['item_code', 'item_name', 'category', 'unit', 'opening:n', 'received:n', 'returned:n', 'transfer_in:n', 'issued:n', 'transfer_out:n', 'adjusted:n', 'current:n', 'unit_cost:f', 'current_value:n', 'status'],
        'receive' => ['date', 'doc_no', 'store', 'supplier', 'item_code', 'item_name', 'category', 'unit', 'qty:n', 'price:f', 'value:n', 'challan_ref', 'user'],
        'issue' => ['date', 'doc_no', 'store', 'department', 'item_code', 'item_name', 'category', 'unit', 'qty:n', 'unit_cost:f', 'issue_value:n', 'receiver_req', 'issued_by', 'doc_status'],
        'return' => ['date', 'doc_no', 'store', 'department', 'item_code', 'item_name', 'category', 'unit', 'qty:n', 'price:f', 'value:n', 'against', 'user'],
        'adjustment' => ['date', 'doc_no', 'store', 'adjustment_type', 'item_code', 'item_name', 'category', 'unit', 'signed_qty:n', 'price:f', 'value:n', 'reference', 'user'],
        'transfer' => ['date', 'doc_no', 'from_store', 'to_store', 'item_code', 'item_name', 'category', 'unit', 'qty:n', 'price:f', 'value:n', 'reference', 'user'],
        'department' => ['department', 'issued:n', 'returned:n', 'net:n', 'value:n'],
        'consumption' => ['item_code', 'item_name', 'category', 'unit', 'issued:n', 'returned:n', 'net:n', 'value:n'],
        'category' => ['category', 'items:n', 'stock_qty:n', 'priced:n', 'value:n', 'low:n', 'out_of_stock:n'],
        'valuation' => ['item_code', 'item_name', 'category', 'unit', 'stock_qty:n', 'price:f', 'value:n'],
        'low-stock' => ['item_code', 'item_name', 'category', 'unit', 'current_stock:f', 'min:f', 'reorder:f', 'shortage:n', 'status'],
        'monthly' => ['month', 'receive:n', 'issue:n', 'return:n', 'adj_in:n', 'adj_out:n', 'consumption:n'],
        'history' => ['date', 'doc_no', 'type', 'store', 'item_code', 'item_name', 'unit', 'in:n', 'out:n', 'balance:f', 'user'],
    ];

    /** The document type behind each line report. */
    private const LINE_TYPES = ['receive' => 'RECEIVE', 'issue' => 'ISSUE', 'return' => 'RETURN', 'adjustment' => 'ADJUST', 'transfer' => 'TRANSFER'];

    /** What the report filters pick from. */
    public function options(): JsonResponse
    {
        $this->allow('store.inventory.view');

        return ApiResponse::ok([
            'stores' => GsStore::query()->where('active', true)->orderBy('name')->get()->map(fn ($s) => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->all(),
            'categories' => GsCategory::query()->where('active', true)->orderBy('name')->get()->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            'items' => GsItem::query()->where('active', true)->orderBy('name')->get()->map(fn ($i) => ['value' => $i->id, 'label' => "{$i->name} ({$i->item_code})", 'category_id' => $i->gs_category_id])->all(),
            'suppliers' => GsSupplier::query()->where('active', true)->orderBy('name')->get()->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all(),
            'departments' => GsDepartment::query()->where('active', true)->orderBy('name')->get()->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->all(),
        ]);
    }

    public function show(Request $request, string $report): JsonResponse
    {
        $this->allow('store.inventory.view');
        abort_unless(isset(self::REPORTS[$report]), 404);
        $f = $this->filters($request, $report);
        $rows = $this->rows($report, $f);

        return ApiResponse::ok(['report' => $report, 'columns' => $this->columns($report), 'rows' => $rows, 'total' => $this->total($report, $rows), 'subtitle' => $this->subtitle($report, $f)]);
    }

    public function excel(Request $request, string $report): StreamedResponse
    {
        $this->allow('store.inventory.view');
        abort_unless(isset(self::REPORTS[$report]), 404);
        $f = $this->filters($request, $report);
        $rows = $this->rows($report, $f);
        $columns = $this->columns($report);
        $total = $this->total($report, $rows);
        GsBook::audit('REPORT_EXPORT', "gs-rpt-{$report}", null, __("store.rpt.titles.{$report}", [], 'en').' exported to Excel ('.count($rows).' rows)');

        return response()->streamDownload(function () use ($rows, $columns, $total): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(array_column($columns, 'label')));
            foreach ([...$rows, ...($total ? [$total] : [])] as $r) {
                $writer->addRow(Row::fromValues(array_map(fn ($c) => $this->cell($r, $c['key']), $columns)));
            }
            $writer->close();
        }, "store-report-{$report}-".now()->format('Ymd-His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** Print / PDF: the same rows on landscape A4, the voucher name above, opened in the browser to print or save. */
    public function pdf(Request $request, string $report): Response
    {
        $this->allow('store.inventory.view');
        abort_unless(isset(self::REPORTS[$report]), 404);
        $f = $this->filters($request, $report);
        $rows = $this->rows($report, $f);
        $columns = $this->columns($report);
        $total = $this->total($report, $rows);
        GsBook::audit('REPORT_EXPORT', "gs-rpt-{$report}", null, __("store.rpt.titles.{$report}", [], 'en').' printed ('.count($rows).' rows)');
        $body = (new TablePdf(
            __("store.rpt.titles.{$report}"),
            array_map(fn ($c) => ['label' => $c['label'], 'numeric' => $c['numeric']], $columns),
            array_map(fn ($r) => array_map(fn ($c) => $this->cell($r, $c['key']), $columns), $rows),
            [],
            $total ? array_map(fn ($c) => $c['key'] === $columns[0]['key'] ? __('store.rpt.total') : ($total[$c['key']] ?? null), $columns) : null,
            GsBook::voucher()['name'].' · '.$this->subtitle($report, $f),
        ))->output();

        return response($body, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="store-report-'.$report.'.pdf"']);
    }

    /** @return list<array{key: string, label: string, numeric: bool, totals: bool}> */
    private function columns(string $report): array
    {
        return array_map(function (string $c) use ($report): array {
            $key = explode(':', $c)[0];

            return ['key' => $key, 'label' => $this->label($report, $key), 'numeric' => str_contains($c, ':'), 'totals' => str_ends_with($c, ':n')];
        }, self::REPORTS[$report]);
    }

    /** A column's heading as that report words it ("Issued Qty" here, "Issued" there). */
    private function label(string $report, string $key): string
    {
        $own = "store.rpt.col_{$report}.{$key}";

        return __($own) !== $own ? __($own) : __("store.rpt.col.{$key}");
    }

    /** A cell as the export writes it: statuses and types in words. */
    private function cell(array $r, string $key): mixed
    {
        $v = $r[$key] ?? null;

        return match ($key) {
            'status' => $v ? __("store.inv.stock_status.{$v}") : null,
            'doc_status' => $v ? __("store.inv.status.{$v}") : null,
            'adjustment_type' => $v ? __("store.inv.adjust.{$v}") : null,
            'type' => $v ? (($r['reversal'] ?? false) ? __('store.inv.ledger.reversal', ['type' => __("store.inv.badge.{$v}")]) : __("store.inv.badge.{$v}")) : null,
            default => $v,
        };
    }

    /** @return array<string, mixed> */
    private function filters(Request $request, string $report): array
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:120'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'store' => ['nullable', 'string'],
            'category' => ['nullable', 'string'], 'item' => ['nullable', 'string'], 'supplier' => ['nullable', 'string'], 'department' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:20'], 'type' => ['nullable', Rule::in(GsDocument::TYPES)],
        ]);
        $f = array_merge(array_fill_keys(['q', 'from', 'to', 'store', 'category', 'item', 'supplier', 'department', 'status', 'type'], null), $f);
        // Brickland's Monthly report starts a year back unless a date is given.
        if ($report === 'monthly' && ! $f['from']) {
            $f['from'] = now()->subYear()->startOfMonth()->toDateString();
        }

        return $f;
    }

    private function subtitle(string $report, array $f): string
    {
        $range = match (true) {
            (bool) ($f['from'] && $f['to']) => "{$f['from']} – {$f['to']}",
            (bool) $f['from'] => __('store.rpt.onward', ['date' => $f['from']]),
            (bool) $f['to'] => __('store.rpt.until', ['date' => $f['to']]),
            default => __('store.rpt.all_dates'),
        };

        return match ($report) {
            'stock' => __('store.rpt.notes.stock', ['date' => now()->toDateString()]),
            'valuation' => __('store.rpt.notes.valuation', ['date' => now()->toDateString()]),
            'category', 'low-stock' => __('store.rpt.as_of', ['date' => now()->toDateString()]),
            'department', 'consumption' => $range.' — '.__('store.rpt.notes.units'),
            default => $range,
        };
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $report, array $f): array
    {
        return match ($report) {
            'stock' => $this->stockRows($f),
            'receive', 'issue', 'return', 'adjustment', 'transfer' => $this->lineRows($report, $f),
            'department' => $this->consumptionRows($f, 'department'),
            'consumption' => $this->consumptionRows($f, 'item'),
            'category' => $this->categoryRows($f),
            'valuation' => collect($this->itemRows($f))->filter(fn ($r) => abs($r['qty']) > 0.00005)->map(fn ($r) => [
                'item_code' => $r['item_code'], 'item_name' => $r['item_name'], 'category' => $r['category'], 'unit' => $r['unit'], 'stock_qty' => $r['qty'], 'price' => $r['price'], 'value' => $r['value'],
            ])->values()->all(),
            'low-stock' => collect($this->itemRows($f))->filter(fn ($r) => in_array($r['status'], ['LOW', 'OUT'], true))->map(fn ($r) => [
                'item_code' => $r['item_code'], 'item_name' => $r['item_name'], 'category' => $r['category'], 'unit' => $r['unit'], 'current_stock' => $r['qty'], 'min' => $r['min'], 'reorder' => $r['reorder'],
                'shortage' => round(max(0, ($r['min'] ?? 0) - $r['qty']), 4), 'status' => $r['status'],
            ])->values()->all(),
            'monthly' => $this->monthlyRows($f),
            'history' => $this->historyRows($f),
        };
    }

    /** @return array<string, mixed>|null the total row: summed figures, the first column saying Total */
    private function total(string $report, array $rows): ?array
    {
        if ($rows === [] || $report === 'history') {
            return null;
        }
        $total = [];
        foreach ($this->columns($report) as $c) {
            if ($c['totals']) {
                $total[$c['key']] = round((float) array_sum(array_map(fn ($r) => (float) ($r[$c['key']] ?? 0), $rows)), 4);
            }
        }

        return $total;
    }

    /**
     * Every active item in the filters with its stock, price, value and status.
     *
     * @return list<array<string, mixed>>
     */
    private function itemRows(array $f): array
    {
        $items = $this->items($f);
        $stock = GsBook::stock($items->pluck('id'));
        $prices = GsBook::lastPrices($items);
        [$cats, $units] = [GsCategory::query()->pluck('name', 'id'), GsUnit::query()->pluck('code', 'id')];

        return $items->map(function (GsItem $i) use ($stock, $prices, $cats, $units, $f): array {
            $held = $stock[$i->id] ?? [];
            $qty = round($f['store'] ? ($held[$f['store']] ?? 0.0) : array_sum($held), 4);
            $min = (float) $i->minimum_stock;
            $price = $prices[$i->id] ?? null;

            return [
                'id' => $i->id, 'item_code' => $i->item_code, 'item_name' => $i->name, 'category' => $cats[$i->gs_category_id] ?? null, 'category_id' => $i->gs_category_id,
                'unit' => $units[$i->gs_unit_id] ?? null, 'qty' => $qty, 'price' => $price, 'value' => $price === null ? null : round(max(0, $qty) * $price, 2),
                'min' => $min > 0 ? $min : null, 'reorder' => (float) $i->reorder_level > 0 ? (float) $i->reorder_level : null,
                'status' => $qty <= 0 ? 'OUT' : ($min > 0 && $qty <= $min ? 'LOW' : 'IN_STOCK'),
            ];
        })->values()->all();
    }

    /** @return Collection<int, GsItem> */
    private function items(array $f): Collection
    {
        return GsItem::query()->where('active', true)->when($f['category'], fn ($q, $v) => $q->where('gs_category_id', $v))->when($f['item'], fn ($q, $v) => $q->whereKey($v))
            ->when($f['q'], function ($q, $v) {
                $like = '%'.mb_strtolower(trim($v)).'%';
                $q->where(fn ($w) => $w->whereRaw('lower(name) like ?', [$like])->orWhereRaw('lower(item_code) like ?', [$like]));
            })->orderBy('item_code')->get();
    }

    /** @return list<array<string, mixed>> (out-going figures as 0.0 − x, never −x, so nothing reads -0) */
    private function stockRows(array $f): array
    {
        $rows = $this->itemRows($f);
        if ($f['status']) {
            $rows = array_values(array_filter($rows, fn ($r) => $r['status'] === $f['status']));
        }
        $moves = GsStockMovement::query()->whereIn('gs_item_id', array_column($rows, 'id'))->when($f['store'], fn ($q, $v) => $q->where('gs_store_id', $v))->get()->groupBy('gs_item_id');

        return array_map(function (array $r) use ($moves): array {
            $set = $moves[$r['id']] ?? collect();
            // A transfer's leg into this store is positive, its leg out negative; a cancellation's reversal belongs to the leg it undoes.
            $transfer = $set->where('movement_type', 'TRANSFER');

            return [
                'item_code' => $r['item_code'], 'item_name' => $r['item_name'], 'category' => $r['category'], 'unit' => $r['unit'],
                'opening' => round((float) $set->where('movement_type', 'OPENING')->sum('quantity'), 4), 'received' => round((float) $set->where('movement_type', 'RECEIVE')->sum('quantity'), 4),
                'returned' => round((float) $set->where('movement_type', 'RETURN')->sum('quantity'), 4),
                'transfer_in' => round((float) $transfer->filter(fn ($m) => (float) $m->quantity > 0 xor $m->is_reversal)->sum('quantity'), 4),
                'issued' => round(0.0 - (float) $set->where('movement_type', 'ISSUE')->sum('quantity'), 4),
                'transfer_out' => round(0.0 - (float) $transfer->filter(fn ($m) => (float) $m->quantity < 0 xor $m->is_reversal)->sum('quantity'), 4),
                'adjusted' => round((float) $set->where('movement_type', 'ADJUST')->sum('quantity'), 4),
                'current' => $r['qty'], 'unit_cost' => $r['price'], 'current_value' => $r['value'], 'status' => $r['status'],
            ];
        }, $rows);
    }

    /** @return list<array<string, mixed>> */
    private function lineRows(string $report, array $f): array
    {
        $type = self::LINE_TYPES[$report];
        $items = $this->items(['q' => null] + $f)->keyBy('id');
        $docs = GsDocument::query()->where('doc_type', $type)
            ->where('status', $f['status'] === 'CANCELLED' ? 'CANCELLED' : 'POSTED')
            ->when($f['from'], fn ($q, $v) => $q->where('doc_date', '>=', $v))->when($f['to'], fn ($q, $v) => $q->where('doc_date', '<=', $v))
            ->when($f['store'], fn ($q, $v) => $q->where(fn ($w) => $w->where('gs_store_id', $v)->orWhere('to_store_id', $v)))
            ->when($f['supplier'], fn ($q, $v) => $q->where('gs_supplier_id', $v))->when($f['department'], fn ($q, $v) => $q->where('gs_department_id', $v))
            ->orderBy('doc_date')->orderBy('doc_no')->get()->keyBy('id');
        $q = $f['q'] ? mb_strtolower(trim($f['q'])) : null;
        $lines = GsDocumentLine::query()->whereIn('gs_document_id', $docs->keys())->whereIn('gs_item_id', $items->keys())->orderBy('sort_order')->get()
            ->filter(fn ($l) => ! $q || str_contains(mb_strtolower($docs[$l->gs_document_id]->doc_no), $q) || str_contains(mb_strtolower($items[$l->gs_item_id]->name), $q) || str_contains(mb_strtolower($items[$l->gs_item_id]->item_code), $q))
            ->sortBy(fn ($l) => [$docs[$l->gs_document_id]->doc_date->toDateString(), $docs[$l->gs_document_id]->doc_no, $l->sort_order]);
        [$cats, $units, $stores] = [GsCategory::query()->pluck('name', 'id'), GsUnit::query()->pluck('code', 'id'), GsStore::query()->pluck('name', 'id')];
        $suppliers = GsSupplier::query()->pluck('name', 'id');
        $depts = GsDepartment::query()->pluck('name', 'id');
        $names = DB::table('users')->whereIn('id', $docs->pluck('created_by')->filter()->unique())->pluck('name', 'id');
        $againsts = GsDocument::query()->whereIn('id', $docs->pluck('against_id')->filter())->pluck('doc_no', 'id');
        $reqNos = GsRequisition::query()->whereIn('id', $docs->pluck('requisition_id')->filter())->pluck('req_no', 'id');

        return $lines->map(function (GsDocumentLine $l) use ($docs, $items, $cats, $units, $stores, $suppliers, $depts, $names, $againsts, $reqNos): array {
            $d = $docs[$l->gs_document_id];
            $i = $items[$l->gs_item_id];
            $qty = abs((float) $l->movement_qty);
            $price = $l->unit_price === null ? null : (float) $l->unit_price;
            $value = $price === null ? null : round($qty * $price, 2);

            return [
                'date' => $d->doc_date->toDateString(), 'doc_no' => $d->doc_no, 'store' => $stores[$d->gs_store_id] ?? null, 'from_store' => $stores[$d->gs_store_id] ?? null,
                'to_store' => $d->to_store_id ? ($stores[$d->to_store_id] ?? null) : null, 'supplier' => $d->gs_supplier_id ? ($suppliers[$d->gs_supplier_id] ?? null) : null,
                'department' => $d->gs_department_id ? ($depts[$d->gs_department_id] ?? null) : null, 'adjustment_type' => $d->adjustment_type,
                'item_code' => $i->item_code, 'item_name' => $i->name, 'category' => $cats[$i->gs_category_id] ?? null, 'unit' => $units[$i->gs_unit_id] ?? null,
                'qty' => $qty, 'signed_qty' => (float) $l->movement_qty, 'price' => $price, 'unit_cost' => $price, 'value' => $value, 'issue_value' => $value,
                'challan_ref' => $d->challan_no ?: $d->reference_no, 'reference' => $d->reference_no,
                'receiver_req' => collect([$d->person, $d->requisition_id ? ($reqNos[$d->requisition_id] ?? null) : null])->filter()->implode(' / ') ?: null,
                'against' => $d->against_id ? ($againsts[$d->against_id] ?? null) : null, 'user' => $names[$d->created_by] ?? null, 'issued_by' => $names[$d->created_by] ?? null,
                'doc_status' => $d->status,
            ];
        })->values()->all();
    }

    /**
     * Issued, returned and net — per department or per item — over posted issues and returns.
     *
     * @return list<array<string, mixed>>
     */
    private function consumptionRows(array $f, string $by): array
    {
        $items = $this->items($f)->keyBy('id');
        $docs = GsDocument::query()->whereIn('doc_type', ['ISSUE', 'RETURN'])->where('status', 'POSTED')
            ->when($f['from'], fn ($q, $v) => $q->where('doc_date', '>=', $v))->when($f['to'], fn ($q, $v) => $q->where('doc_date', '<=', $v))
            ->when($f['store'], fn ($q, $v) => $q->where('gs_store_id', $v))->when($f['department'], fn ($q, $v) => $q->where('gs_department_id', $v))->get()->keyBy('id');
        $lines = GsDocumentLine::query()->whereIn('gs_document_id', $docs->keys())->whereIn('gs_item_id', $items->keys())->get();
        $key = fn (GsDocumentLine $l) => $by === 'department' ? (string) $docs[$l->gs_document_id]->gs_department_id : $l->gs_item_id;
        $depts = GsDepartment::query()->pluck('name', 'id');
        [$cats, $units] = [GsCategory::query()->pluck('name', 'id'), GsUnit::query()->pluck('code', 'id')];

        return $lines->groupBy($key)->map(function ($set, $k) use ($docs, $by, $depts, $items, $cats, $units): array {
            $issued = $set->filter(fn ($l) => $docs[$l->gs_document_id]->doc_type === 'ISSUE');
            $returned = $set->filter(fn ($l) => $docs[$l->gs_document_id]->doc_type === 'RETURN');
            $qty = fn ($s) => round((float) $s->sum(fn ($l) => abs((float) $l->movement_qty)), 4);
            $val = fn ($s) => (float) $s->sum(fn ($l) => $l->unit_price === null ? 0 : abs((float) $l->movement_qty) * (float) $l->unit_price);
            $row = ['issued' => $qty($issued), 'returned' => $qty($returned), 'net' => round($qty($issued) - $qty($returned), 4), 'value' => round($val($issued) - $val($returned), 2)];
            if ($by === 'department') {
                return ['department' => $k !== '' ? ($depts[$k] ?? null) : null] + $row;
            }
            $i = $items[$k];

            return ['item_code' => $i->item_code, 'item_name' => $i->name, 'category' => $cats[$i->gs_category_id] ?? null, 'unit' => $units[$i->gs_unit_id] ?? null] + $row;
        })->sortByDesc('value')->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function categoryRows(array $f): array
    {
        return collect($this->itemRows(['q' => null, 'category' => null, 'item' => null] + $f))->groupBy('category')->map(fn ($set, $cat) => [
            'category' => $cat, 'items' => $set->count(), 'stock_qty' => round((float) $set->sum(fn ($r) => max(0, $r['qty'])), 4), 'priced' => $set->whereNotNull('price')->count(),
            'value' => round((float) $set->sum(fn ($r) => $r['value'] ?? 0), 2), 'low' => $set->where('status', 'LOW')->count(), 'out_of_stock' => $set->where('status', 'OUT')->count(),
        ])->sortByDesc('stock_qty')->values()->all();
    }

    /** Month by month: receive (with opening), issue, return, adjustment in and out, and consumption (issue − return). @return list<array<string, mixed>> */
    private function monthlyRows(array $f): array
    {
        $items = $this->items($f);

        return GsStockMovement::query()->whereIn('gs_item_id', $items->pluck('id'))->where('movement_type', '!=', 'TRANSFER')
            ->when($f['store'], fn ($q, $v) => $q->where('gs_store_id', $v))->when($f['from'], fn ($q, $v) => $q->where('moved_on', '>=', $v))->when($f['to'], fn ($q, $v) => $q->where('moved_on', '<=', $v))
            ->get()->groupBy(fn ($m) => $m->moved_on->format('Y-m'))->sortKeys()->map(function ($set, $month): array {
                $net = fn (array $types) => (float) $set->whereIn('movement_type', $types)->sum('quantity');
                $adj = $set->where('movement_type', 'ADJUST');
                $issue = round(0.0 - $net(['ISSUE']), 4);
                $return = round($net(['RETURN']), 4);

                return [
                    'month' => $month, 'receive' => round($net(['RECEIVE', 'OPENING']), 4), 'issue' => $issue, 'return' => $return,
                    'adj_in' => round((float) $adj->filter(fn ($m) => (float) $m->quantity > 0)->sum('quantity'), 4),
                    'adj_out' => round(abs((float) $adj->filter(fn ($m) => (float) $m->quantity < 0)->sum('quantity')), 4), 'consumption' => round($issue - $return, 4),
                ];
            })->values()->all();
    }

    /** Every movement, newest first, with the store's balance of that item after it. @return list<array<string, mixed>> */
    private function historyRows(array $f): array
    {
        $items = $this->items($f)->keyBy('id');
        $all = GsStockMovement::query()->whereIn('gs_item_id', $items->keys())->when($f['store'], fn ($q, $v) => $q->where('gs_store_id', $v))
            ->orderBy('moved_on')->orderBy('created_at')->orderBy('id')->get();
        $docs = GsDocument::query()->whereIn('id', $all->pluck('gs_document_id')->filter()->unique())->pluck('doc_no', 'id');
        $stores = GsStore::query()->pluck('name', 'id');
        $units = GsUnit::query()->pluck('code', 'id');
        $names = DB::table('users')->whereIn('id', $all->pluck('created_by')->filter()->unique())->pluck('name', 'id');
        $balance = [];
        $rows = [];
        foreach ($all as $m) {
            $k = $m->gs_item_id.'|'.$m->gs_store_id;
            $balance[$k] = round(($balance[$k] ?? 0) + (float) $m->quantity, 4);
            $date = $m->moved_on->toDateString();
            if (($f['from'] && $date < $f['from']) || ($f['to'] && $date > $f['to']) || ($f['type'] && $m->movement_type !== $f['type'])) {
                continue;
            }
            $i = $items[$m->gs_item_id];
            $q = (float) $m->quantity;
            $rows[] = [
                'date' => $date, 'doc_no' => $m->gs_document_id ? ($docs[$m->gs_document_id] ?? $m->reference) : $m->reference, 'type' => $m->movement_type, 'reversal' => $m->is_reversal,
                'store' => $stores[$m->gs_store_id] ?? null, 'item_code' => $i->item_code, 'item_name' => $i->name, 'unit' => $units[$i->gs_unit_id] ?? null,
                'in' => $q > 0 ? $q : null, 'out' => $q < 0 ? -$q : null, 'balance' => $balance[$k], 'user' => $names[$m->created_by] ?? null,
            ];
        }

        return array_reverse($rows);
    }
}

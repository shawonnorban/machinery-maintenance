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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * General Store's views of its stock (AnnotechERP 10/02–10/03), as Brickland
 * draws them: Current Stock — every item's quantity, price, value, minimum,
 * stock by store, rack and status, with its six tiles; the Stock Ledger — one
 * item's every movement with the running balance; and the Dashboard — the
 * tiles, today's documents, the last thirty days' movement, stock by
 * category, low and out of stock, recent transactions and the largest stocks.
 *
 * Status: Out of Stock at zero or below, Low at or below the item's minimum,
 * otherwise In Stock. Value is quantity × the last purchase price, where a
 * price is known.
 */
class GsStockApiController extends ApiController
{
    /** Current Stock: filters, tiles and rows. */
    public function current(Request $request): JsonResponse
    {
        $this->allow('store.inventory.view');
        $rows = $this->stockRows($this->stockFilters($request));

        return ApiResponse::ok(['tiles' => $this->tiles($rows), 'rows' => $rows]);
    }

    /** Current Stock as an Excel workbook. */
    public function currentExcel(Request $request): StreamedResponse
    {
        $this->allow('store.inventory.view');
        $rows = $this->stockRows($this->stockFilters($request));
        $columns = ['item_code', 'item', 'category', 'unit', 'qty', 'price', 'value', 'min', 'by_store', 'rack', 'status'];
        GsBook::audit('REPORT_EXPORT', 'gs-current-stock', null, 'Current Stock exported ('.count($rows).' items)');

        return response()->streamDownload(function () use ($rows, $columns): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(array_map(fn ($c) => __("store.inv.stock.col.{$c}"), $columns)));
            foreach ($rows as $r) {
                $writer->addRow(Row::fromValues(array_map(fn ($c) => match ($c) {
                    'status' => __("store.inv.stock_status.{$r['status']}"),
                    'by_store' => implode(', ', array_map(fn ($s) => "{$s['store']}: ".GsBook::qty($s['qty']), $r['by_store'])),
                    default => $r[$c],
                }, $columns)));
            }
            $writer->close();
        }, 'store-current-stock-'.now()->format('Ymd-His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** Stock Ledger: one item's movements, in order, with the balance after each. */
    public function ledger(Request $request): JsonResponse
    {
        $this->allow('store.inventory.view');
        $f = $request->validate(['item' => ['nullable', 'string'], 'store' => ['nullable', 'string'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        // An item from its code (?item=ITM-001, from Current Stock) or its key (the picker).
        $item = empty($f['item']) ? null : GsItem::query()->where(fn ($q) => $q->where('item_code', $f['item'])->orWhere('id', $f['item']))->first();
        if (! $item) {
            return ApiResponse::ok(['item' => null, 'rows' => []]);
        }
        $store = $f['store'] ?? null;
        $base = GsStockMovement::query()->where('gs_item_id', $item->id)->when($store, fn ($q, $v) => $q->where('gs_store_id', $v));
        $opening = empty($f['from']) ? 0.0 : round((float) (clone $base)->where('moved_on', '<', $f['from'])->sum('quantity'), 4);
        $moves = (clone $base)->when($f['from'] ?? null, fn ($q, $v) => $q->where('moved_on', '>=', $v))->when($f['to'] ?? null, fn ($q, $v) => $q->where('moved_on', '<=', $v))
            ->orderBy('moved_on')->orderBy('created_at')->orderBy('id')->get();
        $docs = GsDocument::query()->whereIn('id', $moves->pluck('gs_document_id')->filter()->unique())->get()->keyBy('id');
        $stores = GsStore::query()->pluck('name', 'id');
        $suppliers = GsSupplier::query()->whereIn('id', $docs->pluck('gs_supplier_id')->filter())->pluck('name', 'id');
        $depts = GsDepartment::query()->whereIn('id', $docs->pluck('gs_department_id')->filter())->pluck('name', 'id');
        $names = DB::table('users')->whereIn('id', $moves->pluck('created_by')->filter()->unique())->pluck('name', 'id');
        $column = ['OPENING' => 'receive', 'RECEIVE' => 'receive', 'ISSUE' => 'issue', 'RETURN' => 'return', 'ADJUST' => 'adjustment', 'TRANSFER' => 'transfer'];
        $balance = $opening;
        $rows = $moves->map(function (GsStockMovement $m) use (&$balance, $docs, $stores, $suppliers, $depts, $names, $column): array {
            $balance = round($balance + (float) $m->quantity, 4);
            $doc = $m->gs_document_id ? ($docs[$m->gs_document_id] ?? null) : null;
            // The Issue column counts what went out, so it reads 45 for an issue and -45 for its reversal; every other column shows the sign as posted.
            $q = (float) $m->quantity;
            $shown = $m->movement_type === 'ISSUE' ? -$q : $q;
            $party = match (true) {
                ! $doc => null,
                $doc->doc_type === 'RECEIVE' => $doc->gs_supplier_id ? ($suppliers[$doc->gs_supplier_id] ?? null) : null,
                in_array($doc->doc_type, ['ISSUE', 'RETURN'], true) => $doc->gs_department_id ? ($depts[$doc->gs_department_id] ?? null) : null,
                $doc->doc_type === 'TRANSFER' => $stores[$m->gs_store_id === $doc->gs_store_id ? $doc->to_store_id : $doc->gs_store_id] ?? null,
                default => null,
            };

            return [
                'date' => $m->moved_on->toDateString(), 'type' => $m->movement_type, 'reversal' => $m->is_reversal, 'doc_no' => $doc?->doc_no ?? $m->reference,
                'store' => $stores[$m->gs_store_id] ?? null, 'party' => $party, 'column' => $column[$m->movement_type] ?? 'adjustment', 'qty' => $shown,
                'balance' => $balance, 'user' => $names[$m->created_by] ?? null,
            ];
        })->values()->all();
        $in = round((float) $moves->filter(fn ($m) => (float) $m->quantity > 0)->sum('quantity'), 4);
        $out = round(abs((float) $moves->filter(fn ($m) => (float) $m->quantity < 0)->sum('quantity')), 4);
        $price = GsBook::lastPrices(collect([$item]))[$item->id] ?? null;

        return ApiResponse::ok([
            'item' => [
                'code' => $item->item_code, 'name' => $item->name, 'category' => GsCategory::query()->whereKey($item->gs_category_id)->value('name'),
                'unit' => GsUnit::query()->whereKey($item->gs_unit_id)->value('code'), 'price' => $price, 'store' => $store ? ($stores[$store] ?? null) : null,
            ],
            'opening' => $opening, 'total_in' => $in, 'total_out' => $out, 'closing' => $balance, 'rows' => $rows,
        ]);
    }

    /** The ledger as an Excel workbook: the opening balance, then every movement with its balance. */
    public function ledgerExcel(Request $request): StreamedResponse
    {
        $this->allow('store.inventory.view');
        $data = $this->ledger($request)->getData(true)['data'];
        abort_unless($data['item'] !== null, 404);
        $columns = ['date', 'type', 'doc_no', 'store', 'party', 'receive', 'issue', 'return', 'adjustment', 'transfer', 'balance', 'user'];
        GsBook::audit('REPORT_EXPORT', 'gs-ledger', $data['item']['code'], "Stock Ledger {$data['item']['code']} exported");

        return response()->streamDownload(function () use ($data, $columns): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(array_map(fn ($c) => __("store.inv.ledger.col.{$c}"), $columns)));
            $writer->addRow(Row::fromValues(['', __('store.inv.ledger.opening_row'), '', '', '', '', '', '', '', '', $data['opening'], '']));
            foreach ($data['rows'] as $r) {
                $type = __('store.inv.badge.'.$r['type']);
                $writer->addRow(Row::fromValues(array_map(fn ($c) => match ($c) {
                    'type' => $r['reversal'] ? __('store.inv.ledger.reversal', ['type' => $type]) : $type,
                    'receive', 'issue', 'return', 'adjustment', 'transfer' => $r['column'] === $c ? $r['qty'] : null,
                    default => $r[$c],
                }, $columns)));
            }
            $writer->close();
        }, 'store-ledger-'.$data['item']['code'].'-'.now()->format('Ymd-His').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** Dashboard. */
    public function dashboard(): JsonResponse
    {
        $this->allow('store.inventory.view');
        $rows = $this->stockRows(['q' => null, 'store' => null, 'category' => null, 'status' => null]);
        $tiles = $this->tiles($rows);
        $posted = fn (array $types) => GsDocumentLine::query()->whereIn('gs_document_id', GsDocument::query()->whereIn('doc_type', $types)->where('status', 'POSTED')->select('id'));
        $value = fn (Collection $lines) => round((float) $lines->filter(fn ($l) => $l->unit_price !== null)->sum(fn ($l) => abs((float) $l->movement_qty) * (float) $l->unit_price), 2);
        $received = $value($posted(['RECEIVE', 'OPENING'])->get());
        $issued = $value($posted(['ISSUE'])->get());
        $today = now()->toDateString();
        $todays = GsDocument::query()->where('doc_date', $today)->where('status', 'POSTED')->get()->countBy('doc_type');
        $since = now()->subDays(29)->toDateString();
        $daily = GsStockMovement::query()->where('moved_on', '>=', $since)->where('movement_type', '!=', 'TRANSFER')->get()
            ->groupBy(fn ($m) => $m->moved_on->toDateString());
        $categories = collect($rows)->groupBy('category');
        $recent = GsDocumentLine::query()->whereIn('gs_document_id', GsDocument::query()->where('status', 'POSTED')->orderByDesc('doc_date')->orderByDesc('created_at')->limit(10)->select('id'))->get();
        $recentDocs = GsDocument::query()->whereIn('id', $recent->pluck('gs_document_id'))->get()->keyBy('id');
        $items = GsItem::query()->whereIn('id', $recent->pluck('gs_item_id'))->get()->keyBy('id');
        $units = GsUnit::query()->pluck('code', 'id');
        $depts = GsDepartment::query()->pluck('name', 'id');
        $stores = GsStore::query()->pluck('name', 'id');
        $suppliers = GsSupplier::query()->pluck('name', 'id');
        $names = DB::table('users')->whereIn('id', $recentDocs->pluck('created_by')->filter())->pluck('name', 'id');

        return ApiResponse::ok([
            'tiles' => [
                'items' => count($rows), 'qty' => $tiles['qty'], 'value' => $tiles['value'], 'received_value' => $received, 'issued_value' => $issued,
                'in_house_value' => round($received - $issued, 2), 'categories' => GsCategory::query()->where('active', true)->count(),
                'low' => $tiles['low'], 'out' => $tiles['out'],
                'today' => ['RECEIVE' => $todays['RECEIVE'] ?? 0, 'ISSUE' => $todays['ISSUE'] ?? 0, 'RETURN' => $todays['RETURN'] ?? 0, 'ADJUST' => $todays['ADJUST'] ?? 0],
            ],
            'movement' => collect(range(29, 0))->map(function (int $back) use ($daily): array {
                $day = now()->subDays($back)->toDateString();
                $set = $daily[$day] ?? collect();

                return ['date' => $day, 'in' => round((float) $set->filter(fn ($m) => (float) $m->quantity > 0)->sum('quantity'), 4), 'out' => round(abs((float) $set->filter(fn ($m) => (float) $m->quantity < 0)->sum('quantity')), 4)];
            })->all(),
            'by_category' => $categories->map(fn ($set, $name) => ['category' => $name, 'qty' => round((float) collect($set)->sum(fn ($r) => max(0, $r['qty'])), 4), 'value' => round((float) collect($set)->sum(fn ($r) => $r['value'] ?? 0), 2)])
                ->sortByDesc('qty')->values()->all(),
            'low' => collect($rows)->where('status', 'LOW')->values()->all(),
            'out_of_stock' => collect($rows)->where('status', 'OUT')->take(20)->values()->all(),
            'recent' => $recent->sortByDesc(fn ($l) => [$recentDocs[$l->gs_document_id]->doc_date->toDateString(), $recentDocs[$l->gs_document_id]->created_at?->format('U.u')])->take(12)->values()
                ->map(function (GsDocumentLine $l) use ($recentDocs, $items, $units, $depts, $stores, $suppliers, $names): array {
                    $d = $recentDocs[$l->gs_document_id];

                    return [
                        'date' => $d->doc_date->toDateString(), 'type' => $d->doc_type, 'doc_no' => $d->doc_no, 'item' => $items[$l->gs_item_id]->name ?? null,
                        'party' => $d->gs_department_id ? ($depts[$d->gs_department_id] ?? null) : ($d->gs_supplier_id ? ($suppliers[$d->gs_supplier_id] ?? null) : ($stores[$d->gs_store_id] ?? null)),
                        'qty' => abs((float) $l->movement_qty), 'unit' => $units[$items[$l->gs_item_id]->gs_unit_id ?? ''] ?? null, 'user' => $names[$d->created_by] ?? null,
                    ];
                })->all(),
            'highest' => collect($rows)->sortByDesc('qty')->take(10)->values()->all(),
            // The two banners: requisitions waiting for approval, and approved ones waiting to be issued.
            'requisitions' => [
                'pending' => GsRequisition::query()->where('status', 'PENDING')->count(),
                'to_issue' => GsRequisition::query()->whereIn('status', GsRequisition::ISSUABLE)->count(),
            ],
        ]);
    }

    /** @return array{q: ?string, store: ?string, category: ?string, status: ?string} */
    private function stockFilters(Request $request): array
    {
        $f = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'store' => ['nullable', 'string'], 'category' => ['nullable', 'string'], 'status' => ['nullable', Rule::in(['IN_STOCK', 'LOW', 'OUT'])]]);

        return array_merge(['q' => null, 'store' => null, 'category' => null, 'status' => null], $f);
    }

    /** @return list<array<string, mixed>> */
    private function stockRows(array $f): array
    {
        $items = GsItem::query()->where('active', true)->when($f['category'], fn ($q, $v) => $q->where('gs_category_id', $v))
            ->when($f['q'], function ($q, $v) {
                $like = '%'.mb_strtolower(trim($v)).'%';
                $q->where(fn ($w) => $w->whereRaw('lower(name) like ?', [$like])->orWhereRaw('lower(item_code) like ?', [$like]));
            })->orderBy('item_code')->get();
        $stock = GsBook::stock($items->pluck('id'));
        $prices = GsBook::lastPrices($items);
        $cats = GsCategory::query()->pluck('name', 'id');
        $units = GsUnit::query()->pluck('code', 'id');
        $stores = GsStore::query()->orderBy('name')->pluck('name', 'id');

        return $items->map(function (GsItem $i) use ($stock, $prices, $cats, $units, $stores, $f): ?array {
            $held = $stock[$i->id] ?? [];
            $qty = round($f['store'] ? ($held[$f['store']] ?? 0.0) : array_sum($held), 4);
            $min = (float) $i->minimum_stock;
            $status = match (true) {
                $qty <= 0 => 'OUT',
                $min > 0 && $qty <= $min => 'LOW',
                default => 'IN_STOCK',
            };
            if ($f['status'] && $f['status'] !== $status) {
                return null;
            }
            $price = $prices[$i->id] ?? null;

            return [
                'item_code' => $i->item_code, 'item' => $i->name, 'category' => $cats[$i->gs_category_id] ?? null, 'unit' => $units[$i->gs_unit_id] ?? null,
                'qty' => $qty, 'price' => $price, 'value' => $price === null ? null : round(max(0, $qty) * $price, 2), 'min' => $min > 0 ? $min : null,
                'by_store' => collect($held)->filter(fn ($q) => abs($q) > 0.00005)->map(fn ($q, $s) => ['store' => $stores[$s] ?? '', 'qty' => $q])->sortBy('store')->values()->all(),
                'rack' => $i->rack_location, 'status' => $status,
            ];
        })->filter()->values()->all();
    }

    /** @return array{items: int, qty: float, value: float, priced: int, low: int, out: int} */
    private function tiles(array $rows): array
    {
        $rows = collect($rows);

        return [
            'items' => $rows->count(), 'qty' => round((float) $rows->sum(fn ($r) => max(0, $r['qty'])), 4), 'value' => round((float) $rows->sum(fn ($r) => $r['value'] ?? 0), 2),
            'priced' => $rows->whereNotNull('price')->count(), 'low' => $rows->where('status', 'LOW')->count(), 'out' => $rows->where('status', 'OUT')->count(),
        ];
    }
}

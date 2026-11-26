<?php

declare(strict_types=1);

namespace App\Modules\StoreMaterial\Http\Controllers\Api;

use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\PurchaseRequisition;
use App\Modules\MaterialProcurement\Models\PurchaseRequisitionLine;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\Style;
use App\Modules\StoreMaterial\Models\Grn;
use App\Modules\StoreMaterial\Models\LedgerEntry;
use App\Modules\StoreMaterial\Models\Roll;
use App\Modules\StoreMaterial\Models\Stock;
use App\Modules\StoreMaterial\Support\StoreBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Store & Material's views of the books (AnnotechERP 09/01–09/02): the
 * Dashboard (PR quantity, in-house, pending, out, returned and current stock
 * by buyer → PO → style → material, every movement and stock by material
 * type); the Fabric Warehouse (stock list, roll, colour and dye-lot wise,
 * movement) and the Trims & Accessories Warehouse; the Stock Ledger with its
 * running balance; and the employee a receiver's ID or card names.
 */
class StockApiController extends ApiController
{
    /** The filters every view offers: buyers, POs, styles and materials held. */
    public function filters(): JsonResponse
    {
        $this->allow('storematerial.view');
        $stocks = Stock::query()->get();
        $items = MaterialItem::query()->whereIn('id', $stocks->pluck('item_id'))->get()->keyBy('id');
        $distinct = fn (string $col) => $stocks->pluck($col)->filter()->unique()->sort()->values()->map(fn ($v) => ['value' => $v, 'label' => $v])->all();

        return ApiResponse::ok([
            'buyers' => $distinct('buyer'), 'pos' => $distinct('po_number'), 'styles' => $distinct('style'),
            'materials' => $items->sortBy('name')->values()->map(fn (MaterialItem $i) => ['value' => $i->id, 'label' => StoreBook::material($i), 'kind' => $i->kind, 'category' => $i->category])->all(),
            'categories' => $items->where('kind', 'TRIMS')->pluck('category')->filter()->unique()->sort()->values()->map(fn ($c) => ['value' => $c, 'label' => $c])->all(),
        ]);
    }

    /** Dashboard. */
    public function dashboard(Request $request): JsonResponse
    {
        $this->allow('storematerial.view');
        $f = $this->filterInput($request);
        $stocks = $this->stocks($f);
        $entries = $this->entries($stocks, $f);
        $items = MaterialItem::query()->whereIn('id', $stocks->pluck('item_id'))->get()->keyBy('id');
        $balances = StoreBook::balances($stocks->pluck('id'));
        $prQty = $this->prQuantity($f);
        $in = (float) $entries->where('type', 'GRN')->sum('in_qty') - (float) $entries->where('type', 'REVERSAL')->sum('out_qty');
        $byKind = $stocks->groupBy(fn (Stock $s) => $items[$s->item_id]->kind)->map(fn ($set) => round($set->sum(fn (Stock $s) => max(0, $balances[$s->id] ?? 0)), 3));
        $refs = $this->prOf($entries);
        $running = [];

        return ApiResponse::ok([
            'units' => $stocks->pluck('uom')->unique()->values()->all(),
            'tiles' => [
                'pr_qty' => round($prQty, 3), 'in_house' => round($in, 3), 'pending' => round(max(0, $prQty - $in), 3),
                'out' => round((float) $entries->where('type', 'MATERIAL_OUT')->sum('out_qty'), 3), 'returned' => round((float) $entries->where('type', 'MATERIAL_RETURN')->sum('return_qty'), 3),
                'stock' => round(array_sum(array_map(fn ($b) => max(0, $b), $balances)), 3),
            ],
            'by_kind' => ['FABRIC' => $byKind['FABRIC'] ?? 0, 'TRIMS' => $byKind['TRIMS'] ?? 0],
            'movements' => $entries->sortBy(fn ($e) => [$e->entry_date->toDateString(), $e->created_at->format('U.u')])->values()->map(function (LedgerEntry $e) use (&$running, $refs): array {
                $running[$e->stock_id] = ($running[$e->stock_id] ?? 0) + (float) $e->in_qty + (float) $e->return_qty - (float) $e->out_qty;

                return $this->movement($e, round(array_sum($running), 3)) + ['pr_no' => $refs[$e->reference] ?? null];
            })->reverse()->values()->all(),
        ]);
    }

    /** Fabric Warehouse: stock list, roll / colour / dye-lot wise, or movement. */
    public function fabric(Request $request): JsonResponse
    {
        $this->allow('storematerial.view');
        $f = $this->filterInput($request);
        $view = in_array($request->query('view'), ['stock', 'roll', 'color', 'lot', 'movement'], true) ? $request->query('view') : 'stock';
        $stocks = $this->stocks($f, 'FABRIC');
        $items = MaterialItem::query()->whereIn('id', $stocks->pluck('item_id'))->get()->keyBy('id');
        $totals = $this->totals($stocks);
        // The rolls fabric was received in, and what each has left; a roll with nothing left is no longer counted as one held.
        $rolls = StoreBook::rolls($stocks->pluck('id'));
        $left = StoreBook::rollTotals($rolls->pluck('id'));
        $held = $rolls->filter(fn (Roll $r) => ($left[$r->id]['available'] ?? 0) > 0);
        $list = fn (Collection $values): ?string => ($v = $values->filter()->unique()->values())->isEmpty() ? null
            : $v->take(3)->implode(', ').($v->count() > 3 ? ' +'.($v->count() - 3) : '');
        $row = fn (Stock $s): array => [
            'roll_no' => $list($held->where('stock_id', $s->id)->pluck('roll_no')), 'dye_lot' => $list($held->where('stock_id', $s->id)->pluck('dye_lot')),
            'material' => $items[$s->item_id]->name, 'color' => $items[$s->item_id]->colour, 'gsm' => $items[$s->item_id]->gsm, 'width' => $items[$s->item_id]->width, 'unit' => $s->uom,
            'buyer' => $s->buyer ?: null, 'style' => $s->style ?: null, 'recv' => $totals[$s->id]['recv'], 'out' => $totals[$s->id]['out'], 'available' => $totals[$s->id]['available'],
        ];
        $rows = match ($view) {
            'roll' => $rolls->map(fn (Roll $r): array => [
                'roll_no' => $r->roll_no, 'material' => $items[$r->item_id]->name, 'color' => $items[$r->item_id]->colour, 'dye_lot' => $r->dye_lot,
                'gsm' => $r->gsm ?? $items[$r->item_id]->gsm, 'width' => $r->width ?? $items[$r->item_id]->width, 'unit' => $r->uom,
                'recv' => $left[$r->id]['recv'] ?? 0.0, 'out' => $left[$r->id]['out'] ?? 0.0, 'available' => $left[$r->id]['available'] ?? 0.0,
            ])->values()->all(),
            'lot' => $rolls->groupBy(fn (Roll $r) => $r->item_id.'|'.$r->uom.'|'.$r->dye_lot)->map(function ($set) use ($items, $left): array {
                $first = $set->first();

                return ['material' => $items[$first->item_id]->name, 'dye_lot' => $first->dye_lot, 'rolls' => $set->filter(fn (Roll $r) => ($left[$r->id]['available'] ?? 0) > 0)->count(), 'unit' => $first->uom]
                    + collect(['recv', 'out', 'available'])->mapWithKeys(fn ($k) => [$k => round($set->sum(fn (Roll $r) => $left[$r->id][$k] ?? 0), 3)])->all();
            })->sortBy(fn (array $r) => $r['material'].'|'.$r['dye_lot'])->values()->all(),
            'color' => $stocks->groupBy(fn (Stock $s) => $s->item_id.'|'.$s->uom)->map(function ($set) use ($items, $totals, $held): array {
                $first = $set->first();

                return ['material' => $items[$first->item_id]->name, 'color' => $items[$first->item_id]->colour, 'rolls' => $held->whereIn('stock_id', $set->pluck('id')->all())->count(), 'unit' => $first->uom]
                    + collect(['recv', 'out', 'available'])->mapWithKeys(fn ($k) => [$k => round($set->sum(fn (Stock $s) => $totals[$s->id][$k]), 3)])->all();
            })->values()->all(),
            'movement' => $this->entries($stocks, $f)->sortByDesc(fn ($e) => [$e->entry_date->toDateString(), $e->created_at->format('U.u')])->values()
                ->map(fn (LedgerEntry $e) => $this->movement($e, StoreBook::balance($e->stock_id)) + ['material' => StoreBook::material($items[$stocks->firstWhere('id', $e->stock_id)->item_id])])->all(),
            default => $stocks->map($row)->values()->all(),
        };
        if ($view === 'movement') {
            $rows = $this->withRunningBalance($rows);
        }

        return ApiResponse::ok(['view' => $view, 'rows' => $rows]);
    }

    /** Trims & Accessories Warehouse. */
    public function trims(Request $request): JsonResponse
    {
        $this->allow('storematerial.view');
        $f = $this->filterInput($request);
        $stocks = $this->stocks($f, 'TRIMS')->when($request->query('category'), fn ($c, $cat) => $c->filter(fn (Stock $s) => MaterialItem::query()->whereKey($s->item_id)->value('category') === $cat));
        $items = MaterialItem::query()->whereIn('id', $stocks->pluck('item_id'))->get()->keyBy('id');
        $totals = $this->totals($stocks);

        return ApiResponse::ok(['rows' => $stocks->map(fn (Stock $s): array => [
            'item' => $items[$s->item_id]->name, 'category' => $items[$s->item_id]->category, 'color' => $items[$s->item_id]->colour, 'size' => $items[$s->item_id]->size,
            'unit' => $s->uom, 'buyer' => $s->buyer ?: null, 'style' => $s->style ?: null,
        ] + $totals[$s->id])->values()->all()]);
    }

    /** Stock Ledger: every movement in order with the running balance. */
    public function ledger(Request $request): JsonResponse
    {
        $this->allow('storematerial.view');
        $f = $this->filterInput($request);
        $stocks = $this->stocks($f);
        $rows = $this->entries($stocks, $f)->sortBy(fn ($e) => [$e->entry_date->toDateString(), $e->created_at->format('U.u')])->values()->map(fn (LedgerEntry $e) => $this->movement($e, 0))->all();
        $opening = $f['from'] ? round((float) LedgerEntry::query()->whereIn('stock_id', $stocks->pluck('id'))->where('entry_date', '<', $f['from'])->get()->sum(fn ($e) => (float) $e->in_qty + (float) $e->return_qty - (float) $e->out_qty), 3) : 0.0;
        $balance = $opening;
        foreach ($rows as &$r) {
            $balance = round($balance + $r['in'] + $r['returned'] - $r['out'], 3);
            $r['balance'] = $balance;
        }

        return ApiResponse::ok(['rows' => $rows, 'opening' => $opening, 'units' => $stocks->pluck('uom')->unique()->values()->all()]);
    }

    /** The employee an ID or card / proxy number names — Received By, Receiver, Returned By. */
    public function employee(Request $request): JsonResponse
    {
        if (! collect(['storematerial.receive', 'storematerial.issue', 'hrpayroll.employee.view'])->contains(fn ($p) => $this->caller()->can($p))) {
            throw ApiException::of(ErrorCode::FORBIDDEN);
        }
        $e = StoreBook::employee((string) $request->query('id'), 'id') ?? throw ValidationException::withMessages(['id' => __('storematerial.employee_required')]);

        return ApiResponse::ok(['code' => $e->employee_code, 'card_no' => $e->card_no, 'name' => $e->name, 'label' => StoreBook::person($e), 'status' => $e->status]);
    }

    /** @return array{buyer: ?string, po: ?string, style: ?string, material: ?string, from: ?string, to: ?string} */
    private function filterInput(Request $request): array
    {
        $f = $request->validate([
            'buyer' => ['nullable', 'string'], 'po' => ['nullable', 'string'], 'style' => ['nullable', 'string'], 'material' => ['nullable', 'string'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);

        return ['buyer' => $f['buyer'] ?? null, 'po' => $f['po'] ?? null, 'style' => $f['style'] ?? null, 'material' => $f['material'] ?? null, 'from' => $f['from'] ?? null, 'to' => $f['to'] ?? null];
    }

    private function stocks(array $f, ?string $kind = null): Collection
    {
        return Stock::query()
            ->when($f['buyer'], fn ($q, $v) => $q->where('buyer', $v))->when($f['po'], fn ($q, $v) => $q->where('po_number', $v))
            ->when($f['style'], fn ($q, $v) => $q->where('style', $v))->when($f['material'], fn ($q, $v) => $q->where('item_id', $v))
            ->when($kind, fn ($q, $k) => $q->whereIn('item_id', MaterialItem::query()->where('kind', $k)->select('id')))
            ->orderBy('buyer')->orderBy('style')->get();
    }

    private function entries(Collection $stocks, array $f): Collection
    {
        return LedgerEntry::query()->whereIn('stock_id', $stocks->pluck('id'))
            ->when($f['from'], fn ($q, $v) => $q->where('entry_date', '>=', $v))->when($f['to'], fn ($q, $v) => $q->where('entry_date', '<=', $v))->get();
    }

    /** @return array<string, array{recv: float, out: float, returned: float, available: float}> */
    private function totals(Collection $stocks): array
    {
        $out = [];
        $entries = LedgerEntry::query()->whereIn('stock_id', $stocks->pluck('id'))->get()->groupBy('stock_id');
        foreach ($stocks as $s) {
            $set = $entries[$s->id] ?? collect();
            $recv = (float) $set->where('type', 'GRN')->sum('in_qty') - (float) $set->where('type', 'REVERSAL')->sum('out_qty') + (float) $set->where('type', 'ADJUSTMENT')->sum('in_qty');
            $issued = (float) $set->where('type', 'MATERIAL_OUT')->sum('out_qty') + (float) $set->where('type', 'ADJUSTMENT')->sum('out_qty');
            $returned = (float) $set->sum('return_qty');
            $out[$s->id] = ['recv' => round($recv, 3), 'out' => round($issued, 3), 'returned' => round($returned, 3), 'available' => round($recv - $issued + $returned, 3)];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function movement(LedgerEntry $e, float $balance): array
    {
        return [
            'date' => $e->entry_date->toDateString(), 'reference' => $e->reference, 'type' => $e->type,
            'in' => (float) $e->in_qty, 'out' => (float) $e->out_qty, 'returned' => (float) $e->return_qty, 'balance' => $balance,
            'unit' => Stock::query()->whereKey($e->stock_id)->value('uom'),
        ];
    }

    /** Movement newest first, its balance the store's after each entry. @param list<array<string, mixed>> $rows */
    private function withRunningBalance(array $rows): array
    {
        $balance = 0.0;
        foreach (array_reverse(array_keys($rows)) as $i) {
            $balance = round($balance + $rows[$i]['in'] + $rows[$i]['returned'] - $rows[$i]['out'], 3);
            $rows[$i]['balance'] = $balance;
        }

        return $rows;
    }

    /** The PR quantity of approved requisitions, by the same buyer, PO, style and material filters. */
    private function prQuantity(array $f): float
    {
        $prs = PurchaseRequisition::query()->where('status', 'APPROVED')->get(['id', 'order_id']);
        $orders = Order::query()->whereIn('id', $prs->pluck('order_id')->filter())->get(['id', 'buyer_id', 'po_no', 'style_id', 'style_no'])->keyBy('id');
        $keep = $prs->filter(function ($pr) use ($orders, $f): bool {
            $o = $pr->order_id ? $orders[$pr->order_id] ?? null : null;
            if (($f['buyer'] || $f['po'] || $f['style']) && ! $o) {
                return false;
            }

            return (! $f['buyer'] || Buyer::query()->whereKey($o->buyer_id)->value('short_name') === $f['buyer'])
                && (! $f['po'] || $o->po_no === $f['po'])
                && (! $f['style'] || (Style::query()->whereKey($o->style_id)->value('style_ref') ?? $o->style_no) === $f['style']);
        });

        return (float) PurchaseRequisitionLine::query()->whereIn('requisition_id', $keep->pluck('id'))->whereIn('status', PurchaseRequisitionLine::DELIVERY)
            ->when($f['material'], fn ($q, $v) => $q->where('item_id', $v))->sum('req_qty');
    }

    /** @return array<string, string> GRN number => its PR */
    private function prOf(Collection $entries): array
    {
        $grns = Grn::query()->whereIn('grn_no', $entries->pluck('reference')->unique())->whereNotNull('requisition_id')->pluck('requisition_id', 'grn_no');
        $prs = PurchaseRequisition::query()->whereIn('id', $grns->values())->pluck('pr_no', 'id');

        return $grns->map(fn ($id) => $prs[$id] ?? null)->filter()->all();
    }
}

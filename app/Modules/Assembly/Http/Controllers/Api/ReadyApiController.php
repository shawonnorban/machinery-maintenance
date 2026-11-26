<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Http\Controllers\Api;

use App\Modules\Assembly\Models\AsmFgLedger;
use App\Modules\Assembly\Models\AsmPackingBatch;
use App\Modules\Assembly\Models\AsmPackingCarton;
use App\Modules\Assembly\Models\AsmPackingIn;
use App\Modules\Assembly\Models\AsmQualityIn;
use App\Modules\Assembly\Models\AsmQualityOut;
use App\Modules\Assembly\Models\AsmShipment;
use App\Modules\Assembly\Support\PackingBook;
use App\Modules\Assembly\Support\ReadyBook;
use App\Modules\Cutting\Models\CutBundle;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Assembly Floor → Ready For Shipment (AnnotechERP 20/06), as Brickland keeps
 * it: the Ready for Shipment Dashboard — everything packed out and not yet
 * shipped, one row per PO and colour (final packed, shipped, ready, cartons,
 * ship date, Ready / Partially Shipped / Shipment Completed), its key figures,
 * filters and a row's detail (the quantities from order to ready, the packed
 * batches and the shipments); and Finished Goods Stock — the stock by PO,
 * colour and size with the ledger it is read from (in from Packing Out, out
 * by Shipment and back on a Shipment Reversal, the last two from 20/07).
 */
class ReadyApiController extends ApiController
{
    public const STATUSES = ['READY', 'PARTIALLY_SHIPPED', 'SHIPMENT_COMPLETED'];

    private ?PackingBook $book = null;

    /** The Ready for Shipment Dashboard for the filters: key figures, the PO / colour rows and the buyers to pick from. */
    public function dashboard(Request $request): JsonResponse
    {
        $this->allow('assembly.ready.view');
        $q = $request->validate([
            'q' => ['nullable', 'string', 'max:60'], 'buyer' => ['nullable', 'string', 'max:80'], 'status' => ['nullable', Rule::in(self::STATUSES)],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'all' => ['nullable', 'boolean'],
        ]);
        if (isset($q['from'], $q['to']) && $q['to'] < $q['from']) {
            throw ValidationException::withMessages(['to' => __('assembly.v.range_order')]);
        }
        $all = $this->rows();
        $needle = mb_strtolower(trim((string) ($q['q'] ?? '')));
        $rows = $all->filter(function ($r) use ($q, $needle) {
            if (! empty($q['status'])) {
                if ($r['status'] !== $q['status']) {
                    return false;
                }
            } elseif (empty($q['all']) && $r['ready_qty'] < 1) {
                return false;
            }
            if (! empty($q['buyer']) && $r['buyer'] !== $q['buyer']) {
                return false;
            }
            if (! empty($q['from']) && (! $r['ship_date'] || $r['ship_date'] < $q['from'])) {
                return false;
            }
            if (! empty($q['to']) && (! $r['ship_date'] || $r['ship_date'] > $q['to'])) {
                return false;
            }

            return $needle === '' || str_contains($r['search'], $needle);
        })->values();
        $active = $all->where('ready_qty', '>', 0);

        return ApiResponse::ok([
            'kpis' => [
                'total_ready_qty' => (int) $active->sum('ready_qty'), 'ready_orders' => $active->pluck('order_no')->filter()->unique()->count(),
                'ready_styles' => $active->pluck('style')->filter()->unique()->count(), 'total_cartons' => (int) $active->sum('cartons'),
                'total_buyers' => $active->pluck('buyer')->filter()->unique()->count(), 'shipment_pending' => $all->where('status', 'READY')->count(),
                'partially_shipped' => $all->where('status', 'PARTIALLY_SHIPPED')->count(), 'shipment_completed' => $all->where('status', 'SHIPMENT_COMPLETED')->count(),
            ],
            'buyers' => $all->pluck('buyer')->filter()->unique()->sort()->values()->all(),
            'rows' => $rows->map(fn ($r) => ReadyBook::public($r))->all(),
        ]);
    }

    /** One PO / colour row in full: order to ready, the packed batches and the shipments. */
    public function detail(Request $request): JsonResponse
    {
        $this->allow('assembly.ready.view');
        $q = $request->validate(['key' => ['required', 'string', 'max:120']]);
        $row = $this->rows()->firstWhere('key', $q['key']) ?? throw ValidationException::withMessages(['key' => __('assembly.v.rs_unknown')]);
        $batches = AsmPackingBatch::query()->whereIn('id', $row['batch_ids'])->orderBy('packed_out_at')->get();
        $shipped = AsmFgLedger::query()->whereIn('batch_id', $row['batch_ids'])->where('doc_type', '!=', 'PACKING_OUT')->get()->groupBy('batch_id');
        $cartons = AsmPackingCarton::query()->whereIn('batch_id', $row['batch_ids'])->where('status', 'SEALED')->get()->groupBy('batch_id');
        $bundleIds = AsmPackingIn::query()->whereIn('batch_id', $row['batch_ids'])->pluck('bundle_id')->unique();
        $orderBundles = CutBundle::query()->where('colour', $row['colour'])->when($row['order_id'], fn ($w, $id) => $w->where('order_id', $id), fn ($w) => $w->where('po_no', $row['po_no']))->pluck('id');
        $qcPass = (int) AsmQualityOut::query()->whereIn('quality_in_id', AsmQualityIn::query()->whereIn('bundle_id', $orderBundles)->select('id'))->sum('pass_qty');

        return ApiResponse::ok([
            'row' => ReadyBook::public($row),
            'info' => [
                'order_qty' => $this->book()->orderQty($row['order_id'], $row['colour']), 'qc_pass_qty' => $qcPass,
                'packing_in_qty' => (int) AsmPackingIn::query()->whereIn('batch_id', $row['batch_ids'])->sum('qty'), 'final_packed_qty' => $row['final_packed_qty'],
                'cartons' => $row['cartons'], 'shipped_qty' => $row['shipped_qty'], 'reserved_qty' => $row['reserved_qty'], 'ready_qty' => $row['ready_qty'], 'bundles' => $bundleIds->count(),
            ],
            'batches' => $batches->map(fn ($b) => [
                'packing_no' => $b->packing_no, 'packed_out_at' => PackingBook::at($b->packed_out_at), 'final_packed_qty' => (int) $b->final_packed_qty,
                'shipped_qty' => (int) $shipped->get($b->id, collect())->sum(fn ($l) => $l->qty_out - $l->qty_in), 'sealed_cartons' => $cartons->get($b->id, collect())->count(), 'status' => $b->status,
            ])->values()->all(),
            'shipments' => AsmShipment::query()->where('colour', $row['colour'])->when($row['order_id'], fn ($w, $id) => $w->where('order_id', $id), fn ($w) => $w->whereNull('order_id')->where('po_no', $row['po_no']))
                ->orderBy('ship_date')->orderBy('created_at')->get()->map(fn ($s) => (new ReadyBook($this->book()))->shipment($s))->all(),
        ]);
    }

    /** Finished Goods Stock: the balance by PO, colour and size, and the ledger, newest first. */
    public function stock(Request $request): JsonResponse
    {
        $this->allow('assembly.ready.view');
        $q = $request->validate(['q' => ['nullable', 'string', 'max:60'], 'all' => ['nullable', 'boolean']]);
        $needle = mb_strtolower(trim((string) ($q['q'] ?? '')));
        $ledger = AsmFgLedger::query()->orderByDesc('posted_at')->orderByDesc('id')->get();
        $book = $this->book();
        $present = function (AsmFgLedger $l) use ($book) {
            $order = $book->order($l->order_id);

            return ['buyer' => $book->buyer($l->buyer_id)[0], 'order_no' => $order?->order_no, 'po_no' => $l->po_no, 'style' => $l->style, 'colour' => $l->colour, 'size' => $l->size];
        };
        $match = fn (array $r, array $extra = []) => $needle === '' || str_contains(mb_strtolower(implode(' ', array_filter([...array_values($r), ...$extra]))), $needle);

        $stock = $ledger->groupBy(fn ($l) => implode('|', [$l->order_id ?? $l->po_no, $l->colour, $l->size]))->map(function (Collection $g) use ($present) {
            $in = (int) $g->sum('qty_in');
            $out = (int) $g->sum('qty_out');

            return $present($g->first()) + ['in_qty' => $in, 'out_qty' => $out, 'balance' => $in - $out];
        })->filter(fn ($r) => ! empty($q['all']) || $r['balance'] !== 0)->filter(fn ($r) => $match($r))
            ->sortBy(fn ($r) => [$r['buyer'] ?? '', $r['po_no'] ?? '', $r['colour'], PackingBook::sizeRank($r['size']), $r['size']])->values();
        $moves = $ledger->map(fn (AsmFgLedger $l) => $present($l) + [
            'id' => $l->id, 'at' => PackingBook::at($l->posted_at), 'doc_type' => $l->doc_type, 'doc_no' => $l->doc_no,
            'packing_no' => $l->batch_id ? AsmPackingBatch::query()->whereKey($l->batch_id)->value('packing_no') : null,
            'qty_in' => $l->qty_in, 'qty_out' => $l->qty_out, 'by' => $book->userName($l->created_by),
        ])->filter(fn ($r) => $match(collect($r)->only(['buyer', 'order_no', 'po_no', 'style', 'colour', 'size'])->all(), [$r['doc_no'], $r['packing_no']]))->values();
        $in = (int) $ledger->sum('qty_in');
        $out = (int) $ledger->sum('qty_out');

        return ApiResponse::ok([
            'totals' => ['balance' => $in - $out, 'in_qty' => $in, 'out_qty' => $out,
                'orders' => $ledger->groupBy(fn ($l) => $l->order_id ?? $l->po_no)->filter(fn ($g) => $g->sum('qty_in') - $g->sum('qty_out') > 0)->count()],
            'stock' => $stock->all(),
            'stock_totals' => ['in_qty' => (int) $stock->sum('in_qty'), 'out_qty' => (int) $stock->sum('out_qty'), 'balance' => (int) $stock->sum('balance')],
            'ledger' => $moves->all(),
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function rows(): Collection
    {
        return (new ReadyBook($this->book()))->rows();
    }

    private function book(): PackingBook
    {
        return $this->book ??= new PackingBook($this->caller()->companyId);
    }
}

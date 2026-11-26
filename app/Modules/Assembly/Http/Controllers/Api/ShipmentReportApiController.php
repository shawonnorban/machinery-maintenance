<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Http\Controllers\Api;

use App\Modules\Assembly\Models\AsmFgLedger;
use App\Modules\Assembly\Models\AsmShipment;
use App\Modules\Assembly\Support\PackingBook;
use App\Modules\Assembly\Support\ReadyBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Assembly Floor → Shipment Reports (AnnotechERP 20/07), Brickland's
 * seventeen: the shipment reports (daily, buyer-, PO- and style-wise,
 * quantity, partial, completed, history) read the shipments shipped in the
 * date range; the Ready reports (ready, buyer-, PO- and style-wise ready,
 * ready quantity by size, shipment pending, partially shipped) and Finished
 * Goods Stock are as they stand now; the Finished Goods Ledger reads the
 * movements in the range. Every one narrows by buyer, PO or style.
 */
class ShipmentReportApiController extends ApiController
{
    public const KINDS = ['daily', 'buyer', 'po', 'style', 'quantity', 'partial', 'completed', 'history', 'ready', 'buyer_ready', 'po_ready', 'style_ready', 'ready_qty',
        'pending', 'partially_shipped', 'fg_stock', 'fg_ledger'];

    private const MAX_RANGE_DAYS = 366;

    private ?PackingBook $book = null;

    public function run(Request $request): JsonResponse
    {
        $this->allow('assembly.shipment.view');
        $q = $request->validate(['kind' => ['required', Rule::in(self::KINDS)], 'from' => ['required', 'date'], 'to' => ['required', 'date'], 'q' => ['nullable', 'string', 'max:60']]);
        $from = CarbonImmutable::parse($q['from'])->startOfDay();
        $to = CarbonImmutable::parse($q['to'])->startOfDay();
        if ($to < $from) {
            throw ValidationException::withMessages(['to' => __('assembly.v.range_order')]);
        }
        if ($from->diffInDays($to) + 1 > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => __('assembly.v.range_max', ['days' => self::MAX_RANGE_DAYS])]);
        }
        [$f, $t] = [$from->toDateString(), $to->toDateString()];
        [$columns, $rows] = $this->build($q['kind'], $f, $t);
        $rows = array_map(fn ($r) => array_diff_key($r, ['order_id' => true]), $rows);
        $needle = mb_strtolower(trim((string) ($q['q'] ?? '')));
        if ($needle !== '') {
            $rows = array_values(array_filter($rows, fn ($r) => str_contains(mb_strtolower(implode(' ', [$r['buyer'] ?? '', $r['po_no'] ?? '', $r['style'] ?? '', $r['order_no'] ?? ''])), $needle)));
        }
        $totals = [];
        foreach ($columns as $c) {
            if ($c['numeric']) {
                $totals[$c['key']] = round(array_sum(array_map(fn ($r) => (float) ($r[$c['key']] ?? 0), $rows)), 2);
            }
        }

        return ApiResponse::ok(['kind' => $q['kind'], 'from' => $f, 'to' => $t, 'columns' => $columns, 'rows' => $rows, 'totals' => $totals]);
    }

    private function build(string $kind, string $f, string $t): array
    {
        $ready = fn () => (new ReadyBook($this->book()))->rows();
        $shipped = fn () => collect($this->shipped($f, $t));
        $readyCols = ['po_no', 'buyer', 'style', 'colour', 'final#', 'shipped#', 'ready#', 'cartons#', 'ship_date', 'status'];
        $readyRow = fn ($r) => ['po_no' => $r['po_no'], 'order_no' => $r['order_no'], 'buyer' => $r['buyer'], 'style' => $r['style'], 'colour' => $r['colour'], 'final' => $r['final_packed_qty'],
            'shipped' => $r['shipped_qty'], 'ready' => $r['ready_qty'], 'cartons' => $r['cartons'], 'ship_date' => $r['ship_date'], 'status' => $r['status']];

        return match ($kind) {
            'daily' => [$this->cols(['date', 'shipments#', 'qty#', 'cartons#', 'buyers#']), $shipped()->groupBy('date')->map(fn ($g, $d) => ['date' => $d, 'shipments' => $g->count(),
                'qty' => (int) $g->sum('qty'), 'cartons' => (int) $g->sum('cartons'), 'buyers' => $g->pluck('buyer')->filter()->unique()->count()])->sortKeysDesc()->values()->all()],
            'buyer' => $this->grouped($shipped(), ['buyer']),
            'po' => $this->grouped($shipped(), ['buyer', 'order_no', 'po_no'], true),
            'style' => $this->grouped($shipped(), ['buyer', 'style']),
            'quantity' => [$this->cols(['date', 'shipment_no', 'buyer', 'po_no', 'style', 'colour', 'qty#', 'cartons#', 'mode', 'destination']), $shipped()->all()],
            'partial' => [$this->cols(['date', 'shipment_no', 'buyer', 'po_no', 'style', 'colour', 'ready_before#', 'qty#', 'remaining#']), $shipped()->where('remaining', '>', 0)->values()->all()],
            'completed' => [$this->cols([...$readyCols, 'last_shipped']), $ready()->where('status', 'SHIPMENT_COMPLETED')->map(fn ($r) => $readyRow($r) + ['last_shipped' => $this->lastShipped($r)])->values()->all()],
            'history' => [$this->cols(['date', 'shipment_no', 'buyer', 'po_no', 'style', 'colour', 'qty#', 'ready_before#', 'remaining#', 'status']), $this->history($f, $t)],
            'ready' => [$this->cols($readyCols), $ready()->where('ready_qty', '>', 0)->map($readyRow)->values()->all()],
            'buyer_ready' => $this->readyGrouped($ready(), ['buyer']),
            'po_ready' => $this->readyGrouped($ready(), ['buyer', 'order_no', 'po_no']),
            'style_ready' => $this->readyGrouped($ready(), ['buyer', 'style']),
            'ready_qty' => [$this->cols(['buyer', 'po_no', 'style', 'colour', 'size', 'qty#']), $this->stock(true)],
            'pending' => [$this->cols($readyCols), $ready()->where('status', 'READY')->map($readyRow)->values()->all()],
            'partially_shipped' => [$this->cols($readyCols), $ready()->where('status', 'PARTIALLY_SHIPPED')->map($readyRow)->values()->all()],
            'fg_stock' => [$this->cols(['buyer', 'po_no', 'style', 'colour', 'size', 'in_qty#', 'out_qty#', 'qty#']), $this->stock(false)],
            'fg_ledger' => [$this->cols(['date', 'doc_type', 'doc_no', 'buyer', 'po_no', 'style', 'colour', 'size', 'in_qty#', 'out_qty#']), $this->ledger($f, $t)],
        };
    }

    /** Shipments shipped (and not reversed) on a day in the range. */
    private function shipped(string $f, string $t): array
    {
        return AsmShipment::query()->where('status', 'SHIPPED')->orderBy('shipped_at')->orderBy('id')->get()
            ->filter(fn ($s) => ($d = PackingBook::day($s->shipped_at)) >= $f && $d <= $t)
            ->map(fn ($s) => $this->base($s) + ['date' => PackingBook::day($s->shipped_at), 'shipment_no' => $s->shipment_no, 'qty' => $s->qty, 'cartons' => (int) $s->carton_qty,
                'mode' => $s->mode, 'destination' => $s->destination, 'ready_before' => $s->ready_before, 'remaining' => $s->remaining_after])->values()->all();
    }

    /** Every shipment entered for a ship date in the range, whatever its status. */
    private function history(string $f, string $t): array
    {
        return AsmShipment::query()->whereBetween('ship_date', [$f, $t])->orderByDesc('ship_date')->orderByDesc('created_at')->get()
            ->map(fn ($s) => $this->base($s) + ['date' => $s->ship_date->toDateString(), 'shipment_no' => $s->shipment_no, 'qty' => $s->qty,
                'ready_before' => $s->ready_before, 'remaining' => $s->remaining_after, 'status' => $s->status])->values()->all();
    }

    private function grouped(Collection $shipped, array $head, bool $orderQty = false): array
    {
        $rows = $shipped->groupBy(fn ($r) => implode('|', array_map(fn ($h) => $r[$h] ?? '', $head)))->map(function ($g) use ($head, $orderQty) {
            $row = array_intersect_key($g->first(), array_flip($head));
            if ($orderQty) {
                $row['order_qty'] = (int) $g->unique(fn ($r) => $r['order_id'].'|'.$r['colour'])->sum(fn ($r) => (int) $this->book()->orderQty($r['order_id'], $r['colour']));
            }

            return $row + ['shipments' => $g->count(), 'qty' => (int) $g->sum('qty'), 'cartons' => (int) $g->sum('cartons')];
        })->sortBy(fn ($r) => implode('|', array_map(fn ($h) => $r[$h] ?? '', $head)))->values()->all();

        return [$this->cols([...$head, ...($orderQty ? ['order_qty#'] : []), 'shipments#', 'qty#', 'cartons#']), $rows];
    }

    private function readyGrouped(Collection $ready, array $head): array
    {
        $rows = $ready->where('ready_qty', '>', 0)->groupBy(fn ($r) => implode('|', array_map(fn ($h) => $r[$h] ?? '', $head)))->map(fn ($g) => array_intersect_key($g->first(), array_flip($head)) + [
            'rows' => $g->count(), 'final' => (int) $g->sum('final_packed_qty'), 'shipped' => (int) $g->sum('shipped_qty'), 'ready' => (int) $g->sum('ready_qty'), 'cartons' => (int) $g->sum('cartons'),
        ])->sortBy(fn ($r) => implode('|', array_map(fn ($h) => $r[$h] ?? '', $head)))->values()->all();

        return [$this->cols([...$head, 'rows#', 'final#', 'shipped#', 'ready#', 'cartons#']), $rows];
    }

    /** Finished goods by PO, colour and size; $onlyBalance keeps the sizes with stock and reports the balance alone. */
    private function stock(bool $onlyBalance): array
    {
        return AsmFgLedger::query()->get()->groupBy(fn ($l) => implode('|', [$l->order_id ?? $l->po_no, $l->colour, $l->size]))->map(function ($g) use ($onlyBalance) {
            $l = $g->first();
            $in = (int) $g->sum('qty_in');
            $out = (int) $g->sum('qty_out');

            return $this->base($l) + ['size' => $l->size, 'qty' => $in - $out] + ($onlyBalance ? [] : ['in_qty' => $in, 'out_qty' => $out]);
        })->filter(fn ($r) => ! $onlyBalance || $r['qty'] > 0)
            ->sortBy(fn ($r) => [$r['buyer'] ?? '', $r['po_no'] ?? '', $r['colour'], PackingBook::sizeRank($r['size']), $r['size']])->values()->all();
    }

    private function ledger(string $f, string $t): array
    {
        return AsmFgLedger::query()->orderByDesc('posted_at')->orderByDesc('id')->get()->filter(fn ($l) => ($d = PackingBook::day($l->posted_at)) >= $f && $d <= $t)
            ->map(fn ($l) => $this->base($l) + ['date' => PackingBook::at($l->posted_at), 'doc_type' => $l->doc_type, 'doc_no' => $l->doc_no, 'size' => $l->size,
                'in_qty' => $l->qty_in, 'out_qty' => $l->qty_out])->values()->all();
    }

    private function lastShipped(array $row): ?string
    {
        $at = AsmShipment::query()->where('status', 'SHIPPED')->where('colour', $row['colour'])
            ->when($row['order_id'], fn ($w, $id) => $w->where('order_id', $id), fn ($w) => $w->where('po_no', $row['po_no']))->max('shipped_at');

        return $at ? PackingBook::day(CarbonImmutable::parse($at)) : null;
    }

    /** @return list<array{key: string, numeric: bool}> */
    private function cols(array $keys): array
    {
        return array_map(fn ($k) => ['key' => rtrim($k, '#'), 'numeric' => str_ends_with($k, '#')], $keys);
    }

    private function base(object $s): array
    {
        return ['order_id' => $s->order_id, 'order_no' => $this->book()->order($s->order_id)?->order_no, 'buyer' => $this->book()->buyer($s->buyer_id)[0],
            'po_no' => $s->po_no, 'style' => $s->style, 'colour' => $s->colour];
    }

    private function book(): PackingBook
    {
        return $this->book ??= new PackingBook($this->caller()->companyId);
    }
}

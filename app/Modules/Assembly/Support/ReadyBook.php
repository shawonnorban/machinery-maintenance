<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Support;

use App\Modules\Assembly\Models\AsmFgLedger;
use App\Modules\Assembly\Models\AsmPackingBatch;
use App\Modules\Assembly\Models\AsmPackingCarton;
use App\Modules\Assembly\Models\AsmPackingIn;
use App\Modules\Assembly\Models\AsmShipment;
use App\Modules\Cutting\Models\CutBundle;
use Illuminate\Support\Collection;

/**
 * Assembly Floor → Ready For Shipment (AnnotechERP 20/06) and Shipment
 * (20/07), what both read: every packed-out PO / colour with what was
 * packed, shipped, held on open (planned or confirmed) shipments, ready and
 * still available to ship, its cartons, sizes in stock, destination, ship
 * date and shipment status; and a shipment as the screens list it.
 */
final class ReadyBook
{
    public function __construct(private readonly PackingBook $book) {}

    /** A PO / colour's key: its order (or PO when there is none) and colour. */
    public static function key(?string $orderId, ?string $poNo, string $colour): string
    {
        return ($orderId ?? 'PO:'.$poNo).'|'.$colour;
    }

    /** @return Collection<int, array<string, mixed>> */
    public function rows(): Collection
    {
        $book = $this->book;
        $batches = AsmPackingBatch::query()->where('status', 'PACKED_OUT')->orderBy('packed_out_at')->get();
        $ledger = AsmFgLedger::query()->whereIn('batch_id', $batches->pluck('id'))->get()->groupBy('batch_id');
        $cartons = AsmPackingCarton::query()->whereIn('batch_id', $batches->pluck('id'))->where('status', 'SEALED')->get(['batch_id', 'carton_no']);
        $ins = AsmPackingIn::query()->whereIn('batch_id', $batches->pluck('id'))->get(['batch_id', 'bundle_id']);
        $bundleNos = CutBundle::query()->whereIn('id', $ins->pluck('bundle_id')->unique())->pluck('bundle_no', 'id');
        $open = AsmShipment::query()->whereIn('status', AsmShipment::OPEN)->get()->groupBy(fn ($s) => self::key($s->order_id, $s->po_no, $s->colour));

        return $batches->groupBy(fn ($b) => self::key($b->order_id, $b->po_no, $b->colour))->map(function (Collection $g, string $key) use ($book, $ledger, $cartons, $ins, $bundleNos, $open) {
            $b = $g->first();
            $ids = $g->pluck('id');
            $order = $book->order($b->order_id);
            $final = (int) $g->sum('final_packed_qty');
            $lines = $ids->flatMap(fn ($id) => $ledger->get($id, collect()));
            $shipped = (int) $lines->where('doc_type', '!=', 'PACKING_OUT')->sum(fn ($l) => $l->qty_out - $l->qty_in);
            $ready = max(0, $final - $shipped);
            $reserved = (int) $open->get($key, collect())->sum('qty');
            $myCartons = $cartons->whereIn('batch_id', $ids);
            [$buyer] = $book->buyer($b->buyer_id);
            $status = match (true) {
                $ready === 0 && $shipped > 0 => 'SHIPMENT_COMPLETED',
                $shipped > 0 => 'PARTIALLY_SHIPPED',
                default => 'READY',
            };
            $sizes = $lines->groupBy('size')->map(fn ($s) => (int) $s->sum('qty_in') - (int) $s->sum('qty_out'))->filter(fn ($q) => $q > 0)->keys()
                ->sortBy(fn ($s) => [PackingBook::sizeRank((string) $s), (string) $s])->values()->all();
            $bundles = $ins->whereIn('batch_id', $ids)->pluck('bundle_id')->unique()->map(fn ($id) => $bundleNos[$id] ?? '')->all();
            $search = mb_strtolower(implode(' ', array_filter([$buyer, $b->po_no, $b->style, $order?->order_no, $b->colour, ...$g->pluck('packing_no')->all(),
                ...$myCartons->pluck('carton_no')->all(), ...$myCartons->map(fn ($c) => str_replace('-', '', $c->carton_no))->all(), ...$bundles, ...array_map(fn ($n) => str_replace('-', '', $n), $bundles)])));

            return [
                'key' => $key, 'order_id' => $b->order_id, 'order_no' => $order?->order_no, 'po_no' => $b->po_no, 'buyer' => $buyer, 'style' => $b->style, 'style_name' => $order?->style_name,
                'colour' => $b->colour, 'factory' => $order?->factory, 'final_packed_qty' => $final, 'shipped_qty' => $shipped, 'reserved_qty' => $reserved, 'ready_qty' => $ready,
                'available_qty' => max(0, $ready - $reserved), 'cartons' => $myCartons->count(), 'sizes' => $sizes, 'destination' => $g->last()->destination,
                'ship_date' => $order?->ship_date, 'status' => $status, 'batches' => $g->count(), 'batch_ids' => $ids->all(), 'search' => $search,
            ];
        })->sortBy(fn ($r) => [$r['ship_date'] ?? '9999', $r['buyer'] ?? '', $r['po_no'] ?? '', $r['colour']])->values();
    }

    /** A row without what stays on the server. */
    public static function public(array $row): array
    {
        return collect($row)->except(['search', 'batch_ids'])->all();
    }

    /** A shipment as the screens list it. */
    public function shipment(AsmShipment $s, array $nos = []): array
    {
        return [
            'id' => $s->id, 'shipment_no' => $s->shipment_no, 'key' => self::key($s->order_id, $s->po_no, $s->colour), 'buyer' => $this->book->buyer($s->buyer_id)[0],
            'order_no' => $this->book->order($s->order_id)?->order_no, 'po_no' => $s->po_no, 'style' => $s->style, 'colour' => $s->colour, 'qty' => $s->qty, 'carton_qty' => $s->carton_qty,
            'ready_before' => $s->ready_before, 'remaining_after' => $s->remaining_after, 'ship_date' => $s->ship_date?->toDateString(), 'destination' => $s->destination,
            'mode' => $s->mode, 'vehicle_ref' => $s->vehicle_ref, 'remarks' => $s->remarks, 'status' => $s->status,
            'cancel_reason' => $s->cancel_reason, 'reverse_reason' => $s->reverse_reason, 'reversed_by' => $this->book->userName($s->reversed_by),
            'corrects_no' => $s->corrects_id ? ($nos[$s->corrects_id] ?? AsmShipment::query()->whereKey($s->corrects_id)->value('shipment_no')) : null,
            'created_by' => $this->book->userName($s->created_by), 'confirmed_by' => $this->book->userName($s->confirmed_by),
            'shipped_by' => $this->book->userName($s->shipped_by), 'shipped_at' => PackingBook::at($s->shipped_at), 'created_at' => PackingBook::at($s->created_at),
        ];
    }
}

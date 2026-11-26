<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support\Reports;

use App\Modules\Knitting\Models\KnitCard;
use App\Modules\Knitting\Models\KnitGreyReceiptRoll;
use App\Modules\Knitting\Models\KnitGreyReceipt;
use App\Modules\Knitting\Models\KnitProdOrder;
use App\Modules\Knitting\Models\KnitCcRequirement;
use App\Modules\Knitting\Models\KnitCcChallanLine;
use App\Modules\Knitting\Models\KnitCcChallan;
use App\Modules\Knitting\Models\KnitGreyLot;
use App\Modules\Knitting\Models\KnitGreyMove;
use App\Modules\Knitting\Models\KnitGreyTransfer;
use App\Modules\Knitting\Models\KnitGreyTransferLine;
use App\Modules\Knitting\Models\KnitRoll;
use App\Modules\Knitting\Support\KnitCollarCuff;
use Illuminate\Support\Collection;

/**
 * The grey fabric store's reports: stock on the To Date (in detail, by
 * buyer, dia and yarn, projection orders, sub-contract parties' fabric),
 * transfers, left-over fabric re-used, deliveries, and rolls the store has
 * not taken in.
 */
trait GreyReports
{
    /** @var Collection<int, array<string, mixed>>|null */
    private ?Collection $greyCache = null;

    /**
     * Every grey lot holding fabric on the To Date, with Production Type,
     * Style/Order#, Store and Buyer A/C applied: its order, construction,
     * rolls and kilograms, valued at its rate.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function greyRows(): Collection
    {
        return $this->greyCache ??= (function (): Collection {
            $q = $this->q;
            $lots = KnitGreyLot::query()
                ->when($q->get('prod_type'), fn ($w, $v) => $w->where('prod_type', $v))->when($q->get('order'), fn ($w, $v) => $w->where('order_id', $v))
                ->when($q->get('store'), fn ($w, $v) => $w->where('store_id', $v))->when($q->get('buyer'), fn ($w, $v) => $w->where('buyer_id', $v))->get();
            $moves = KnitGreyMove::query()->whereIn('lot_id', $lots->pluck('id'))->whereDate('move_date', '<=', $q->asOf())->groupBy('lot_id')
                ->selectRaw('lot_id, sum(qty) as q, sum(rolls) as r')->get()->keyBy('lot_id');
            $yarn = KnitCard::query()->whereIn('id', KnitRoll::query()->whereIn('id', $lots->pluck('roll_id')->filter())->select('card_id'))->get()->keyBy('id');
            $rollCard = KnitRoll::query()->whereIn('id', $lots->pluck('roll_id')->filter())->pluck('card_id', 'id');

            return $lots->map(function (KnitGreyLot $l) use ($q, $moves, $yarn, $rollCard): array {
                $m = $moves[$l->id] ?? null;
                $stock = round((float) ($m->q ?? 0), 3);
                $card = $l->roll_id ? ($yarn[$rollCard[$l->roll_id] ?? ''] ?? null) : null;
                $o = $q->order($l->order_id);

                return ['store' => $q->stores()[$l->store_id] ?? null, 'kind' => $l->kind, 'prod_type' => $l->prod_type ?: null, 'party' => $q->parties()[$l->party_id] ?? null,
                    'buyer' => $q->buyers()[$l->buyer_id] ?? null, 'order_no' => $o?->order_no, 'style' => $o ? ($o->work_style_no ?: $o->style_no) : null, 'colour' => $l->colour_name ?: ($q->colours()[$l->colour_id] ?? null),
                    'fabric' => $l->fabric ?: ($q->fabricTypes()[$l->fabric_type_id] ?? null), 'finish_dia' => $l->finish_dia, 'gsm' => $l->gsm ? (float) $l->gsm : null, 'part' => $l->part, 'roll_no' => $l->roll_no,
                    'batch_no' => $l->batch_no, 'yarn' => $card?->yarn_details, 'machine_dia' => $card ? trim(($card->machine_dia ?? '').($card->gauge ? ' x '.$card->gauge : '')) : null,
                    'rolls' => (int) ($m->r ?? 0), 'stock' => $stock, 'rate' => round((float) $l->rate, 2), 'value' => round($stock * (float) $l->rate, 2), 'first_date' => $l->first_date?->toDateString(),
                    'order_type' => $o?->order_type, 'lot_id' => $l->id, 'party_id' => $l->party_id, 'order_id' => $l->order_id];
            })->filter(fn ($r) => $r['stock'] != 0.0)->sortBy([['store', 'asc'], ['buyer', 'asc'], ['order_no', 'asc'], ['colour', 'asc']])->values();
        })();
    }

    private const GREY_STATS = ['rolls' => 'rolls', 'stock' => 'stock'];

    private function greyGroup(Collection $rows, array $keys): array
    {
        return $this->table([...$keys, 'entries:int', 'rolls:int', 'stock:number'], $this->group($rows, array_map(fn ($k) => explode(':', $k)[0], $keys), ['rolls', 'stock']), self::GREY_STATS);
    }

    private function greyFabricStockSummary(): array
    {
        return $this->greyGroup($this->greyRows(), ['buyer', 'order_no', 'colour', 'fabric']);
    }

    private function projectionOrderGreyStockSummary(): array
    {
        return $this->greyGroup($this->greyRows()->where('order_type', 'PROJECTION')->values(), ['buyer', 'order_no', 'colour', 'fabric']);
    }

    private function greyFabricStockDetail(): array
    {
        return $this->table(['store', 'kind:status', 'prod_type:status', 'buyer', 'order_no', 'style', 'colour', 'fabric', 'finish_dia', 'gsm:number', 'part', 'roll_no', 'batch_no', 'first_date:date',
            'rolls:int', 'stock:number', 'rate:money', 'value:money'], $this->greyRows(), self::GREY_STATS);
    }

    private function buyerAcWiseGreyFabricStock(): array
    {
        return $this->greyGroup($this->greyRows(), ['buyer']);
    }

    private function greyFabricStockByDiaAndYarn(): array
    {
        return $this->greyGroup($this->greyRows(), ['finish_dia', 'machine_dia', 'yarn', 'fabric']);
    }

    private function projectionOrderGreyFabricStockByFinDia(): array
    {
        return $this->greyGroup($this->greyRows()->where('order_type', 'PROJECTION')->values(), ['order_no', 'finish_dia', 'fabric', 'colour']);
    }

    private function orderAndColorWiseRollAvailableInGreyStoreScInHouse(): array
    {
        return $this->greyGroup($this->greyRows()->where('kind', 'SC_IN_HOUSE')->values(), ['party', 'order_no', 'colour', 'fabric']);
    }

    /** Sample fabric in the grey store: what came in, went out, and is left. */
    private function sampleGreyRows(): Collection
    {
        $q = $this->q;
        $lots = KnitGreyLot::query()->where('prod_type', 'SAMPLE')->get()->keyBy('id');
        $moves = $q->window(KnitGreyMove::query()->whereIn('lot_id', $lots->keys()), 'move_date')->orderBy('move_date')->get();

        return $moves->map(function (KnitGreyMove $m) use ($q, $lots): array {
            $l = $lots[$m->lot_id];

            return ['move_date' => $m->move_date->toDateString(), 'doc_no' => $m->doc_no, 'kind' => $m->kind, 'store' => $q->stores()[$m->store_id] ?? null, 'buyer' => $q->buyers()[$l->buyer_id] ?? null,
                'order_no' => $q->orderNo($l->order_id), 'colour' => $l->colour_name, 'fabric' => $l->fabric, 'received' => $m->qty > 0 ? self::r3($m->qty) : 0.0,
                'delivered' => $m->qty < 0 ? round(abs((float) $m->qty), 3) : 0.0, 'rolls' => (int) $m->rolls, 'stock' => self::r3($m->qty)];
        });
    }

    private function sampleGreyFabricReceiveAndDeliveryStatus(): array
    {
        $rows = $this->group($this->sampleGreyRows(), ['buyer', 'order_no', 'colour', 'fabric'], ['received', 'delivered', 'rolls', 'stock']);

        return $this->table(['buyer', 'order_no', 'colour', 'fabric', 'entries:int', 'received:number', 'delivered:number', 'rolls:int', 'stock:number'], $rows, self::GREY_STATS);
    }

    private function sampleGreyFabricReceiveAndDeliveryStatusTest(): array
    {
        return $this->sampleGreyFabricReceiveAndDeliveryStatus();
    }

    private function dateWiseSampleGreyFabricReceiveAndDelivery(): array
    {
        return $this->table(['move_date:date', 'doc_no', 'kind:status', 'store', 'buyer', 'order_no', 'colour', 'fabric', 'received:number', 'delivered:number', 'rolls:int', 'stock:number'],
            $this->sampleGreyRows(), self::GREY_STATS);
    }

    // ── Transfers and left-overs ──────────────────────────────────────────

    /** @param list<string>|null $kinds */
    private function transferRows(?array $kinds = null): Collection
    {
        $q = $this->q;
        $docs = $q->window(KnitGreyTransfer::query(), 'transfer_date')->where('status', '!=', 'CANCELLED')->when($kinds, fn ($w) => $w->whereIn('kind', $kinds))
            ->when($q->get('order'), fn ($w, $v) => $w->where(fn ($x) => $x->where('order_id', $v)->orWhere('to_order_id', $v)))->get()->keyBy('id');
        $lots = KnitGreyLot::query()->whereIn('id', KnitGreyTransferLine::query()->whereIn('transfer_id', $docs->keys())->select('lot_id'))->get()->keyBy('id');

        return KnitGreyTransferLine::query()->whereIn('transfer_id', $docs->keys())->orderBy('sort_order')->get()->map(function (KnitGreyTransferLine $l) use ($q, $docs, $lots): array {
            $d = $docs[$l->transfer_id];
            $lot = $lots[$l->lot_id] ?? null;

            return ['transfer_date' => $d->transfer_date->toDateString(), 'transfer_no' => $d->transfer_no, 'kind' => $d->kind, 'trans_type' => $d->trans_type, 'buyer' => $q->buyers()[$d->buyer_id] ?? null,
                'from_order' => $q->orderNo($d->order_id ?? $lot?->order_id), 'to_order' => $q->orderNo($d->to_order_id), 'from_store' => $q->stores()[$d->from_store_id] ?? null,
                'to_store' => $q->stores()[$d->to_store_id] ?? null, 'colour' => $l->colour_name ?: $lot?->colour_name, 'fabric' => $l->fabric ?: $lot?->fabric, 'roll_no' => $l->roll_no,
                'rolls' => (int) $l->rolls, 'qty' => self::r3($l->qty), 'rate' => round((float) ($lot?->rate ?? 0), 2), 'value' => round((float) $l->qty * (float) ($lot?->rate ?? 0), 2), 'status' => $d->status];
        })->sortBy([['transfer_date', 'asc'], ['transfer_no', 'asc']])->values();
    }

    private function orderAndColorWiseGreyFabricTransferStatement(): array
    {
        return $this->table(['transfer_date:date', 'transfer_no', 'kind:status', 'trans_type:status', 'buyer', 'from_order', 'to_order', 'from_store', 'to_store', 'colour', 'fabric', 'rolls:int', 'qty:number', 'status:status'],
            $this->transferRows(), ['rolls' => 'rolls', 'transferred' => 'qty']);
    }

    /** Left-over fabric: what was put aside (to left-over) and what was taken up again (re-use). */
    private function leftOverRows(): Collection
    {
        return $this->transferRows(['TO_LEFTOVER', 'BATCH_TO_LEFTOVER', 'REUSE'])
            ->map(fn ($r) => $r + ['to_leftover' => $r['kind'] === 'REUSE' ? 0.0 : $r['qty'], 'reused' => $r['kind'] === 'REUSE' ? $r['qty'] : 0.0]);
    }

    private function utilizationOfLeftOverGreyFabricTransferAndReUse(): array
    {
        return $this->table(['transfer_date:date', 'transfer_no', 'kind:status', 'buyer', 'from_order', 'to_order', 'colour', 'fabric', 'rolls:int', 'qty:number', 'to_leftover:number', 'reused:number', 'status:status'],
            $this->leftOverRows(), ['moved' => 'qty']);
    }

    private function utilizationOfLeftOverGreyFabricTransferAndReUseSummery(): array
    {
        $rows = $this->group($this->leftOverRows(), ['buyer', 'from_order', 'fabric'], ['rolls', 'qty', 'to_leftover', 'reused'])->map(fn ($r) => $r + ['reuse_pct' => self::pct($r['reused'], $r['to_leftover'])]);

        return $this->table(['buyer', 'from_order', 'fabric', 'entries:int', 'rolls:int', 'qty:number', 'to_leftover:number', 'reused:number', 'reuse_pct:pct'], $rows, ['moved' => 'qty']);
    }

    private function utilizationOfLeftOverGreyFabricReUseWithValue(): array
    {
        return $this->table(['transfer_date:date', 'transfer_no', 'buyer', 'from_order', 'to_order', 'colour', 'fabric', 'rolls:int', 'qty:number', 'rate:money', 'value:money'],
            $this->transferRows(['REUSE']), ['moved' => 'qty', 'value' => 'value']);
    }

    /** Brickland draws it as the order-and-colour knitting statement: booked, knitted, passed, in store, delivered, left. */
    private function orderAndColorWiseFabricDeliveryStatus(): array
    {
        return $this->orderAndColorWiseKnittingStatementNew();
    }

    /** Rolls knitted in the window that no grey receipt has taken in. */
    private function rollListWithoutGreyFabricReceive(): array
    {
        $rows = $this->rolls()->filter(fn ($r) => $r['store_receipt'] === null)->values();

        return $this->table(['prod_date:date', 'kc_no', 'roll_no', 'machine', 'shift', 'buyer', 'order_no', 'colour', 'fabric', 'gross:number', 'deduct:number', 'net:number', 'rolls:int', 'qc_status:status', 'where:status'],
            $rows, ['rolls' => 'rolls', 'stock' => 'net']);
    }

    /** Sub-contract pre-treatment belongs to the dye house, not built yet: an empty ledger. */
    private function scPreTreatmentLedger(): array
    {
        return $this->table(['challan_date:date', 'challan_no', 'party', 'buyer', 'order_no', 'colour', 'fabric', 'rolls:int', 'sent:number', 'returned:number', 'balance:number'], [], ['rolls' => 'rolls', 'returned' => 'returned']);
    }

    private function scPreTreatmentLedgerForAll(): array
    {
        return $this->scPreTreatmentLedger();
    }

    // ── Grey Fabric Stock Position (its own screen) ───────────────────────

    /**
     * Grey fabric (and collar/cuff) on its way: per production order — and per
     * collar/cuff requirement — what the store took in on the To Date (Today
     * Rcv.), everything taken in over the window (Total Rcv.), the order's
     * target, and Due for Prod. — the target less what the floor has knitted.
     * Is G/F Fab? picks fabric or collar/cuff; Order Type, Buyer A/C,
     * Style/Order# and Fab. Color narrow it.
     */
    public function greyPosition(): array
    {
        $q = $this->q;
        $today = $q->asOf();
        $rows = collect();
        if ($q->get('part') !== 'COLLAR_CUFF') {
            $orders = KnitProdOrder::query()->where('status', '!=', 'CANCELLED')->when($q->get('prod_type'), fn ($w, $v) => $w->where('order_kind', $v))
                ->when($q->get('buyer'), fn ($w, $v) => $w->where('buyer_id', $v))->when($q->get('order'), fn ($w, $v) => $w->where('order_id', $v))
                ->when($q->get('colour'), fn ($w, $v) => $w->where('colour_id', $v))->orderBy('po_no')->get();
            $cards = KnitCard::query()->whereIn('prod_order_id', $orders->pluck('id'))->get();
            $rolls = KnitRoll::query()->whereIn('card_id', $cards->pluck('id'))->get();
            $cardOrder = $cards->pluck('prod_order_id', 'id');
            $notes = KnitGreyReceipt::query()->where('status', '!=', 'CANCELLED')->get()->keyBy('id');
            $taken = KnitGreyReceiptRoll::query()->whereIn('roll_id', $rolls->pluck('id'))->get()
                ->map(fn ($x) => ['po' => $cardOrder[$rolls->firstWhere('id', $x->roll_id)?->card_id] ?? null, 'date' => $notes[$x->receipt_id]?->receive_date?->toDateString(), 'qty' => (float) $x->weight])
                ->filter(fn ($x) => $x['date'] !== null)->groupBy('po');
            $knitted = $rolls->where('quality', '!=', 'REJECTED')->groupBy(fn ($r) => $cardOrder[$r->card_id] ?? '')->map(fn ($g) => (float) $g->sum(fn ($r) => (float) $r->weight - (float) $r->deducted));
            foreach ($orders as $po) {
                $mine = collect($taken[$po->id] ?? []);
                $todays = $mine->where('date', $today);
                $window = $mine->filter(fn ($x) => $q->inWindow($x['date']) || ($q->from() === null && $x['date'] <= $today));
                $card = $cards->firstWhere('prod_order_id', $po->id);
                $o = $q->order($po->order_id);
                $rows->push(['type' => 'FABRIC', 'buyer' => $q->buyers()[$po->buyer_id] ?? null, 'order_no' => $o?->order_no, 'style' => $o ? ($o->work_style_no ?: $o->style_no) : null,
                    'colour' => $po->colour_name, 'gf_fab' => true, 'fabric' => $po->fabric_desc, 'finish_dia' => $po->finish_dia,
                    'machine_dia' => $card ? trim(($card->machine_dia ?? '').($card->gauge ? ' x '.$card->gauge : '')) ?: null : null, 'yarn' => $card?->yarn_details,
                    'today_rolls' => $todays->count(), 'today_qty' => round((float) $todays->sum('qty'), 3), 'target' => round((float) $po->req_qty, 3),
                    'total_rcv' => round((float) $window->sum('qty'), 3), 'due' => round((float) $po->req_qty - (float) ($knitted[$po->id] ?? 0), 3)]);
            }
        }
        if ($q->get('part') !== 'GREY') {
            $kind = match ($q->get('prod_type')) { 'SAMPLE' => 'SAMPLE', null => null, default => 'BULK' };
            $reqs = KnitCcRequirement::query()->when($kind, fn ($w, $v) => $w->where('req_type', $v))->when($q->get('buyer'), fn ($w, $v) => $w->where('buyer_id', $v))
                ->when($q->get('order'), fn ($w, $v) => $w->where('order_id', $v))->when($q->get('colour'), fn ($w, $v) => $w->where('colour_id', $v))->orderBy('req_no')->get();
            $made = KnitCollarCuff::produced($reqs->pluck('id'));
            $counted = KnitCcChallan::query()->where('status', 'RECEIVED')->get()->keyBy('id');
            $lines = KnitCcChallanLine::query()->whereIn('challan_id', $counted->keys())->whereIn('requirement_id', $reqs->pluck('id'))->get()->groupBy('requirement_id');
            foreach ($reqs as $r) {
                $mine = collect($lines[$r->id] ?? [])->map(fn ($l) => ['date' => ($counted[$l->challan_id]->rcv_date ?? $counted[$l->challan_id]->challan_date)?->toDateString(), 'qty' => (float) $l->qty]);
                $todays = $mine->where('date', $today);
                $window = $mine->filter(fn ($x) => $q->inWindow($x['date']) || ($q->from() === null && $x['date'] <= $today));
                $target = (float) $r->req_in_house + (float) $r->req_sc;
                $m = $made[$r->id] ?? ['in' => 0.0, 'sc' => 0.0];
                $o = $q->order($r->order_id);
                $rows->push(['type' => $r->item, 'buyer' => $q->buyers()[$r->buyer_id] ?? null, 'order_no' => $o?->order_no, 'style' => $r->work_style_no ?: ($o ? ($o->work_style_no ?: $o->style_no) : null),
                    'colour' => $q->colours()[$r->colour_id] ?? null, 'gf_fab' => false, 'fabric' => $r->description, 'finish_dia' => null, 'machine_dia' => $r->gauge, 'yarn' => null,
                    'today_rolls' => $todays->count(), 'today_qty' => round((float) $todays->sum('qty'), 3), 'target' => round($target, 3), 'total_rcv' => round((float) $window->sum('qty'), 3),
                    'due' => round($target - $m['in'] - $m['sc'], 3)]);
            }
        }

        return $this->table(['type:status', 'buyer', 'order_no', 'style', 'colour', 'gf_fab:yesno', 'fabric', 'finish_dia', 'machine_dia', 'yarn', 'today_rolls:int@today_rcv', 'today_qty:number@today_rcv',
            'target:number', 'total_rcv:number', 'due:number'], $rows, ['today_rcv' => 'today_qty', 'total_rcv' => 'total_rcv', 'due' => 'due']);
    }
}

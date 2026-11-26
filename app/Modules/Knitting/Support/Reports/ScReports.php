<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support\Reports;

use App\Modules\Knitting\Models\KnitGreyLot;
use App\Modules\Knitting\Models\KnitScBill;
use App\Modules\Knitting\Models\KnitScBillLine;
use App\Modules\Knitting\Models\KnitScDelivery;
use App\Modules\Knitting\Models\KnitScDeliveryLine;
use App\Modules\Knitting\Models\KnitScGreyReceipt;
use App\Modules\Knitting\Models\KnitScGreyReceiptLine;
use App\Modules\Knitting\Models\KnitScGreyReturn;
use App\Modules\Knitting\Models\KnitScGreyReturnLine;
use App\Modules\Knitting\Models\KnitScProgram;
use App\Modules\Knitting\Models\KnitScProgramLine;
use App\Modules\Knitting\Models\KnitScReceipt;
use App\Modules\Knitting\Models\KnitScReceiptFabric;
use App\Modules\Knitting\Models\KnitScYarnChallan;
use App\Modules\Knitting\Models\KnitScYarnChallanLine;
use App\Modules\Knitting\Models\KnitYarnTestResult;
use App\Modules\Knitting\Models\KnitYdDyedReceipt;
use App\Modules\Knitting\Models\KnitYdDyedReceiptLine;
use App\Modules\Knitting\Models\KnitYdGreyTransfer;
use App\Modules\Knitting\Models\KnitYdGreyTransferLine;
use App\Modules\Knitting\Models\YarnLot;
use App\Modules\Knitting\Models\YarnPartyChallan;
use App\Modules\Knitting\Models\YarnPartyChallanLine;
use App\Modules\Knitting\Support\KnitProduction;
use Illuminate\Support\Collection;

/**
 * Sub-contract reports. Out-house: programmes placed with outside knitters,
 * yarn sent them, grey fabric back, rejects, bills and their ledgers; yarn
 * sent to dye houses and dyed yarn back. In-house: parties' orders knitted
 * here, their yarn received, fabric delivered, returns and bills.
 */
trait ScReports
{
    /** Out-house programme lines placed in the window (cancelled and rejected aside). @param list<string>|null $kinds */
    private function scProgramRows(?array $kinds = null): Collection
    {
        $q = $this->q;
        $programs = $q->window(KnitScProgram::query(), 'po_date')->whereNotIn('status', ['CANCELLED', 'REJECTED'])->when($kinds, fn ($w) => $w->whereIn('kind', $kinds))
            ->when($q->get('order'), fn ($w, $v) => $w->where('order_id', $v))->get()->keyBy('id');
        $categories = collect(KnitProduction::categories())->pluck('label', 'value');

        return KnitScProgramLine::query()->whereIn('program_id', $programs->keys())->orderBy('sort_order')->get()->map(function (KnitScProgramLine $l) use ($q, $programs, $categories): array {
            $p = $programs[$l->program_id];
            $o = $q->order($p->order_id);
            $qty = (float) $l->qty;
            $got = (float) $l->received_qty;

            return ['po_date' => $p->po_date->toDateString(), 'program_no' => $p->program_no, 'party' => $q->parties()[$p->party_id] ?? null, 'kind' => $p->kind, 'program_type' => $p->program_type,
                'buyer' => $q->buyers()[$p->buyer_id] ?? null, 'order_no' => $o ? trim(($o->work_style_no ?: $o->style_no ?: $o->job_no).' / '.$o->order_no, ' /') : $p->style_ref, 'work_style' => $p->work_style_no,
                'category' => $categories[$p->category_code] ?? $p->category_code, 'fabric' => $l->fabric, 'colour' => $q->colours()[$l->colour_id] ?? $l->colour_group,
                'machine_dia' => trim(($l->machine_dia ?? '').($l->gauge ? ' x '.$l->gauge : '')) ?: null, 'finish_dia' => $l->finish_dia, 'gsm' => $l->gsm, 'target_date' => $p->target_date?->toDateString(),
                'qty' => round($qty, 3), 'received' => round($got, 3), 'balance' => round($qty - $got, 3), 'achieved' => self::pct($got, $qty), 'rate' => round((float) $l->rate, 2),
                'value' => round($qty * (float) $l->rate, 2), 'status' => $p->status, 'party_id' => $p->party_id, 'program_id' => $p->id];
        })->values();
    }

    private const SC_STATS = ['placed' => 'qty', 'received' => 'received', 'outstanding' => 'balance'];

    /** Programme lines summed over `keys`, the rate their value over their kilograms. */
    private function scGroup(array $keys, ?array $kinds = null): Collection
    {
        return $this->group($this->scProgramRows($kinds), $keys, ['qty', 'received', 'balance', 'value'], fn (array $r) => $r + [
            'rate' => $r['qty'] > 0 ? round($r['value'] / $r['qty'], 2) : 0.0, 'value' => round($r['value'], 2), 'achieved' => self::pct($r['received'], $r['qty']),
        ]);
    }

    private const SC_SUMS = ['entries:int', 'qty:number', 'received:number', 'balance:number', 'rate:money', 'value:money', 'achieved:pct'];

    private function scProgramCumContractOutHouse(): array
    {
        return $this->table(['po_date:date', 'program_no', 'party', 'program_type:status', 'buyer', 'order_no', 'work_style', 'category', 'fabric', 'colour', 'machine_dia', 'finish_dia', 'gsm', 'target_date:date',
            'qty:number', 'received:number', 'balance:number', 'achieved:pct', 'rate:money', 'value:money', 'status:status'], $this->scProgramRows(), self::SC_STATS);
    }

    private function partyWiseOrderStatementScOuthouseKnitting(): array
    {
        return $this->table(['party', 'order_no', ...self::SC_SUMS], $this->scGroup(['party', 'order_no']), self::SC_STATS);
    }

    private function partyWiseKnittingPerformanceSummaryScOutHouse(): array
    {
        return $this->table(['party', ...self::SC_SUMS], $this->scGroup(['party']), self::SC_STATS);
    }

    private function partyWiseKnittingPerformanceByProgramScOutHouse(): array
    {
        return $this->table(['party', 'program_no', ...self::SC_SUMS], $this->scGroup(['party', 'program_no']), self::SC_STATS);
    }

    private function scPartyWiseRate(): array
    {
        return $this->table(['party', 'fabric', ...self::SC_SUMS], $this->scGroup(['party', 'fabric']), self::SC_STATS);
    }

    /** Grey fabric received back from outside knitters (out-house receipts), per party. @return Collection<string, float> */
    private function scGreyIn(): Collection
    {
        $docs = $this->q->window(KnitScGreyReceipt::query(), 'rcv_date')->whereNotIn('status', ['DRAFT', 'CANCELLED'])->get()->keyBy('id');

        return KnitScGreyReceiptLine::query()->whereIn('receipt_id', $docs->keys())->get()->groupBy(fn ($l) => $docs[$l->receipt_id]->party_id)->map(fn ($g) => (float) $g->sum('qty'));
    }

    /** Billed kilograms and amount per programme or party receipt (cancelled and draft bills aside). */
    private function billedBy(string $column): Collection
    {
        $bills = KnitScBill::query()->whereNotIn('status', ['DRAFT', 'CANCELLED'])->select('id');

        return KnitScBillLine::query()->whereIn('bill_id', $bills)->whereNotNull($column)->get()->groupBy($column)->map(fn ($g) => [(float) $g->sum('qty'), (float) $g->sum('amount')]);
    }

    /** Per party: placed, received, with the party, billed, unbilled and the bill amount. @param list<string> $kinds */
    private function scLedger(array $kinds): array
    {
        $billed = $this->billedBy('sc_program_id');
        $rows = $this->scProgramRows($kinds)->groupBy('party')->map(function (Collection $g, string $party) use ($billed): array {
            $placed = (float) $g->sum('qty');
            $got = (float) $g->sum('received');
            $ids = $g->pluck('program_id')->unique();
            $kg = (float) $ids->sum(fn ($id) => $billed[$id][0] ?? 0);
            $amount = (float) $ids->sum(fn ($id) => $billed[$id][1] ?? 0);

            return ['party' => $party ?: null, 'placed' => round($placed, 3), 'received' => round($got, 3), 'with_party' => round($placed - $got, 3), 'billed' => round($kg, 3),
                'unbilled' => round(max(0, $got - $kg), 3), 'amount' => round($amount, 2), 'achieved' => self::pct($got, $placed)];
        })->values();

        return $this->table(['party', 'placed:number', 'received:number', 'with_party:number', 'billed:number', 'unbilled:number', 'amount:money', 'achieved:pct'], $rows,
            ['received' => 'received', 'unbilled' => 'unbilled', 'billed' => 'amount']);
    }

    private function partyWiseLedgerScOuthouseKnitting(): array
    {
        return $this->scLedger(['BULK_SOLID', 'SAMPLE_SOLID']);
    }

    private function partyWiseLedgerScOuthouseYd(): array
    {
        return $this->scLedger(['PARTY_YD']);
    }

    /** Yarn sent to outside knitters on party challans against grey fabric they sent back. */
    private function partyWiseYarnBalanceScOuthouseKnitting(): array
    {
        $q = $this->q;
        $challans = $q->window(YarnPartyChallan::query(), 'challan_date')->whereIn('status', ['ISSUED', 'ACCEPTED'])->get()->keyBy('id');
        $out = YarnPartyChallanLine::query()->whereIn('challan_id', $challans->keys())->get()->groupBy(fn ($l) => $challans[$l->challan_id]->party_id)->map(fn ($g) => (float) $g->sum('qty'));
        $in = $this->scGreyIn();
        $parties = $this->scProgramRows(['BULK_SOLID', 'SAMPLE_SOLID'])->pluck('party_id')->merge($out->keys())->merge($in->keys())->filter()->unique();
        $rows = $parties->map(fn ($id) => ['party' => $q->parties()[$id] ?? null, 'yarn_out' => round((float) ($out[$id] ?? 0), 3), 'fabric_in' => round((float) ($in[$id] ?? 0), 3),
            'with_party' => round((float) ($out[$id] ?? 0) - (float) ($in[$id] ?? 0), 3), 'conversion' => self::pct((float) ($in[$id] ?? 0), (float) ($out[$id] ?? 0)) ?? 0.0])->sortBy('party')->values();

        return $this->table(['party', 'yarn_out:number', 'fabric_in:number', 'with_party:number', 'conversion:pct'], $rows, ['yarn_out' => 'yarn_out', 'with_party' => 'with_party']);
    }

    private function fabricRejectSummeryScOutHouse(): array
    {
        $q = $this->q;
        $in = $this->scGreyIn();
        $returns = $q->window(KnitScGreyReturn::query(), 'challan_date')->where('kind', 'OUT_HOUSE')->whereNotIn('status', ['DRAFT', 'CANCELLED'])->get()->keyBy('id');
        $back = KnitScGreyReturnLine::query()->whereIn('return_id', $returns->keys())->get()->groupBy(fn ($l) => $returns[$l->return_id]->party_id)->map(fn ($g) => (float) $g->sum('qty'));
        $rows = $in->keys()->merge($back->keys())->unique()->map(fn ($id) => ['party' => $q->parties()[$id] ?? null, 'received' => round((float) ($in[$id] ?? 0), 3),
            'rejected' => round((float) ($back[$id] ?? 0), 3), 'reject_pct' => self::pct((float) ($back[$id] ?? 0), (float) ($in[$id] ?? 0))])->sortBy('party')->values();

        return $this->table(['party', 'received:number', 'rejected:number', 'reject_pct:pct'], $rows, ['received' => 'received', 'rejected' => 'rejected']);
    }

    private function scBillStatement(string $kind): array
    {
        $q = $this->q;
        $bills = $q->window(KnitScBill::query(), 'bill_date')->where('kind', $kind)->where('status', '!=', 'CANCELLED')->orderBy('bill_date')->get();
        $kg = KnitScBillLine::query()->whereIn('bill_id', $bills->pluck('id'))->groupBy('bill_id')->selectRaw('bill_id, sum(qty) as q')->pluck('q', 'bill_id');
        $rows = $bills->map(fn (KnitScBill $b) => ['bill_date' => $b->bill_date->toDateString(), 'bill_no' => $b->bill_no, 'party' => $q->parties()[$b->party_id] ?? null, 'party_bill_no' => $b->party_bill_no,
            'billed' => round((float) ($kg[$b->id] ?? 0), 3), 'gross' => round($b->gross, 2), 'deduction' => round($b->deduction, 2), 'vat' => round($b->vat, 2), 'amount' => round($b->net, 2), 'status' => $b->status]);

        return $this->table(['bill_date:date', 'bill_no', 'party', 'party_bill_no', 'billed:number', 'gross:money', 'deduction:money', 'vat:money', 'amount:money', 'status:status'], $rows, ['billed' => 'billed', 'amount' => 'amount']);
    }

    private function partyWiseBillStatementScOutHouse(): array
    {
        return $this->scBillStatement('OUT_HOUSE');
    }

    private function partyWiseBillStatementScInHouse(): array
    {
        return $this->scBillStatement('IN_HOUSE');
    }

    // ── Yarn dyeing out-house ─────────────────────────────────────────────

    /** Greige yarn sent to dye houses and dyed yarn received back, one row each. */
    private function ydRows(): Collection
    {
        $q = $this->q;
        $out = $q->window(KnitYdGreyTransfer::query(), 'challan_date')->whereIn('status', ['ISSUED', 'FINALIZED'])->get()->keyBy('id');
        $back = $q->window(KnitYdDyedReceipt::query(), 'challan_date')->whereIn('status', ['ISSUED', 'FINALIZED'])->get()->keyBy('id');
        $lots = YarnLot::query()->whereIn('id', KnitYdGreyTransferLine::query()->whereIn('transfer_id', $out->keys())->select('lot_id'))->get()->keyBy('id');
        $rows = KnitYdGreyTransferLine::query()->whereIn('transfer_id', $out->keys())->get()->map(function ($l) use ($q, $out, $lots): array {
            $d = $out[$l->transfer_id];
            $lot = $lots[$l->lot_id] ?? null;

            return ['party' => $q->parties()[$d->party_id] ?? null, 'order_no' => $q->orderNo($lot?->order_id), 'buyer' => $q->buyers()[$lot?->buyer_id] ?? null, 'greige_out' => self::r3($l->qty), 'dyed_back' => 0.0];
        });

        return $rows->merge(KnitYdDyedReceiptLine::query()->whereIn('receipt_id', $back->keys())->get()->map(function ($l) use ($q, $back): array {
            $d = $back[$l->receipt_id];

            return ['party' => $q->parties()[$d->party_id] ?? null, 'order_no' => $q->orderNo($d->order_id), 'buyer' => $q->buyers()[$d->buyer_id] ?? null, 'greige_out' => 0.0, 'dyed_back' => self::r3($l->qty)];
        }))->values();
    }

    private function ydBy(array $keys): array
    {
        $rows = $this->group($this->ydRows(), $keys, ['greige_out', 'dyed_back'], fn ($r) => $r + ['with_party' => round($r['greige_out'] - $r['dyed_back'], 3)]);

        return $this->table([...$keys, 'entries:int', 'greige_out:number', 'dyed_back:number', 'with_party:number'], $rows->sortBy($keys[0])->values(), ['greige_out' => 'greige_out', 'dyed_back' => 'dyed_back', 'with_party' => 'with_party']);
    }

    private function partyAndOrderWiseYdRegisterScOuthouse(): array
    {
        return $this->ydBy(['party', 'buyer', 'order_no']);
    }

    private function partyWiseYarnBalanceScOuthouseYd(): array
    {
        return $this->ydBy(['party']);
    }

    private function orderWiseYdLedger(): array
    {
        return $this->ydBy(['buyer', 'order_no']);
    }

    private function supplierWiseYarnTestResult(): array
    {
        $q = $this->q;
        $rows = $q->window(KnitYarnTestResult::query(), 'test_date')->get()->groupBy('supplier_id')->map(fn (Collection $g, $id) => [
            'supplier' => $q->parties()[$id] ?? null, 'tests' => $g->count(), 'passed' => $g->where('result', 'PASSED')->count(), 'failed' => $g->where('result', 'FAILED')->count(),
            'conditional' => $g->where('result', 'CONDITIONAL')->count(), 'pending' => $g->where('result', 'PENDING')->count(),
            'pass_pct' => self::pct((float) $g->where('result', 'PASSED')->count(), (float) $g->whereIn('result', ['PASSED', 'FAILED', 'CONDITIONAL'])->count()),
        ])->sortBy('supplier')->values();

        return $this->table(['supplier', 'tests:int', 'passed:int', 'failed:int', 'conditional:int', 'pending:int', 'pass_pct:pct'], $rows, ['passed' => 'passed', 'failed' => 'failed']);
    }

    // ── In-house sub-contract ─────────────────────────────────────────────

    /**
     * Parties' orders knitted here, per party and order: ordered, the party's
     * yarn received, fabric delivered, returned (rejected) and still to deliver.
     */
    private function scInRows(): Collection
    {
        $q = $this->q;
        $receipts = $q->window(KnitScReceipt::query(), 'prog_date')->where('status', '!=', 'CANCELLED')->get()->keyBy('id');
        $fabric = KnitScReceiptFabric::query()->whereIn('receipt_id', $receipts->keys())->get();
        $yarnDocs = KnitScYarnChallan::query()->whereIn('receipt_id', $receipts->keys())->where('status', 'ACCEPTED')->get()->keyBy('id');
        $yarn = KnitScYarnChallanLine::query()->whereIn('challan_id', $yarnDocs->keys())->get()->groupBy(fn ($l) => $yarnDocs[$l->challan_id]->receipt_id)->map(fn ($g) => (float) $g->sum('qty'));
        $parties = $receipts->pluck('party_id')->unique();
        $deliveries = KnitScDelivery::query()->whereIn('party_id', $parties)->where('status', 'ISSUED')->get()->keyBy('id');
        $delLines = KnitScDeliveryLine::query()->whereIn('delivery_id', $deliveries->keys())->where('section', '!=', 'YARN')->get();
        $lotOrders = KnitGreyLot::query()->whereIn('id', $delLines->pluck('lot_id')->filter())->pluck('order_id', 'id');
        $delivered = $delLines->groupBy(fn ($l) => $deliveries[$l->delivery_id]->party_id.'|'.($lotOrders[$l->lot_id] ?? ''))->map(fn ($g) => (float) $g->sum('qty'));
        $returns = KnitScGreyReturn::query()->where('kind', 'IN_HOUSE')->whereIn('sc_receipt_id', $receipts->keys())->whereNotIn('status', ['DRAFT', 'CANCELLED'])->get()->keyBy('id');
        $back = KnitScGreyReturnLine::query()->whereIn('return_id', $returns->keys())->get()->groupBy(fn ($l) => $returns[$l->return_id]->sc_receipt_id)->map(fn ($g) => (float) $g->sum('qty'));

        return $fabric->groupBy(fn ($f) => $receipts[$f->receipt_id]->party_id.'|'.$f->order_id)->map(function (Collection $g, string $key) use ($q, $receipts, $yarn, $delivered, $back): array {
            [$party, $order] = explode('|', $key) + [1 => ''];
            $ids = $g->pluck('receipt_id')->unique();
            $ordered = (float) $g->sum('qty');
            $out = (float) ($delivered[$key] ?? 0);

            return ['party' => $q->parties()[$party] ?? null, 'order_no' => $q->orderNo($order ?: null) ?? $g->first()->party_ref, 'programs' => $ids->map(fn ($id) => $receipts[$id]->program_no)->implode(', '),
                'ordered' => round($ordered, 3), 'yarn_received' => round((float) $ids->sum(fn ($id) => $yarn[$id] ?? 0), 3), 'delivered' => round($out, 3),
                'rejected' => round((float) $ids->sum(fn ($id) => $back[$id] ?? 0), 3), 'balance' => round($ordered - $out, 3), 'party_id' => $party, 'receipt_ids' => $ids->all()];
        })->sortBy('party')->values();
    }

    private function partyWiseOrderStatementScInHouse(): array
    {
        return $this->table(['party', 'order_no', 'programs', 'ordered:number', 'yarn_received:number', 'delivered:number', 'rejected:number', 'balance:number'], $this->scInRows(),
            ['received' => 'yarn_received', 'rejected' => 'rejected']);
    }

    /** In-house parties' standing in our store: their yarn in, fabric out, returns, and their fabric still held here. */
    private function partyWiseStockSummeryScInHouse(): array
    {
        $stock = $this->greyRows()->where('kind', 'SC_IN_HOUSE')->groupBy('party_id')->map(fn ($g) => [(int) $g->sum('rolls'), (float) $g->sum('stock')]);
        $rows = $this->scInRows()->groupBy('party_id')->map(fn (Collection $g, $id) => ['party' => $g->first()['party'], 'yarn_received' => round((float) $g->sum('yarn_received'), 3),
            'delivered' => round((float) $g->sum('delivered'), 3), 'rejected' => round((float) $g->sum('rejected'), 3), 'rolls' => $stock[$id][0] ?? 0, 'stock' => round((float) ($stock[$id][1] ?? 0), 3)])->values();

        return $this->table(['party', 'yarn_received:number', 'delivered:number', 'rejected:number', 'rolls:int', 'stock:number'], $rows, ['received' => 'yarn_received', 'rejected' => 'rejected']);
    }

    /** Per in-house party: ordered (placed), delivered, still to deliver, billed and unbilled. */
    private function scInLedger(): array
    {
        $billed = $this->billedBy('sc_receipt_id');
        $rows = $this->scInRows()->groupBy('party_id')->map(function (Collection $g) use ($billed): array {
            $placed = (float) $g->sum('ordered');
            $out = (float) $g->sum('delivered');
            $ids = $g->pluck('receipt_ids')->flatten()->unique();
            $kg = (float) $ids->sum(fn ($id) => $billed[$id][0] ?? 0);

            return ['party' => $g->first()['party'], 'placed' => round($placed, 3), 'received' => round($out, 3), 'with_party' => round($placed - $out, 3), 'billed' => round($kg, 3),
                'unbilled' => round(max(0, $out - $kg), 3), 'amount' => round((float) $ids->sum(fn ($id) => $billed[$id][1] ?? 0), 2), 'achieved' => self::pct($out, $placed)];
        })->values();

        return $this->table(['party', 'placed:number', 'received:number', 'with_party:number', 'billed:number', 'unbilled:number', 'amount:money', 'achieved:pct'], $rows,
            ['received' => 'received', 'unbilled' => 'unbilled', 'billed' => 'amount']);
    }

    private function partyWiseLedgerScInHouse(): array
    {
        return $this->scInLedger();
    }

    private function partyWiseSummeryScInHouse(): array
    {
        return $this->scInLedger();
    }

    /** In-house delivery challans, one row per line of the given sections. @param list<string> $sections */
    private function scDeliveryRows(array $sections): Collection
    {
        $q = $this->q;
        $docs = $q->window(KnitScDelivery::query(), 'challan_date')->where('status', '!=', 'CANCELLED')->get()->keyBy('id');

        return KnitScDeliveryLine::query()->whereIn('delivery_id', $docs->keys())->whereIn('section', $sections)->orderBy('sort_order')->get()->map(function (KnitScDeliveryLine $l) use ($q, $docs): array {
            $d = $docs[$l->delivery_id];

            return ['challan_date' => $d->challan_date->toDateString(), 'challan_no' => $d->challan_no, 'party' => $q->parties()[$d->party_id] ?? null, 'store' => $q->stores()[$d->store_id] ?? null,
                'section' => $l->section, 'description' => $l->description, 'colour' => $l->colour_name, 'shade' => $l->shade, 'roll_no' => $l->roll_no, 'lot_no' => $l->lot_no, 'finish_dia' => $l->finish_dia,
                'rolls' => (int) $l->r_qty, 'bags' => (int) $l->bags, 'qty' => self::r3($l->qty), 'uom' => $l->uom, 'status' => $d->status];
        })->values();
    }

    private function greyFabricDeliveryChallanScInHouse(): array
    {
        return $this->table(['challan_date:date', 'challan_no', 'party', 'store', 'section:status', 'description', 'colour', 'shade', 'roll_no', 'finish_dia', 'rolls:int', 'qty:number', 'uom', 'status:status'],
            $this->scDeliveryRows(['FABRIC', 'COLLAR_CUFF']), ['rolls' => 'rolls', 'delivered' => 'qty']);
    }

    private function yarnTransferChallanScInHouse(): array
    {
        return $this->table(['challan_date:date', 'challan_no', 'party', 'store', 'description', 'lot_no', 'bags:int', 'qty:number', 'uom', 'status:status'], $this->scDeliveryRows(['YARN']), ['yarn' => 'qty']);
    }

    /** Yarn delivered to outside knitters on party challans. */
    private function scYarnDeliveryChallanOutHouse(): array
    {
        $q = $this->q;
        $challans = $q->window(YarnPartyChallan::query(), 'challan_date')->where('status', '!=', 'CANCELLED')->get()->keyBy('id');
        $lots = YarnLot::query()->whereIn('id', YarnPartyChallanLine::query()->whereIn('challan_id', $challans->keys())->select('lot_id'))->get()->keyBy('id');
        $rows = YarnPartyChallanLine::query()->whereIn('challan_id', $challans->keys())->orderBy('sort_order')->get()->map(function ($l) use ($q, $challans, $lots): array {
            $c = $challans[$l->challan_id];
            $lot = $lots[$l->lot_id] ?? null;

            return ['challan_date' => $c->challan_date->toDateString(), 'challan_no' => $c->challan_no, 'party' => $q->parties()[$c->party_id] ?? null, 'gate_pass_no' => $c->gate_pass_no,
                'yarn' => $lot ? $q->yarn($lot->composition_id, $lot->yarn_count_id) : null, 'colour' => $lot?->colour, 'lot_no' => $lot?->lot_no, 'cones' => (int) $l->cones, 'qty' => self::r3($l->qty), 'status' => $c->status];
        })->values();

        return $this->table(['challan_date:date', 'challan_no', 'party', 'gate_pass_no', 'yarn', 'colour', 'lot_no', 'cones:int', 'qty:number', 'status:status'], $rows, ['yarn' => 'qty']);
    }
}

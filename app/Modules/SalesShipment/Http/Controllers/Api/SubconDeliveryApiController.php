<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Http\Controllers\Api;

use App\Modules\SalesShipment\Models\SsSalesOrder;
use App\Modules\SalesShipment\Models\SsScDelivery;
use App\Modules\SalesShipment\Models\SsScDeliveryLine;
use App\Modules\SalesShipment\Models\SsScProgramme;
use App\Modules\SalesShipment\Models\SsScReceipt;
use App\Modules\SalesShipment\Models\SsScReceiptLine;
use App\Modules\SalesShipment\Support\SalesBook;
use App\Modules\SalesShipment\Support\SubconBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sales & Shipment → Sub-Contract (AnnotechERP 21/03): the Sub-Contract
 * Delivery Register — goods sent out to the party against an S/C
 * programme's lines (never more than the contract still owes), marked
 * delivered, finalized or cancelled; the Sub-Contract Receipt Register —
 * what came back on a delivered chalan, finished goods, left-over or
 * rejection, good and rejected per line (never more than is still out);
 * and Sub-Contract Pending — what is still with each party, overdue first.
 */
class SubconDeliveryApiController extends ApiController
{
    private ?SalesBook $book = null;

    /* ── Deliveries ────────────────────────────────────────────────── */

    public function index(): JsonResponse
    {
        $this->allow('salesshipment.subcon.view');
        $b = $this->book();
        $rows = SsScDelivery::query()->orderByDesc('chalan_date')->orderByDesc('chalan_no')->get();
        $lines = DB::table('ss_sc_delivery_lines')->whereIn('delivery_id', $rows->pluck('id'))->get(['id', 'delivery_id', 'qty']);
        $fig = SubconBook::lines($lines->pluck('id')->all());
        $sc = DB::table('ss_sc_programmes')->whereIn('id', $rows->pluck('programme_id'))->pluck('sc_no', 'id');
        $names = $b->customerNames();
        $stores = $b->stores();

        return ApiResponse::ok($rows->map(function (SsScDelivery $d) use ($lines, $fig, $sc, $names, $stores, $b) {
            $mine = $lines->where('delivery_id', $d->id);

            return [
                'chalan_no' => $d->chalan_no, 'chalan_date' => $d->chalan_date->toDateString(), 'company' => $b->company(), 'sc_no' => $sc[$d->programme_id] ?? '—', 'customer' => $names[$d->customer_id] ?? '—',
                'customer_id' => $d->customer_id, 'store' => $stores[$d->store_id] ?? '—', 'target_date' => $d->delivery_target_date->toDateString(), 'is_revised' => (bool) $d->is_revised,
                'finalized' => (bool) $d->finalized, 'closed' => (bool) $d->closed, 'state' => $d->state, 'total_qty' => (float) $mine->sum('qty'),
                'returned_qty' => (float) $mine->sum(fn ($l) => $fig[$l->id]['received'] + $fig[$l->id]['rejected']),
                'out_qty' => $d->state === 'DELIVERED' && ! $d->closed ? (float) $mine->sum(fn ($l) => max(0.0, $fig[$l->id]['out'])) : 0.0,
            ];
        })->values()->all());
    }

    public function show(string $no): JsonResponse
    {
        $this->allow('salesshipment.subcon.view');

        return ApiResponse::ok($this->present($this->find($no)));
    }

    /** The SC List on the left: programmes with what they still owe. */
    public function contracts(): JsonResponse
    {
        $this->allow('salesshipment.subcon.view');
        $rows = SsScProgramme::query()->orderByDesc('sc_date')->orderByDesc('sc_no')->get();
        $lines = DB::table('ss_sc_programme_lines')->whereIn('programme_id', $rows->pluck('id'))->get(['id', 'programme_id', 'qty']);
        $held = SubconBook::held($lines->pluck('id')->all());
        $orders = DB::table('ss_sales_orders')->whereIn('id', $rows->pluck('order_id'))->pluck('so_no', 'id');
        $names = $this->book()->customerNames();

        return ApiResponse::ok($rows->map(fn (SsScProgramme $p) => [
            'sc_no' => $p->sc_no, 'so_no' => $orders[$p->order_id] ?? '—', 'party' => $names[$p->party_id] ?? '—', 'customer_id' => $p->party_id, 'process' => $p->process,
            'due' => max(0.0, round((float) $lines->where('programme_id', $p->id)->sum(fn ($l) => $l->qty - $held[$l->id]), 4)),
        ])->values()->all());
    }

    public function source(Request $request, string $sc): JsonResponse
    {
        $this->allow('salesshipment.subcon.view');
        $p = SsScProgramme::query()->where('sc_no', $sc)->firstOrFail();
        $except = $request->query('except') ? SsScDelivery::query()->where('chalan_no', $request->query('except'))->value('id') : null;

        return ApiResponse::ok($this->sourceOf($p, $except));
    }

    public function save(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('salesshipment.subcon.manage');
        $row = $no ? $this->find($no) : null;
        if ($row) {
            $this->assertRewritable($row);
        }
        $c = $this->caller()->companyId;
        $party = Rule::exists('mp_suppliers', 'id')->where('company_id', $c);
        $data = $request->validate([
            'sc_no' => ['required', 'string', 'max:24'], 'customer_id' => ['required', $party], 'transfer_from_id' => ['nullable', $party], 'chalan_date' => ['required', 'date'],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $c)], 'delivery_target_date' => ['required', 'date', 'after_or_equal:chalan_date'],
            'mode_of_shipment' => ['required', Rule::in(SsSalesOrder::SHIP_MODES)], 'carrier_name' => ['nullable', 'string', 'max:120'], 'carrier_no' => ['nullable', 'string', 'max:60'],
            'pr_po_ref' => ['nullable', 'string', 'max:2000'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['nullable', 'array'], 'lines.*.programme_line_id' => ['required', 'string', 'max:26', 'distinct'], 'lines.*.qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0'], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], ['lines.*.programme_line_id.distinct' => __('salesshipment.v.line_twice'), 'delivery_target_date.after_or_equal' => __('salesshipment.v.scd_target')]);
        $p = SsScProgramme::query()->where('sc_no', $data['sc_no'])->firstOrFail();
        if ($row && $row->programme_id !== $p->id) {
            throw ValidationException::withMessages(['sc_no' => __('salesshipment.v.scd_contract_fixed', ['no' => $row->chalan_no])]);
        }
        if (DB::table('ss_sales_orders')->where('id', $p->order_id)->value('state') !== 'OPEN') {
            throw ValidationException::withMessages(['sc_no' => __('salesshipment.v.scd_order_closed', ['no' => $p->sc_no])]);
        }
        $this->book()->assertOpen($data['chalan_date'], 'chalan_date');
        $lines = collect(array_values($data['lines'] ?? []))->filter(fn ($l) => (float) ($l['qty'] ?? 0) > 0)->values();
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('salesshipment.v.scd_lines')]);
        }
        $src = collect($this->sourceOf($p, $row?->id)['lines'])->keyBy('programme_line_id');
        foreach ($lines as $i => $l) {
            $s = $src[$l['programme_line_id']] ?? null;
            if (! $s) {
                throw ValidationException::withMessages(["lines.{$i}.programme_line_id" => __('salesshipment.v.scd_line_unknown', ['no' => $p->sc_no])]);
            }
            if ((float) $l['qty'] > $s['balance'] + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __('salesshipment.v.scd_over', ['line' => $s['label'], 'max' => $this->num($s['balance'])])]);
            }
        }
        $head = ['programme_id' => $p->id, ...collect($data)->only(['customer_id', 'transfer_from_id', 'chalan_date', 'store_id', 'delivery_target_date', 'mode_of_shipment', 'carrier_name', 'carrier_no', 'pr_po_ref', 'remarks'])->all()];
        $row = DB::transaction(function () use ($row, $head, $lines, $src, $data) {
            if ($row) {
                $row->update($head + ['is_revised' => true]);
                SsScDeliveryLine::query()->where('delivery_id', $row->id)->delete();
            } else {
                $no = $this->book()->nextNo('SCD', 'ss_sc_deliveries', 'chalan_no', $data['chalan_date']);
                $row = SsScDelivery::query()->create($head + ['chalan_no' => $no, 'gate_pass_no' => 'SGP'.substr($no, 3), 'state' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach ($lines as $i => $l) {
                SsScDeliveryLine::query()->create(['delivery_id' => $row->id, 'programme_line_id' => $l['programme_line_id'], 'qty' => $l['qty'], 'rate' => $l['rate'] ?? $src[$l['programme_line_id']]['rate'],
                    'remarks' => $l['remarks'] ?? null, 'sort_order' => $i]);
            }

            return $row;
        });

        return ApiResponse::ok($this->present($row->refresh()), [], $no ? 200 : 201);
    }

    /** Mark Delivered, Finalize, Cancel. */
    public function act(string $no, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['deliver', 'finalize', 'cancel'], true), 404);
        $this->allow('salesshipment.subcon.manage');
        $row = DB::transaction(function () use ($no, $action) {
            $row = SsScDelivery::query()->where('chalan_no', $no)->lockForUpdate()->firstOrFail();
            $ok = match ($action) {
                'deliver' => $row->state === 'DRAFT',
                'finalize' => $row->state === 'DELIVERED' && ! $row->finalized,
                'cancel' => $row->state === 'DRAFT',
            };
            if (! $ok || $row->closed) {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.step', ['no' => $row->chalan_no, 'status' => $this->stateText($row)])]);
            }
            $this->book()->assertOpen($row->chalan_date, 'status');
            $by = $this->caller()->auditUserId();
            match ($action) {
                'deliver' => $row->update(['state' => 'DELIVERED', 'delivered_at' => now(), 'delivered_by' => $by]),
                'finalize' => $row->update(['finalized' => true, 'finalized_at' => now(), 'finalized_by' => $by]),
                'cancel' => $row->update(['state' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $by]),
            };

            return $row;
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    public function destroy(string $no): JsonResponse
    {
        $this->allow('salesshipment.subcon.manage');
        $row = $this->find($no);
        if ($row->state !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('salesshipment.v.delete_draft', ['no' => $row->chalan_no])]);
        }
        $row->delete();

        return ApiResponse::ok(['deleted' => $no]);
    }

    /* ── Receipts ──────────────────────────────────────────────────── */

    public function receipts(): JsonResponse
    {
        $this->allow('salesshipment.subcon.view');
        $b = $this->book();
        $rows = SsScReceipt::query()->orderByDesc('mrr_date')->orderByDesc('mrr_no')->get();
        $deliveries = DB::table('ss_sc_deliveries as d')->join('ss_sc_programmes as p', 'p.id', '=', 'd.programme_id')->join('ss_sales_orders as o', 'o.id', '=', 'p.order_id')
            ->whereIn('d.id', $rows->pluck('delivery_id'))->get(['d.id', 'd.chalan_no', 'o.so_no', 'p.process'])->keyBy('id');
        $qty = DB::table('ss_sc_receipt_lines')->whereIn('receipt_id', $rows->pluck('id'))->groupBy('receipt_id')->selectRaw('receipt_id, sum(qty) as q, sum(rejected_qty) as rj')->get()->keyBy('receipt_id');
        $names = $b->customerNames();
        $stores = $b->stores();

        return ApiResponse::ok($rows->map(fn (SsScReceipt $r) => [
            'mrr_no' => $r->mrr_no, 'mrr_date' => $r->mrr_date->toDateString(), 'company' => $b->company(), 'chalan_no' => $deliveries[$r->delivery_id]->chalan_no ?? '—', 'so_no' => $deliveries[$r->delivery_id]->so_no ?? '—',
            'customer' => $names[$r->customer_id] ?? '—', 'customer_id' => $r->customer_id, 'store' => $stores[$r->store_id] ?? '—', 'store_id' => $r->store_id, 'receive_type' => $r->receive_type,
            'is_revised' => (bool) $r->is_revised, 'sc_type' => $deliveries[$r->delivery_id]->process ?? '—', 'finalized' => (bool) $r->finalized, 'state' => $r->state,
            'received_qty' => (float) ($qty[$r->id]->q ?? 0), 'rejected_qty' => (float) ($qty[$r->id]->rj ?? 0), 'total_qty' => (float) (($qty[$r->id]->q ?? 0) + ($qty[$r->id]->rj ?? 0)),
        ])->values()->all());
    }

    public function receipt(string $no): JsonResponse
    {
        $this->allow('salesshipment.subcon.view');

        return ApiResponse::ok($this->presentReceipt($this->findReceipt($no)));
    }

    /** The S/C Challan List on the left: delivered chalans still open, with what is still out. */
    public function outstanding(): JsonResponse
    {
        $this->allow('salesshipment.subcon.view');
        $rows = SsScDelivery::query()->where('state', 'DELIVERED')->where('closed', false)->orderByDesc('chalan_date')->orderByDesc('chalan_no')->get();
        $lines = DB::table('ss_sc_delivery_lines')->whereIn('delivery_id', $rows->pluck('id'))->get(['id', 'delivery_id']);
        $fig = SubconBook::lines($lines->pluck('id')->all());
        $sc = DB::table('ss_sc_programmes')->whereIn('id', $rows->pluck('programme_id'))->pluck('sc_no', 'id');
        $names = $this->book()->customerNames();
        $open = DB::table('ss_sc_reconciliations')->whereIn('delivery_id', $rows->pluck('id'))->where('state', '!=', 'RECONCILED')->pluck('recon_no', 'delivery_id');

        return ApiResponse::ok($rows->map(fn (SsScDelivery $d) => [
            'chalan_no' => $d->chalan_no, 'sc_no' => $sc[$d->programme_id] ?? '—', 'party' => $names[$d->customer_id] ?? '—', 'customer_id' => $d->customer_id,
            'out' => round((float) $lines->where('delivery_id', $d->id)->sum(fn ($l) => $fig[$l->id]['out']), 4), 'open_recon' => $open[$d->id] ?? null,
        ])->values()->all());
    }

    public function receiptSource(Request $request, string $chalan): JsonResponse
    {
        $this->allow('salesshipment.subcon.view');
        $d = $this->find($chalan);
        $except = $request->query('except') ? SsScReceipt::query()->where('mrr_no', $request->query('except'))->value('id') : null;

        return ApiResponse::ok($this->receiptSourceOf($d, $except));
    }

    public function saveReceipt(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('salesshipment.subcon.manage');
        $row = $no ? $this->findReceipt($no) : null;
        if ($row && ($row->state !== 'RECEIVED' || $row->finalized || $this->hasReturns($row->id))) {
            throw ValidationException::withMessages(['mrr_no' => __('salesshipment.v.scr_locked', ['no' => $row->mrr_no])]);
        }
        $c = $this->caller()->companyId;
        $data = $request->validate([
            'chalan_no' => ['required', 'string', 'max:24'], 'mrr_date' => ['required', 'date'], 'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $c)],
            'customer_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $c)], 'receive_type' => ['required', Rule::in(SsScReceipt::TYPES)], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['nullable', 'array'], 'lines.*.delivery_line_id' => ['required', 'string', 'max:26', 'distinct'], 'lines.*.qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.rejected_qty' => ['nullable', 'numeric', 'min:0'], 'lines.*.rate' => ['nullable', 'numeric', 'min:0'], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], ['lines.*.delivery_line_id.distinct' => __('salesshipment.v.line_twice')]);
        $d = $this->find($data['chalan_no']);
        if ($row && $row->delivery_id !== $d->id) {
            throw ValidationException::withMessages(['chalan_no' => __('salesshipment.v.scr_chalan_fixed', ['no' => $row->mrr_no])]);
        }
        if ($d->state !== 'DELIVERED' || $d->closed) {
            throw ValidationException::withMessages(['chalan_no' => __('salesshipment.v.scr_not_out', ['no' => $d->chalan_no])]);
        }
        if ($data['mrr_date'] < $d->chalan_date->toDateString()) {
            throw ValidationException::withMessages(['mrr_date' => __('salesshipment.v.scr_before', ['date' => $d->chalan_date->format('d M Y')])]);
        }
        $this->book()->assertOpen($data['mrr_date'], 'mrr_date');
        $lines = collect(array_values($data['lines'] ?? []))->filter(fn ($l) => (float) ($l['qty'] ?? 0) + (float) ($l['rejected_qty'] ?? 0) > 0)->values();
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('salesshipment.v.scr_lines')]);
        }
        $src = collect($this->receiptSourceOf($d, $row?->id)['lines'])->keyBy('delivery_line_id');
        foreach ($lines as $i => $l) {
            $s = $src[$l['delivery_line_id']] ?? null;
            if (! $s) {
                throw ValidationException::withMessages(["lines.{$i}.delivery_line_id" => __('salesshipment.v.scr_line_unknown', ['no' => $d->chalan_no])]);
            }
            if ((float) ($l['qty'] ?? 0) + (float) ($l['rejected_qty'] ?? 0) > $s['balance'] + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __('salesshipment.v.scr_over', ['line' => $s['label'], 'max' => $this->num($s['balance'])])]);
            }
        }
        $head = ['delivery_id' => $d->id, ...collect($data)->only(['mrr_date', 'store_id', 'customer_id', 'receive_type', 'remarks'])->all()];
        $row = DB::transaction(function () use ($row, $head, $lines, $src, $data) {
            if ($row) {
                $row->update($head + ['is_revised' => true]);
                SsScReceiptLine::query()->where('receipt_id', $row->id)->delete();
            } else {
                $row = SsScReceipt::query()->create($head + ['mrr_no' => $this->book()->nextNo('SCR', 'ss_sc_receipts', 'mrr_no', $data['mrr_date']), 'state' => 'RECEIVED', 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach ($lines as $i => $l) {
                SsScReceiptLine::query()->create(['receipt_id' => $row->id, 'delivery_line_id' => $l['delivery_line_id'], 'qty' => $l['qty'] ?? 0, 'rejected_qty' => $l['rejected_qty'] ?? 0,
                    'rate' => $l['rate'] ?? $src[$l['delivery_line_id']]['rate'], 'remarks' => $l['remarks'] ?? null, 'sort_order' => $i]);
            }

            return $row;
        });

        return ApiResponse::ok($this->presentReceipt($row->refresh()), [], $no ? 200 : 201);
    }

    /** Finalize locks a receipt; Cancel takes it back off the chalan. */
    public function receiptAct(string $no, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['finalize', 'cancel'], true), 404);
        $this->allow('salesshipment.subcon.manage');
        $row = DB::transaction(function () use ($no, $action) {
            $row = SsScReceipt::query()->where('mrr_no', $no)->lockForUpdate()->firstOrFail();
            $closed = (bool) DB::table('ss_sc_deliveries')->where('id', $row->delivery_id)->value('closed');
            if ($row->state !== 'RECEIVED' || $row->finalized || $closed) {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.scr_locked', ['no' => $row->mrr_no])]);
            }
            if ($action === 'cancel' && $this->hasReturns($row->id)) {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.scr_has_returns', ['no' => $row->mrr_no])]);
            }
            $this->book()->assertOpen($row->mrr_date, 'status');
            $by = $this->caller()->auditUserId();
            $action === 'finalize'
                ? $row->update(['finalized' => true, 'finalized_at' => now(), 'finalized_by' => $by])
                : $row->update(['state' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $by]);

            return $row;
        });

        return ApiResponse::ok($this->presentReceipt($row->refresh()));
    }

    public function receiptDestroy(string $no): JsonResponse
    {
        $this->allow('salesshipment.subcon.manage');
        $row = $this->findReceipt($no);
        if ($row->finalized || $this->hasReturns($row->id) || DB::table('ss_sc_deliveries')->where('id', $row->delivery_id)->value('closed')) {
            throw ValidationException::withMessages(['status' => __('salesshipment.v.scr_locked', ['no' => $row->mrr_no])]);
        }
        $row->delete();

        return ApiResponse::ok(['deleted' => $no]);
    }

    /* ── Pending ───────────────────────────────────────────────────── */

    public function pending(): JsonResponse
    {
        $this->allow('salesshipment.subcon.view');
        $rows = DB::table('ss_sc_delivery_lines as l')->join('ss_sc_deliveries as d', 'd.id', '=', 'l.delivery_id')->join('ss_sc_programmes as p', 'p.id', '=', 'd.programme_id')
            ->join('ss_sc_programme_lines as pl', 'pl.id', '=', 'l.programme_line_id')->join('ss_sales_order_sizes as s', 's.id', '=', 'pl.size_id')
            ->join('ss_sales_order_lines as ol', 'ol.id', '=', 's.line_id')->join('ss_sales_orders as o', 'o.id', '=', 'p.order_id')
            ->where('d.company_id', $this->caller()->companyId)->where('d.state', 'DELIVERED')
            ->orderBy('d.delivery_target_date')->orderBy('d.chalan_no')->orderBy('l.sort_order')
            ->get(['l.id', 'd.chalan_no', 'd.chalan_date', 'd.customer_id', 'd.delivery_target_date', 'd.closed', 'p.sc_no', 'o.so_no', 'o.po_wo_no', 'ol.style_ref', 'ol.item_id', 'ol.gmt_color', 's.size']);
        $fig = SubconBook::lines($rows->pluck('id')->all());
        $names = $this->book()->customerNames();
        $items = $this->book()->itemNames();
        $today = CarbonImmutable::now('Asia/Dhaka')->startOfDay();

        return ApiResponse::ok($rows->map(function ($r) use ($fig, $names, $items, $today) {
            $f = $fig[$r->id];
            $pending = $r->closed ? 0.0 : max(0.0, $f['out']);
            $target = CarbonImmutable::parse(substr((string) $r->delivery_target_date, 0, 10), 'Asia/Dhaka')->startOfDay();
            $overdue = $pending > 0 && $target->lessThan($today) ? (int) $target->diffInDays($today) : 0;

            return [
                'line_id' => $r->id, 'party' => $names[$r->customer_id] ?? '—', 'customer_id' => $r->customer_id, 'chalan_no' => $r->chalan_no, 'chalan_date' => substr((string) $r->chalan_date, 0, 10),
                'sc_no' => $r->sc_no, 'so_no' => $r->so_no, 'style' => $r->style_ref, 'po' => $r->po_wo_no,
                'item' => implode(' · ', array_filter([$items[$r->item_id] ?? '—', $r->gmt_color, $r->size])),
                'delivered' => $f['sent'], 'received' => $f['received'], 'rejected' => $f['rejected'], 'returned' => $f['returned'], 'pending' => $pending,
                'expected' => substr((string) $r->delivery_target_date, 0, 10), 'overdue' => $overdue, 'status' => $pending <= 0 ? 'COMPLETED' : ($overdue > 0 ? 'OVERDUE' : 'PENDING'),
            ];
        })->values()->all());
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    private function book(): SalesBook
    {
        return $this->book ??= new SalesBook($this->caller()->companyId);
    }

    private function find(string $no): SsScDelivery
    {
        return SsScDelivery::query()->where('chalan_no', $no)->firstOrFail();
    }

    private function findReceipt(string $no): SsScReceipt
    {
        return SsScReceipt::query()->where('mrr_no', $no)->firstOrFail();
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }

    private function hasReturns(string $receiptId): bool
    {
        return DB::table('ss_sc_returns')->where('receipt_id', $receiptId)->where('state', '!=', 'CANCELLED')->exists();
    }

    private function stateText(SsScDelivery $d): string
    {
        return $d->closed ? __('salesshipment.scd.state.CLOSED') : ($d->finalized ? __('salesshipment.scd.state.FINALIZED') : __('salesshipment.scd.state.'.$d->state));
    }

    /** A chalan is rewritten only while not cancelled, not finalized, not closed and with nothing received against it. */
    private function assertRewritable(SsScDelivery $d): void
    {
        $received = DB::table('ss_sc_receipts')->where('delivery_id', $d->id)->where('state', '!=', 'CANCELLED')->exists();
        if ($d->state === 'CANCELLED' || $d->finalized || $d->closed || $received) {
            throw ValidationException::withMessages(['chalan_no' => __('salesshipment.v.scd_locked', ['no' => $d->chalan_no, 'status' => $received ? __('salesshipment.scd.received') : $this->stateText($d)])]);
        }
    }

    /** @return array<string, mixed> */
    private function sourceOf(SsScProgramme $p, ?string $exceptDelivery): array
    {
        $lines = DB::table('ss_sc_programme_lines')->where('programme_id', $p->id)->get(['id', 'qty', 'rate']);
        $held = SubconBook::held($lines->pluck('id')->all(), $exceptDelivery);
        $labels = SubconBook::labels($this->book(), $lines->pluck('id')->all());

        return [
            'sc_no' => $p->sc_no, 'customer_id' => $p->party_id, 'process' => $p->process, 'target_date' => $p->target_date->toDateString(),
            'lines' => $lines->map(fn ($l) => ['programme_line_id' => $l->id, 'label' => $labels[$l->id] ?? '—', 'contract' => (float) $l->qty, 'sent' => $held[$l->id],
                'balance' => max(0.0, round($l->qty - $held[$l->id], 4)), 'rate' => (float) $l->rate])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function receiptSourceOf(SsScDelivery $d, ?string $exceptReceipt): array
    {
        $lines = SsScDeliveryLine::query()->where('delivery_id', $d->id)->orderBy('sort_order')->get();
        $fig = SubconBook::lines($lines->pluck('id')->all(), $exceptReceipt);
        $labels = SubconBook::labels($this->book(), $lines->pluck('programme_line_id')->all());

        return [
            'chalan_no' => $d->chalan_no, 'customer_id' => $d->customer_id, 'store_id' => $d->store_id,
            'lines' => $lines->map(fn ($l) => ['delivery_line_id' => $l->id, 'label' => $labels[$l->programme_line_id] ?? '—', 'sent' => (float) $l->qty,
                'prev' => round($fig[$l->id]['received'] + $fig[$l->id]['rejected'] - $fig[$l->id]['returned'], 4), 'balance' => max(0.0, $fig[$l->id]['out']), 'rate' => (float) $l->rate])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function present(SsScDelivery $d): array
    {
        $b = $this->book();
        $p = SsScProgramme::query()->findOrFail($d->programme_id);
        $lines = SsScDeliveryLine::query()->where('delivery_id', $d->id)->orderBy('sort_order')->get();
        $labels = SubconBook::labels($b, $lines->pluck('programme_line_id')->all());

        return [
            ...collect($d->only(['chalan_no', 'gate_pass_no', 'customer_id', 'transfer_from_id', 'store_id', 'mode_of_shipment', 'carrier_name', 'carrier_no', 'pr_po_ref', 'remarks', 'state']))->all(),
            'sc_no' => $p->sc_no, 'process' => $p->process, 'chalan_date' => $d->chalan_date->toDateString(), 'delivery_target_date' => $d->delivery_target_date->toDateString(),
            'finalized' => (bool) $d->finalized, 'closed' => (bool) $d->closed, 'is_revised' => (bool) $d->is_revised, 'company' => $b->company(),
            'customer' => $b->customerNames()[$d->customer_id] ?? '—', 'address' => $b->addresses()[$d->customer_id] ?? '', 'store' => $b->stores()[$d->store_id] ?? '—',
            'transfer_from' => $d->transfer_from_id ? ($b->customerNames()[$d->transfer_from_id] ?? null) : null, 'delivered' => $b->trail($d->delivered_by, $d->delivered_at),
            'lines' => $lines->map(fn ($l) => ['programme_line_id' => $l->programme_line_id, 'label' => $labels[$l->programme_line_id] ?? '—', 'qty' => (float) $l->qty, 'rate' => (float) $l->rate, 'remarks' => $l->remarks])->values()->all(),
            'source' => $this->sourceOf($p, $d->id),
        ];
    }

    /** @return array<string, mixed> */
    private function presentReceipt(SsScReceipt $r): array
    {
        $b = $this->book();
        $d = SsScDelivery::query()->findOrFail($r->delivery_id);
        $src = $this->receiptSourceOf($d, $r->id);
        $by = collect($src['lines'])->keyBy('delivery_line_id');
        $lines = SsScReceiptLine::query()->where('receipt_id', $r->id)->orderBy('sort_order')->get();

        return [
            'mrr_no' => $r->mrr_no, 'chalan_no' => $d->chalan_no, 'mrr_date' => $r->mrr_date->toDateString(), 'store_id' => $r->store_id, 'customer_id' => $r->customer_id, 'receive_type' => $r->receive_type,
            'remarks' => $r->remarks, 'state' => $r->state, 'finalized' => (bool) $r->finalized, 'company' => $b->company(), 'customer' => $b->customerNames()[$r->customer_id] ?? '—', 'store' => $b->stores()[$r->store_id] ?? '—',
            'lines' => $lines->map(fn ($l) => ['delivery_line_id' => $l->delivery_line_id, 'label' => $by[$l->delivery_line_id]['label'] ?? '—', 'qty' => (float) $l->qty, 'rejected_qty' => (float) $l->rejected_qty,
                'rate' => (float) $l->rate, 'remarks' => $l->remarks])->values()->all(),
            'source' => $src,
        ];
    }
}

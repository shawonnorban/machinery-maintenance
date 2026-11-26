<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Http\Controllers\Api;

use App\Modules\SalesShipment\Models\SsChallan;
use App\Modules\SalesShipment\Models\SsChallanLine;
use App\Modules\SalesShipment\Models\SsChallanReturn;
use App\Modules\SalesShipment\Models\SsChallanReturnLine;
use App\Modules\SalesShipment\Models\SsSalesOrder;
use App\Modules\SalesShipment\Support\SalesBook;
use App\Modules\SalesShipment\Support\SsSettings;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sales & Shipment → Order & Delivery (AnnotechERP 21/02): Delivery Challan
 * Issue — the chalan the goods leave the gate on, size by size against a
 * confirmed order (never more than the order still owes plus its overage),
 * submitted to the gate and marked delivered, or cancelled back onto the
 * order; and Delivery Challan Return — goods a customer sends back on a
 * delivered challan (never more than it delivered, less other returns),
 * putting the quantity back on the order's balance once confirmed.
 */
class ChallanApiController extends ApiController
{
    private ?SalesBook $book = null;

    /* ── Challans ──────────────────────────────────────────────────── */

    public function index(): JsonResponse
    {
        $this->allow('salesshipment.delivery.view');
        $b = $this->book();
        $rows = SsChallan::query()->orderByDesc('chalan_date')->orderByDesc('chalan_no')->get();
        $lines = DB::table('ss_challan_lines')->whereIn('challan_id', $rows->pluck('id'))->get(['id', 'challan_id', 'size_id', 'qty', 'rate']);
        $fig = $b->lineFigures($lines->pluck('id')->all());
        $orders = DB::table('ss_sales_orders')->whereIn('id', $rows->pluck('order_id'))->pluck('so_no', 'id');
        $refs = DB::table('ss_sales_order_sizes as s')->join('ss_sales_order_lines as l', 'l.id', '=', 's.line_id')->whereIn('s.id', $lines->pluck('size_id'))
            ->get(['s.id', 'l.buyer', 'l.order_ref', 'l.style_ref', 'l.section_id'])->keyBy('id');
        $names = $b->customerNames();
        $stores = $b->stores();
        $company = $b->company();

        return ApiResponse::ok($rows->map(function (SsChallan $c) use ($lines, $fig, $orders, $refs, $names, $stores, $company) {
            $mine = $lines->where('challan_id', $c->id);
            $qty = (float) $mine->sum('qty');
            $billed = (float) $mine->sum(fn ($l) => $fig[$l->id]['billed'] ?? 0);
            $r = $mine->map(fn ($l) => $refs[$l->size_id] ?? null)->filter();

            return [
                'chalan_no' => $c->chalan_no, 'chalan_date' => $c->chalan_date->toDateString(), 'company' => $company, 'so_no' => $orders[$c->order_id] ?? '—', 'customer' => $names[$c->customer_id] ?? '—',
                'customer_id' => $c->customer_id, 'store' => $stores[$c->store_id] ?? '—', 'target_date' => $c->delivery_target_date->toDateString(), 'is_revised' => (bool) $c->is_revised,
                'bill_status' => $billed <= 0 ? 'WAITING_FOR_BILL' : ($billed + 0.00005 < $qty ? 'PARTIALLY_BILLED' : 'BILLED'), 'state' => $c->state,
                'total_qty' => $qty, 'total_value' => round((float) $mine->sum(fn ($l) => $l->qty * $l->rate), 2), 'gate_pass_no' => $c->gate_pass_no,
                'search' => $r->map(fn ($x) => "{$x->buyer} {$x->order_ref} {$x->style_ref}")->implode(' '), 'section_ids' => $r->pluck('section_id')->filter()->unique()->values()->all(),
            ];
        })->values()->all());
    }

    public function show(string $no): JsonResponse
    {
        $this->allow('salesshipment.delivery.view');

        return ApiResponse::ok($this->present($this->find($no)));
    }

    /** The SO List on the left: confirmed open orders with what they still owe. */
    public function orders(): JsonResponse
    {
        $this->allow('salesshipment.delivery.view');
        $orders = SsSalesOrder::query()->where('approval_status', 'CONFIRMED')->where('state', 'OPEN')->orderBy('delivery_target_date')->orderBy('so_no')->get();
        $fig = $this->book()->sizeFigures($orders->pluck('id')->all());
        $sizes = DB::table('ss_sales_order_sizes')->whereIn('order_id', $orders->pluck('id'))->get(['id', 'order_id', 'qty'])->groupBy('order_id');
        $names = $this->book()->customerNames();

        return ApiResponse::ok($orders->map(fn (SsSalesOrder $o) => [
            'so_no' => $o->so_no, 'customer' => $names[$o->customer_id] ?? '—', 'customer_id' => $o->customer_id, 'po_wo_no' => $o->po_wo_no, 'target' => $o->delivery_target_date->toDateString(),
            'due' => max(0.0, round((float) $sizes->get($o->id, collect())->sum(fn ($s) => $s->qty - ($fig[$s->id]['held'] ?? 0) + ($fig[$s->id]['returned'] ?? 0)), 4)),
        ])->values()->all());
    }

    /** An order's sizes as they drop into a challan: ordered, gone out on other challans, the balance, rate — and its header defaults. */
    public function source(Request $request, string $so): JsonResponse
    {
        $this->allow('salesshipment.delivery.view');
        $o = SsSalesOrder::query()->where('so_no', $so)->firstOrFail();
        $except = $request->query('except') ? SsChallan::query()->where('chalan_no', $request->query('except'))->value('id') : null;

        return ApiResponse::ok($this->sourceOf($o, $except));
    }

    public function save(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('salesshipment.delivery.manage');
        $row = $no ? $this->find($no) : null;
        if ($row && $row->state !== 'DRAFT') {
            throw ValidationException::withMessages(['chalan_no' => __('salesshipment.v.locked', ['no' => $row->chalan_no, 'status' => __('salesshipment.dc.state.'.$row->state)])]);
        }
        $c = $this->caller()->companyId;
        $data = $request->validate([
            'so_no' => ['required', 'string', 'max:24'], 'customer_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $c)], 'chalan_date' => ['required', 'date'],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $c)], 'delivery_target_date' => ['required', 'date'],
            'mode_of_shipment' => ['required', Rule::in(SsSalesOrder::SHIP_MODES)], 'currency_id' => ['required', Rule::exists('currencies', 'id')->where('company_id', $c)],
            'conv_rate' => ['required', 'numeric', 'gt:0'], 'carrier_name' => ['nullable', 'string', 'max:120'], 'carrier_no' => ['nullable', 'string', 'max:60'],
            'pr_po_ref' => ['nullable', 'string', 'max:2000'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['nullable', 'array'], 'lines.*.size_id' => ['required', 'string', 'max:26', 'distinct'], 'lines.*.qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0'], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], ['lines.*.size_id.distinct' => __('salesshipment.v.line_twice')]);
        $order = SsSalesOrder::query()->where('so_no', $data['so_no'])->firstOrFail();
        if ($row && $row->order_id !== $order->id) {
            throw ValidationException::withMessages(['so_no' => __('salesshipment.v.dc_order_fixed', ['no' => $row->chalan_no])]);
        }
        if ($order->approval_status !== 'CONFIRMED' || $order->state !== 'OPEN') {
            throw ValidationException::withMessages(['so_no' => __('salesshipment.v.dc_not_confirmed', ['no' => $order->so_no])]);
        }
        $this->book()->assertOpen($data['chalan_date'], 'chalan_date');
        $lines = collect(array_values($data['lines'] ?? []))->filter(fn ($l) => (float) ($l['qty'] ?? 0) > 0)->values();
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('salesshipment.v.dc_lines')]);
        }
        $src = collect($this->sourceOf($order, $row?->id)['lines'])->keyBy('size_id');
        foreach ($lines as $i => $l) {
            $s = $src[$l['size_id']] ?? null;
            if (! $s) {
                throw ValidationException::withMessages(["lines.{$i}.size_id" => __('salesshipment.v.size_not_on_order', ['no' => $order->so_no])]);
            }
            if ((float) $l['qty'] > $s['allowed'] + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __('salesshipment.v.dc_over', ['line' => $s['label'], 'max' => $this->num($s['allowed'])])]);
            }
        }
        $head = ['order_id' => $order->id, ...collect($data)->only(['customer_id', 'chalan_date', 'store_id', 'delivery_target_date', 'mode_of_shipment', 'currency_id', 'conv_rate', 'carrier_name',
            'carrier_no', 'pr_po_ref', 'remarks'])->all()];
        $row = DB::transaction(function () use ($row, $head, $lines, $src, $data) {
            if ($row) {
                $row->update($head + ['is_revised' => true]);
                SsChallanLine::query()->where('challan_id', $row->id)->delete();
            } else {
                $no = $this->book()->nextNo('DC', 'ss_challans', 'chalan_no', $data['chalan_date']);
                $row = SsChallan::query()->create($head + ['chalan_no' => $no, 'gate_pass_no' => 'GP'.substr($no, 2), 'state' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach ($lines as $i => $l) {
                SsChallanLine::query()->create(['challan_id' => $row->id, 'size_id' => $l['size_id'], 'qty' => $l['qty'], 'rate' => $l['rate'] ?? $src[$l['size_id']]['rate'],
                    'remarks' => $l['remarks'] ?? null, 'sort_order' => $i]);
            }

            return $row;
        });

        return ApiResponse::ok($this->present($row->refresh()), [], $no ? 200 : 201);
    }

    /** Submit to the gate, Mark Delivered, Cancel. */
    public function act(string $no, string $action): JsonResponse
    {
        $from = ['submit' => ['DRAFT'], 'deliver' => ['SUBMITTED'], 'cancel' => ['DRAFT', 'SUBMITTED']];
        abort_unless(isset($from[$action]), 404);
        $this->allow('salesshipment.delivery.manage');
        $row = DB::transaction(function () use ($no, $action, $from) {
            $row = SsChallan::query()->where('chalan_no', $no)->lockForUpdate()->firstOrFail();
            if (! in_array($row->state, $from[$action], true)) {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.step', ['no' => $row->chalan_no, 'status' => __('salesshipment.dc.state.'.$row->state)])]);
            }
            $this->book()->assertOpen($row->chalan_date, 'status');
            $by = $this->caller()->auditUserId();
            if ($action === 'submit') {
                $order = SsSalesOrder::query()->findOrFail($row->order_id);
                if ($order->approval_status !== 'CONFIRMED' || $order->state !== 'OPEN') {
                    throw ValidationException::withMessages(['status' => __('salesshipment.v.dc_not_confirmed', ['no' => $order->so_no])]);
                }
            }
            match ($action) {
                'submit' => $row->update(['state' => 'SUBMITTED', 'submitted_at' => now(), 'submitted_by' => $by]),
                'deliver' => $row->update(['state' => 'DELIVERED', 'delivered_at' => now(), 'delivered_by' => $by]),
                'cancel' => $row->update(['state' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $by]),
            };

            return $row;
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    public function destroy(string $no): JsonResponse
    {
        $this->allow('salesshipment.delivery.manage');
        $row = $this->find($no);
        if ($row->state !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('salesshipment.v.delete_draft', ['no' => $row->chalan_no])]);
        }
        $row->delete();

        return ApiResponse::ok(['deleted' => $no]);
    }

    /* ── Challan returns ───────────────────────────────────────────── */

    public function returns(): JsonResponse
    {
        $this->allow('salesshipment.delivery.view');
        $b = $this->book();
        $rows = SsChallanReturn::query()->orderByDesc('return_date')->orderByDesc('return_no')->get();
        $challans = DB::table('ss_challans as c')->join('ss_sales_orders as o', 'o.id', '=', 'c.order_id')->whereIn('c.id', $rows->pluck('challan_id'))
            ->get(['c.id', 'c.chalan_no', 'o.so_no', 'c.customer_id'])->keyBy('id');
        $qty = DB::table('ss_challan_return_lines')->whereIn('return_id', $rows->pluck('id'))->groupBy('return_id')->selectRaw('return_id, sum(qty) as q')->pluck('q', 'return_id');
        $names = $b->customerNames();

        return ApiResponse::ok($rows->map(fn (SsChallanReturn $r) => [
            'return_no' => $r->return_no, 'return_date' => $r->return_date->toDateString(), 'chalan_no' => $challans[$r->challan_id]->chalan_no ?? '—', 'so_no' => $challans[$r->challan_id]->so_no ?? '—',
            'customer' => $names[$challans[$r->challan_id]->customer_id ?? ''] ?? '—', 'customer_id' => $challans[$r->challan_id]->customer_id ?? null, 'reason' => $r->reason,
            'total_qty' => (float) ($qty[$r->id] ?? 0), 'confirmed_by' => $b->trail($r->confirmed_by, $r->confirmed_at), 'state' => $r->state,
        ])->values()->all());
    }

    public function returnShow(string $no): JsonResponse
    {
        $this->allow('salesshipment.delivery.view');

        return ApiResponse::ok($this->presentReturn($this->findReturn($no)));
    }

    /** The Delivered Challan List on the left: delivered challans with what is still returnable. */
    public function delivered(): JsonResponse
    {
        $this->allow('salesshipment.delivery.view');

        return ApiResponse::ok($this->deliveredChallans(fn ($f) => $f['delivered'] - $f['returned'], 'returnable'));
    }

    /** A delivered challan's lines as they drop into a return. */
    public function returnSource(Request $request, string $chalan): JsonResponse
    {
        $this->allow('salesshipment.delivery.view');
        $c = $this->find($chalan);
        $except = $request->query('except') ? SsChallanReturn::query()->where('return_no', $request->query('except'))->value('id') : null;

        return ApiResponse::ok($this->returnSourceOf($c, $except));
    }

    public function saveReturn(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('salesshipment.delivery.manage');
        $row = $no ? $this->findReturn($no) : null;
        if ($row && $row->state !== 'DRAFT') {
            throw ValidationException::withMessages(['return_no' => __('salesshipment.v.locked', ['no' => $row->return_no, 'status' => __('salesshipment.dr.state.'.$row->state)])]);
        }
        $data = $request->validate([
            'chalan_no' => ['required', 'string', 'max:24'], 'return_date' => ['required', 'date'], 'reason' => ['required', Rule::in(SsChallanReturn::REASONS)], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['nullable', 'array'], 'lines.*.challan_line_id' => ['required', 'string', 'max:26', 'distinct'], 'lines.*.qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], ['reason.required' => __('salesshipment.v.dr_reason')]);
        $challan = $this->find($data['chalan_no']);
        if ($row && $row->challan_id !== $challan->id) {
            throw ValidationException::withMessages(['chalan_no' => __('salesshipment.v.dr_challan_fixed', ['no' => $row->return_no])]);
        }
        if ($challan->state !== 'DELIVERED') {
            throw ValidationException::withMessages(['chalan_no' => __('salesshipment.v.dr_not_delivered', ['no' => $challan->chalan_no])]);
        }
        if ($data['return_date'] < $challan->chalan_date->toDateString()) {
            throw ValidationException::withMessages(['return_date' => __('salesshipment.v.dr_before', ['date' => $challan->chalan_date->format('d M Y')])]);
        }
        $this->book()->assertOpen($data['return_date'], 'return_date');
        $lines = collect(array_values($data['lines'] ?? []))->filter(fn ($l) => (float) ($l['qty'] ?? 0) > 0)->values();
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('salesshipment.v.dr_lines')]);
        }
        $src = collect($this->returnSourceOf($challan, $row?->id)['lines'])->keyBy('challan_line_id');
        foreach ($lines as $i => $l) {
            $s = $src[$l['challan_line_id']] ?? null;
            if (! $s) {
                throw ValidationException::withMessages(["lines.{$i}.challan_line_id" => __('salesshipment.v.dr_line_unknown', ['no' => $challan->chalan_no])]);
            }
            if ((float) $l['qty'] > $s['returnable'] + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __('salesshipment.v.dr_over', ['line' => $s['label'], 'max' => $this->num($s['returnable'])])]);
            }
        }
        $head = ['challan_id' => $challan->id, 'return_date' => $data['return_date'], 'reason' => $data['reason'], 'remarks' => $data['remarks'] ?? null];
        $row = DB::transaction(function () use ($row, $head, $lines, $data) {
            if ($row) {
                $row->update($head);
                SsChallanReturnLine::query()->where('return_id', $row->id)->delete();
            } else {
                $row = SsChallanReturn::query()->create($head + ['return_no' => SsSettings::nextNo('prefix_challan_return', 'ss_challan_returns', 'return_no', $data['return_date'], $this->caller()->companyId),
                    'state' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach ($lines as $l) {
                SsChallanReturnLine::query()->create(['return_id' => $row->id, 'challan_line_id' => $l['challan_line_id'], 'qty' => $l['qty'], 'remarks' => $l['remarks'] ?? null]);
            }

            return $row;
        });

        return ApiResponse::ok($this->presentReturn($row->refresh()), [], $no ? 200 : 201);
    }

    /** Confirm puts the quantity back on the order's balance; Cancel withdraws a draft or takes a confirmed one back off. */
    public function returnAct(string $no, string $action): JsonResponse
    {
        $from = ['confirm' => ['DRAFT'], 'cancel' => ['DRAFT', 'CONFIRMED']];
        abort_unless(isset($from[$action]), 404);
        $this->allow('salesshipment.delivery.manage');
        $row = DB::transaction(function () use ($no, $action, $from) {
            $row = SsChallanReturn::query()->where('return_no', $no)->lockForUpdate()->firstOrFail();
            if (! in_array($row->state, $from[$action], true)) {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.step', ['no' => $row->return_no, 'status' => __('salesshipment.dr.state.'.$row->state)])]);
            }
            $this->book()->assertOpen($row->return_date, 'status');
            $by = $this->caller()->auditUserId();
            $action === 'confirm'
                ? $row->update(['state' => 'CONFIRMED', 'confirmed_at' => now(), 'confirmed_by' => $by])
                : $row->update(['state' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $by]);

            return $row;
        });

        return ApiResponse::ok($this->presentReturn($row->refresh()));
    }

    public function returnDestroy(string $no): JsonResponse
    {
        $this->allow('salesshipment.delivery.manage');
        $row = $this->findReturn($no);
        if ($row->state !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('salesshipment.v.delete_draft', ['no' => $row->return_no])]);
        }
        $row->delete();

        return ApiResponse::ok(['deleted' => $no]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    private function book(): SalesBook
    {
        return $this->book ??= new SalesBook($this->caller()->companyId);
    }

    private function find(string $no): SsChallan
    {
        return SsChallan::query()->where('chalan_no', $no)->firstOrFail();
    }

    private function findReturn(string $no): SsChallanReturn
    {
        return SsChallanReturn::query()->where('return_no', $no)->firstOrFail();
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }

    /**
     * Delivered challans with what is still open on them by `$open` (returnable, unbilled …).
     *
     * @return list<array<string, mixed>>
     */
    public function deliveredChallans(callable $open, string $key): array
    {
        $rows = SsChallan::query()->where('state', 'DELIVERED')->orderByDesc('chalan_date')->orderByDesc('chalan_no')->get();
        $lines = DB::table('ss_challan_lines')->whereIn('challan_id', $rows->pluck('id'))->get(['id', 'challan_id']);
        $fig = $this->book()->lineFigures($lines->pluck('id')->all());
        $orders = DB::table('ss_sales_orders')->whereIn('id', $rows->pluck('order_id'))->pluck('so_no', 'id');
        $names = $this->book()->customerNames();

        return $rows->map(fn (SsChallan $c) => [
            'chalan_no' => $c->chalan_no, 'so_no' => $orders[$c->order_id] ?? '—', 'customer' => $names[$c->customer_id] ?? '—', 'customer_id' => $c->customer_id,
            $key => max(0.0, round((float) $lines->where('challan_id', $c->id)->sum(fn ($l) => $open($fig[$l->id])), 4)),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function sourceOf(SsSalesOrder $o, ?string $exceptChallan): array
    {
        $fig = $this->book()->sizeFigures([$o->id], $exceptChallan);
        $sizes = DB::table('ss_sales_order_sizes')->where('order_id', $o->id)->orderBy('sort_order')->get(['id', 'qty', 'rate']);
        $labels = $this->book()->sizeLabels($sizes->pluck('id')->all());
        $pct = (float) $o->additional_qty_pct;

        return [
            'so_no' => $o->so_no, 'customer_id' => $o->delivery_to_id ?: $o->customer_id, 'currency_id' => $o->currency_id, 'conv_rate' => (float) $o->conv_rate,
            'delivery_target_date' => $o->delivery_target_date->toDateString(), 'mode_of_shipment' => $o->mode_of_shipment, 'pr_po_ref' => $o->pr_po_ref, 'overage_pct' => $pct,
            'lines' => $sizes->map(function ($s) use ($fig, $labels, $pct) {
                $f = $fig[$s->id];
                $prev = $f['held'] - $f['returned'];

                return ['size_id' => $s->id, 'label' => $labels[$s->id] ?? '—', 'ordered' => (float) $s->qty, 'prev' => $prev, 'balance' => max(0.0, round($s->qty - $prev, 4)),
                    'allowed' => max(0.0, round($s->qty * (1 + $pct / 100) - $prev, 4)), 'rate' => (float) $s->rate];
            })->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function returnSourceOf(SsChallan $c, ?string $exceptReturn): array
    {
        $lines = SsChallanLine::query()->where('challan_id', $c->id)->orderBy('sort_order')->get();
        $fig = $this->book()->lineFigures($lines->pluck('id')->all(), $exceptReturn);
        $labels = $this->book()->sizeLabels($lines->pluck('size_id')->all());
        $uoms = DB::table('ss_sales_order_sizes as s')->join('ss_sales_order_lines as l', 'l.id', '=', 's.line_id')->whereIn('s.id', $lines->pluck('size_id'))->pluck('l.uom', 's.id');
        $o = SsSalesOrder::query()->findOrFail($c->order_id);

        return [
            'chalan_no' => $c->chalan_no, 'order_info' => implode(' / ', array_filter([$o->so_no, $o->po_wo_no, $this->book()->customerNames()[$c->customer_id] ?? null])),
            'lines' => $lines->map(fn ($l) => ['challan_line_id' => $l->id, 'label' => ($o->so_no.' · '.($labels[$l->size_id] ?? '—')), 'unit' => $uoms[$l->size_id] ?? null,
                'delivered' => (float) $l->qty, 'prev' => $fig[$l->id]['returned'], 'returnable' => max(0.0, round($l->qty - $fig[$l->id]['returned'], 4))])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function present(SsChallan $c): array
    {
        $b = $this->book();
        $o = SsSalesOrder::query()->findOrFail($c->order_id);
        $lines = SsChallanLine::query()->where('challan_id', $c->id)->orderBy('sort_order')->get();
        $labels = $b->sizeLabels($lines->pluck('size_id')->all());

        return [
            ...collect($c->only(['chalan_no', 'gate_pass_no', 'customer_id', 'store_id', 'mode_of_shipment', 'currency_id', 'carrier_name', 'carrier_no', 'pr_po_ref', 'remarks', 'state']))->all(),
            'so_no' => $o->so_no, 'po_wo_no' => $o->po_wo_no, 'chalan_date' => $c->chalan_date->toDateString(), 'delivery_target_date' => $c->delivery_target_date->toDateString(), 'conv_rate' => (float) $c->conv_rate,
            'is_revised' => (bool) $c->is_revised, 'customer' => $b->customerNames()[$c->customer_id] ?? '—', 'address' => $b->addresses()[$c->customer_id] ?? '', 'store' => $b->stores()[$c->store_id] ?? '—',
            'currency' => $b->currencyCodes()[$c->currency_id] ?? '—', 'company' => $b->company(),
            'submitted' => $b->trail($c->submitted_by, $c->submitted_at), 'delivered' => $b->trail($c->delivered_by, $c->delivered_at),
            'lines' => $lines->map(fn ($l) => ['size_id' => $l->size_id, 'label' => $labels[$l->size_id] ?? '—', 'qty' => (float) $l->qty, 'rate' => (float) $l->rate, 'remarks' => $l->remarks])->values()->all(),
            'source' => $c->state === 'DRAFT' ? $this->sourceOf($o, $c->id) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function presentReturn(SsChallanReturn $r): array
    {
        $b = $this->book();
        $c = SsChallan::query()->findOrFail($r->challan_id);
        $src = $this->returnSourceOf($c, $r->id);
        $lines = SsChallanReturnLine::query()->where('return_id', $r->id)->get();
        $by = collect($src['lines'])->keyBy('challan_line_id');

        return [
            'return_no' => $r->return_no, 'chalan_no' => $c->chalan_no, 'order_info' => $src['order_info'], 'return_date' => $r->return_date->toDateString(), 'reason' => $r->reason,
            'remarks' => $r->remarks, 'state' => $r->state, 'confirmed' => $b->trail($r->confirmed_by, $r->confirmed_at), 'company' => $b->company(),
            'customer' => $b->customerNames()[$c->customer_id] ?? '—',
            'lines' => $lines->map(fn ($l) => ['challan_line_id' => $l->challan_line_id, 'label' => $by[$l->challan_line_id]['label'] ?? '—', 'unit' => $by[$l->challan_line_id]['unit'] ?? null,
                'qty' => (float) $l->qty, 'remarks' => $l->remarks])->values()->all(),
            'source' => $src,
        ];
    }
}

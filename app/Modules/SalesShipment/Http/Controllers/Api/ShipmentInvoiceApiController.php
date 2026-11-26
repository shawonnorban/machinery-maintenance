<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Http\Controllers\Api;

use App\Modules\SalesShipment\Models\SsInvoice;
use App\Modules\SalesShipment\Models\SsInvoiceLine;
use App\Modules\SalesShipment\Models\SsPackingLine;
use App\Modules\SalesShipment\Models\SsPackingList;
use App\Modules\SalesShipment\Models\SsSalesOrder;
use App\Modules\SalesShipment\Models\SsShipment;
use App\Modules\SalesShipment\Support\SalesBook;
use App\Modules\SalesShipment\Support\ShipmentBook;
use App\Modules\SalesShipment\Support\SsSettings;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sales & Shipment → Shipment (AnnotechERP 21/05): the Shipment Invoice —
 * the shipment's lines billed (never more than the shipment carries less
 * other live invoices), consignee, notify party, shipping and terms; and
 * the Packing List — carton runs per shipment line (cartons = to − from + 1,
 * pieces = cartons × pieces per carton; ranges from 1, never overlapping;
 * gross not below net; never packed beyond the shipment line). Both are
 * drafted, finalized (counting towards the shipment's readiness) or
 * cancelled, and only while the shipment is unconfirmed.
 */
class ShipmentInvoiceApiController extends ApiController
{
    private ?SalesBook $book = null;

    /** The Shipment List on the left: open shipments with what is still to invoice (or pack). */
    public function shipments(Request $request): JsonResponse
    {
        $this->allow('salesshipment.shipment.view');
        $pack = $request->query('for') === 'packing';
        $rows = SsShipment::query()->whereIn('stage', SsShipment::OPEN)->orderBy('shipment_date')->orderBy('shipment_no')->get();
        $lines = DB::table('ss_shipment_lines')->whereIn('shipment_id', $rows->pluck('id'))->get(['id', 'shipment_id', 'qty']);
        $done = $pack ? ShipmentBook::packed($lines->pluck('id')->all()) : ShipmentBook::invoiced($lines->pluck('id')->all());
        $orders = DB::table('ss_sales_orders')->whereIn('id', $rows->pluck('order_id'))->get(['id', 'so_no', 'customer_id'])->keyBy('id');
        $names = $this->book()->customerNames();

        return ApiResponse::ok($rows->map(fn (SsShipment $s) => [
            'shipment_no' => $s->shipment_no, 'so_no' => $orders[$s->order_id]->so_no ?? '—', 'buyer' => $names[$orders[$s->order_id]->customer_id ?? ''] ?? '—', 'customer_id' => $orders[$s->order_id]->customer_id ?? null,
            'ship_date' => $s->shipment_date->toDateString(), 'due' => max(0.0, round((float) $lines->where('shipment_id', $s->id)->sum(fn ($l) => $l->qty - $done[$l->id]), 4)),
        ])->values()->all());
    }

    /* ── Invoices ──────────────────────────────────────────────────── */

    public function invoices(): JsonResponse
    {
        $this->allow('salesshipment.shipment.view');
        $rows = SsInvoice::query()->orderByDesc('invoice_date')->orderByDesc('invoice_no')->get();
        $ships = $this->shipments_($rows->pluck('shipment_id')->all());
        $lines = DB::table('ss_invoice_lines')->whereIn('invoice_id', $rows->pluck('id'))->get(['invoice_id', 'qty', 'unit_price', 'carton_qty'])->groupBy('invoice_id');
        $codes = $this->book()->currencyCodes();
        $names = $this->book()->customerNames();

        return ApiResponse::ok($rows->map(function (SsInvoice $i) use ($ships, $lines, $codes, $names) {
            $mine = $lines->get($i->id, collect());

            return [
                'invoice_no' => $i->invoice_no, 'invoice_date' => $i->invoice_date->toDateString(), 'shipment_no' => $ships[$i->shipment_id]->shipment_no ?? '—', 'so_no' => $ships[$i->shipment_id]->so_no ?? '—',
                'buyer' => $names[$ships[$i->shipment_id]->customer_id ?? ''] ?? '—', 'customer_id' => $ships[$i->shipment_id]->customer_id ?? null, 'consignee' => strtok((string) $i->consignee, "\n"),
                'currency' => $codes[$i->currency_id] ?? '—', 'currency_id' => $i->currency_id, 'total_qty' => (float) $mine->sum('qty'), 'total_amount' => round((float) $mine->sum(fn ($l) => $l->qty * $l->unit_price), 2),
                'cartons' => (int) $mine->sum('carton_qty'), 'commercial_invoice' => $i->commercial_invoice_ref, 'state' => $i->state,
            ];
        })->values()->all());
    }

    public function invoice(string $no): JsonResponse
    {
        $this->allow('salesshipment.shipment.view');

        return ApiResponse::ok($this->presentInvoice(SsInvoice::query()->where('invoice_no', $no)->firstOrFail()));
    }

    public function invoiceSource(Request $request, string $shipment): JsonResponse
    {
        $this->allow('salesshipment.shipment.view');
        $s = SsShipment::query()->where('shipment_no', $shipment)->firstOrFail();
        $except = $request->query('except') ? SsInvoice::query()->where('invoice_no', $request->query('except'))->value('id') : null;

        return ApiResponse::ok($this->invoiceSourceOf($s, $except));
    }

    public function saveInvoice(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('salesshipment.shipment.manage');
        $row = $no ? SsInvoice::query()->where('invoice_no', $no)->firstOrFail() : null;
        if ($row && $row->state !== 'DRAFT') {
            throw ValidationException::withMessages(['invoice_no' => __('salesshipment.v.locked', ['no' => $row->invoice_no, 'status' => __('salesshipment.si.state.'.$row->state)])]);
        }
        $c = $this->caller()->companyId;
        $data = $request->validate([
            'shipment_no' => ['required', 'string', 'max:24'], 'invoice_date' => ['required', 'date'], 'currency_id' => ['required', Rule::exists('currencies', 'id')->where('company_id', $c)],
            'commercial_invoice_ref' => ['nullable', 'string', 'max:40'], 'consignee' => ['required', 'string', 'max:2000'], 'notify_party' => ['nullable', 'string', 'max:2000'],
            'country_id' => ['nullable', Rule::exists('origin_countries', 'id')->where('company_id', $c)], 'port' => ['nullable', 'string', 'max:80'], 'shipment_mode' => ['required', Rule::in(SsShipment::MODES)],
            'incoterm' => ['nullable', Rule::in(SsSalesOrder::INCOTERMS)], 'payment_terms' => ['nullable', 'string', 'max:120'], 'lc_contract_no' => ['nullable', 'string', 'max:60'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['nullable', 'array'], 'lines.*.shipment_line_id' => ['required', 'string', 'max:26', 'distinct'], 'lines.*.description' => ['nullable', 'string', 'max:300'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0'], 'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'], 'lines.*.carton_qty' => ['nullable', 'integer', 'min:0'],
            'lines.*.net_weight' => ['nullable', 'numeric', 'min:0'], 'lines.*.gross_weight' => ['nullable', 'numeric', 'min:0'],
        ], ['consignee.required' => __('salesshipment.v.si_consignee'), 'lines.*.shipment_line_id.distinct' => __('salesshipment.v.line_twice')]);
        $s = $this->unconfirmed($data['shipment_no']);
        if ($row && $row->shipment_id !== $s->id) {
            throw ValidationException::withMessages(['shipment_no' => __('salesshipment.v.si_shipment_fixed', ['no' => $row->invoice_no])]);
        }
        $this->book()->assertOpen($data['invoice_date'], 'invoice_date');
        $lines = collect(array_values($data['lines'] ?? []))->filter(fn ($l) => (float) ($l['qty'] ?? 0) > 0)->values();
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('salesshipment.v.si_lines')]);
        }
        $src = collect($this->invoiceSourceOf($s, $row?->id)['lines'])->keyBy('shipment_line_id');
        foreach ($lines as $i => $l) {
            $x = $src[$l['shipment_line_id']] ?? null;
            if (! $x) {
                throw ValidationException::withMessages(["lines.{$i}.shipment_line_id" => __('salesshipment.v.shp_line_unknown', ['no' => $s->shipment_no])]);
            }
            if ((float) $l['qty'] > $x['balance'] + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __('salesshipment.v.si_over', ['line' => $x['label'], 'max' => $this->num($x['balance'])])]);
            }
        }
        $head = ['shipment_id' => $s->id, ...collect($data)->except(['shipment_no', 'lines'])->all()];
        $row = DB::transaction(function () use ($row, $head, $lines, $src, $data) {
            if ($row) {
                $row->update($head);
                SsInvoiceLine::query()->where('invoice_id', $row->id)->delete();
            } else {
                $row = SsInvoice::query()->create($head + ['invoice_no' => SsSettings::nextNo('prefix_invoice', 'ss_invoices', 'invoice_no', $data['invoice_date'], $this->caller()->companyId),
                    'state' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach ($lines as $i => $l) {
                $x = $src[$l['shipment_line_id']];
                SsInvoiceLine::query()->create(['invoice_id' => $row->id, 'shipment_line_id' => $l['shipment_line_id'], 'description' => ($l['description'] ?? null) ?: $x['label'], 'qty' => $l['qty'],
                    'unit_price' => $l['unit_price'] ?? $x['rate'], 'carton_qty' => $l['carton_qty'] ?? 0, 'net_weight' => $l['net_weight'] ?? null, 'gross_weight' => $l['gross_weight'] ?? null, 'sort_order' => $i]);
            }

            return $row;
        });

        return ApiResponse::ok($this->presentInvoice($row->refresh()), [], $no ? 200 : 201);
    }

    public function invoiceAct(string $no, string $action): JsonResponse
    {
        return $this->act(SsInvoice::class, 'invoice_no', $no, $action, 'si');
    }

    public function invoiceDestroy(string $no): JsonResponse
    {
        return $this->destroy(SsInvoice::class, 'invoice_no', $no);
    }

    /* ── Packing lists ─────────────────────────────────────────────── */

    public function packings(): JsonResponse
    {
        $this->allow('salesshipment.shipment.view');
        $rows = SsPackingList::query()->orderByDesc('packing_date')->orderByDesc('packing_no')->get();
        $ships = $this->shipments_($rows->pluck('shipment_id')->all());
        $inv = DB::table('ss_invoices')->whereIn('id', $rows->pluck('invoice_id')->filter())->pluck('invoice_no', 'id');
        $lines = DB::table('ss_packing_lines')->whereIn('packing_list_id', $rows->pluck('id'))->get()->groupBy('packing_list_id');
        $names = $this->book()->customerNames();

        return ApiResponse::ok($rows->map(function (SsPackingList $p) use ($ships, $inv, $lines, $names) {
            $mine = $lines->get($p->id, collect());

            return [
                'packing_no' => $p->packing_no, 'packing_date' => $p->packing_date->toDateString(), 'shipment_no' => $ships[$p->shipment_id]->shipment_no ?? '—', 'invoice_no' => $p->invoice_id ? ($inv[$p->invoice_id] ?? null) : null,
                'buyer' => $names[$ships[$p->shipment_id]->customer_id ?? ''] ?? '—', 'customer_id' => $ships[$p->shipment_id]->customer_id ?? null,
                'cartons' => (int) $mine->sum(fn ($l) => $l->carton_to - $l->carton_from + 1), 'pcs' => (float) $mine->sum(fn ($l) => ($l->carton_to - $l->carton_from + 1) * $l->pcs_per_carton),
                'net_weight' => (float) $mine->sum('net_weight'), 'gross_weight' => (float) $mine->sum('gross_weight'), 'cbm' => (float) $mine->sum('cbm'), 'state' => $p->state,
            ];
        })->values()->all());
    }

    public function packing(string $no): JsonResponse
    {
        $this->allow('salesshipment.shipment.view');

        return ApiResponse::ok($this->presentPacking(SsPackingList::query()->where('packing_no', $no)->firstOrFail()));
    }

    public function packingSource(Request $request, string $shipment): JsonResponse
    {
        $this->allow('salesshipment.shipment.view');
        $s = SsShipment::query()->where('shipment_no', $shipment)->firstOrFail();
        $except = $request->query('except') ? SsPackingList::query()->where('packing_no', $request->query('except'))->value('id') : null;

        return ApiResponse::ok($this->packingSourceOf($s, $except));
    }

    public function savePacking(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('salesshipment.shipment.manage');
        $row = $no ? SsPackingList::query()->where('packing_no', $no)->firstOrFail() : null;
        if ($row && $row->state !== 'DRAFT') {
            throw ValidationException::withMessages(['packing_no' => __('salesshipment.v.locked', ['no' => $row->packing_no, 'status' => __('salesshipment.si.state.'.$row->state)])]);
        }
        $data = $request->validate([
            'shipment_no' => ['required', 'string', 'max:24'], 'packing_date' => ['required', 'date'], 'invoice_no' => ['nullable', 'string', 'max:24'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['nullable', 'array'], 'lines.*.shipment_line_id' => ['required', 'string', 'max:26'], 'lines.*.color' => ['nullable', 'string', 'max:120'], 'lines.*.size' => ['nullable', 'string', 'max:60'],
            'lines.*.carton_from' => ['required', 'integer', 'min:1'], 'lines.*.carton_to' => ['required', 'integer', 'min:1'], 'lines.*.pcs_per_carton' => ['required', 'numeric', 'gt:0'],
            'lines.*.net_weight' => ['nullable', 'numeric', 'min:0'], 'lines.*.gross_weight' => ['nullable', 'numeric', 'min:0'], 'lines.*.cbm' => ['nullable', 'numeric', 'min:0'],
        ], ['lines.*.carton_from.min' => __('salesshipment.v.pl_from'), 'lines.*.carton_from.required' => __('salesshipment.v.pl_from'), 'lines.*.carton_to.required' => __('salesshipment.v.pl_to'),
            'lines.*.pcs_per_carton.required' => __('salesshipment.v.pl_pcs'), 'lines.*.pcs_per_carton.gt' => __('salesshipment.v.pl_pcs')]);
        $s = $this->unconfirmed($data['shipment_no']);
        if ($row && $row->shipment_id !== $s->id) {
            throw ValidationException::withMessages(['shipment_no' => __('salesshipment.v.si_shipment_fixed', ['no' => $row->packing_no])]);
        }
        $invoiceId = null;
        if (! empty($data['invoice_no'])) {
            $invoiceId = DB::table('ss_invoices')->where('invoice_no', $data['invoice_no'])->where('shipment_id', $s->id)->where('state', '!=', 'CANCELLED')->value('id');
            if (! $invoiceId) {
                throw ValidationException::withMessages(['invoice_no' => __('salesshipment.v.dh_paper', ['no' => $data['invoice_no'], 'shipment' => $s->shipment_no])]);
            }
        }
        $this->book()->assertOpen($data['packing_date'], 'packing_date');
        $lines = collect(array_values($data['lines'] ?? []));
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('salesshipment.v.pl_lines')]);
        }
        $src = collect($this->packingSourceOf($s, $row?->id)['lines'])->keyBy('shipment_line_id');
        $ranges = [];
        $pcs = [];
        foreach ($lines as $i => $l) {
            $n = $i + 1;
            if (! isset($src[$l['shipment_line_id']])) {
                throw ValidationException::withMessages(["lines.{$i}.shipment_line_id" => __('salesshipment.v.shp_line_unknown', ['no' => $s->shipment_no])]);
            }
            if ((int) $l['carton_to'] < (int) $l['carton_from']) {
                throw ValidationException::withMessages(["lines.{$i}.carton_to" => __('salesshipment.v.pl_range', ['row' => $n])]);
            }
            foreach ($ranges as [$from, $to, $other]) {
                if ((int) $l['carton_from'] <= $to && (int) $l['carton_to'] >= $from) {
                    throw ValidationException::withMessages(["lines.{$i}.carton_from" => __('salesshipment.v.pl_overlap', ['row' => $n, 'other' => $other])]);
                }
            }
            $ranges[] = [(int) $l['carton_from'], (int) $l['carton_to'], $n];
            if ((float) ($l['gross_weight'] ?? 0) + 0.0005 < (float) ($l['net_weight'] ?? 0)) {
                throw ValidationException::withMessages(["lines.{$i}.gross_weight" => __('salesshipment.v.pl_gross', ['row' => $n])]);
            }
            $pcs[$l['shipment_line_id']] = ($pcs[$l['shipment_line_id']] ?? 0) + ((int) $l['carton_to'] - (int) $l['carton_from'] + 1) * (float) $l['pcs_per_carton'];
        }
        foreach ($pcs as $id => $q) {
            if ($q > $src[$id]['balance'] + 0.0005) {
                throw ValidationException::withMessages(['lines' => __('salesshipment.v.pl_over', ['line' => $src[$id]['label'], 'max' => $this->num($src[$id]['balance']), 'qty' => $this->num($q)])]);
            }
        }
        $head = ['shipment_id' => $s->id, 'invoice_id' => $invoiceId, 'packing_date' => $data['packing_date'], 'remarks' => $data['remarks'] ?? null];
        $row = DB::transaction(function () use ($row, $head, $lines, $data) {
            if ($row) {
                $row->update($head);
                SsPackingLine::query()->where('packing_list_id', $row->id)->delete();
            } else {
                $row = SsPackingList::query()->create($head + ['packing_no' => SsSettings::nextNo('prefix_packing_list', 'ss_packing_lists', 'packing_no', $data['packing_date'], $this->caller()->companyId),
                    'state' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach ($lines as $i => $l) {
                SsPackingLine::query()->create(['packing_list_id' => $row->id, ...collect($l)->only(['shipment_line_id', 'color', 'size', 'carton_from', 'carton_to', 'pcs_per_carton'])->all(),
                    'net_weight' => $l['net_weight'] ?? 0, 'gross_weight' => $l['gross_weight'] ?? 0, 'cbm' => $l['cbm'] ?? 0, 'sort_order' => $i]);
            }

            return $row;
        });

        return ApiResponse::ok($this->presentPacking($row->refresh()), [], $no ? 200 : 201);
    }

    public function packingAct(string $no, string $action): JsonResponse
    {
        return $this->act(SsPackingList::class, 'packing_no', $no, $action, 'pl');
    }

    public function packingDestroy(string $no): JsonResponse
    {
        return $this->destroy(SsPackingList::class, 'packing_no', $no);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    private function book(): SalesBook
    {
        return $this->book ??= new SalesBook($this->caller()->companyId);
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }

    /** Finalize (counting towards readiness) or Cancel (releasing it) — only while the shipment is unconfirmed. */
    private function act(string $model, string $col, string $no, string $action, string $prefix): JsonResponse
    {
        $from = ['finalize' => [['DRAFT'], 'FINAL'], 'cancel' => [['DRAFT', 'FINAL'], 'CANCELLED']];
        abort_unless(isset($from[$action]), 404);
        $this->allow('salesshipment.shipment.manage');
        $row = DB::transaction(function () use ($model, $col, $no, $action, $from, $prefix) {
            $row = $model::query()->where($col, $no)->lockForUpdate()->firstOrFail();
            if (! in_array($row->state, $from[$action][0], true)) {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.step', ['no' => $row->{$col}, 'status' => __("salesshipment.{$prefix}.state.".$row->state)])]);
            }
            $s = SsShipment::query()->findOrFail($row->shipment_id);
            if (! in_array($s->stage, SsShipment::OPEN, true)) {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.shp_not_open', ['no' => $s->shipment_no, 'stage' => __('salesshipment.sp.stage.'.$s->stage)])]);
            }
            $row->update(['state' => $from[$action][1]]);
            ShipmentBook::restage($s);

            return $row;
        });

        return ApiResponse::ok([$col => $row->{$col}, 'state' => $row->state]);
    }

    private function destroy(string $model, string $col, string $no): JsonResponse
    {
        $this->allow('salesshipment.shipment.manage');
        $row = $model::query()->where($col, $no)->firstOrFail();
        if ($row->state !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('salesshipment.v.delete_draft', ['no' => $no])]);
        }
        $row->delete();

        return ApiResponse::ok(['deleted' => $no]);
    }

    private function unconfirmed(string $no): SsShipment
    {
        $s = SsShipment::query()->where('shipment_no', $no)->firstOrFail();
        if (! in_array($s->stage, SsShipment::OPEN, true)) {
            throw ValidationException::withMessages(['shipment_no' => __('salesshipment.v.shp_not_open', ['no' => $s->shipment_no, 'stage' => __('salesshipment.sp.stage.'.$s->stage)])]);
        }

        return $s;
    }

    /** @return Collection<string, object> */
    private function shipments_(array $ids)
    {
        return DB::table('ss_shipments as s')->join('ss_sales_orders as o', 'o.id', '=', 's.order_id')->whereIn('s.id', $ids)->get(['s.id', 's.shipment_no', 'o.so_no', 'o.customer_id'])->keyBy('id');
    }

    /** @return array<string, mixed> */
    private function invoiceSourceOf(SsShipment $s, ?string $exceptInvoice): array
    {
        $o = SsSalesOrder::query()->findOrFail($s->order_id);
        $lines = DB::table('ss_shipment_lines')->where('shipment_id', $s->id)->orderBy('sort_order')->get(['id', 'qty', 'rate', 'carton_qty']);
        $done = ShipmentBook::invoiced($lines->pluck('id')->all(), $exceptInvoice);
        $labels = ShipmentBook::labels($this->book(), $lines->pluck('id')->all());
        $b = $this->book();

        return [
            'shipment_no' => $s->shipment_no, 'currency_id' => $o->currency_id, 'consignee' => trim(($b->customerNames()[$o->delivery_to_id] ?? '')."\n".($b->addresses()[$o->delivery_to_id] ?? '')),
            'country_id' => $s->destination_country_id, 'port' => $s->destination_port, 'shipment_mode' => $s->shipment_mode, 'incoterm' => $s->incoterm, 'payment_terms' => $o->payment_term,
            'lines' => $lines->map(fn ($l) => ['shipment_line_id' => $l->id, 'label' => $labels[$l->id] ?? '—', 'shipment_qty' => (float) $l->qty, 'prev' => $done[$l->id],
                'balance' => max(0.0, round($l->qty - $done[$l->id], 4)), 'rate' => (float) $l->rate, 'cartons' => (int) $l->carton_qty])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function packingSourceOf(SsShipment $s, ?string $exceptPacking): array
    {
        $lines = DB::table('ss_shipment_lines as l')->join('ss_sales_order_sizes as z', 'z.id', '=', 'l.size_id')->join('ss_sales_order_lines as ol', 'ol.id', '=', 'z.line_id')
            ->where('l.shipment_id', $s->id)->orderBy('l.sort_order')->get(['l.id', 'l.qty', 'z.size', 'ol.gmt_color']);
        $done = ShipmentBook::packed($lines->pluck('id')->all(), $exceptPacking);
        $labels = ShipmentBook::labels($this->book(), $lines->pluck('id')->all());

        return [
            'shipment_no' => $s->shipment_no,
            'invoices' => DB::table('ss_invoices')->where('shipment_id', $s->id)->where('state', '!=', 'CANCELLED')->orderBy('invoice_no')->pluck('invoice_no')->all(),
            'lines' => $lines->map(fn ($l) => ['shipment_line_id' => $l->id, 'label' => $labels[$l->id] ?? '—', 'color' => $l->gmt_color, 'size' => $l->size, 'shipment_qty' => (float) $l->qty,
                'prev' => $done[$l->id], 'balance' => max(0.0, round($l->qty - $done[$l->id], 4))])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentInvoice(SsInvoice $i): array
    {
        $b = $this->book();
        $s = SsShipment::query()->findOrFail($i->shipment_id);
        $o = SsSalesOrder::query()->findOrFail($s->order_id);
        $lines = SsInvoiceLine::query()->where('invoice_id', $i->id)->orderBy('sort_order')->get();
        $labels = ShipmentBook::labels($b, $lines->pluck('shipment_line_id')->all());

        return [
            ...collect($i->only(['invoice_no', 'currency_id', 'commercial_invoice_ref', 'consignee', 'notify_party', 'country_id', 'port', 'shipment_mode', 'incoterm', 'payment_terms', 'lc_contract_no', 'remarks', 'state']))->all(),
            'shipment_no' => $s->shipment_no, 'so_no' => $o->so_no, 'po' => $o->po_wo_no, 'buyer' => $b->customerNames()[$o->customer_id] ?? '—', 'invoice_date' => $i->invoice_date->toDateString(),
            'currency' => $b->currencyCodes()[$i->currency_id] ?? '—', 'country' => $i->country_id ? ($b->countries()[$i->country_id] ?? null) : null, 'company' => $b->company(),
            'lines' => $lines->map(fn ($l) => ['shipment_line_id' => $l->shipment_line_id, 'label' => $labels[$l->shipment_line_id] ?? '—', 'description' => $l->description, 'qty' => (float) $l->qty,
                'unit_price' => (float) $l->unit_price, 'carton_qty' => (int) $l->carton_qty, 'net_weight' => $l->net_weight === null ? null : (float) $l->net_weight,
                'gross_weight' => $l->gross_weight === null ? null : (float) $l->gross_weight])->values()->all(),
            'source' => $i->state === 'DRAFT' ? $this->invoiceSourceOf($s, $i->id) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function presentPacking(SsPackingList $p): array
    {
        $b = $this->book();
        $s = SsShipment::query()->findOrFail($p->shipment_id);
        $o = SsSalesOrder::query()->findOrFail($s->order_id);
        $lines = SsPackingLine::query()->where('packing_list_id', $p->id)->orderBy('sort_order')->get();
        $labels = ShipmentBook::labels($b, $lines->pluck('shipment_line_id')->all());

        return [
            'packing_no' => $p->packing_no, 'shipment_no' => $s->shipment_no, 'so_no' => $o->so_no, 'buyer' => $b->customerNames()[$o->customer_id] ?? '—', 'packing_date' => $p->packing_date->toDateString(),
            'invoice_no' => $p->invoice_id ? DB::table('ss_invoices')->where('id', $p->invoice_id)->value('invoice_no') : null, 'remarks' => $p->remarks, 'state' => $p->state, 'company' => $b->company(),
            'lines' => $lines->map(fn ($l) => ['shipment_line_id' => $l->shipment_line_id, 'label' => $labels[$l->shipment_line_id] ?? '—', 'color' => $l->color, 'size' => $l->size, 'carton_from' => $l->carton_from,
                'carton_to' => $l->carton_to, 'pcs_per_carton' => (float) $l->pcs_per_carton, 'net_weight' => (float) $l->net_weight, 'gross_weight' => (float) $l->gross_weight, 'cbm' => (float) $l->cbm])->values()->all(),
            'source' => $p->state === 'DRAFT' ? $this->packingSourceOf($s, $p->id) : $this->packingSourceOf($s, $p->id),
        ];
    }
}

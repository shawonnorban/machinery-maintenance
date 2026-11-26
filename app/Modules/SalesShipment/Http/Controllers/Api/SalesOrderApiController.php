<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\SalesShipment\Models\SsSalesOrder;
use App\Modules\SalesShipment\Models\SsSalesOrderLine;
use App\Modules\SalesShipment\Models\SsSalesOrderParticular;
use App\Modules\SalesShipment\Models\SsSalesOrderSize;
use App\Modules\SalesShipment\Models\SsScProgramme;
use App\Modules\SalesShipment\Models\SsScProgrammeLine;
use App\Modules\SalesShipment\Support\SalesBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sales & Shipment → Order & Delivery (AnnotechERP 21/02): Sales Order Entry
 * — the Sales Order List, the entry (master data, terms, shipment, the item
 * lines with the sizes and particulars under them, saved together while a
 * draft) and the S/C Programme (an order's work put out to a sub-contractor);
 * Order Confirmation — the approval chain, submitted → checked → approved →
 * confirmed, returned to draft or closed; and Delivery Completion — each
 * confirmed order's delivered, returned, net delivered and balance.
 */
class SalesOrderApiController extends ApiController
{
    private ?SalesBook $book = null;

    /** What the papers pick from. */
    public function options(): JsonResponse
    {
        $this->allowEither('salesshipment.order.view', 'salesshipment.delivery.view', 'salesshipment.bill.view', 'salesshipment.subcon.view', 'salesshipment.shipment.view');
        $b = $this->book();
        $opt = fn (array $map) => collect($map)->map(fn ($label, $id) => ['value' => (string) $id, 'label' => $label])->values()->all();
        $addresses = $b->addresses();

        return ApiResponse::ok([
            'company' => $b->company(),
            'customers' => collect($b->customers())->map(fn ($label, $id) => ['value' => $id, 'label' => $label, 'address' => $addresses[$id] ?? ''])->values()->all(),
            'currencies' => $opt($b->currencies()),
            'sections' => $opt($b->sections()),
            'items' => $opt($b->items()),
            'stores' => $opt($b->stores()),
            'countries' => $opt($b->countries()),
            'particulars' => $opt($b->particulars()),
            'uoms' => array_map(fn ($u) => ['value' => $u['code'], 'label' => $u['code'].' — '.$u['name']], UomCodes::options($this->caller()->companyId)),
            'so_types' => SsSalesOrder::SO_TYPES, 'pay_methods' => SsSalesOrder::PAY_METHODS, 'pay_terms' => SsSalesOrder::PAY_TERMS,
            'ship_modes' => SsSalesOrder::SHIP_MODES, 'incoterms' => SsSalesOrder::INCOTERMS,
        ]);
    }

    /* ── Sales Order List / Entry ──────────────────────────────────── */

    public function index(): JsonResponse
    {
        $this->allow('salesshipment.order.view');

        return ApiResponse::ok($this->rows(SsSalesOrder::query()->orderByDesc('receive_date')->orderByDesc('so_no')->get()));
    }

    public function show(string $no): JsonResponse
    {
        $this->allowEither('salesshipment.order.view', 'salesshipment.delivery.view');

        return ApiResponse::ok($this->present($this->find($no)));
    }

    /** The order, its lines, sizes and particulars together: new, or rewritten while still a draft. */
    public function save(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('salesshipment.order.manage');
        $row = $no ? $this->find($no) : null;
        if ($row && $row->approval_status !== 'DRAFT') {
            throw ValidationException::withMessages(['so_no' => __('salesshipment.v.so_locked', ['no' => $row->so_no, 'status' => __('salesshipment.od.approval.'.$row->approval_status)])]);
        }
        $c = $this->caller()->companyId;
        $party = Rule::exists('mp_suppliers', 'id')->where('company_id', $c);
        $data = $request->validate([
            'search_for' => ['required', Rule::in(['PURCHASE_ORDER', 'SALES_ORDER'])], 'is_inter_company' => ['boolean'], 'is_embroidery' => ['boolean'],
            'so_type' => ['required', Rule::in(SsSalesOrder::SO_TYPES)], 'customer_id' => ['required', $party], 'receive_date' => ['required', 'date'],
            'program_by' => ['required', 'string', 'max:120'], 'program_to' => ['required', 'string', 'max:120'], 'bill_to_id' => ['required', $party],
            'delivery_target_date' => ['required', 'date', 'after_or_equal:receive_date'], 'same_as_bill_to' => ['boolean'],
            'delivery_to_id' => [$request->boolean('same_as_bill_to') ? 'nullable' : 'required', $party], 'delivery_address' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::in(SsSalesOrder::PAY_METHODS)], 'currency_id' => ['required', Rule::exists('currencies', 'id')->where('company_id', $c)],
            'conv_rate' => ['required', 'numeric', 'gt:0'], 'payment_term' => ['nullable', Rule::in(SsSalesOrder::PAY_TERMS)], 'mode_of_shipment' => ['required', Rule::in(SsSalesOrder::SHIP_MODES)],
            'po_wo_no' => ['required', 'string', 'max:60'], 'others_term' => ['nullable', 'string', 'max:2000'], 'pr_po_ref' => ['nullable', 'string', 'max:2000'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'additional_qty_pct' => ['nullable', 'numeric', 'min:0', 'max:100'], 'season' => ['nullable', 'string', 'max:40'], 'department' => ['nullable', 'string', 'max:60'],
            'incoterm' => ['nullable', Rule::in(SsSalesOrder::INCOTERMS)], 'destination_country_id' => ['nullable', Rule::exists('origin_countries', 'id')->where('company_id', $c)],
            'destination_port' => ['nullable', 'string', 'max:80'], 'shipment_date' => ['required', 'date', 'after_or_equal:receive_date'],
            'item_id' => ['nullable', Rule::exists('mp_items', 'id')->where('company_id', $c)], 'is_poly_item' => ['boolean'],
            'lines' => ['required', 'array', 'min:1'], 'lines.*.sl_no' => ['required', 'integer', 'min:1', 'distinct'], 'lines.*.buyer' => ['nullable', 'string', 'max:160'],
            'lines.*.order_ref' => ['nullable', 'string', 'max:120'], 'lines.*.style_ref' => ['nullable', 'string', 'max:120'], 'lines.*.wo_ref' => ['nullable', 'string', 'max:120'],
            'lines.*.section_id' => ['nullable', Rule::exists('sections', 'id')->where('company_id', $c)], 'lines.*.item_id' => ['required', Rule::exists('mp_items', 'id')->where('company_id', $c)],
            'lines.*.gmt_color' => ['nullable', 'string', 'max:120'], 'lines.*.product_color' => ['nullable', 'string', 'max:120'], 'lines.*.rate' => ['required', 'numeric', 'min:0'],
            'lines.*.uom' => ['nullable', UomCodes::rule($c)], 'lines.*.specification' => ['nullable', 'string', 'max:300'], 'lines.*.code' => ['nullable', 'string', 'max:60'],
            'lines.*.gross_weight' => ['nullable', 'numeric', 'min:0'], 'lines.*.net_weight' => ['nullable', 'numeric', 'min:0'],
            'sizes' => ['required', 'array', 'min:1'], 'sizes.*.sl_no' => ['required', 'integer', 'min:1'], 'sizes.*.size' => ['required', 'string', 'max:60'],
            'sizes.*.pcs_per_size' => ['nullable', 'numeric', 'min:0'], 'sizes.*.qty' => ['required', 'numeric', 'gt:0'],
            'particulars' => ['nullable', 'array'], 'particulars.*.sl_no' => ['required', 'integer', 'min:1'],
            'particulars.*.particular_id' => ['required', Rule::exists('ss_particulars', 'id')->where('company_id', $c)], 'particulars.*.value' => ['required', 'string', 'max:200'],
        ], [
            'lines.required' => __('salesshipment.v.so_lines'), 'lines.min' => __('salesshipment.v.so_lines'), 'sizes.required' => __('salesshipment.v.so_sizes'), 'sizes.min' => __('salesshipment.v.so_sizes'),
            'sizes.*.qty.gt' => __('salesshipment.v.qty'), 'lines.*.sl_no.distinct' => __('salesshipment.v.sl_distinct'), 'shipment_date.after_or_equal' => __('salesshipment.v.ship_before_receive'),
            'delivery_target_date.after_or_equal' => __('salesshipment.v.target_before_receive'),
        ]);
        $this->book()->assertOpen($data['receive_date'], 'receive_date');
        foreach (['lines', 'sizes', 'particulars'] as $k) {
            $data[$k] = array_values($data[$k] ?? []);
        }
        $sl = array_column($data['lines'], 'sl_no');
        foreach (['sizes', 'particulars'] as $k) {
            foreach ($data[$k] as $i => $r) {
                if (! in_array($r['sl_no'], $sl, true)) {
                    throw ValidationException::withMessages(["{$k}.{$i}.sl_no" => __('salesshipment.v.sl_unknown', ['sl' => $r['sl_no']])]);
                }
            }
        }
        foreach ($sl as $n) {
            if (! in_array($n, array_column($data['sizes'], 'sl_no'), true)) {
                throw ValidationException::withMessages(['sizes' => __('salesshipment.v.sl_no_size', ['sl' => $n])]);
            }
        }
        $deliveryTo = ! empty($data['same_as_bill_to']) ? $data['bill_to_id'] : $data['delivery_to_id'];
        $head = [
            ...collect($data)->only(['search_for', 'so_type', 'customer_id', 'receive_date', 'program_by', 'program_to', 'bill_to_id', 'delivery_target_date', 'payment_method', 'currency_id', 'conv_rate',
                'payment_term', 'mode_of_shipment', 'po_wo_no', 'others_term', 'pr_po_ref', 'remarks', 'season', 'department', 'incoterm', 'destination_country_id', 'destination_port', 'shipment_date', 'item_id'])->all(),
            'is_inter_company' => (bool) ($data['is_inter_company'] ?? false), 'is_embroidery' => (bool) ($data['is_embroidery'] ?? false), 'is_poly_item' => (bool) ($data['is_poly_item'] ?? false),
            'same_as_bill_to' => (bool) ($data['same_as_bill_to'] ?? false), 'delivery_to_id' => $deliveryTo, 'additional_qty_pct' => (float) ($data['additional_qty_pct'] ?? 0),
            'delivery_address' => ($data['delivery_address'] ?? null) ?: ($this->book()->addresses()[$deliveryTo] ?? null) ?: null,
            'lead_time_days' => (int) CarbonImmutable::parse($data['receive_date'])->diffInDays(CarbonImmutable::parse($data['delivery_target_date'])),
        ];
        $row = DB::transaction(function () use ($row, $head, $data) {
            if ($row) {
                $row->update($head);
                SsSalesOrderLine::query()->where('order_id', $row->id)->delete();
            } else {
                $row = SsSalesOrder::query()->create($head + ['so_no' => $this->book()->nextNo('SO', 'ss_sales_orders', 'so_no', $data['receive_date']), 'approval_status' => 'DRAFT', 'state' => 'OPEN',
                    'created_by' => $this->caller()->auditUserId()]);
            }
            $ids = [];
            foreach ($data['lines'] as $l) {
                $ids[$l['sl_no']] = SsSalesOrderLine::query()->create(['order_id' => $row->id, ...collect($l)->only(['sl_no', 'buyer', 'order_ref', 'style_ref', 'wo_ref', 'section_id', 'item_id', 'gmt_color',
                    'product_color', 'rate', 'specification', 'code', 'gross_weight', 'net_weight'])->all(), 'uom' => UomCodes::normalize($l['uom'] ?? null)]);
            }
            foreach ($data['sizes'] as $i => $s) {
                SsSalesOrderSize::query()->create(['order_id' => $row->id, 'line_id' => $ids[$s['sl_no']]->id, 'size' => $s['size'], 'pcs_per_size' => $s['pcs_per_size'] ?? 1, 'qty' => $s['qty'],
                    'rate' => $ids[$s['sl_no']]->rate, 'sort_order' => $i]);
            }
            foreach ($data['particulars'] as $p) {
                SsSalesOrderParticular::query()->create(['order_id' => $row->id, 'line_id' => $ids[$p['sl_no']]->id, 'particular_id' => $p['particular_id'], 'value' => $p['value']]);
            }

            return $row;
        });

        return ApiResponse::ok($this->present($row->refresh()), [], $no ? 200 : 201);
    }

    /* ── Order Confirmation ────────────────────────────────────────── */

    public function confirmations(): JsonResponse
    {
        $this->allow('salesshipment.order.view');
        $b = $this->book();
        $orders = SsSalesOrder::query()->orderByDesc('receive_date')->orderByDesc('so_no')->get();
        $sizes = DB::table('ss_sales_order_sizes')->whereIn('order_id', $orders->pluck('id'))->get(['order_id', 'qty', 'rate'])->groupBy('order_id');
        $buyers = DB::table('ss_sales_order_lines')->whereIn('order_id', $orders->pluck('id'))->whereNotNull('buyer')->get(['order_id', 'buyer'])->groupBy('order_id');
        $names = $b->customerNames();

        return ApiResponse::ok($orders->map(fn (SsSalesOrder $o) => [
            'so_no' => $o->so_no, 'customer' => $names[$o->customer_id] ?? '—', 'buyers' => $buyers->get($o->id, collect())->pluck('buyer')->unique()->implode(', '), 'customer_id' => $o->customer_id,
            'po_wo_no' => $o->po_wo_no, 'order_qty' => (float) $sizes->get($o->id, collect())->sum('qty'),
            'value' => round((float) $sizes->get($o->id, collect())->sum(fn ($s) => $s->qty * $s->rate), 2),
            'delivery_date' => $o->delivery_target_date->toDateString(), 'shipment_date' => $o->shipment_date->toDateString(), 'receive_date' => $o->receive_date->toDateString(),
            'approval_status' => $o->approval_status, 'state' => $o->state, 'version' => $o->version,
            'submitted' => $b->trail($o->submitted_by, $o->submitted_at), 'checked' => $b->trail($o->checked_by, $o->checked_at),
            'approved' => $b->trail($o->approved_by, $o->approved_at), 'confirmed' => $b->trail($o->confirmed_by, $o->confirmed_at),
        ])->values()->all());
    }

    /** Submit, Check, Approve, Confirm, Return to Draft, Close Order. */
    public function act(string $no, string $action): JsonResponse
    {
        $steps = ['submit' => ['DRAFT', 'SUBMITTED'], 'check' => ['SUBMITTED', 'CHECKED'], 'approve' => ['CHECKED', 'APPROVED'], 'confirm' => ['APPROVED', 'CONFIRMED']];
        abort_unless(isset($steps[$action]) || in_array($action, ['return-to-draft', 'close-order'], true), 404);
        $action === 'submit' ? $this->allow('salesshipment.order.manage') : $this->allow('salesshipment.order.approve');
        $row = DB::transaction(function () use ($no, $action, $steps) {
            $row = SsSalesOrder::query()->where('so_no', $no)->lockForUpdate()->firstOrFail();
            $this->book()->assertOpen($row->receive_date, 'status');
            $by = $this->caller()->auditUserId();
            $from = match ($action) {
                'return-to-draft' => ['SUBMITTED', 'CHECKED', 'APPROVED'],
                'close-order' => ['CONFIRMED'],
                default => [$steps[$action][0]],
            };
            if (! in_array($row->approval_status, $from, true) || $row->state !== 'OPEN') {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.so_step', ['no' => $row->so_no, 'status' => $this->statusText($row)])]);
            }
            if ($action === 'return-to-draft') {
                $row->update(['approval_status' => 'DRAFT', ...array_fill_keys(['submitted_at', 'submitted_by', 'checked_at', 'checked_by', 'approved_at', 'approved_by'], null)]);
            } elseif ($action === 'close-order') {
                if (DB::table('ss_challans')->where('order_id', $row->id)->whereIn('state', ['DRAFT', 'SUBMITTED'])->exists()) {
                    throw ValidationException::withMessages(['status' => __('salesshipment.v.close_open_challans', ['no' => $row->so_no])]);
                }
                if (DB::table('ss_amendments')->where('order_id', $row->id)->whereIn('state', ['DRAFT', 'SUBMITTED', 'APPROVED'])->exists()) {
                    throw ValidationException::withMessages(['status' => __('salesshipment.v.close_open_amendment', ['no' => $row->so_no])]);
                }
                $row->update(['state' => 'CLOSED', 'closed_at' => now(), 'closed_by' => $by]);
            } else {
                $to = $steps[$action][1];
                $col = strtolower($to);
                $row->update(['approval_status' => $to, "{$col}_at" => now(), "{$col}_by" => $by]);
            }

            return $row;
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /* ── Delivery Completion ───────────────────────────────────────── */

    public function completion(): JsonResponse
    {
        $this->allowEither('salesshipment.order.view', 'salesshipment.delivery.view');
        $orders = SsSalesOrder::query()->where('approval_status', 'CONFIRMED')->orderBy('delivery_target_date')->orderBy('so_no')->get();
        $fig = $this->book()->sizeFigures($orders->pluck('id')->all());
        $sizes = DB::table('ss_sales_order_sizes')->whereIn('order_id', $orders->pluck('id'))->get(['id', 'order_id', 'qty'])->groupBy('order_id');
        $styles = DB::table('ss_sales_order_lines')->whereIn('order_id', $orders->pluck('id'))->get(['order_id', 'style_ref'])->groupBy('order_id');
        $names = $this->book()->customerNames();
        $today = CarbonImmutable::now('Asia/Dhaka')->startOfDay();

        return ApiResponse::ok($orders->map(function (SsSalesOrder $o) use ($fig, $sizes, $styles, $names, $today) {
            $mine = $sizes->get($o->id, collect());
            $ordered = (float) $mine->sum('qty');
            $out = (float) $mine->sum(fn ($s) => $fig[$s->id]['out'] ?? 0);
            $back = (float) $mine->sum(fn ($s) => $fig[$s->id]['returned'] ?? 0);
            $net = $out - $back;
            $balance = max(0.0, round($ordered - $net, 4));

            return [
                'so_no' => $o->so_no, 'customer' => $names[$o->customer_id] ?? '—', 'customer_id' => $o->customer_id, 'po_wo_no' => $o->po_wo_no,
                'style' => $styles->get($o->id, collect())->pluck('style_ref')->filter()->unique()->implode(', '),
                'order_qty' => $ordered, 'delivered' => $out, 'returned' => $back, 'net_delivered' => $net, 'balance' => $balance,
                'target' => $o->delivery_target_date->toDateString(), 'days' => (int) $today->diffInDays(CarbonImmutable::parse($o->delivery_target_date->toDateString(), 'Asia/Dhaka'), false),
                'status' => $net <= 0 ? 'PENDING' : ($balance > 0 ? 'PARTIAL' : 'COMPLETED'), 'state' => $o->state,
            ];
        })->values()->all());
    }

    /* ── S/C Programme ─────────────────────────────────────────────── */

    public function programmes(): JsonResponse
    {
        $this->allow('salesshipment.order.view');
        $names = $this->book()->customerNames();
        $orders = DB::table('ss_sales_orders')->pluck('so_no', 'id');

        return ApiResponse::ok(SsScProgramme::query()->orderByDesc('sc_date')->orderByDesc('sc_no')->get()->map(fn (SsScProgramme $p) => [
            'sc_no' => $p->sc_no, 'sc_date' => $p->sc_date->toDateString(), 'so_no' => $orders[$p->order_id] ?? '—', 'party' => $names[$p->party_id] ?? '—', 'process' => $p->process,
        ])->values()->all());
    }

    public function programme(string $no): JsonResponse
    {
        $this->allow('salesshipment.order.view');

        return ApiResponse::ok($this->presentProgramme(SsScProgramme::query()->where('sc_no', $no)->firstOrFail()));
    }

    /** An order's lines as they go out on a programme: ordered, still to be delivered, rate. */
    public function programmeSource(string $so): JsonResponse
    {
        $this->allow('salesshipment.order.view');
        $o = $this->find($so);

        return ApiResponse::ok($this->sourceLines($o));
    }

    public function saveProgramme(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('salesshipment.order.manage');
        $row = $no ? SsScProgramme::query()->where('sc_no', $no)->firstOrFail() : null;
        $c = $this->caller()->companyId;
        $data = $request->validate([
            'so_no' => ['required', 'string', 'max:24'], 'party_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $c)], 'sc_date' => ['required', 'date'],
            'target_date' => ['required', 'date', 'after_or_equal:sc_date'], 'process' => ['required', 'string', 'max:80'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['nullable', 'array'], 'lines.*.size_id' => ['required', 'string', 'max:26'], 'lines.*.qty' => ['nullable', 'numeric', 'min:0'], 'lines.*.rate' => ['nullable', 'numeric', 'min:0'],
        ], ['target_date.after_or_equal' => __('salesshipment.v.target_before_sc')]);
        $order = $this->find($data['so_no']);
        if ($order->approval_status !== 'CONFIRMED' || $order->state !== 'OPEN') {
            throw ValidationException::withMessages(['so_no' => __('salesshipment.v.not_confirmed', ['no' => $order->so_no])]);
        }
        $this->book()->assertOpen($data['sc_date'], 'sc_date');
        $sizes = DB::table('ss_sales_order_sizes')->where('order_id', $order->id)->pluck('qty', 'id');
        $lines = collect(array_values($data['lines'] ?? []))->filter(fn ($l) => (float) ($l['qty'] ?? 0) > 0)->values();
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('salesshipment.v.sc_lines')]);
        }
        foreach ($lines as $i => $l) {
            if (! isset($sizes[$l['size_id']])) {
                throw ValidationException::withMessages(["lines.{$i}.size_id" => __('salesshipment.v.size_not_on_order', ['no' => $order->so_no])]);
            }
            if ((float) $l['qty'] > (float) $sizes[$l['size_id']] + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __('salesshipment.v.sc_over', ['max' => rtrim(rtrim(number_format((float) $sizes[$l['size_id']], 3, '.', ''), '0'), '.')])]);
            }
        }
        $head = ['order_id' => $order->id, ...collect($data)->only(['party_id', 'sc_date', 'target_date', 'process', 'remarks'])->all()];
        $row = DB::transaction(function () use ($row, $head, $lines, $data) {
            if ($row) {
                $row->update($head);
                SsScProgrammeLine::query()->where('programme_id', $row->id)->delete();
            } else {
                $row = SsScProgramme::query()->create($head + ['sc_no' => $this->book()->nextNo('SC', 'ss_sc_programmes', 'sc_no', $data['sc_date']), 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach ($lines as $l) {
                SsScProgrammeLine::query()->create(['programme_id' => $row->id, 'size_id' => $l['size_id'], 'qty' => $l['qty'], 'rate' => $l['rate'] ?? 0]);
            }

            return $row;
        });

        return ApiResponse::ok($this->presentProgramme($row->refresh()), [], $no ? 200 : 201);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    private function book(): SalesBook
    {
        return $this->book ??= new SalesBook($this->caller()->companyId);
    }

    private function find(string $no): SsSalesOrder
    {
        return SsSalesOrder::query()->where('so_no', $no)->firstOrFail();
    }

    private function statusText(SsSalesOrder $o): string
    {
        return $o->state === 'CLOSED' ? __('salesshipment.od.state.CLOSED') : __('salesshipment.od.approval.'.$o->approval_status);
    }

    /**
     * The Sales Order List: one row per order with its references, quantities, latest challan and statuses.
     *
     * @param  Collection<int, SsSalesOrder>  $orders
     * @return list<array<string, mixed>>
     */
    private function rows(Collection $orders): array
    {
        $b = $this->book();
        $ids = $orders->pluck('id')->all();
        $fig = $b->sizeFigures($ids);
        $sizes = DB::table('ss_sales_order_sizes')->whereIn('order_id', $ids)->get(['id', 'order_id', 'qty', 'rate'])->groupBy('order_id');
        $lines = DB::table('ss_sales_order_lines')->whereIn('order_id', $ids)->orderBy('sl_no')->get(['order_id', 'buyer', 'order_ref', 'style_ref', 'section_id'])->groupBy('order_id');
        $challans = DB::table('ss_challans')->whereIn('order_id', $ids)->where('state', '!=', 'CANCELLED')->orderByDesc('chalan_date')->orderByDesc('chalan_no')->get(['order_id', 'chalan_no', 'chalan_date'])->groupBy('order_id');
        $sc = DB::table('ss_sc_programmes')->whereIn('order_id', $ids)->get(['order_id', 'sc_no'])->groupBy('order_id');
        $ship = DB::table('ss_shipment_lines as l')->join('ss_shipments as s', 's.id', '=', 'l.shipment_id')->whereIn('s.order_id', $ids)->where('s.stage', '!=', 'CANCELLED')
            ->groupBy('s.order_id')->selectRaw("s.order_id, sum(l.qty) as planned, sum(case when s.stage in ('CONFIRMED', 'CLOSED') then l.shipped_qty else 0 end) as shipped")->get()->keyBy('order_id');
        $names = $b->customerNames();
        $sections = $b->sectionNames();
        $company = $b->company();

        return $orders->map(function (SsSalesOrder $o) use ($fig, $sizes, $lines, $challans, $sc, $ship, $names, $sections, $company) {
            $mine = $sizes->get($o->id, collect());
            $ls = $lines->get($o->id, collect());
            $ordered = (float) $mine->sum('qty');
            $net = (float) $mine->sum(fn ($s) => $fig[$s->id]['net'] ?? 0);
            $last = $challans->get($o->id)?->first();
            $join = fn (string $k) => $ls->pluck($k)->filter()->unique()->implode(', ');
            $shipped = (float) ($ship[$o->id]->shipped ?? 0);
            $planned = (float) ($ship[$o->id]->planned ?? 0);

            return [
                'so_no' => $o->so_no, 'so_type' => $o->so_type, 'section' => $ls->pluck('section_id')->filter()->unique()->map(fn ($id) => $sections[$id] ?? '')->filter()->implode(', '),
                'section_ids' => $ls->pluck('section_id')->filter()->unique()->values()->all(), 'ref_buyer' => $join('buyer'),
                'ref_style' => trim($join('order_ref').($join('order_ref') && $join('style_ref') ? ' / ' : '').$join('style_ref')),
                'po_wo_no' => $o->po_wo_no, 'creation_date' => $o->receive_date->toDateString(), 'so_qty' => $ordered, 'delivery_qty' => $net, 'due_qty' => max(0.0, round($ordered - $net, 4)),
                'chalan_no' => $last?->chalan_no, 'chalan_date' => $last?->chalan_date, 'company' => $company, 'customer' => $names[$o->customer_id] ?? '—', 'customer_id' => $o->customer_id,
                'delivery_address' => $o->delivery_address, 'pay_method' => $o->payment_method, 'target_date' => $o->delivery_target_date->toDateString(), 'is_revised' => $o->version > 0,
                'challan_status' => $net <= 0 ? 'WAITING' : ($net + 0.00005 < $ordered ? 'PARTIAL' : 'DONE'), 'approval_status' => $o->approval_status, 'state' => $o->state,
                'shipped_qty' => $shipped, 'shipment_status' => $shipped <= 0 ? ($planned > 0 ? 'PLANNED' : 'NOT_SHIPPED') : ($shipped + 0.00005 < $ordered ? 'PARTIAL' : 'SHIPPED'), 'total_value' => round((float) $mine->sum(fn ($s) => $s->qty * $s->rate), 2),
                'sc_nos' => $sc->get($o->id, collect())->pluck('sc_no')->implode(', '), 'version' => $o->version,
                'search' => implode(' ', [$join('buyer'), $join('order_ref'), $join('style_ref')]),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function present(SsSalesOrder $o): array
    {
        $b = $this->book();
        $lines = SsSalesOrderLine::query()->where('order_id', $o->id)->orderBy('sl_no')->get();
        $sl = $lines->pluck('sl_no', 'id');
        $items = $b->itemNames();

        return [
            ...collect($o->only(['so_no', 'search_for', 'so_type', 'customer_id', 'program_by', 'program_to', 'bill_to_id', 'delivery_to_id', 'delivery_address', 'payment_method', 'currency_id',
                'payment_term', 'mode_of_shipment', 'po_wo_no', 'others_term', 'pr_po_ref', 'remarks', 'season', 'department', 'incoterm', 'destination_country_id', 'destination_port', 'item_id',
                'approval_status', 'state', 'version', 'lead_time_days']))->all(),
            'is_inter_company' => (bool) $o->is_inter_company, 'is_embroidery' => (bool) $o->is_embroidery, 'is_poly_item' => (bool) $o->is_poly_item, 'same_as_bill_to' => (bool) $o->same_as_bill_to,
            'receive_date' => $o->receive_date->toDateString(), 'delivery_target_date' => $o->delivery_target_date->toDateString(), 'shipment_date' => $o->shipment_date->toDateString(),
            'conv_rate' => (float) $o->conv_rate, 'additional_qty_pct' => (float) $o->additional_qty_pct,
            'customer' => $b->customerNames()[$o->customer_id] ?? '—', 'bill_to' => $b->customerNames()[$o->bill_to_id] ?? '—', 'delivery_to' => $b->customerNames()[$o->delivery_to_id] ?? '—',
            'currency' => $b->currencyCodes()[$o->currency_id] ?? '—', 'country' => $o->destination_country_id ? ($b->countries()[$o->destination_country_id] ?? null) : null,
            'item' => $o->item_id ? ($items[$o->item_id] ?? null) : null, 'company' => $b->company(),
            'submitted' => $b->trail($o->submitted_by, $o->submitted_at), 'checked' => $b->trail($o->checked_by, $o->checked_at),
            'approved' => $b->trail($o->approved_by, $o->approved_at), 'confirmed' => $b->trail($o->confirmed_by, $o->confirmed_at), 'closed' => $b->trail($o->closed_by, $o->closed_at),
            'lines' => $lines->map(fn ($l) => [...collect($l->only(['sl_no', 'buyer', 'order_ref', 'style_ref', 'wo_ref', 'section_id', 'item_id', 'gmt_color', 'product_color', 'uom', 'specification', 'code']))->all(),
                'rate' => (float) $l->rate, 'gross_weight' => $l->gross_weight === null ? null : (float) $l->gross_weight, 'net_weight' => $l->net_weight === null ? null : (float) $l->net_weight,
                'item' => $items[$l->item_id] ?? '—', 'section' => $l->section_id ? ($b->sectionNames()[$l->section_id] ?? null) : null])->values()->all(),
            'sizes' => SsSalesOrderSize::query()->where('order_id', $o->id)->orderBy('sort_order')->get()->map(fn ($s) => ['id' => $s->id, 'sl_no' => $sl[$s->line_id] ?? null, 'size' => $s->size,
                'pcs_per_size' => (float) $s->pcs_per_size, 'qty' => (float) $s->qty, 'rate' => (float) $s->rate])->values()->all(),
            'particulars' => SsSalesOrderParticular::query()->where('order_id', $o->id)->get()->map(fn ($p) => ['sl_no' => $sl[$p->line_id] ?? null, 'particular_id' => $p->particular_id,
                'particular' => $b->particulars()[$p->particular_id] ?? '—', 'value' => $p->value])->values()->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function sourceLines(SsSalesOrder $o): array
    {
        $fig = $this->book()->sizeFigures([$o->id]);
        $sizes = DB::table('ss_sales_order_sizes')->where('order_id', $o->id)->orderBy('sort_order')->get(['id', 'qty', 'rate']);
        $labels = $this->book()->sizeLabels($sizes->pluck('id')->all());

        return $sizes->map(fn ($s) => ['size_id' => $s->id, 'label' => $labels[$s->id] ?? '—', 'ordered' => (float) $s->qty, 'rate' => (float) $s->rate,
            'due' => max(0.0, round((float) $s->qty - ($fig[$s->id]['net'] ?? 0), 4))])->values()->all();
    }

    /** @return array<string, mixed> */
    private function presentProgramme(SsScProgramme $p): array
    {
        $o = SsSalesOrder::query()->findOrFail($p->order_id);
        $lines = SsScProgrammeLine::query()->where('programme_id', $p->id)->get();
        $labels = $this->book()->sizeLabels($lines->pluck('size_id')->all());

        return [
            'sc_no' => $p->sc_no, 'so_no' => $o->so_no, 'party_id' => $p->party_id, 'party' => $this->book()->customerNames()[$p->party_id] ?? '—', 'sc_date' => $p->sc_date->toDateString(),
            'target_date' => $p->target_date->toDateString(), 'process' => $p->process, 'remarks' => $p->remarks, 'company' => $this->book()->company(),
            'lines' => $lines->map(fn ($l) => ['size_id' => $l->size_id, 'label' => $labels[$l->size_id] ?? '—', 'qty' => (float) $l->qty, 'rate' => (float) $l->rate])->values()->all(),
        ];
    }
}

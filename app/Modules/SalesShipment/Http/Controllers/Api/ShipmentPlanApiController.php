<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Http\Controllers\Api;

use App\Modules\SalesShipment\Models\SsSalesOrder;
use App\Modules\SalesShipment\Models\SsShipment;
use App\Modules\SalesShipment\Models\SsShipmentLine;
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
 * Sales & Shipment → General → Shipment Planning (AnnotechERP 21/04),
 * "Order to Shipment · Step 4 of 12": one shipment of a confirmed order —
 * the order picked on the left, its lines still unplanned dropping into the
 * grid (never more than the order has unplanned, counting every other
 * shipment not cancelled; with Ship Against Delivered on, never more than
 * its net delivered either), cartons and rate per line; the shipment and
 * ex-factory dates, mode, destination, port of loading and incoterm. The
 * plan is rewritten while it is still being planned and cancelled while
 * the shipment is open.
 */
class ShipmentPlanApiController extends ApiController
{
    private ?SalesBook $book = null;

    public function index(): JsonResponse
    {
        $this->allow('salesshipment.shipment.view');
        $b = $this->book();
        $rows = SsShipment::query()->orderByDesc('shipment_date')->orderByDesc('shipment_no')->get();
        $orders = DB::table('ss_sales_orders')->whereIn('id', $rows->pluck('order_id'))->get(['id', 'so_no', 'customer_id', 'po_wo_no'])->keyBy('id');
        $lines = DB::table('ss_shipment_lines')->whereIn('shipment_id', $rows->pluck('id'))->get(['shipment_id', 'qty', 'carton_qty', 'rate', 'shipped_qty'])->groupBy('shipment_id');
        $names = $b->customerNames();
        $countries = $b->countries();

        return ApiResponse::ok($rows->map(function (SsShipment $s) use ($orders, $lines, $names, $countries) {
            $o = $orders[$s->order_id] ?? null;
            $mine = $lines->get($s->id, collect());

            return [
                'shipment_no' => $s->shipment_no, 'plan_date' => $s->plan_date->toDateString(), 'so_no' => $o?->so_no, 'customer' => $names[$o?->customer_id] ?? '—', 'customer_id' => $o?->customer_id,
                'po' => $o?->po_wo_no, 'shipment_date' => $s->shipment_date->toDateString(), 'ex_factory' => $s->ex_factory_date?->toDateString(),
                'destination' => implode(', ', array_filter([$s->destination_port, $s->destination_country_id ? ($countries[$s->destination_country_id] ?? null) : null])) ?: null,
                'mode' => $s->shipment_mode, 'planned_qty' => (float) $mine->sum('qty'), 'cartons' => (int) $mine->sum('carton_qty'),
                'value' => round((float) $mine->sum(fn ($l) => $l->qty * $l->rate), 2), 'shipped_qty' => (float) $mine->sum('shipped_qty'), 'stage' => $s->stage,
                'locked' => ! in_array($s->stage, SsShipment::OPEN, true) || ShipmentBook::papered($s->id),
            ];
        })->values()->all());
    }

    public function show(string $no): JsonResponse
    {
        $this->allow('salesshipment.shipment.view');

        return ApiResponse::ok($this->present($this->find($no)));
    }

    /** The Order List on the left: confirmed open orders with what they still have unplanned. */
    public function orders(): JsonResponse
    {
        $this->allow('salesshipment.shipment.view');
        $orders = SsSalesOrder::query()->where('approval_status', 'CONFIRMED')->where('state', 'OPEN')->orderBy('shipment_date')->orderBy('so_no')->get();
        $names = $this->book()->customerNames();

        return ApiResponse::ok($orders->map(fn (SsSalesOrder $o) => [
            'so_no' => $o->so_no, 'customer' => $names[$o->customer_id] ?? '—', 'customer_id' => $o->customer_id, 'po' => $o->po_wo_no, 'ship_date' => $o->shipment_date->toDateString(),
            'balance' => round((float) collect($this->sourceOf($o, null)['lines'])->sum('balance'), 4),
        ])->values()->all());
    }

    public function source(Request $request, string $so): JsonResponse
    {
        $this->allow('salesshipment.shipment.view');
        $o = SsSalesOrder::query()->where('so_no', $so)->firstOrFail();
        $except = $request->query('except') ? SsShipment::query()->where('shipment_no', $request->query('except'))->value('id') : null;

        return ApiResponse::ok($this->sourceOf($o, $except));
    }

    public function save(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('salesshipment.shipment.manage');
        $row = $no ? $this->find($no) : null;
        if ($row && (! in_array($row->stage, SsShipment::OPEN, true) || ShipmentBook::papered($row->id))) {
            throw ValidationException::withMessages(['shipment_no' => __(ShipmentBook::papered($row->id) ? 'salesshipment.v.sp_papered' : 'salesshipment.v.sp_locked', ['no' => $row->shipment_no, 'stage' => __('salesshipment.sp.stage.'.$row->stage)])]);
        }
        $c = $this->caller()->companyId;
        $data = $request->validate([
            'so_no' => ['required', 'string', 'max:24'], 'plan_date' => ['required', 'date'], 'shipment_date' => ['required', 'date'],
            'ex_factory_date' => ['nullable', 'date', 'before_or_equal:shipment_date'], 'shipment_mode' => ['required', Rule::in(SsShipment::MODES)],
            'destination_country_id' => ['nullable', Rule::exists('origin_countries', 'id')->where('company_id', $c)], 'destination_port' => ['nullable', 'string', 'max:80'],
            'port_of_loading' => ['nullable', 'string', 'max:80'], 'incoterm' => ['nullable', Rule::in(SsSalesOrder::INCOTERMS)], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['nullable', 'array'], 'lines.*.size_id' => ['required', 'string', 'max:26', 'distinct'], 'lines.*.qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.carton_qty' => ['nullable', 'integer', 'min:0'], 'lines.*.rate' => ['nullable', 'numeric', 'min:0'],
        ], ['ex_factory_date.before_or_equal' => __('salesshipment.v.sp_ex_factory'), 'lines.*.size_id.distinct' => __('salesshipment.v.line_twice')]);
        $order = SsSalesOrder::query()->where('so_no', $data['so_no'])->firstOrFail();
        if ($row && $row->order_id !== $order->id) {
            throw ValidationException::withMessages(['so_no' => __('salesshipment.v.sp_order_fixed', ['no' => $row->shipment_no])]);
        }
        if ($order->approval_status !== 'CONFIRMED' || $order->state !== 'OPEN') {
            throw ValidationException::withMessages(['so_no' => __('salesshipment.v.not_confirmed', ['no' => $order->so_no])]);
        }
        $this->book()->assertOpen($data['plan_date'], 'plan_date');
        $lines = collect(array_values($data['lines'] ?? []))->filter(fn ($l) => (float) ($l['qty'] ?? 0) > 0)->values();
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('salesshipment.v.sp_lines')]);
        }
        $src = $this->sourceOf($order, $row?->id);
        $by = collect($src['lines'])->keyBy('size_id');
        foreach ($lines as $i => $l) {
            $s = $by[$l['size_id']] ?? null;
            if (! $s) {
                throw ValidationException::withMessages(["lines.{$i}.size_id" => __('salesshipment.v.size_not_on_order', ['no' => $order->so_no])]);
            }
            if ((float) $l['qty'] > $s['balance'] + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __($src['against_delivered'] ? 'salesshipment.v.sp_over_delivered' : 'salesshipment.v.sp_over', ['line' => $s['label'], 'max' => $this->num($s['balance'])])]);
            }
        }
        $head = ['order_id' => $order->id, ...collect($data)->only(['plan_date', 'shipment_date', 'ex_factory_date', 'shipment_mode', 'destination_country_id', 'destination_port', 'port_of_loading', 'incoterm', 'remarks'])->all()];
        $row = DB::transaction(function () use ($row, $head, $lines, $by, $data) {
            if ($row) {
                $row->update($head);
                SsShipmentLine::query()->where('shipment_id', $row->id)->delete();
            } else {
                $row = SsShipment::query()->create($head + ['shipment_no' => SsSettings::nextNo('prefix_shipment', 'ss_shipments', 'shipment_no', $data['plan_date'], $this->caller()->companyId),
                    'stage' => 'PLANNING', 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach ($lines as $i => $l) {
                SsShipmentLine::query()->create(['shipment_id' => $row->id, 'size_id' => $l['size_id'], 'qty' => $l['qty'], 'carton_qty' => $l['carton_qty'] ?? 0,
                    'rate' => $l['rate'] ?? $by[$l['size_id']]['rate'], 'sort_order' => $i]);
            }

            return $row;
        });

        return ApiResponse::ok($this->present($row->refresh()), [], $no ? 200 : 201);
    }

    /** Cancel releases the planned quantity back onto the order. */
    public function cancel(string $no): JsonResponse
    {
        $this->allow('salesshipment.shipment.manage');
        $row = DB::transaction(function () use ($no) {
            $row = SsShipment::query()->where('shipment_no', $no)->lockForUpdate()->firstOrFail();
            if (! in_array($row->stage, SsShipment::OPEN, true)) {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.step', ['no' => $row->shipment_no, 'status' => __('salesshipment.sp.stage.'.$row->stage)])]);
            }
            $this->book()->assertOpen($row->plan_date, 'status');
            $row->update(['stage' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $this->caller()->auditUserId()]);

            return $row;
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    public function destroy(string $no): JsonResponse
    {
        $this->allow('salesshipment.shipment.manage');
        $row = $this->find($no);
        if ($row->stage !== 'PLANNING' || ShipmentBook::papered($row->id) || DB::table('ss_bookings')->where('shipment_id', $row->id)->exists() || DB::table('ss_containers')->where('shipment_id', $row->id)->exists()
            || DB::table('ss_doc_handovers')->where('shipment_id', $row->id)->exists()) {
            throw ValidationException::withMessages(['status' => __('salesshipment.v.sp_delete', ['no' => $row->shipment_no])]);
        }
        $row->delete();

        return ApiResponse::ok(['deleted' => $no]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    private function book(): SalesBook
    {
        return $this->book ??= new SalesBook($this->caller()->companyId);
    }

    private function find(string $no): SsShipment
    {
        return SsShipment::query()->where('shipment_no', $no)->firstOrFail();
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }

    /**
     * Per order size, what every other shipment not cancelled has planned.
     *
     * @return Collection<string, float>
     */
    public static function planned(array $sizeIds, ?string $exceptShipment = null): Collection
    {
        $rows = DB::table('ss_shipment_lines as l')->join('ss_shipments as s', 's.id', '=', 'l.shipment_id')->whereIn('l.size_id', $sizeIds)->where('s.stage', '!=', 'CANCELLED')
            ->when($exceptShipment, fn ($q) => $q->where('s.id', '!=', $exceptShipment))->groupBy('l.size_id')->selectRaw('l.size_id, sum(l.qty) as q')->pluck('q', 'size_id');

        return collect($sizeIds)->mapWithKeys(fn ($id) => [$id => (float) ($rows[$id] ?? 0)]);
    }

    /** An order's sizes as they drop into a plan: ordered, planned elsewhere, the balance (capped by net delivered when the setting says so), rate — and the order's header defaults. */
    private function sourceOf(SsSalesOrder $o, ?string $exceptShipment): array
    {
        $sizes = DB::table('ss_sales_order_sizes')->where('order_id', $o->id)->orderBy('sort_order')->get(['id', 'qty', 'rate']);
        $planned = self::planned($sizes->pluck('id')->all(), $exceptShipment);
        $labels = $this->book()->sizeLabels($sizes->pluck('id')->all());
        $againstDelivered = (bool) SsSettings::get()->ship_against_delivered;
        $fig = $againstDelivered ? $this->book()->sizeFigures([$o->id]) : collect();

        return [
            'so_no' => $o->so_no, 'shipment_date' => $o->shipment_date->toDateString(), 'destination_country_id' => $o->destination_country_id, 'destination_port' => $o->destination_port,
            'incoterm' => $o->incoterm, 'against_delivered' => $againstDelivered,
            'lines' => $sizes->map(function ($s) use ($planned, $labels, $againstDelivered, $fig) {
                $cap = (float) $s->qty - $planned[$s->id];
                if ($againstDelivered) {
                    $cap = min($cap, ($fig[$s->id]['net'] ?? 0) - $planned[$s->id]);
                }

                return ['size_id' => $s->id, 'label' => $labels[$s->id] ?? '—', 'ordered' => (float) $s->qty, 'prev' => $planned[$s->id], 'balance' => max(0.0, round($cap, 4)), 'rate' => (float) $s->rate];
            })->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function present(SsShipment $s): array
    {
        $b = $this->book();
        $o = SsSalesOrder::query()->findOrFail($s->order_id);
        $lines = SsShipmentLine::query()->where('shipment_id', $s->id)->orderBy('sort_order')->get();
        $labels = $b->sizeLabels($lines->pluck('size_id')->all());

        return [
            ...collect($s->only(['shipment_no', 'shipment_mode', 'destination_country_id', 'destination_port', 'port_of_loading', 'incoterm', 'remarks', 'stage']))->all(),
            'so_no' => $o->so_no, 'po' => $o->po_wo_no, 'customer' => $b->customerNames()[$o->customer_id] ?? '—', 'plan_date' => $s->plan_date->toDateString(),
            'shipment_date' => $s->shipment_date->toDateString(), 'ex_factory_date' => $s->ex_factory_date?->toDateString(), 'company' => $b->company(),
            'country' => $s->destination_country_id ? ($b->countries()[$s->destination_country_id] ?? null) : null,
            'lines' => $lines->map(fn ($l) => ['size_id' => $l->size_id, 'label' => $labels[$l->size_id] ?? '—', 'qty' => (float) $l->qty, 'carton_qty' => (int) $l->carton_qty, 'rate' => (float) $l->rate,
                'shipped_qty' => (float) $l->shipped_qty])->values()->all(),
            'locked' => $locked = ! in_array($s->stage, SsShipment::OPEN, true) || ShipmentBook::papered($s->id),
            'source' => $locked ? null : $this->sourceOf($o, $s->id),
        ];
    }
}

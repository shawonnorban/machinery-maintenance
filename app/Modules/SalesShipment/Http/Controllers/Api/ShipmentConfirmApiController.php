<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Http\Controllers\Api;

use App\Modules\SalesShipment\Models\SsShipment;
use App\Modules\SalesShipment\Support\SalesBook;
use App\Modules\SalesShipment\Support\ShipmentBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sales & Shipment → Shipment (AnnotechERP 21/05): Shipment Confirmation —
 * every shipment with its planned, invoiced and packed quantity, vessel and
 * ETD and a tick or a cross per readiness check (quantity, shipping,
 * container, invoice, packing, documents); Confirm refuses until all pass
 * and writes the final invoices' quantities as shipped against the shipment
 * and its order; Unconfirm takes them back. Shipment Closing — confirmed
 * shipments closed as complete files, reopened only by an administrator.
 */
class ShipmentConfirmApiController extends ApiController
{
    private ?SalesBook $book = null;

    public function confirmations(): JsonResponse
    {
        $this->allow('salesshipment.shipment.view');
        $rows = SsShipment::query()->where('stage', '!=', 'CANCELLED')->orderBy('shipment_date')->orderBy('shipment_no')->get();
        $orders = DB::table('ss_sales_orders')->whereIn('id', $rows->pluck('order_id'))->get(['id', 'so_no', 'customer_id'])->keyBy('id');
        $names = $this->book()->customerNames();

        return ApiResponse::ok($rows->map(function (SsShipment $s) use ($orders, $names) {
            $r = ShipmentBook::readiness($s);

            return [
                'shipment_no' => $s->shipment_no, 'so_no' => $orders[$s->order_id]->so_no ?? '—', 'customer' => $names[$orders[$s->order_id]->customer_id ?? ''] ?? '—', 'customer_id' => $orders[$s->order_id]->customer_id ?? null,
                'planned_qty' => $r['planned'], 'invoiced' => $r['invoiced'], 'packed' => $r['packed'], 'vessel' => $r['vessel'], 'etd' => $r['etd'], ...$r['checks'], 'ready' => $r['ready'], 'stage' => $s->stage,
                'confirmed' => $this->book()->trail($s->confirmed_by, $s->confirmed_at),
            ];
        })->values()->all());
    }

    /** Check: the shipment's readiness in full. */
    public function check(string $no): JsonResponse
    {
        $this->allow('salesshipment.shipment.view');
        $s = $this->find($no);

        return ApiResponse::ok(['shipment_no' => $s->shipment_no, 'stage' => $s->stage] + ShipmentBook::readiness($s));
    }

    public function confirm(string $no): JsonResponse
    {
        $this->allow('salesshipment.shipment.confirm');
        $s = DB::transaction(function () use ($no) {
            $s = SsShipment::query()->where('shipment_no', $no)->lockForUpdate()->firstOrFail();
            if (! in_array($s->stage, SsShipment::OPEN, true)) {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.shp_not_open', ['no' => $s->shipment_no, 'stage' => __('salesshipment.sp.stage.'.$s->stage)])]);
            }
            $r = ShipmentBook::readiness($s);
            if (! $r['ready']) {
                $failed = collect($r['checks'])->filter(fn ($ok) => ! $ok)->keys()->map(fn ($k) => __('salesshipment.cf.check.'.$k))->implode(', ');
                throw ValidationException::withMessages(['status' => __('salesshipment.v.cf_not_ready', ['no' => $s->shipment_no, 'checks' => $failed])]);
            }
            $this->book()->assertOpen($s->shipment_date, 'status');
            $lines = DB::table('ss_shipment_lines')->where('shipment_id', $s->id)->pluck('id');
            $final = ShipmentBook::invoiced($lines->all(), null, true);
            foreach ($lines as $id) {
                DB::table('ss_shipment_lines')->where('id', $id)->update(['shipped_qty' => $final[$id], 'updated_at' => now()]);
            }
            $s->update(['stage' => 'CONFIRMED', 'confirmed_at' => now(), 'confirmed_by' => $this->caller()->auditUserId()]);

            return $s;
        });

        return ApiResponse::ok(['shipment_no' => $s->shipment_no, 'stage' => $s->stage]);
    }

    public function unconfirm(string $no): JsonResponse
    {
        $this->allow('salesshipment.shipment.confirm');
        $s = DB::transaction(function () use ($no) {
            $s = SsShipment::query()->where('shipment_no', $no)->lockForUpdate()->firstOrFail();
            if ($s->stage !== 'CONFIRMED') {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.step', ['no' => $s->shipment_no, 'status' => __('salesshipment.sp.stage.'.$s->stage)])]);
            }
            $this->book()->assertOpen($s->shipment_date, 'status');
            DB::table('ss_shipment_lines')->where('shipment_id', $s->id)->update(['shipped_qty' => 0, 'updated_at' => now()]);
            $s->update(['stage' => 'PLANNING', 'confirmed_at' => null, 'confirmed_by' => null]);
            ShipmentBook::restage($s);

            return $s;
        });

        return ApiResponse::ok(['shipment_no' => $s->shipment_no, 'stage' => $s->refresh()->stage]);
    }

    /* ── Closing ───────────────────────────────────────────────────── */

    public function closings(): JsonResponse
    {
        $this->allow('salesshipment.shipment.view');
        $b = $this->book();
        $rows = SsShipment::query()->whereIn('stage', ['CONFIRMED', 'CLOSED'])->orderByDesc('shipment_date')->orderByDesc('shipment_no')->get();
        $ids = $rows->pluck('id');
        $orders = DB::table('ss_sales_orders')->whereIn('id', $rows->pluck('order_id'))->get(['id', 'so_no', 'po_wo_no'])->keyBy('id');
        $shipped = DB::table('ss_shipment_lines')->whereIn('shipment_id', $ids)->groupBy('shipment_id')->selectRaw('shipment_id, sum(shipped_qty) as q')->pluck('q', 'shipment_id');
        $value = DB::table('ss_invoice_lines as l')->join('ss_invoices as i', 'i.id', '=', 'l.invoice_id')->whereIn('i.shipment_id', $ids)->where('i.state', 'FINAL')
            ->groupBy('i.shipment_id')->selectRaw('i.shipment_id, sum(l.qty * l.unit_price) as v')->pluck('v', 'shipment_id');
        $containers = DB::table('ss_containers')->whereIn('shipment_id', $ids)->orderBy('container_no')->get(['shipment_id', 'container_no'])->groupBy('shipment_id');
        $docs = DB::table('ss_doc_handovers')->whereIn('shipment_id', $ids)->where('status', '!=', 'RETURNED')->groupBy('shipment_id')->selectRaw('shipment_id, count(*) as n')->pluck('n', 'shipment_id');

        return ApiResponse::ok($rows->map(function (SsShipment $s) use ($orders, $shipped, $value, $containers, $docs, $b) {
            $booking = ShipmentBook::booking($s->id);

            return [
                'shipment_no' => $s->shipment_no, 'so_no' => $orders[$s->order_id]->so_no ?? '—', 'po' => $orders[$s->order_id]->po_wo_no ?? null, 'shipped_qty' => (float) ($shipped[$s->id] ?? 0),
                'invoice_value' => round((float) ($value[$s->id] ?? 0), 2), 'shipment_date' => $s->shipment_date->toDateString(),
                'vessel' => $booking ? trim($booking->vessel_name.($booking->voyage_no ? ' / '.$booking->voyage_no : '')) : null,
                'containers' => $containers->get($s->id, collect())->pluck('container_no')->implode(', ') ?: null, 'documents' => (int) ($docs[$s->id] ?? 0),
                'confirmation' => $b->trail($s->confirmed_by, $s->confirmed_at), 'closed_at' => $s->closed_at?->toIso8601String(), 'closed_by' => $b->user($s->closed_by), 'stage' => $s->stage,
            ];
        })->values()->all());
    }

    public function close(string $no): JsonResponse
    {
        $this->allow('salesshipment.shipment.confirm');

        return $this->flip($no, 'CONFIRMED', 'CLOSED');
    }

    public function reopen(string $no): JsonResponse
    {
        $this->allow('salesshipment.shipment.reopen');

        return $this->flip($no, 'CLOSED', 'CONFIRMED');
    }

    private function flip(string $no, string $from, string $to): JsonResponse
    {
        $s = DB::transaction(function () use ($no, $from, $to) {
            $s = SsShipment::query()->where('shipment_no', $no)->lockForUpdate()->firstOrFail();
            if ($s->stage !== $from) {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.step', ['no' => $s->shipment_no, 'status' => __('salesshipment.sp.stage.'.$s->stage)])]);
            }
            $s->update(['stage' => $to, ...($to === 'CLOSED' ? ['closed_at' => now(), 'closed_by' => $this->caller()->auditUserId()] : ['closed_at' => null, 'closed_by' => null])]);

            return $s;
        });

        return ApiResponse::ok(['shipment_no' => $s->shipment_no, 'stage' => $s->stage]);
    }

    private function book(): SalesBook
    {
        return $this->book ??= new SalesBook($this->caller()->companyId);
    }

    private function find(string $no): SsShipment
    {
        return SsShipment::query()->where('shipment_no', $no)->firstOrFail();
    }
}

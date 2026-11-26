<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Http\Controllers\Api;

use App\Modules\SalesShipment\Models\SsBooking;
use App\Modules\SalesShipment\Models\SsContainer;
use App\Modules\SalesShipment\Models\SsDocHandover;
use App\Modules\SalesShipment\Models\SsShipment;
use App\Modules\SalesShipment\Support\SalesBook;
use App\Modules\SalesShipment\Support\ShipmentBook;
use App\Modules\SalesShipment\Support\SsSettings;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sales & Shipment → Shipment (AnnotechERP 21/05): the papers kept as plain
 * records, as Brickland lists them — Shipment Booking (forwarder, line,
 * vessel and voyage, ETD / ETA, ports, containers; requested, confirmed,
 * completed or cancelled), Container / Vessel Details (each container on a
 * shipment, its blanks filled from the booking) and Commercial Document
 * Handover (documents from Commercial to Shipping, handed over, received or
 * returned, with an attachment). Every change re-reads the shipment's
 * stage from its paperwork.
 */
class ShipmentPaperApiController extends ApiController
{
    private ?SalesBook $book = null;

    /** FINAL on the final-handover routes (21/06); the commercial handover otherwise. */
    private ?string $kind = null;

    /** The shipments, bookings, invoices and packing lists the forms pick from. */
    public function options(): JsonResponse
    {
        $this->allowEither('salesshipment.shipment.view', 'salesshipment.exportdoc.view');
        $names = $this->book()->customerNames();
        $ships = DB::table('ss_shipments as s')->join('ss_sales_orders as o', 'o.id', '=', 's.order_id')->where('s.company_id', $this->caller()->companyId)->where('s.stage', '!=', 'CANCELLED')
            ->orderByDesc('s.shipment_no')->get(['s.shipment_no', 's.stage', 's.shipment_mode', 's.port_of_loading', 's.destination_port', 'o.so_no', 'o.customer_id']);

        return ApiResponse::ok([
            'shipments' => $ships->map(fn ($s) => ['shipment_no' => $s->shipment_no, 'so_no' => $s->so_no, 'customer' => $names[$s->customer_id] ?? '—', 'stage' => $s->stage, 'mode' => $s->shipment_mode,
                'port_of_loading' => $s->port_of_loading, 'destination_port' => $s->destination_port, 'open' => in_array($s->stage, SsShipment::OPEN, true)])->values()->all(),
            'bookings' => DB::table('ss_bookings as b')->join('ss_shipments as s', 's.id', '=', 'b.shipment_id')->where('b.company_id', $this->caller()->companyId)->where('b.status', '!=', 'CANCELLED')
                ->orderByDesc('b.booking_no')->get(['b.booking_no', 's.shipment_no', 'b.vessel_name', 'b.voyage_no', 'b.etd', 'b.eta', 'b.container_type'])->map(fn ($b) => (array) $b)->all(),
            'invoices' => DB::table('ss_invoices as i')->join('ss_shipments as s', 's.id', '=', 'i.shipment_id')->where('i.company_id', $this->caller()->companyId)->where('i.state', '!=', 'CANCELLED')
                ->orderByDesc('i.invoice_no')->get(['i.invoice_no', 's.shipment_no'])->map(fn ($b) => (array) $b)->all(),
            'packing_lists' => DB::table('ss_packing_lists as p')->join('ss_shipments as s', 's.id', '=', 'p.shipment_id')->where('p.company_id', $this->caller()->companyId)->where('p.state', '!=', 'CANCELLED')
                ->orderByDesc('p.packing_no')->get(['p.packing_no', 's.shipment_no'])->map(fn ($b) => (array) $b)->all(),
            'container_types' => SsBooking::CONTAINER_TYPES, 'me' => $this->book()->user($this->caller()->auditUserId()),
        ]);
    }

    /* ── Bookings ──────────────────────────────────────────────────── */

    public function bookings(): JsonResponse
    {
        $this->allow('salesshipment.shipment.view');
        $rows = SsBooking::query()->orderByDesc('booking_date')->orderByDesc('booking_no')->get();
        $ships = $this->shipmentNos($rows->pluck('shipment_id')->all());

        return ApiResponse::ok($rows->map(fn (SsBooking $b) => [
            'booking_no' => $b->booking_no, 'booking_date' => $b->booking_date->toDateString(), 'shipment_no' => $ships[$b->shipment_id]->shipment_no ?? '—', 'so_no' => $ships[$b->shipment_id]->so_no ?? '—',
            'forwarder' => $b->forwarder, 'shipping_line' => $b->shipping_line, 'vessel' => trim(($b->vessel_name ?? '').($b->voyage_no ? ' / '.$b->voyage_no : '')) ?: null,
            'vessel_name' => $b->vessel_name, 'voyage_no' => $b->voyage_no, 'etd' => $b->etd?->toDateString(), 'eta' => $b->eta?->toDateString(), 'pol' => $b->port_of_loading, 'pod' => $b->port_of_discharge,
            'final_destination' => $b->final_destination, 'container_type' => $b->container_type, 'container_qty' => $b->container_qty,
            'containers' => $b->container_qty ? $b->container_qty.' × '.($b->container_type ?? '—') : null, 'status' => $b->status, 'remarks' => $b->remarks,
        ])->values()->all());
    }

    public function saveBooking(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('salesshipment.shipment.manage');
        $row = $no ? SsBooking::query()->where('booking_no', $no)->firstOrFail() : null;
        if ($row && ! in_array($row->status, ['REQUESTED', 'CONFIRMED'], true)) {
            throw ValidationException::withMessages(['booking_no' => __('salesshipment.v.locked', ['no' => $row->booking_no, 'status' => __('salesshipment.bk.status.'.$row->status)])]);
        }
        $data = $request->validate([
            'shipment_no' => ['required', 'string', 'max:24'], 'booking_date' => ['required', 'date'], 'forwarder' => ['required', 'string', 'max:160'], 'shipping_line' => ['nullable', 'string', 'max:160'],
            'vessel_name' => ['nullable', 'string', 'max:120'], 'voyage_no' => ['nullable', 'string', 'max:40'], 'etd' => ['nullable', 'date'], 'eta' => ['nullable', 'date', 'after_or_equal:etd'],
            'port_of_loading' => ['nullable', 'string', 'max:80'], 'port_of_discharge' => ['nullable', 'string', 'max:80'], 'final_destination' => ['nullable', 'string', 'max:120'],
            'container_type' => ['nullable', Rule::in(SsBooking::CONTAINER_TYPES)], 'container_qty' => ['nullable', 'integer', 'min:0', 'max:999'],
            'status' => ['required', Rule::in(SsBooking::STATUSES)], 'remarks' => ['nullable', 'string', 'max:2000'],
        ], ['eta.after_or_equal' => __('salesshipment.v.eta_before_etd'), 'forwarder.required' => __('salesshipment.v.bk_forwarder')]);
        $s = $this->openShipment($data['shipment_no']);
        $this->book()->assertOpen($data['booking_date'], 'booking_date');
        $head = ['shipment_id' => $s->id, ...collect($data)->except(['shipment_no'])->all(), 'container_qty' => (int) ($data['container_qty'] ?? 0)];
        $row = DB::transaction(function () use ($row, $head, $data, $s) {
            if ($row) {
                $old = SsShipment::query()->find($row->shipment_id);
                $row->update($head);
                if ($old && $old->id !== $s->id) {
                    ShipmentBook::restage($old);
                }
            } else {
                $row = SsBooking::query()->create($head + ['booking_no' => SsSettings::nextNo('prefix_booking', 'ss_bookings', 'booking_no', $data['booking_date'], $this->caller()->companyId), 'created_by' => $this->caller()->auditUserId()]);
            }
            ShipmentBook::restage($s->refresh());

            return $row;
        });

        return ApiResponse::ok(['booking_no' => $row->booking_no, 'status' => $row->status, 'stage' => $s->refresh()->stage], [], $no ? 200 : 201);
    }

    public function bookingAct(string $no, string $action): JsonResponse
    {
        $from = ['confirm' => [['REQUESTED'], 'CONFIRMED'], 'complete' => [['CONFIRMED'], 'COMPLETED'], 'cancel' => [['REQUESTED', 'CONFIRMED'], 'CANCELLED']];
        abort_unless(isset($from[$action]), 404);
        $this->allow('salesshipment.shipment.manage');
        $row = DB::transaction(function () use ($no, $action, $from) {
            $row = SsBooking::query()->where('booking_no', $no)->lockForUpdate()->firstOrFail();
            if (! in_array($row->status, $from[$action][0], true)) {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.step', ['no' => $row->booking_no, 'status' => __('salesshipment.bk.status.'.$row->status)])]);
            }
            $s = SsShipment::query()->findOrFail($row->shipment_id);
            if (! in_array($s->stage, SsShipment::OPEN, true)) {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.shp_not_open', ['no' => $s->shipment_no, 'stage' => __('salesshipment.sp.stage.'.$s->stage)])]);
            }
            $row->update(['status' => $from[$action][1]]);
            ShipmentBook::restage($s);

            return $row;
        });

        return ApiResponse::ok(['booking_no' => $row->booking_no, 'status' => $row->status]);
    }

    public function bookingDestroy(string $no): JsonResponse
    {
        $this->allow('salesshipment.shipment.manage');
        $row = SsBooking::query()->where('booking_no', $no)->firstOrFail();
        if (! in_array($row->status, ['REQUESTED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['status' => __('salesshipment.v.bk_delete', ['no' => $row->booking_no])]);
        }
        $s = SsShipment::query()->find($row->shipment_id);
        $row->delete();
        if ($s) {
            ShipmentBook::restage($s);
        }

        return ApiResponse::ok(['deleted' => $no]);
    }

    /* ── Containers ────────────────────────────────────────────────── */

    public function containers(): JsonResponse
    {
        $this->allow('salesshipment.shipment.view');
        $rows = SsContainer::query()->orderByDesc('created_at')->get();
        $ships = $this->shipmentNos($rows->pluck('shipment_id')->all());
        $bookings = DB::table('ss_bookings')->whereIn('id', $rows->pluck('booking_id')->filter())->pluck('booking_no', 'id');

        return ApiResponse::ok($rows->map(fn (SsContainer $c) => [
            'key' => ($ships[$c->shipment_id]->shipment_no ?? '—').'/'.$c->container_no, 'container_no' => $c->container_no, 'shipment_no' => $ships[$c->shipment_id]->shipment_no ?? '—',
            'booking_no' => $c->booking_id ? ($bookings[$c->booking_id] ?? null) : null, 'type' => $c->container_type, 'seal_no' => $c->seal_no, 'gross_weight' => (float) $c->gross_weight,
            'net_weight' => (float) $c->net_weight, 'cbm' => (float) $c->cbm, 'cartons' => $c->carton_qty, 'vessel' => trim(($c->vessel_name ?? '').($c->voyage_no ? ' / '.$c->voyage_no : '')) ?: null,
            'vessel_name' => $c->vessel_name, 'voyage_no' => $c->voyage_no, 'loading_date' => $c->loading_date?->toDateString(), 'etd' => $c->etd?->toDateString(), 'eta' => $c->eta?->toDateString(), 'remarks' => $c->remarks,
        ])->values()->all());
    }

    public function saveContainer(Request $request, ?string $shipmentNo = null, ?string $containerNo = null): JsonResponse
    {
        $this->allow('salesshipment.shipment.manage');
        $row = $shipmentNo ? SsContainer::query()->where('container_no', $containerNo)->where('shipment_id', SsShipment::query()->where('shipment_no', $shipmentNo)->value('id'))->firstOrFail() : null;
        $request->merge(['container_no' => strtoupper(trim((string) $request->input('container_no')))]);
        $data = $request->validate([
            'shipment_no' => ['required', 'string', 'max:24'], 'booking_no' => ['nullable', 'string', 'max:24'], 'container_no' => ['required', 'regex:/^[A-Z]{4}\d{7}$/'],
            'container_type' => ['nullable', Rule::in(SsBooking::CONTAINER_TYPES)], 'seal_no' => ['nullable', 'string', 'max:40'], 'gross_weight' => ['nullable', 'numeric', 'min:0'],
            'net_weight' => ['nullable', 'numeric', 'min:0'], 'cbm' => ['nullable', 'numeric', 'min:0'], 'carton_qty' => ['nullable', 'integer', 'min:0'], 'vessel_name' => ['nullable', 'string', 'max:120'],
            'voyage_no' => ['nullable', 'string', 'max:40'], 'loading_date' => ['nullable', 'date'], 'etd' => ['nullable', 'date'], 'eta' => ['nullable', 'date', 'after_or_equal:etd'], 'remarks' => ['nullable', 'string', 'max:2000'],
        ], ['container_no.regex' => __('salesshipment.v.ct_no'), 'container_no.required' => __('salesshipment.v.ct_no'), 'eta.after_or_equal' => __('salesshipment.v.eta_before_etd')]);
        $s = $this->openShipment($data['shipment_no']);
        if ($row && $row->shipment_id !== $s->id) {
            throw ValidationException::withMessages(['shipment_no' => __('salesshipment.v.ct_shipment_fixed', ['no' => $row->container_no])]);
        }
        $dup = SsContainer::query()->where('shipment_id', $s->id)->where('container_no', $data['container_no'])->when($row, fn ($q) => $q->where('id', '!=', $row->id))->exists();
        if ($dup) {
            throw ValidationException::withMessages(['container_no' => __('salesshipment.v.ct_twice', ['no' => $data['container_no'], 'shipment' => $s->shipment_no])]);
        }
        $booking = null;
        if (! empty($data['booking_no'])) {
            $booking = SsBooking::query()->where('booking_no', $data['booking_no'])->first();
            if (! $booking || $booking->shipment_id !== $s->id) {
                throw ValidationException::withMessages(['booking_no' => __('salesshipment.v.ct_booking', ['no' => $s->shipment_no])]);
            }
        }
        $head = ['shipment_id' => $s->id, 'booking_id' => $booking?->id, ...collect($data)->except(['shipment_no', 'booking_no'])->all()];
        foreach (['container_type' => 'container_type', 'vessel_name' => 'vessel_name', 'voyage_no' => 'voyage_no', 'etd' => 'etd', 'eta' => 'eta'] as $k => $from) {
            $head[$k] = ($head[$k] ?? null) ?: ($booking?->{$from} ?? null);
        }
        foreach (['gross_weight', 'net_weight', 'cbm', 'carton_qty'] as $k) {
            $head[$k] = $head[$k] ?? 0;
        }
        $row = DB::transaction(function () use ($row, $head, $s) {
            $row ? $row->update($head) : $row = SsContainer::query()->create($head);
            ShipmentBook::restage($s->refresh());

            return $row;
        });

        return ApiResponse::ok(['container_no' => $row->container_no, 'shipment_no' => $s->shipment_no, 'stage' => $s->refresh()->stage], [], $shipmentNo ? 200 : 201);
    }

    public function containerDestroy(string $shipmentNo, string $containerNo): JsonResponse
    {
        $this->allow('salesshipment.shipment.manage');
        $s = $this->openShipment($shipmentNo);
        SsContainer::query()->where('shipment_id', $s->id)->where('container_no', $containerNo)->firstOrFail()->delete();
        ShipmentBook::restage($s);

        return ApiResponse::ok(['deleted' => $containerNo]);
    }

    /* ── Commercial document handovers ─────────────────────────────── */

    public function handovers(Request $request): JsonResponse
    {
        $kind = $this->kind($request);
        $this->allow($this->perm($kind, 'view'));
        $rows = SsDocHandover::query()->where('kind', $kind)->orderByDesc('handover_date')->orderByDesc('handover_no')->get();
        $ships = $this->shipmentNos($rows->pluck('shipment_id')->all());
        $inv = DB::table('ss_invoices')->whereIn('id', $rows->pluck('invoice_id')->filter())->pluck('invoice_no', 'id');
        $pack = DB::table('ss_packing_lists')->whereIn('id', $rows->pluck('packing_list_id')->filter())->pluck('packing_no', 'id');
        $names = $this->book()->customerNames();

        return ApiResponse::ok($rows->map(fn (SsDocHandover $h) => [
            'handover_no' => $h->handover_no, 'handover_date' => $h->handover_date->toDateString(), 'shipment_no' => $ships[$h->shipment_id]->shipment_no ?? '—',
            'buyer' => $names[$ships[$h->shipment_id]->customer_id ?? ''] ?? '—', 'invoice_no' => $h->invoice_id ? ($inv[$h->invoice_id] ?? null) : null,
            'packing_no' => $h->packing_list_id ? ($pack[$h->packing_list_id] ?? null) : null, 'documents' => implode(' / ', array_filter([$h->exp_no, $h->certificate])) ?: null,
            'exp_no' => $h->exp_no, 'certificate' => $h->certificate, 'document_type' => $h->document_type, 'document_list' => $h->document_list, 'submitted_by' => $h->submitted_by, 'received_by' => $h->received_by,
            'status' => $h->status, 'file_id' => $h->file_id, 'attachment' => $h->file_name, 'remarks' => $h->remarks,
        ])->values()->all());
    }

    public function saveHandover(Request $request, ?string $no = null): JsonResponse
    {
        $kind = $this->kind($request);
        $this->allow($this->perm($kind, 'manage'));
        $row = $no ? SsDocHandover::query()->where('kind', $kind)->where('handover_no', $no)->firstOrFail() : null;
        if ($row && $row->status === 'RECEIVED') {
            throw ValidationException::withMessages(['handover_no' => __('salesshipment.v.dh_locked', ['no' => $row->handover_no])]);
        }
        $data = $request->validate([
            'handover_date' => ['required', 'date'], 'shipment_no' => ['required', 'string', 'max:24'], 'invoice_no' => ['nullable', 'string', 'max:24'], 'packing_no' => ['nullable', 'string', 'max:24'],
            'exp_no' => ['nullable', 'string', 'max:60'], 'certificate' => ['nullable', 'string', 'max:120'], 'document_type' => ['nullable', 'string', 'max:120'], 'document_list' => [$kind === 'FINAL' ? 'required' : 'nullable', 'string', 'max:2000'],
            'submitted_by' => ['nullable', 'string', 'max:120'], 'received_by' => ['nullable', 'string', 'max:120'], 'remarks' => ['nullable', 'string', 'max:2000'],
        ], ['document_list.required' => __('salesshipment.v.fh_list')]);
        $s = SsShipment::query()->where('shipment_no', $data['shipment_no'])->firstOrFail();
        if ($s->stage === 'CANCELLED') {
            throw ValidationException::withMessages(['shipment_no' => __('salesshipment.v.shp_not_open', ['no' => $s->shipment_no, 'stage' => __('salesshipment.sp.stage.CANCELLED')])]);
        }
        $paper = function (string $table, string $col, ?string $value) use ($s) {
            if (! $value) {
                return null;
            }
            $id = DB::table($table)->where($col, $value)->where('shipment_id', $s->id)->where('state', '!=', 'CANCELLED')->value('id');
            if (! $id) {
                throw ValidationException::withMessages([$col => __('salesshipment.v.dh_paper', ['no' => $value, 'shipment' => $s->shipment_no])]);
            }

            return $id;
        };
        $this->book()->assertOpen($data['handover_date'], 'handover_date');
        $head = ['shipment_id' => $s->id, 'invoice_id' => $paper('ss_invoices', 'invoice_no', $data['invoice_no'] ?? null), 'packing_list_id' => $paper('ss_packing_lists', 'packing_no', $data['packing_no'] ?? null),
            ...collect($data)->only(['handover_date', 'exp_no', 'certificate', 'document_type', 'document_list', 'received_by', 'remarks'])->all(),
            'submitted_by' => ($data['submitted_by'] ?? null) ?: $this->book()->user($this->caller()->auditUserId())];
        $row = DB::transaction(function () use ($row, $head, $data, $s, $kind) {
            $row ? $row->update($head) : $row = SsDocHandover::query()->create($head + ['handover_no' => SsSettings::nextNo('prefix_handover', 'ss_doc_handovers', 'handover_no', $data['handover_date'], $this->caller()->companyId),
                'kind' => $kind, 'status' => 'PENDING', 'created_by' => $this->caller()->auditUserId()]);
            ShipmentBook::restage($s->refresh());

            return $row;
        });

        return ApiResponse::ok(['handover_no' => $row->handover_no, 'status' => $row->status], [], $no ? 200 : 201);
    }

    /** Export Documentation → Document Handover (21/06): the final set to the buyer or the bank. */
    public function finalHandovers(Request $request): JsonResponse
    {
        $this->kind = 'FINAL';

        return $this->handovers($request);
    }

    public function saveFinalHandover(Request $request, ?string $no = null): JsonResponse
    {
        $this->kind = 'FINAL';

        return $this->saveHandover($request, $no);
    }

    public function handoverAct(string $no, string $action): JsonResponse
    {
        $from = ['handed-over' => [['PENDING'], 'HANDED_OVER'], 'received' => [['HANDED_OVER'], 'RECEIVED'], 'returned' => [['HANDED_OVER', 'RECEIVED'], 'RETURNED']];
        abort_unless(isset($from[$action]), 404);
        $this->allow($this->perm((string) SsDocHandover::query()->where('handover_no', $no)->value('kind'), 'manage'));
        $row = DB::transaction(function () use ($no, $action, $from) {
            $row = SsDocHandover::query()->where('handover_no', $no)->lockForUpdate()->firstOrFail();
            if (! in_array($row->status, $from[$action][0], true)) {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.step', ['no' => $row->handover_no, 'status' => __('salesshipment.dh.status.'.$row->status)])]);
            }
            if ($action === 'received' && blank($row->received_by)) {
                throw ValidationException::withMessages(['received_by' => __('salesshipment.v.dh_receiver', ['no' => $row->handover_no])]);
            }
            $row->update(['status' => $from[$action][1]]);
            ShipmentBook::restage(SsShipment::query()->findOrFail($row->shipment_id));

            return $row;
        });

        return ApiResponse::ok(['handover_no' => $row->handover_no, 'status' => $row->status]);
    }

    /** The scanned papers, attached to a handover. */
    public function handoverAttach(Request $request, StoreFileAttachment $files, string $no): JsonResponse
    {
        $this->allow($this->perm((string) SsDocHandover::query()->where('handover_no', $no)->value('kind'), 'manage'));
        $row = SsDocHandover::query()->where('handover_no', $no)->firstOrFail();
        $request->validate(['upload' => ['required', 'file', 'max:10240']], ['upload.required' => __('salesshipment.v.dh_file')]);
        $upload = $request->file('upload');
        $file = $files->handle($upload, 'ss_handover', $row->id, $this->caller()->auditUserId());
        $row->update(['file_id' => $file->id, 'file_name' => mb_substr($upload->getClientOriginalName(), 0, 255)]);

        return ApiResponse::ok(['handover_no' => $row->handover_no, 'file_id' => $file->id, 'attachment' => $row->file_name]);
    }

    public function handoverDestroy(string $no): JsonResponse
    {
        $this->allow($this->perm((string) SsDocHandover::query()->where('handover_no', $no)->value('kind'), 'manage'));
        $row = SsDocHandover::query()->where('handover_no', $no)->firstOrFail();
        if ($row->status !== 'PENDING') {
            throw ValidationException::withMessages(['status' => __('salesshipment.v.dh_delete', ['no' => $row->handover_no])]);
        }
        $row->delete();

        return ApiResponse::ok(['deleted' => $no]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    /** COMMERCIAL (21/05, Commercial → Shipping) or FINAL (21/06, to the buyer or the bank) — from the route. */
    private function kind(Request $request): string
    {
        return $this->kind ?? 'COMMERCIAL';
    }

    /** The commercial handover belongs to the shipment desk, the final one to export documentation. */
    private function perm(string $kind, string $level): string
    {
        return ($kind === 'FINAL' ? 'salesshipment.exportdoc.' : 'salesshipment.shipment.').$level;
    }

    private function book(): SalesBook
    {
        return $this->book ??= new SalesBook($this->caller()->companyId);
    }

    /** A shipment still being prepared — booked, contained and handed over only while open. */
    private function openShipment(string $no): SsShipment
    {
        $s = SsShipment::query()->where('shipment_no', $no)->firstOrFail();
        if (! in_array($s->stage, SsShipment::OPEN, true)) {
            throw ValidationException::withMessages(['shipment_no' => __('salesshipment.v.shp_not_open', ['no' => $s->shipment_no, 'stage' => __('salesshipment.sp.stage.'.$s->stage)])]);
        }

        return $s;
    }

    /** @return Collection<string, object> */
    private function shipmentNos(array $ids)
    {
        return DB::table('ss_shipments as s')->join('ss_sales_orders as o', 'o.id', '=', 's.order_id')->whereIn('s.id', $ids)->get(['s.id', 's.shipment_no', 'o.so_no', 'o.customer_id'])->keyBy('id');
    }
}

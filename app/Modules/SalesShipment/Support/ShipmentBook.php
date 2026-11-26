<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Support;

use App\Modules\SalesShipment\Models\SsShipment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sales & Shipment → Shipment (AnnotechERP 21/05): what the shipment screens
 * read the same way — per shipment line what live invoices claim and live
 * packing lists pack (final only, when asked), the readiness checks Brickland
 * confirms against (quantity, shipping, container, invoice, packing,
 * documents — the EXP and the B/L or AWB filed, 21/06), and the stage a shipment's paperwork shows: PLANNING →
 * BOOKED → DOCUMENTATION → READY. A confirmed, closed or cancelled shipment
 * keeps its stage.
 */
final class ShipmentBook
{
    /** @return Collection<string, float> */
    public static function invoiced(array $lineIds, ?string $exceptInvoice = null, bool $finalOnly = false): Collection
    {
        $rows = DB::table('ss_invoice_lines as l')->join('ss_invoices as i', 'i.id', '=', 'l.invoice_id')->whereIn('l.shipment_line_id', $lineIds)
            ->when($finalOnly, fn ($q) => $q->where('i.state', 'FINAL'), fn ($q) => $q->where('i.state', '!=', 'CANCELLED'))
            ->when($exceptInvoice, fn ($q) => $q->where('i.id', '!=', $exceptInvoice))->groupBy('l.shipment_line_id')->selectRaw('l.shipment_line_id, sum(l.qty) as q')->pluck('q', 'shipment_line_id');

        return collect($lineIds)->mapWithKeys(fn ($id) => [$id => (float) ($rows[$id] ?? 0)]);
    }

    /** Pieces packed per shipment line: (to − from + 1) × pcs per carton. @return Collection<string, float> */
    public static function packed(array $lineIds, ?string $exceptPacking = null, bool $finalOnly = false): Collection
    {
        $rows = DB::table('ss_packing_lines as l')->join('ss_packing_lists as p', 'p.id', '=', 'l.packing_list_id')->whereIn('l.shipment_line_id', $lineIds)
            ->when($finalOnly, fn ($q) => $q->where('p.state', 'FINAL'), fn ($q) => $q->where('p.state', '!=', 'CANCELLED'))
            ->when($exceptPacking, fn ($q) => $q->where('p.id', '!=', $exceptPacking))->groupBy('l.shipment_line_id')
            ->selectRaw('l.shipment_line_id, sum((l.carton_to - l.carton_from + 1) * l.pcs_per_carton) as q')->pluck('q', 'shipment_line_id');

        return collect($lineIds)->mapWithKeys(fn ($id) => [$id => (float) ($rows[$id] ?? 0)]);
    }

    /** "SO · Item · Colour · Size" per shipment line. @return array<string, string> */
    public static function labels(SalesBook $book, array $lineIds): array
    {
        $rows = DB::table('ss_shipment_lines')->whereIn('id', $lineIds)->pluck('size_id', 'id');
        $sizes = $book->sizeLabels($rows->values()->all());

        return $rows->map(fn ($size) => $sizes[$size] ?? '—')->all();
    }

    /** The shipping booking that counts: confirmed or completed, with a vessel and an ETD. */
    public static function booking(string $shipmentId): ?object
    {
        return DB::table('ss_bookings')->where('shipment_id', $shipmentId)->whereIn('status', ['CONFIRMED', 'COMPLETED'])->whereNotNull('vessel_name')->whereNotNull('etd')
            ->orderByDesc('booking_date')->orderByDesc('booking_no')->first();
    }

    /**
     * The readiness checks and the figures beside them.
     *
     * @return array{planned: float, invoiced: float, packed: float, vessel: ?string, etd: ?string, checks: array<string, bool>, ready: bool}
     */
    public static function readiness(SsShipment $s): array
    {
        $lines = DB::table('ss_shipment_lines')->where('shipment_id', $s->id)->pluck('qty', 'id');
        $inv = self::invoiced($lines->keys()->all(), null, true);
        $pack = self::packed($lines->keys()->all(), null, true);
        $booking = self::booking($s->id);
        $finalInvoice = DB::table('ss_invoices')->where('shipment_id', $s->id)->where('state', 'FINAL')->exists();
        $finalPacking = DB::table('ss_packing_lists')->where('shipment_id', $s->id)->where('state', 'FINAL')->exists();
        $needsContainer = in_array($s->shipment_mode, ['Sea', 'Sea-Air'], true);
        // Brickland's own rule (21/01): no confirmation until the EXP and the B/L or AWB are filed.
        $filed = fn (array $types) => DB::table('ss_export_docs')->where('shipment_id', $s->id)->whereIn('doc_type', $types)->where('status', '!=', 'CANCELLED')->exists();
        $docs = ! SsSettings::get()->require_export_docs || ($filed(['EXP']) && $filed(['BILL_OF_LADING', 'AIR_WAYBILL']));
        $qtyOk = $inv->sum() > 0 && $lines->keys()->every(fn ($id) => abs($inv[$id] - $pack[$id]) < 0.0005 && $inv[$id] <= (float) $lines[$id] + 0.0005);
        $checks = [
            'qty' => $qtyOk,
            'booking' => $booking !== null,
            'container' => ! $needsContainer || DB::table('ss_containers')->where('shipment_id', $s->id)->exists(),
            'invoice' => $finalInvoice,
            'packing' => $finalPacking,
            'documents' => $docs,
        ];

        return ['planned' => (float) $lines->sum(), 'invoiced' => (float) $inv->sum(), 'packed' => (float) $pack->sum(), 'vessel' => $booking ? trim($booking->vessel_name.' '.($booking->voyage_no ?? '')) : null,
            'etd' => $booking?->etd, 'checks' => $checks, 'ready' => ! in_array(false, $checks, true)];
    }

    /** The stage the shipment's paperwork shows — written back while it is still open. */
    public static function restage(SsShipment $s): string
    {
        if (! in_array($s->stage, SsShipment::OPEN, true)) {
            return $s->stage;
        }
        $r = self::readiness($s);
        $docsStarted = DB::table('ss_invoices')->where('shipment_id', $s->id)->where('state', 'FINAL')->exists()
            || DB::table('ss_packing_lists')->where('shipment_id', $s->id)->where('state', 'FINAL')->exists();
        $stage = $r['ready'] ? 'READY' : ($docsStarted ? 'DOCUMENTATION' : ($r['checks']['booking'] ? 'BOOKED' : 'PLANNING'));
        if ($stage !== $s->stage) {
            $s->update(['stage' => $stage]);
        }

        return $stage;
    }

    /** True once a live invoice or packing list stands on the shipment — its plan lines are then locked. */
    public static function papered(string $shipmentId): bool
    {
        return DB::table('ss_invoices')->where('shipment_id', $shipmentId)->where('state', '!=', 'CANCELLED')->exists()
            || DB::table('ss_packing_lists')->where('shipment_id', $shipmentId)->where('state', '!=', 'CANCELLED')->exists();
    }
}

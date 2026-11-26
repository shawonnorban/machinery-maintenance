<?php

declare(strict_types=1);

namespace App\Modules\Cutting\Support;

use App\Shared\Fabric\FabricLength;
use Illuminate\Support\Facades\DB;

/**
 * The fabric of one PO's colour as the cutting floor sees it (the user's
 * design of 2026-11-16) — counted by length (yards or metres, the company's
 * unit) with the kilos beside it, never the two added together:
 *  - required: Merchandise → Fabric Booking — every line of a booking for the
 *    order (or for every order of its style), in the colour or all colours,
 *    per piece (consumption ÷ 12 per dozen) × the colour's order × (1 + its
 *    cutting wastage), in the line's own unit; a kilo line is put into length
 *    through its GSM and width, a yard or metre line into kilos the same way;
 *    a booking cancelled or rejected never counts;
 *  - issued: what reached cutting — the finished fabric store's requisitions
 *    from the lots of this order and colour, and purchased fabric taken out
 *    of Store & Material for cutting (its returns taken off);
 *  - used: what the cuttings laid on it take — the lay until the output
 *    records what was really used;
 *  - available: issued − used, and about how many pieces it still cuts.
 */
final class CutFabric
{
    /** @return array<string, mixed> */
    public static function of(string $orderId, string $colour, ?string $exceptInputId = null): array
    {
        $colour = mb_strtolower(trim($colour));
        $unit = FabricLength::unit();
        $order = DB::table('merch_orders')->where('id', $orderId)->first(['id', 'style_id', 'total_qty']);
        $colourQty = (int) DB::table('merch_order_sizes')->where('order_id', $orderId)->get(['colour', 'qty'])->filter(fn ($r) => mb_strtolower((string) $r->colour) === $colour)->sum('qty');
        $pieces = $colourQty ?: (int) ($order->total_qty ?? 0);

        // ── Required, line by line, in its own unit ─────────────────────
        $bookings = DB::table('merch_fabric_bookings')->whereNotIn('status', ['CANCELLED', 'REJECTED'])
            ->where(fn ($q) => $q->where('order_id', $orderId)->orWhere('style_id', $order->style_id ?? '__none__'))->pluck('order_id', 'id');
        $colours = DB::table('colours')->pluck('name', 'id');
        $fabrics = DB::table('fabric_types')->pluck('name', 'id');
        $parts = DB::table('garment_parts')->pluck('name', 'id');
        $lines = DB::table('merch_fabric_booking_lines')->whereIn('booking_id', $bookings->keys())->whereNotNull('consumption')->get()
            // A line of a style's booking counts for this order only when it says "all orders".
            ->filter(fn ($l) => $bookings[$l->booking_id] === $orderId || $l->all_orders)
            ->filter(fn ($l) => $l->all_colours || mb_strtolower((string) ($colours[$l->gmt_colour_id] ?? '')) === $colour)
            ->map(function ($l) use ($pieces, $fabrics, $parts) {
                $consUnit = strtoupper((string) $l->cons_unit);
                $perPiece = (float) $l->consumption / (strtoupper((string) $l->cons_per) === 'DZ' ? 12 : 1);
                $widthInch = $l->finish_dia === null ? null : ((string) $l->dia_unit === 'CM' ? (float) $l->finish_dia / 2.54 : (float) $l->finish_dia);
                $kgPiece = $consUnit === 'KG' ? $perPiece : FabricLength::metresToKg((float) FabricLength::toMetres($perPiece, $consUnit), $l->gsm, $widthInch);
                $mPiece = $consUnit === 'KG' ? FabricLength::kgToMetres($perPiece, $l->gsm, $widthInch) : FabricLength::toMetres($perPiece, $consUnit);
                $factor = $pieces * (1 + (float) $l->cutting_wastage / 100);

                return ['fabric' => $fabrics[$l->fabric_type_id] ?? null, 'part' => $parts[$l->garment_part_id] ?? null, 'consumption' => (float) $l->consumption, 'cons_unit' => $consUnit, 'cons_per' => $l->cons_per,
                    'cutting_wastage' => (float) $l->cutting_wastage, 'gsm' => $l->gsm === null ? null : (float) $l->gsm, 'width_inch' => $widthInch,
                    'm_piece' => $mPiece, 'kg_piece' => $kgPiece,
                    'required_len' => $mPiece === null ? null : FabricLength::fromMetres($mPiece * $factor), 'required_kg' => $kgPiece === null ? null : round($kgPiece * $factor, 3)];
            })->values();

        // ── Issued to cutting ─────────────────────────────────────────────
        $lots = DB::table('fin_store_lots')->where('order_id', $orderId)->get(['id', 'lot_code', 'lot_no', 'colour', 'fabric_type', 'fin_gsm', 'fin_dia', 'received_kg', 'received_m'])
            ->filter(fn ($l) => mb_strtolower((string) $l->colour) === $colour)->values();
        $reqLines = DB::table('fin_cut_requisition_lines as l')->join('fin_cut_requisitions as r', 'r.id', '=', 'l.requisition_id')
            ->whereIn('l.lot_id', $lots->pluck('id'))->whereIn('r.status', ['PARTIAL', 'COMPLETE'])->get(['l.lot_id', 'l.issue_qty', 'l.issue_m']);
        $lotOf = $lots->keyBy('id');
        $byLot = $reqLines->groupBy('lot_id')->map(function ($g, $lotId) use ($lotOf) {
            $lot = $lotOf[$lotId];
            $kgPerM = $lot->received_m ? (float) $lot->received_kg / (float) $lot->received_m : null;
            $m = $g->sum(fn ($x) => $x->issue_m !== null ? (float) $x->issue_m
                : ($kgPerM ? (float) $x->issue_qty / $kgPerM : (float) FabricLength::kgToMetres((float) $x->issue_qty, $lot->fin_gsm, $lot->fin_dia)));

            return ['kg' => (float) $g->sum('issue_qty'), 'm' => $m];
        });
        // Purchased fabric taken out of Store & Material for cutting, net of what came back.
        $outs = DB::table('sm_material_outs as o')->join('sm_stocks as s', 's.id', '=', 'o.stock_id')->join('mp_items as i', 'i.id', '=', 's.item_id')
            ->where('o.purpose', 'CUTTING')->where('s.order_id', $orderId)->whereRaw('lower(coalesce(i.colour, \'\')) = ?', [$colour])
            ->get(['o.id', 'o.mo_no', 'o.qty', 's.uom', 'i.name as item']);
        $back = DB::table('sm_material_returns')->whereIn('material_out_id', $outs->pluck('id'))->groupBy('material_out_id')->selectRaw('material_out_id, sum(qty) as qty')->pluck('qty', 'material_out_id');
        $purchased = $outs->map(function ($o) use ($back) {
            $net = (float) $o->qty - (float) ($back[$o->id] ?? 0);
            $u = strtoupper((string) $o->uom);
            $u = in_array($u, ['YD', 'YARD', 'YARDS'], true) ? 'YDS' : (in_array($u, ['M', 'METER', 'METRE', 'METERS'], true) ? 'MTR' : $u);

            return ['mo_no' => $o->mo_no, 'item' => $o->item, 'qty' => round($net, 3), 'uom' => $o->uom, 'm' => in_array($u, FabricLength::UNITS, true) ? FabricLength::toMetres($net, $u) : null, 'kg' => $u === 'KG' ? $net : null];
        })->values();

        $issuedM = (float) $byLot->sum('m') + (float) $purchased->sum('m');
        $issuedKg = (float) $byLot->sum('kg') + (float) $purchased->sum('kg');

        // ── Used by the cuttings laid ──────────────────────────────────────
        $inputs = DB::table('cut_inputs')->where('order_id', $orderId)->where('status', '!=', 'CANCELLED')->whereRaw('lower(colour) = ?', [$colour])
            ->when($exceptInputId, fn ($q, $id) => $q->where('id', '!=', $id))->get(['lay_m', 'used_m', 'fabric_kg', 'used_kg']);
        $usedM = (float) $inputs->sum(fn ($i) => (float) ($i->used_m ?? $i->lay_m ?? 0));
        $usedKg = (float) $inputs->sum(fn ($i) => (float) ($i->used_kg ?? $i->fabric_kg ?? 0));

        $mPiece = $lines->isNotEmpty() && $lines->every(fn ($l) => $l['m_piece'] !== null) ? (float) $lines->sum('m_piece') : null;
        $kgPiece = $lines->isNotEmpty() && $lines->every(fn ($l) => $l['kg_piece'] !== null) ? (float) $lines->sum('kg_piece') : null;
        $availableM = $issuedM - $usedM;

        return [
            'unit' => $unit,
            // What the lines can be counted in; a line without GSM and width leaves the other unit short (flagged).
            'required_len' => $lines->contains(fn ($l) => $l['required_len'] !== null) ? round((float) $lines->sum('required_len'), 3) : null,
            'required_kg' => $lines->contains(fn ($l) => $l['required_kg'] !== null) ? round((float) $lines->sum('required_kg'), 3) : null,
            'required_len_partial' => $lines->contains(fn ($l) => $l['required_len'] === null),
            'required_kg_partial' => $lines->contains(fn ($l) => $l['required_kg'] === null),
            'issued_len' => FabricLength::fromMetres($issuedM), 'issued_kg' => round($issuedKg, 3),
            'used_len' => FabricLength::fromMetres($usedM), 'used_kg' => round($usedKg, 3),
            'available_len' => FabricLength::fromMetres($availableM), 'available_kg' => round($issuedKg - $usedKg, 3), 'available_m' => round($availableM, 3),
            'per_piece_len' => $mPiece === null ? null : FabricLength::fromMetres($mPiece), 'per_piece_kg' => $kgPiece === null ? null : round($kgPiece, 4),
            'pieces_left' => $mPiece ? (int) floor(max(0, $availableM) / $mPiece) : ($kgPiece ? (int) floor(max(0, $issuedKg - $usedKg) / $kgPiece) : null),
            'lines' => $lines->map(fn ($l) => array_diff_key($l, ['m_piece' => 1, 'kg_piece' => 1, 'width_inch' => 1]))->all(),
            'lots' => $lots->map(fn ($l) => ['id' => $l->id, 'lot_code' => $l->lot_code, 'lot_no' => $l->lot_no, 'fabric_type' => $l->fabric_type, 'gsm' => $l->fin_gsm, 'dia' => $l->fin_dia,
                'received_kg' => (float) $l->received_kg, 'received_len' => FabricLength::fromMetres($l->received_m),
                'issued_kg' => round((float) ($byLot[$l->id]['kg'] ?? 0), 3), 'issued_len' => FabricLength::fromMetres($byLot[$l->id]['m'] ?? 0)])->all(),
            'purchased' => $purchased->map(fn ($p) => array_diff_key($p, ['m' => 1, 'kg' => 1]) + ['len' => FabricLength::fromMetres($p['m'])])->all(),
        ];
    }

    /** A lay's length in metres: the marker (in the company's unit) × plies. */
    public static function layMetres(?float $markerLength, int $plies): ?float
    {
        // Multiplied before rounding, so 4.5 yds × 10 plies is 45 yds exactly, not 45.002.
        return $markerLength && $plies > 0 ? round($markerLength * FabricLength::metresPer() * $plies, 3) : null;
    }
}

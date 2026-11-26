<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Http\Controllers\Api;

use App\Modules\SalesShipment\Support\SalesBook;
use App\Modules\SalesShipment\Support\SsSettings;
use App\Modules\SalesShipment\Support\SubconBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Sales & Shipment → Report (AnnotechERP 21/07), the two watch screens:
 *
 * - Order-wise Tracking: every event on one sales order in the order it
 *   happened — booking and approval, amendments, the S/C programme,
 *   deliveries, returns and bills, sub-contract papers, shipment plans,
 *   bookings, invoices, packing lists, handovers and export documents,
 *   confirmation and closing — and the order's balances above it.
 * - Notifications & Alerts: the live alerts worked out from the papers at
 *   the moment of asking (nothing is stored; an alert clears itself once its
 *   paper moves on), each with the screen it is cleared on.
 */
class SalesWatchApiController extends ApiController
{
    /** Each alert kind, its screen and its severity rule. */
    public const KINDS = ['DELIVERY_DUE', 'DELIVERY_OVERDUE', 'SHIPMENT_DUE', 'SHIPMENT_OVERDUE', 'BOOKING_PENDING', 'DOCUMENT_PENDING', 'CERTIFICATE_EXPIRING', 'SUBCONTRACT_OVERDUE', 'CLOSING_PENDING'];

    private const PREP = ['PLANNING', 'BOOKED', 'DOCUMENTATION', 'READY'];

    private ?SalesBook $book = null;

    /* ── Order-wise Tracking ───────────────────────────────────────── */

    public function trackOrders(): JsonResponse
    {
        $this->allow('salesshipment.report.view');
        $names = $this->book()->customerNames();

        return ApiResponse::ok(DB::table('ss_sales_orders')->where('company_id', $this->caller()->companyId)->orderByDesc('receive_date')->orderByDesc('so_no')->get(['so_no', 'customer_id', 'po_wo_no', 'state'])
            ->map(fn ($o) => ['value' => $o->so_no, 'label' => $o->so_no.' · '.($names[$o->customer_id] ?? '—').($o->po_wo_no ? ' · '.$o->po_wo_no : ''), 'state' => $o->state])->values()->all());
    }

    public function track(string $no): JsonResponse
    {
        $this->allow('salesshipment.report.view');
        $b = $this->book();
        $o = DB::table('ss_sales_orders')->where('company_id', $this->caller()->companyId)->where('so_no', $no)->first();
        abort_unless($o, 404);
        $conv = (float) $o->conv_rate ?: 1.0;
        $code = $b->currencyCodes()[$o->currency_id] ?? '';
        $sizes = DB::table('ss_sales_order_sizes')->where('order_id', $o->id)->get(['id', 'qty', 'rate']);
        $fig = $b->sizeFigures([$o->id]);
        $ordered = (float) $sizes->sum('qty');
        $net = (float) $sizes->sum(fn ($s) => $fig[$s->id]['net'] ?? 0);
        $out = (float) $sizes->sum(fn ($s) => $fig[$s->id]['out'] ?? 0);
        $returned = (float) $sizes->sum(fn ($s) => $fig[$s->id]['returned'] ?? 0);
        $shipped = (float) DB::table('ss_shipment_lines as l')->join('ss_shipments as s', 's.id', '=', 'l.shipment_id')->where('s.order_id', $o->id)->whereIn('s.stage', ['CONFIRMED', 'CLOSED'])->sum('l.shipped_qty');
        $against = (bool) SsSettings::get()->ship_against_delivered;
        $events = [];
        $add = function (mixed $date, string $stage, ?string $ref, ?float $qty, ?float $value, ?string $status, ?string $detail, int $seq) use (&$events) {
            if (! $date) {
                return;
            }
            $events[] = ['date' => CarbonImmutable::parse($date)->timezone('Asia/Dhaka')->toDateString(), 'stage' => __("salesshipment.ot.stage.{$stage}"), 'reference' => $ref,
                'qty' => $qty, 'value' => $value === null ? null : round($value, 2), 'status' => $status, 'detail' => $detail, 'seq' => $seq];
        };

        // The order itself and its approval steps.
        $add($o->receive_date, 'order', $o->so_no, $ordered, $sizes->sum(fn ($s) => $s->qty * $s->rate) * $conv, __('salesshipment.sr.status.approval.DRAFT'), $b->user($o->created_by), 1);
        foreach (['submitted' => 'SUBMITTED', 'checked' => 'CHECKED', 'approved' => 'APPROVED', 'confirmed' => 'CONFIRMED'] as $step => $st) {
            $add($o->{"{$step}_at"}, 'approval', $o->so_no, null, null, __("salesshipment.sr.status.approval.{$st}"), $b->user($o->{"{$step}_by"}), 2);
        }
        foreach (DB::table('ss_amendments')->where('order_id', $o->id)->orderBy('amend_date')->get() as $a) {
            $lines = DB::table('ss_amendment_lines')->where('amendment_id', $a->id)->get(['prev_qty', 'revised_qty']);
            $add($a->amend_date, 'amendment', $a->amend_no, (float) $lines->sum('revised_qty') - (float) $lines->sum('prev_qty'), null, __('salesshipment.ot.amend.'.$a->state), $a->reason ?: $b->user($a->created_by), 3);
        }
        foreach (DB::table('ss_sc_programmes')->where('order_id', $o->id)->get() as $p) {
            $q = (float) DB::table('ss_sc_programme_lines')->where('programme_id', $p->id)->sum('qty');
            $add($p->sc_date, 'sc_programme', $p->sc_no, $q, null, $p->process, $b->customerNames()[$p->party_id] ?? null, 4);
        }
        // Deliveries, returns and bills.
        $challans = DB::table('ss_challans')->where('order_id', $o->id)->orderBy('chalan_date')->orderBy('chalan_no')->get();
        foreach ($challans as $c) {
            $ls = DB::table('ss_challan_lines')->where('challan_id', $c->id)->get(['qty', 'rate']);
            $add($c->chalan_date, 'challan', $c->chalan_no, (float) $ls->sum('qty'), $ls->sum(fn ($l) => $l->qty * $l->rate) * ((float) $c->conv_rate ?: 1.0), __('salesshipment.sr.status.challan.'.$c->state),
                trim(($b->stores()[$c->store_id] ?? '').($c->gate_pass_no ? ' · '.$c->gate_pass_no : ''), ' ·') ?: null, 5);
        }
        foreach (DB::table('ss_challan_returns')->whereIn('challan_id', $challans->pluck('id'))->orderBy('return_date')->get() as $r) {
            $add($r->return_date, 'return', $r->return_no, (float) DB::table('ss_challan_return_lines')->where('return_id', $r->id)->sum('qty'), null, __('salesshipment.ot.return.'.$r->state), $r->reason, 6);
        }
        $bills = DB::table('ss_bill_lines as bl')->join('ss_bills as bi', 'bi.id', '=', 'bl.bill_id')->join('ss_challan_lines as l', 'l.id', '=', 'bl.challan_line_id')
            ->whereIn('l.challan_id', $challans->pluck('id'))->groupBy('bi.id', 'bi.bill_no', 'bi.bill_date', 'bi.state')
            ->selectRaw('bi.bill_no, bi.bill_date, bi.state, sum(bl.qty) as q, sum(bl.qty * bl.rate * bl.exchange_rate) as v')->get();
        foreach ($bills as $bi) {
            $add($bi->bill_date, 'bill', $bi->bill_no, (float) $bi->q, (float) $bi->v, __('salesshipment.ot.bill.'.$bi->state), null, 7);
        }
        // Sub-contract papers.
        $scd = DB::table('ss_sc_deliveries as d')->join('ss_sc_programmes as p', 'p.id', '=', 'd.programme_id')->where('p.order_id', $o->id)->orderBy('d.chalan_date')->get(['d.id', 'd.chalan_no', 'd.chalan_date', 'd.state', 'd.closed', 'd.customer_id', 'p.sc_no']);
        foreach ($scd as $d) {
            $add($d->chalan_date, 'sc_delivery', $d->chalan_no, (float) DB::table('ss_sc_delivery_lines')->where('delivery_id', $d->id)->sum('qty'), null, __('salesshipment.sr.status.sc.'.($d->closed ? 'CLOSED' : $d->state)),
                $d->sc_no.' · '.($b->customerNames()[$d->customer_id] ?? '—'), 8);
        }
        foreach (DB::table('ss_sc_receipts')->whereIn('delivery_id', $scd->pluck('id'))->orderBy('mrr_date')->get() as $r) {
            $add($r->mrr_date, 'sc_receipt', $r->mrr_no, (float) DB::table('ss_sc_receipt_lines')->where('receipt_id', $r->id)->sum('qty'), null, __('salesshipment.sr.status.sc.'.$r->state), __('salesshipment.sr.rtype.'.$r->receive_type), 9);
        }
        // Shipments and their papers.
        $ships = DB::table('ss_shipments')->where('order_id', $o->id)->orderBy('plan_date')->orderBy('shipment_no')->get();
        $sid = $ships->pluck('id');
        foreach ($ships as $s) {
            $ls = DB::table('ss_shipment_lines')->where('shipment_id', $s->id)->get(['qty', 'rate', 'shipped_qty']);
            $add($s->plan_date, 'shipment', $s->shipment_no, (float) $ls->sum('qty'), $ls->sum(fn ($l) => $l->qty * $l->rate) * $conv, __('salesshipment.sr.status.stage.'.$s->stage),
                trim($s->shipment_mode.' · '.$s->destination_port, ' ·'), 10);
            $add($s->confirmed_at, 'confirmation', $s->shipment_no, (float) $ls->sum('shipped_qty'), $ls->sum(fn ($l) => $l->shipped_qty * $l->rate) * $conv, __('salesshipment.sr.status.stage.CONFIRMED'), $b->user($s->confirmed_by), 17);
            $add($s->closed_at, 'shipment_closing', $s->shipment_no, null, null, __('salesshipment.sr.status.stage.CLOSED'), $b->user($s->closed_by), 18);
        }
        foreach (DB::table('ss_bookings')->whereIn('shipment_id', $sid)->get() as $k) {
            $add($k->booking_date, 'booking', $k->booking_no, null, null, __('salesshipment.ot.booking.'.$k->status), trim(($k->vessel_name ?? '').($k->voyage_no ? ' / '.$k->voyage_no : '')) ?: null, 11);
        }
        foreach (DB::table('ss_invoices as i')->whereIn('i.shipment_id', $sid)->get() as $i) {
            $ls = DB::table('ss_invoice_lines')->where('invoice_id', $i->id)->get(['qty', 'unit_price']);
            $add($i->invoice_date, 'invoice', $i->invoice_no, (float) $ls->sum('qty'), $ls->sum(fn ($l) => $l->qty * $l->unit_price) * $conv, __('salesshipment.ot.paper.'.$i->state), null, 12);
        }
        foreach (DB::table('ss_packing_lists')->whereIn('shipment_id', $sid)->get() as $p) {
            $pcs = DB::table('ss_packing_lines')->where('packing_list_id', $p->id)->get(['carton_from', 'carton_to', 'pcs_per_carton'])->sum(fn ($l) => ($l->carton_to - $l->carton_from + 1) * $l->pcs_per_carton);
            $add($p->packing_date, 'packing', $p->packing_no, (float) $pcs, null, __('salesshipment.ot.paper.'.$p->state), null, 13);
        }
        foreach (DB::table('ss_export_docs')->whereIn('shipment_id', $sid)->get() as $d) {
            $add($d->document_date, 'export_doc', $d->document_no, null, null, __('salesshipment.ed.status.'.$d->status), __('salesshipment.ed.type.'.$d->doc_type), 14);
        }
        foreach (DB::table('ss_doc_handovers')->whereIn('shipment_id', $sid)->get() as $h) {
            $add($h->handover_date, $h->kind === 'FINAL' ? 'final_handover' : 'handover', $h->handover_no, null, null, __('salesshipment.dh.status.'.$h->status), $h->received_by, 15);
        }
        $add($o->closed_at, 'closing', $o->so_no, null, null, __('salesshipment.sr.status.order.CLOSED'), $b->user($o->closed_by), 19);

        usort($events, fn ($a, $b) => [$a['date'], $a['seq']] <=> [$b['date'], $b['seq']]);
        $available = $against ? max(0.0, $net - $shipped) : max(0.0, $ordered - $shipped);

        return ApiResponse::ok([
            'order' => ['so_no' => $o->so_no, 'customer' => $b->customers()[$o->customer_id] ?? '—', 'po_wo_no' => $o->po_wo_no, 'currency' => $code, 'receive_date' => $o->receive_date,
                'target_date' => $o->delivery_target_date, 'shipment_date' => $o->shipment_date, 'state' => $o->state, 'approval_status' => $o->approval_status],
            'figures' => [
                'order_qty' => $ordered, 'delivered' => $out, 'returned' => $returned, 'net_delivered' => $net, 'shipped' => $shipped,
                'delivery_balance' => round(max(0.0, $ordered - $net), 4), 'shipment_balance' => round(max(0.0, $ordered - $shipped), 4), 'available' => round($available, 4),
                'balance' => round(max(0.0, $ordered - ($against ? max($net, $shipped) : $shipped)), 4), 'against_delivered' => $against,
            ],
            'events' => array_map(fn ($e) => array_diff_key($e, ['seq' => 1]), $events),
        ]);
    }

    /* ── Notifications & Alerts ────────────────────────────────────── */

    public function alerts(): JsonResponse
    {
        $this->allow('salesshipment.report.view');
        $rows = $this->liveAlerts();

        $set = SsSettings::get();

        return ApiResponse::ok([
            'rows' => $rows,
            'windows' => ['due' => (int) $set->due_alert_days, 'cert' => (int) $set->cert_alert_days],
            'summary' => collect(self::KINDS)->map(fn ($k) => ['kind' => $k, 'count' => count(array_filter($rows, fn ($r) => $r['kind'] === $k)),
                'critical' => count(array_filter($rows, fn ($r) => $r['kind'] === $k && $r['severity'] === 'critical'))])->all(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function liveAlerts(): array
    {
        $c = $this->caller()->companyId;
        $b = $this->book();
        $set = SsSettings::get();
        $window = max(0, (int) $set->due_alert_days);
        $certWindow = max(0, (int) $set->cert_alert_days);
        $today = CarbonImmutable::now('Asia/Dhaka')->startOfDay();
        $days = fn ($date) => (int) $today->diffInDays(CarbonImmutable::parse((string) $date, 'Asia/Dhaka')->startOfDay(), false);
        $names = $b->customerNames();
        $rows = [];
        $push = function (string $kind, string $severity, string $ref, ?string $party, mixed $due, ?int $d, string $detail, string $screen) use (&$rows) {
            $rows[] = ['kind' => $kind, 'severity' => $severity, 'reference' => $ref, 'party' => $party, 'due_date' => $due ? substr((string) $due, 0, 10) : null, 'days' => $d, 'detail' => $detail, 'screen' => $screen];
        };

        // Orders: delivery due / overdue, and closing pending once nothing is left to move.
        $orders = DB::table('ss_sales_orders')->where('company_id', $c)->where('state', 'OPEN')->where('approval_status', 'CONFIRMED')->get(['id', 'so_no', 'customer_id', 'delivery_target_date']);
        $fig = $b->sizeFigures($orders->pluck('id')->all());
        $sizes = DB::table('ss_sales_order_sizes')->whereIn('order_id', $orders->pluck('id'))->get(['id', 'order_id', 'qty'])->groupBy('order_id');
        $shipped = DB::table('ss_shipment_lines as l')->join('ss_shipments as s', 's.id', '=', 'l.shipment_id')->whereIn('s.order_id', $orders->pluck('id'))->whereIn('s.stage', ['CONFIRMED', 'CLOSED'])
            ->groupBy('s.order_id')->selectRaw('s.order_id, sum(l.shipped_qty) as q, max(s.shipment_date) as last')->get()->keyBy('order_id');
        $lastChallan = DB::table('ss_challans')->whereIn('order_id', $orders->pluck('id'))->whereIn('state', ['SUBMITTED', 'DELIVERED'])->groupBy('order_id')->selectRaw('order_id, max(chalan_date) as d')->pluck('d', 'order_id');
        foreach ($orders as $o) {
            $mine = $sizes->get($o->id, collect());
            $ordered = (float) $mine->sum('qty');
            $net = (float) $mine->sum(fn ($s) => $fig[$s->id]['net'] ?? 0);
            $sh = (float) ($shipped[$o->id]->q ?? 0);
            $left = round($ordered - max($net, $sh), 4);
            $party = $names[$o->customer_id] ?? null;
            if ($left > 0.00005 && $o->delivery_target_date) {
                $d = $days($o->delivery_target_date);
                $detail = __('salesshipment.al.d.delivery', ['left' => $this->num($left), 'qty' => $this->num($ordered)]);
                if ($d < 0) {
                    $push('DELIVERY_OVERDUE', 'critical', $o->so_no, $party, $o->delivery_target_date, $d, $detail, 'delivery-chalan');
                } elseif ($d <= $window) {
                    $push('DELIVERY_DUE', 'warning', $o->so_no, $party, $o->delivery_target_date, $d, $detail, 'delivery-chalan');
                }
            } elseif ($left <= 0.00005 && $ordered > 0) {
                $last = max((string) ($lastChallan[$o->id] ?? ''), (string) ($shipped[$o->id]->last ?? '')) ?: null;
                $push('CLOSING_PENDING', 'warning', $o->so_no, $party, $last, $last ? $days($last) : null, __('salesshipment.al.d.closing', ['qty' => $this->num($ordered)]), 'delivery-completion');
            }
        }

        // Shipments still being prepared: due / overdue, booking and documents.
        $ships = DB::table('ss_shipments as s')->join('ss_sales_orders as o', 'o.id', '=', 's.order_id')->where('s.company_id', $c)->whereIn('s.stage', self::PREP)
            ->get(['s.id', 's.shipment_no', 's.shipment_date', 's.ex_factory_date', 's.stage', 's.shipment_mode', 'o.customer_id']);
        foreach ($ships as $s) {
            $party = $names[$s->customer_id] ?? null;
            $d = $days($s->shipment_date);
            $qty = (float) DB::table('ss_shipment_lines')->where('shipment_id', $s->id)->sum('qty');
            $stage = __('salesshipment.sr.status.stage.'.$s->stage);
            if ($d < 0) {
                $push('SHIPMENT_OVERDUE', 'critical', $s->shipment_no, $party, $s->shipment_date, $d, __('salesshipment.al.d.shipment', ['qty' => $this->num($qty), 'stage' => $stage]), 'shipment-confirmation');
            } elseif ($d <= $window) {
                $push('SHIPMENT_DUE', 'warning', $s->shipment_no, $party, $s->shipment_date, $d, __('salesshipment.al.d.shipment', ['qty' => $this->num($qty), 'stage' => $stage]), 'shipment-confirmation');
            }
            if ($d > $window) {
                continue;
            }
            $sev = $d < 0 ? 'critical' : 'warning';
            if ($s->stage === 'PLANNING') {
                $push('BOOKING_PENDING', $sev, $s->shipment_no, $party, $s->shipment_date, $d, __('salesshipment.al.d.booking', ['mode' => $s->shipment_mode]), 'shipment-booking');
            }
            if ($set->require_export_docs) {
                $filed = DB::table('ss_export_docs')->where('shipment_id', $s->id)->where('status', '!=', 'CANCELLED')->pluck('doc_type')->unique();
                $missing = array_values(array_filter([
                    $filed->contains('EXP') ? null : __('salesshipment.ed.type.EXP'),
                    $filed->intersect(['BILL_OF_LADING', 'AIR_WAYBILL'])->isNotEmpty() ? null : __('salesshipment.al.d.bl_awb'),
                ]));
                if ($missing) {
                    $push('DOCUMENT_PENDING', $sev, $s->shipment_no, $party, $s->shipment_date, $d, __('salesshipment.al.d.documents', ['docs' => implode(', ', $missing)]), $filed->contains('EXP') ? 'bl-awb' : 'export-documents');
                }
            }
        }

        // Certificates and dated export documents on shipments not closed.
        $live = DB::table('ss_shipments as s')->join('ss_sales_orders as o', 'o.id', '=', 's.order_id')->where('s.company_id', $c)->whereNotIn('s.stage', ['CLOSED', 'CANCELLED'])->pluck('o.customer_id', 's.id');
        $expiring = fn ($date) => $date && $days($date) <= $certWindow;
        foreach (DB::table('ss_certificates as x')->join('ss_shipments as s', 's.id', '=', 'x.shipment_id')->whereIn('x.shipment_id', $live->keys())->where('x.status', 'ACTIVE')->whereNotNull('x.expiry_date')
            ->get(['x.certificate_no', 'x.certificate_name', 'x.expiry_date', 'x.shipment_id', 's.shipment_no']) as $x) {
            if ($expiring($x->expiry_date)) {
                $d = $days($x->expiry_date);
                $push('CERTIFICATE_EXPIRING', $d < 0 ? 'critical' : 'warning', $x->certificate_no, $names[$live[$x->shipment_id]] ?? null, $x->expiry_date, $d,
                    __('salesshipment.al.d.certificate', ['name' => $x->certificate_name, 'shipment' => $x->shipment_no]), 'certificate-management');
            }
        }
        $screens = ['EXPORT' => 'export-documents', 'COO' => 'certificate-of-origin', 'GSP' => 'gsp-rex', 'BLAWB' => 'bl-awb'];
        foreach (DB::table('ss_export_docs as x')->join('ss_shipments as s', 's.id', '=', 'x.shipment_id')->whereIn('x.shipment_id', $live->keys())->whereNotIn('x.status', ['CANCELLED'])->whereNotNull('x.expiry_date')
            ->get(['x.document_no', 'x.doc_type', 'x.family', 'x.expiry_date', 'x.shipment_id', 's.shipment_no']) as $x) {
            if ($expiring($x->expiry_date)) {
                $d = $days($x->expiry_date);
                $push('CERTIFICATE_EXPIRING', $d < 0 ? 'critical' : 'warning', $x->document_no, $names[$live[$x->shipment_id]] ?? null, $x->expiry_date, $d,
                    __('salesshipment.al.d.certificate', ['name' => __('salesshipment.ed.type.'.$x->doc_type), 'shipment' => $x->shipment_no]), $screens[$x->family] ?? 'export-documents');
            }
        }

        // Sub-contract programmes past their target with goods not back.
        $progs = DB::table('ss_sc_programmes')->where('company_id', $c)->whereNotNull('target_date')->where('target_date', '<', $today->toDateString())->get(['id', 'sc_no', 'party_id', 'target_date']);
        foreach ($progs as $p) {
            $plines = DB::table('ss_sc_programme_lines')->where('programme_id', $p->id)->get(['id', 'qty']);
            $dl = DB::table('ss_sc_delivery_lines as dl')->join('ss_sc_deliveries as d', 'd.id', '=', 'dl.delivery_id')->whereIn('dl.programme_line_id', $plines->pluck('id'))->where('d.state', '!=', 'CANCELLED')->pluck('dl.id')->all();
            $f = SubconBook::lines($dl);
            $back = (float) $f->sum(fn ($x) => $x['received'] + $x['rejected']);
            $contract = (float) $plines->sum('qty');
            $out = (float) $f->sum('out');
            $notSent = max(0.0, $contract - (float) $f->sum('sent'));
            if ($out > 0.00005 || $notSent > 0.00005) {
                $push('SUBCONTRACT_OVERDUE', 'critical', $p->sc_no, $names[$p->party_id] ?? null, $p->target_date, $days($p->target_date),
                    __('salesshipment.al.d.subcon', ['back' => $this->num($back), 'qty' => $this->num($contract), 'out' => $this->num($out)]), 'sc-pending');
            }
        }

        usort($rows, fn ($a, $b) => [$a['severity'] === 'critical' ? 0 : 1, $a['days'] ?? 0, $a['reference']] <=> [$b['severity'] === 'critical' ? 0 : 1, $b['days'] ?? 0, $b['reference']]);

        return $rows;
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 4, '.', ','), '0'), '.');
    }

    private function book(): SalesBook
    {
        return $this->book ??= new SalesBook($this->caller()->companyId);
    }
}

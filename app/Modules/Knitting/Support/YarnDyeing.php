<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support;

use App\Modules\Knitting\Models\KnitScReceipt;
use App\Modules\Knitting\Models\KnitScReceiptFabric;
use App\Modules\Knitting\Models\KnitScReceiptYarn;
use App\Modules\Knitting\Models\KnitScYarnChallan;
use App\Modules\Knitting\Models\KnitScYarnChallanLine;
use App\Modules\Knitting\Models\KnitYdGreyTransfer;
use App\Modules\Knitting\Models\KnitYdGreyTransferLine;
use App\Modules\Knitting\Models\KnitYdLossLine;
use App\Modules\Knitting\Models\KnitYdLossMemo;
use App\Modules\Knitting\Models\KnitYdProgram;
use App\Modules\Knitting\Models\KnitYdProgramLine;
use App\Modules\Knitting\Models\KnitYdReceipt;
use App\Modules\Knitting\Models\KnitYdReceiptLine;
use App\Modules\Knitting\Models\KnitYdReprocess;
use App\Modules\Knitting\Models\KnitYdReprocessLine;
use App\Modules\Knitting\Models\YarnIssueReq;
use App\Modules\Knitting\Models\YarnIssueReqLine;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What the yarn dyeing screens share: a Y/D programme's figures — Issue
 * (what the store sent the dye house against it: YARN_DYE requisitions
 * naming it, net of what came back, issued re-process documents and Fabric
 * Store's issued grey yarn transfer challans),
 * Delivery (confirmed dyed receipts), Loss (approved process-loss memos) and
 * With Party (Issue − Delivery − Loss) — and the pickers they open on.
 */
final class YarnDyeing
{
    /**
     * Each programme's figures, and each shade's.
     *
     * @param  Collection<int, KnitYdProgram>  $programs
     * @return array{programs: array<string, array<string, float>>, lines: array<string, array<string, float>>}
     */
    public static function figures(Collection $programs): array
    {
        $ids = $programs->pluck('id');
        $reqs = YarnIssueReq::query()->where('req_type', 'YARN_DYE')->whereIn('program_no', $programs->pluck('program_no'))->pluck('program_no', 'id');
        $byNo = $programs->pluck('id', 'program_no');
        $issued = [];
        foreach (YarnIssueReqLine::query()->whereIn('req_id', $reqs->keys())->get(['req_id', 'issued_qty']) as $l) {
            $pid = $byNo[$reqs[$l->req_id]];
            $issued[$pid] = ($issued[$pid] ?? 0) + (float) $l->issued_qty;
        }
        $out = KnitYdReprocess::query()->whereIn('program_id', $ids)->whereIn('status', ['ISSUED', 'ACCEPTED'])->pluck('program_id', 'id');
        foreach (KnitYdReprocessLine::query()->whereIn('reprocess_id', $out->keys())->get(['reprocess_id', 'qty']) as $l) {
            $pid = $out[$l->reprocess_id];
            $issued[$pid] = ($issued[$pid] ?? 0) + (float) $l->qty;
        }
        // Greige sent on Fabric Store's grey yarn transfer challans (15/05), once issued.
        $sent = KnitYdGreyTransfer::query()->whereIn('program_id', $ids)->whereIn('status', ['ISSUED', 'FINALIZED'])->pluck('program_id', 'id');
        foreach (KnitYdGreyTransferLine::query()->whereIn('transfer_id', $sent->keys())->get(['transfer_id', 'qty']) as $l) {
            $pid = $sent[$l->transfer_id];
            $issued[$pid] = ($issued[$pid] ?? 0) + (float) $l->qty;
        }
        $confirmed = KnitYdReceipt::query()->whereIn('program_id', $ids)->where('status', 'CONFIRMED')->pluck('id');
        $delivered = KnitYdReceiptLine::query()->whereIn('receipt_id', $confirmed)->groupBy('program_line_id')->selectRaw('program_line_id, sum(qty) as q')->pluck('q', 'program_line_id');
        $approved = KnitYdLossMemo::query()->whereIn('program_id', $ids)->where('status', 'APPROVED')->pluck('id');
        $loss = KnitYdLossLine::query()->whereIn('memo_id', $approved)->groupBy('program_line_id')->selectRaw('program_line_id, sum(issued_qty - received_qty) as q')->pluck('q', 'program_line_id');
        $lines = KnitYdProgramLine::query()->whereIn('program_id', $ids)->get(['id', 'program_id', 'qty'])->groupBy('program_id');

        $p = [];
        $l = [];
        foreach ($programs as $prog) {
            $mine = $lines[$prog->id] ?? collect();
            $planned = (float) $mine->sum('qty');
            $issue = round($issued[$prog->id] ?? 0, 4);
            foreach ($mine as $line) {
                $d = round((float) ($delivered[$line->id] ?? 0), 4);
                $l[$line->id] = [
                    'delivered' => $d, 'loss' => round((float) ($loss[$line->id] ?? 0), 4), 'pending' => round(max(0, (float) $line->qty - $d), 4),
                    // The programme's issue shared over its shades by what each was planned at.
                    'issued_share' => $planned > 0 ? round($issue * (float) $line->qty / $planned, 4) : 0.0,
                ];
            }
            $delivery = round((float) $mine->sum(fn ($x) => $l[$x->id]['delivered']), 4);
            $written = round((float) $mine->sum(fn ($x) => $l[$x->id]['loss']), 4);
            $p[$prog->id] = [
                'qty' => round($planned, 4), 'issue' => $issue, 'delivery' => $delivery, 'loss' => $written,
                'balance' => round($issue - $delivery - $written, 4), 'pending' => round((float) $mine->sum(fn ($x) => $l[$x->id]['pending']), 4),
            ];
        }

        return ['programs' => $p, 'lines' => $l];
    }

    /** The pickers every yarn dyeing screen opens on. */
    public static function options(): array
    {
        $company = app(TenantContext::class)->companyId();
        $o = YarnProcurement::options();
        $names = YarnProcurement::names();
        $programs = KnitYdProgram::query()->orderByDesc('program_date')->orderByDesc('program_no')->limit(500)->get();
        $figures = self::figures($programs);
        $lines = KnitYdProgramLine::query()->whereIn('program_id', $programs->pluck('id'))->orderBy('sort_order')->get()->groupBy('program_id');
        $suppliers = collect($o['suppliers'])->pluck('label', 'value');
        $orders = Order::query()->whereIn('id', $programs->pluck('order_id')->filter()->unique())->get(['id', 'order_no', 'style_no', 'work_style_no'])->keyBy('id');

        $months = Order::query()->whereIn('id', collect($o['orders'])->pluck('value'))->whereNotNull('ship_date')->pluck('ship_date', 'id')->map(fn ($d) => $d->format('Y-m'));

        return collect($o)->only(['company', 'stores', 'buyers', 'suppliers', 'uoms', 'compositions', 'counts'])->all() + [
            // An order as Style/Order# names it, with the month it ships in (the statement's Shipment Month).
            'orders' => array_map(fn (array $x) => $x + ['ship_month' => $months[$x['value']] ?? null], $o['orders']),
            'colours' => DB::table('colours')->where('company_id', $company)->where('active', true)->orderBy('name')->get(['id', 'code', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->code ? "{$c->name} ({$c->code})" : $c->name, 'name' => $c->name])->values()->all(),
            'colour_groups' => YarnProcurement::COLOURS,
            'yarn_names' => $names,
            'lots' => array_map(fn (array $l) => collect($l)->only(['lot_id', 'store_id', 'yarn', 'composition_id', 'yarn_count_id', 'colour', 'brand', 'lot_no', 'supplier_id', 'uom', 'balance', 'label'])->all(),
                YarnStore::lots(null, false)),
            'types' => KnitYdProgram::TYPES, 'statuses' => KnitYdProgram::STATUSES, 'receipt_statuses' => KnitYdReceipt::STATUSES,
            'memo_statuses' => KnitYdLossMemo::STATUSES, 'reprocess_statuses' => KnitYdReprocess::STATUSES, 'challan_statuses' => KnitScYarnChallan::STATUSES,
            // A programme as Y/D Program # names it, with its shades and what each still has out.
            'programs' => $programs->map(function (KnitYdProgram $p) use ($lines, $figures, $suppliers, $names, $orders) {
                $order = $orders[$p->order_id] ?? null;

                return [
                    'value' => $p->program_no, 'label' => $p->program_no.' — '.($suppliers[$p->supplier_id] ?? ''), 'yd_type' => $p->yd_type, 'status' => $p->status,
                    'program_date' => $p->program_date->toDateString(), 'supplier_id' => $p->supplier_id, 'supplier' => $suppliers[$p->supplier_id] ?? null,
                    'buyer_id' => $p->buyer_id, 'order_id' => $p->order_id, 'order' => $order ? trim(($order->work_style_no ?: $order->style_no).' · '.$order->order_no, ' ·') : null,
                    'rev_no' => $p->rev_no, 'delivery_date' => $p->delivery_date?->toDateString(),
                ] + $figures['programs'][$p->id] + [
                    'lines' => ($lines[$p->id] ?? collect())->map(fn (KnitYdProgramLine $l) => [
                        'value' => $l->id, 'label' => self::shadeLabel($l, $names), 'colour_name' => $l->colour_name, 'colour_group' => $l->colour_group,
                        'composition_id' => $l->composition_id, 'yarn_count_id' => $l->yarn_count_id, 'lot_no' => $l->lot_no, 'qty' => $l->qty, 'uom' => $l->uom,
                    ] + $figures['lines'][$l->id])->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    /** "Navy · 100% Cotton 30/1 · Lot G-11". */
    public static function shadeLabel(KnitYdProgramLine $l, ?array $names = null): string
    {
        $names ??= YarnProcurement::names();
        $yarn = trim(($names['compositions'][$l->composition_id] ?? '').' '.($names['counts'][$l->yarn_count_id] ?? ''));

        return implode(' · ', array_filter([$l->colour_name, $yarn, $l->lot_no ? "Lot {$l->lot_no}" : null]));
    }

    /**
     * The sub-contract programme receipts a party's yarn may come in against,
     * with what their yarn lines still want.
     *
     * @return list<array<string, mixed>>
     */
    public static function scPrograms(): array
    {
        $rows = KnitScReceipt::query()->whereIn('status', ['CONFIRMED', 'IN_PRODUCTION'])->orderByDesc('prog_date')->orderByDesc('program_no')->limit(500)->get();
        $ids = $rows->pluck('id');
        $required = KnitScReceiptYarn::query()->whereIn('receipt_id', $ids)->groupBy('receipt_id')->selectRaw('receipt_id, sum(required_qty) as q')->pluck('q', 'receipt_id');
        $accepted = KnitScYarnChallan::query()->whereIn('receipt_id', $ids)->where('status', 'ACCEPTED')->pluck('receipt_id', 'id');
        $received = [];
        foreach (KnitScYarnChallanLine::query()->whereIn('challan_id', $accepted->keys())->get(['challan_id', 'qty']) as $l) {
            $received[$accepted[$l->challan_id]] = ($received[$accepted[$l->challan_id]] ?? 0) + (float) $l->qty;
        }
        $orderOf = KnitScReceiptFabric::query()->whereIn('receipt_id', $ids)->whereNotNull('order_id')->orderBy('sl')->get(['receipt_id', 'order_id'])->unique('receipt_id')->pluck('order_id', 'receipt_id');
        $orders = Order::query()->whereIn('id', $orderOf->values()->unique())->get(['id', 'order_no', 'style_no', 'work_style_no'])->keyBy('id');
        $parties = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('party_id')->unique())->pluck('name', 'id');

        return $rows->map(function (KnitScReceipt $r) use ($required, $received, $orderOf, $orders, $parties) {
            $order = $orders[$orderOf[$r->id] ?? null] ?? null;

            return [
                'value' => $r->program_no, 'label' => $r->program_no.' — '.($parties[$r->party_id] ?? ''), 'prog_date' => $r->prog_date->toDateString(),
                'party_id' => $r->party_id, 'party' => $parties[$r->party_id] ?? null, 'store_id' => $r->yarn_store_id,
                'order_no' => $order?->order_no, 'style_no' => $order ? ($order->work_style_no ?: $order->style_no) : null,
                'required' => round((float) ($required[$r->id] ?? 0), 4), 'received' => round($received[$r->id] ?? 0, 4),
                'pending' => round(max(0, (float) ($required[$r->id] ?? 0) - ($received[$r->id] ?? 0)), 4),
            ];
        })->values()->all();
    }
}

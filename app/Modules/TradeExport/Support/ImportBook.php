<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Trade & Export → Import (AnnotechERP 22/03): the supplier's purchase orders
 * the import papers are raised on — accessories (`merch_acc_pos`), fabric
 * (`merch_pfab_pos`) and yarn (`knit_yarn_pos`) — their lines read one way,
 * and the goods received against them (each kind's MRR).
 */
final class ImportBook
{
    public const COMMODITIES = ['YARN', 'FABRIC', 'ACCESSORIES', 'CHEMICALS', 'DYES', 'MACHINERY', 'SPARE PARTS'];

    public const CUSTOMS = ['CHI-CTG', 'CHE-CTG', 'DCH-DHK', 'ICD-KML', 'BEN-JSR', 'MON-MNL', 'PAN-DHK'];

    public const LC_STATUSES = ['APPROVED', 'APPLIED', 'AMENDED', 'CLOSED'];

    public const PAYMENT_TYPES = ['NA', 'PMT', 'TT', 'ADJ'];

    public const AUTHORITIES = ['BKMEA', 'BGMEA', 'EPB', 'CUSTOMS', 'OTHER'];

    /** What a UD transfer's commodity reads on the L/Cs. */
    public const MATERIALS = ['Yarn' => 'YARN', 'Fabric' => 'FABRIC', 'Accessories' => 'ACCESSORIES', 'Chemical' => 'CHEMICALS', 'Dyes' => 'DYES', 'Packing' => 'PACKING'];

    private const PO = [
        'ACC' => ['merch_acc_pos', 'merch_acc_po_lines'],
        'FAB' => ['merch_pfab_pos', 'merch_pfab_po_lines'],
        'YARN' => ['knit_yarn_pos', 'knit_yarn_po_lines'],
    ];

    public function __construct(private readonly string $companyId) {}

    /** @return list<array<string, mixed>> A supplier's POs of every kind, newest first. */
    public function pos(string $supplierId): array
    {
        $rows = collect();
        foreach (self::PO as $kind => [$table]) {
            $rows = $rows->merge(DB::table($table)->where('company_id', $this->companyId)->where('supplier_id', $supplierId)->get(['id', 'po_no', 'po_date', 'inco_term', 'payment_term'])
                ->map(fn ($p) => ['kind' => $kind, 'id' => $p->id, 'po_no' => $p->po_no, 'po_date' => substr((string) $p->po_date, 0, 10), 'inco_term' => $p->inco_term, 'payment_term' => $p->payment_term]));
        }

        return $rows->sortByDesc('po_date')->values()->all();
    }

    /** @return list<array<string, mixed>> A PO's lines with what other PIs have not yet taken. */
    public function poLines(string $kind, string $poId, ?string $exceptPi = null): array
    {
        [$head, $table] = self::PO[$kind];
        $poNo = (string) DB::table($head)->where('company_id', $this->companyId)->where('id', $poId)->value('po_no');
        abort_if($poNo === '', 404);
        $rows = $this->readLines($kind, DB::table($table)->where('po_id', $poId));
        $taken = $this->taken($kind, array_keys($rows), $exceptPi);

        return collect($rows)->map(fn ($l, $id) => $l + ['po_kind' => $kind, 'po_line_id' => $id, 'po_no' => $poNo, 'balance' => round($l['po_qty'] - (float) ($taken[$id] ?? 0), 4)])->values()->all();
    }

    /** @return array<string, float> PO line => quantity on PIs other than `$exceptPi`. */
    public function taken(string $kind, array $lineIds, ?string $exceptPi = null): array
    {
        return DB::table('tx_import_pi_lines')->where('po_kind', $kind)->whereIn('po_line_id', $lineIds)->when($exceptPi, fn ($q) => $q->where('pi_id', '!=', $exceptPi))
            ->groupBy('po_line_id')->selectRaw('po_line_id, sum(qty) as q')->pluck('q', 'po_line_id')->map(fn ($v) => (float) $v)->all();
    }

    /** @return array<string, array{item: string, po_qty: float, uom: string, unit_price: float}> */
    public function lines(string $kind, array $lineIds): array
    {
        return $this->readLines($kind, DB::table(self::PO[$kind][1])->whereIn('id', $lineIds));
    }

    /** @return array<string, array{item: string, po_qty: float, uom: string, unit_price: float}> */
    private function readLines(string $kind, $query): array
    {
        $rows = $query->where('company_id', $this->companyId)->orderBy('sort_order')->get();
        $out = [];
        if ($kind === 'ACC') {
            $items = DB::table('mp_items')->whereIn('id', $rows->pluck('item_id')->filter())->pluck('name', 'id');
            foreach ($rows as $r) {
                $out[$r->id] = ['item' => trim(($items[$r->item_id] ?? '—').($r->specification ? ' — '.$r->specification : '')), 'po_qty' => (float) $r->qty, 'uom' => (string) $r->uom, 'unit_price' => (float) $r->unit_price];
            }
        } elseif ($kind === 'FAB') {
            foreach ($rows as $r) {
                $out[$r->id] = ['item' => (string) ($r->description ?: $r->construction ?: '—'), 'po_qty' => (float) $r->qty, 'uom' => (string) $r->uom, 'unit_price' => (float) $r->rate];
            }
        } else {
            $comps = DB::table('fiber_compositions')->whereIn('id', $rows->pluck('composition_id')->filter())->pluck('description', 'id');
            $counts = DB::table('knit_yarn_counts')->whereIn('id', $rows->pluck('yarn_count_id')->filter())->pluck('count_no', 'id');
            foreach ($rows as $r) {
                $out[$r->id] = ['item' => implode(' · ', array_filter([$counts[$r->yarn_count_id] ?? null, $comps[$r->composition_id] ?? null, $r->colour, $r->brand])) ?: '—', 'po_qty' => (float) $r->qty, 'uom' => (string) $r->uom, 'unit_price' => (float) $r->rate];
            }
        }

        return $out;
    }

    /**
     * The goods received against some PI lines, one row per PI line and MRR.
     *
     * @param  Collection<int, object>  $piLines  id, po_kind, po_line_id, po_no, item, uom, qty, unit_price, amount, pi_no
     * @return list<array<string, mixed>>
     */
    public function receipts(Collection $piLines, ?string $exceptEntry = null): array
    {
        $onEntry = DB::table('tx_bill_of_entry_lines')->whereIn('pi_line_id', $piLines->pluck('id'))->when($exceptEntry, fn ($q) => $q->where('be_id', '!=', $exceptEntry))
            ->groupBy('pi_line_id', 'mrr_no')->selectRaw('pi_line_id, mrr_no, sum(receive_qty) as q')->get()->mapWithKeys(fn ($r) => [$r->pi_line_id.'|'.$r->mrr_no => (float) $r->q]);
        $out = [];
        foreach ($piLines->groupBy('po_kind') as $kind => $group) {
            if (! $kind) {
                continue;
            }
            $received = $this->mrrLines((string) $kind, $group->pluck('po_line_id')->all())->groupBy('po_line_id');
            foreach ($group as $pi) {
                foreach ($received->get($pi->po_line_id, collect()) as $m) {
                    $balance = round((float) $m->qty - (float) ($onEntry[$pi->id.'|'.$m->mrr_no] ?? 0), 4);
                    $out[] = ['key' => $pi->id.'|'.$m->mrr_no, 'pi_line_id' => $pi->id, 'pi_no' => $pi->pi_no, 'po_no' => $pi->po_no, 'mrr_no' => $m->mrr_no, 'challan_no' => $m->challan_no, 'item' => $pi->item, 'hs_code' => null, 'uom' => $pi->uom,
                        'pi_qty' => (float) $pi->qty, 'unit_price' => (float) $pi->unit_price, 'pi_amount' => (float) $pi->amount, 'receive_qty' => (float) $m->qty, 'receive_amount' => round((float) $m->qty * (float) $pi->unit_price, 4), 'balance_qty' => $balance, 'balance_amount' => round($balance * (float) $pi->unit_price, 4)];
                }
            }
        }

        return $out;
    }

    /** Lines of the MRRs that took goods in against some PO lines. */
    private function mrrLines(string $kind, array $poLineIds): Collection
    {
        return match ($kind) {
            'ACC' => DB::table('merch_acc_mrr_lines as l')->join('merch_acc_mrrs as m', 'm.id', '=', 'l.mrr_id')->whereIn('l.po_line_id', $poLineIds)->where('m.status', 'FINALIZED')->get(['l.po_line_id', 'l.qty', 'm.mrr_no', 'm.challan_no']),
            'FAB' => DB::table('merch_pfab_mrr_lines as l')->join('merch_pfab_mrrs as m', 'm.id', '=', 'l.mrr_id')->whereIn('l.po_line_id', $poLineIds)->where('m.status', 'FINALIZED')->get(['l.po_line_id', 'l.accept_qty as qty', 'm.mrr_no', 'm.challan_no']),
            default => DB::table('knit_yarn_receipt_lines as l')->join('knit_yarn_receipts as m', 'm.id', '=', 'l.receipt_id')->whereIn('l.po_line_id', $poLineIds)->whereNotIn('m.status', ['DRAFT', 'CANCELLED'])->get(['l.po_line_id', 'l.recv_qty as qty', 'm.mrr_no', 'm.challan_no']),
        };
    }

    /** The next PREFIX-YYYY-000001 of a table's column. */
    public function nextNo(string $prefix, string $table, string $column, string $date): string
    {
        $stem = $prefix.'-'.substr($date, 0, 4).'-';
        $last = DB::table($table)->where('company_id', $this->companyId)->where($column, 'like', $stem.'%')->orderByDesc($column)->value($column);

        return $stem.str_pad((string) ($last ? (int) substr($last, strlen($stem)) + 1 : 1), 6, '0', STR_PAD_LEFT);
    }
}

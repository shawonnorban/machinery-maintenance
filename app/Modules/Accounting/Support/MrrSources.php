<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The material receive reports a Combined MRR Bill (AnnotechERP 23/02) is
 * drawn from, one kind at a time, each read the same way: its number, date,
 * supplier, order, unit, quantity and value in BDT.
 *
 * - GENERAL: Store & Material goods receipts not reversed.
 * - YARN: Knitting yarn receipts not in draft or cancelled.
 * - DYES: Dye House chemical purchase receipts not in draft or cancelled.
 * - TRIMS: Merchandise accessories MRRs once finalized (at their rate to BDT).
 * - OIL / NEEDLE: Knitting machine-oil and needle receipts not in draft or cancelled.
 */
final class MrrSources
{
    public const KINDS = ['GENERAL', 'YARN', 'DYES', 'TRIMS', 'OIL', 'NEEDLE'];

    public function __construct(private readonly string $companyId) {}

    /**
     * Every receipt of a kind, by supplier and/or ids.
     *
     * @return Collection<string, object{id: string, mrr_no: string, mrr_date: string, supplier_id: ?string, po_wo: ?string, uom: ?string, total_qty: float, total_amount: float, rate: float}>
     */
    public function receipts(string $kind, ?string $supplierId = null, ?array $ids = null): Collection
    {
        [$head, $lines] = $this->query($kind);
        $head->where('h.company_id', $this->companyId)->when($supplierId, fn ($w, $v) => $w->where('h.supplier_id', $v))->when($ids !== null, fn ($w) => $w->whereIn('h.id', $ids));
        $rows = $head->get();
        $sums = $lines($rows->pluck('id')->all());

        return $rows->mapWithKeys(function ($h) use ($sums) {
            $s = $sums[$h->id] ?? null;
            $qty = round((float) ($s->qty ?? 0), 4);
            $amount = round((float) ($s->amount ?? 0), 4);

            return [$h->id => (object) ['id' => $h->id, 'mrr_no' => $h->mrr_no, 'mrr_date' => substr((string) $h->mrr_date, 0, 10), 'supplier_id' => $h->supplier_id, 'po_wo' => $h->po_wo ?: null,
                'uom' => $s->uom ?? null, 'total_qty' => $qty, 'total_amount' => $amount, 'rate' => $qty > 0 ? round($amount / $qty, 4) : 0.0]];
        })->sortBy('mrr_no');
    }

    /** @return array{0: Builder, 1: callable(array): array<string, object>} */
    private function query(string $kind): array
    {
        $sum = function (string $table, string $fk, string $qty, string $amount, string $uom) {
            return fn (array $ids) => $ids ? DB::table($table)->whereIn($fk, $ids)->groupBy($fk)
                ->selectRaw("{$fk} as id, sum({$qty}) as qty, sum({$amount}) as amount, case when count(distinct {$uom}) = 1 then max({$uom}) else 'MIXED' end as uom")->get()->keyBy('id')->all() : [];
        };
        $open = fn (Builder $w) => $w->whereNotIn('h.status', ['DRAFT', 'CANCELLED']);

        return match ($kind) {
            'GENERAL' => [DB::table('sm_grns as h')->where('h.status', '!=', 'REVERSED')->select('h.id', 'h.grn_no as mrr_no', 'h.grn_date as mrr_date', 'h.supplier_id', 'h.po_number as po_wo'),
                $sum('sm_grn_lines', 'grn_id', 'qty', 'qty * unit_price', 'uom')],
            'YARN' => [DB::table('knit_yarn_receipts as h')->leftJoin('knit_yarn_pos as p', 'p.id', '=', 'h.po_id')->tap($open)->select('h.id', 'h.mrr_no', 'h.mrr_date', 'h.supplier_id', 'p.po_no as po_wo'),
                $sum('knit_yarn_receipt_lines', 'receipt_id', 'recv_qty', 'recv_qty * rate', "'KG'")],
            'DYES' => [DB::table('dye_chem_receipts as h')->where('h.kind', 'PURCHASE')->tap($open)->select('h.id', 'h.doc_no as mrr_no', 'h.doc_date as mrr_date', 'h.supplier_id', 'h.po_no as po_wo'),
                $sum('dye_chem_receipt_lines', 'receipt_id', 'qty', 'qty * rate_bdt', 'uom')],
            'TRIMS' => [DB::table('merch_acc_mrrs as h')->leftJoin('merch_acc_pos as p', 'p.id', '=', 'h.po_id')->where('h.status', 'FINALIZED')->select('h.id', 'h.mrr_no', 'h.mrr_date', 'h.supplier_id', 'p.po_no as po_wo'),
                fn (array $ids) => $ids ? DB::table('merch_acc_mrr_lines as l')->join('merch_acc_mrrs as m', 'm.id', '=', 'l.mrr_id')->whereIn('l.mrr_id', $ids)->groupBy('l.mrr_id')
                    ->selectRaw("l.mrr_id as id, sum(l.qty) as qty, sum(l.amount * m.conv_rate) as amount, case when count(distinct l.uom) = 1 then max(l.uom) else 'MIXED' end as uom")->get()->keyBy('id')->all() : []],
            'OIL' => [DB::table('knit_oil_receipts as h')->tap($open)->select('h.id', 'h.mrr_no', 'h.mrr_date', 'h.supplier_id', 'h.lc_no as po_wo'),
                $sum('knit_oil_receipt_lines', 'receipt_id', 'received_qty', 'amount', 'uom')],
            'NEEDLE' => [DB::table('knit_needle_receipts as h')->tap($open)->select('h.id', 'h.mrr_no', 'h.mrr_date', 'h.supplier_id', 'h.lc_no as po_wo'),
                $sum('knit_needle_receipt_lines', 'receipt_id', 'received_qty', 'amount', 'uom')],
        };
    }
}

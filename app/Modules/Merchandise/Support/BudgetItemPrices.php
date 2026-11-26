<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Support;

use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * What each supplier asks for a trims item (the user's call of 2026-11-17:
 * a budget's accessory rate comes from the supplier). One price per supplier
 * and item, the best kind first:
 *  1. an approved quotation (Material Procurement), its latest revision;
 *  2. the last accessories PO to that supplier;
 *  3. a quotation still open (pending, revised, under negotiation).
 * The price is put into the budget's currency when it can be: the same
 * currency as it is, a local (BDT) price over the sheet's conversion rate, a
 * PO in another currency through that PO's own rate; otherwise it is left
 * for the merchandiser (price null, the quoted figure shown).
 */
final class BudgetItemPrices
{
    private const LOCAL = 'BDT';

    /**
     * @param  list<string>  $itemIds
     * @return array<string, list<array<string, mixed>>> by item, best first
     */
    public static function offers(array $itemIds, string $currency, float $rate): array
    {
        $itemIds = array_values(array_unique(array_filter($itemIds)));
        if ($itemIds === []) {
            return [];
        }
        $company = app(TenantContext::class)->companyId();
        $currency = strtoupper($currency);
        $found = [];

        $quotes = DB::table('mp_quotations as q')
            ->join('mp_requisition_lines as l', 'l.id', '=', 'q.requisition_line_id')
            ->join('mp_quotation_revisions as r', 'r.quotation_id', '=', 'q.id')
            ->where('q.company_id', $company)->whereIn('l.item_id', $itemIds)->whereNotIn('q.status', ['REJECTED', 'CLOSED'])
            ->whereRaw('r.revision = (select max(x.revision) from mp_quotation_revisions x where x.quotation_id = q.id)')
            ->whereNotNull('r.unit_price')
            ->get(['l.item_id', 'q.supplier_id', 'q.qt_no as ref', 'q.status', 'r.unit_price', 'r.currency', 'r.submitted_at', 'r.created_at']);
        foreach ($quotes as $q) {
            $found[] = ['item_id' => $q->item_id, 'supplier_id' => $q->supplier_id, 'rank' => $q->status === 'APPROVED' ? 1 : 3, 'source' => 'QUOTATION', 'status' => $q->status, 'ref' => $q->ref,
                'date' => substr((string) ($q->submitted_at ?? $q->created_at), 0, 10), 'quoted' => (float) $q->unit_price, 'currency' => strtoupper((string) ($q->currency ?: self::LOCAL)), 'conv' => null];
        }
        $pos = DB::table('merch_acc_po_lines as l')->join('merch_acc_pos as p', 'p.id', '=', 'l.po_id')
            ->where('p.company_id', $company)->whereIn('l.item_id', $itemIds)->whereNotNull('l.unit_price')->whereNotNull('p.supplier_id')
            ->get(['l.item_id', 'p.supplier_id', 'p.po_no as ref', 'p.po_date', 'l.unit_price', 'p.currency', 'p.conv_rate']);
        foreach ($pos as $p) {
            $found[] = ['item_id' => $p->item_id, 'supplier_id' => $p->supplier_id, 'rank' => 2, 'source' => 'PO', 'status' => null, 'ref' => $p->ref,
                'date' => substr((string) $p->po_date, 0, 10), 'quoted' => (float) $p->unit_price, 'currency' => strtoupper((string) ($p->currency ?: self::LOCAL)), 'conv' => $p->conv_rate === null ? null : (float) $p->conv_rate];
        }

        $names = MaterialSupplier::query()->whereIn('id', array_column($found, 'supplier_id'))->get(['id', 'name', 'short_name'])->keyBy('id');
        $out = [];
        foreach (collect($found)->sortBy([['rank', 'asc'], ['date', 'desc']])->groupBy('item_id') as $itemId => $offers) {
            $out[$itemId] = $offers->unique('supplier_id')->map(fn (array $o): array => [
                'supplier_id' => $o['supplier_id'], 'supplier' => $names[$o['supplier_id']]->name ?? null,
                'price' => self::convert($o['quoted'], $o['currency'], $currency, $rate, $o['conv']),
                'quoted' => $o['quoted'], 'currency' => $o['currency'], 'source' => $o['source'], 'status' => $o['status'], 'ref' => $o['ref'], 'date' => $o['date'],
            ])->values()->all();
        }

        return $out;
    }

    /** A supplier named in free text (a BOM line's), by its name, short name or code. */
    public static function supplierNamed(?string $text): ?string
    {
        $text = mb_strtolower(trim((string) $text));
        if ($text === '') {
            return null;
        }

        return MaterialSupplier::query()->where(fn ($q) => $q->whereRaw('lower(name) = ?', [$text])->orWhereRaw('lower(short_name) = ?', [$text])->orWhereRaw('lower(code) = ?', [$text]))->value('id');
    }

    private static function convert(float $price, string $from, string $to, float $rate, ?float $poRate): ?float
    {
        if ($from === $to) {
            return round($price, 4);
        }
        // A local price over the sheet's rate (local per unit of the sheet's currency).
        if ($from === self::LOCAL && $rate > 0) {
            return round($price / $rate, 4);
        }
        // A foreign PO into a local sheet through the PO's own rate.
        if ($to === self::LOCAL && $poRate) {
            return round($price * $poRate, 4);
        }

        return null;
    }
}

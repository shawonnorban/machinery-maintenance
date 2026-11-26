<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support;

use App\Modules\Knitting\Models\YarnIssueLine;
use App\Modules\Knitting\Models\YarnIssueReq;
use App\Modules\Knitting\Models\YarnIssueReqLine;
use App\Modules\Knitting\Models\YarnIssueReturn;
use App\Modules\Knitting\Models\YarnIssueReturnLine;
use App\Modules\Knitting\Models\YarnLot;
use App\Modules\Knitting\Models\YarnMove;
use App\Modules\Knitting\Models\YarnMonthClose;
use App\Modules\Knitting\Models\YarnPurchaseOrder;
use App\Modules\Knitting\Models\YarnPurchaseOrderLine;
use App\Modules\Knitting\Models\YarnReceipt;
use App\Modules\Knitting\Models\YarnReceiptLine;
use App\Modules\Knitting\Models\YarnReturn;
use App\Modules\Knitting\Models\YarnReturnLine;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The yarn ledger Knit Production → Yarn Store (AnnotechERP 15/03) keeps: a
 * stock line (lot) per store, yarn item, count, colour, brand, lot number,
 * supplier and buyer (or buffer); every document posts signed quantities
 * into it, replacing what it posted before; a struck month takes no more
 * movement; and a purchase order's received column follows the receipts
 * (less what went back) taken against its lines.
 */
final class YarnStore
{
    /** Where a stock line came from, as Brickland lists it. */
    public const SOURCES = ['LOCAL', 'IMPORT', 'SC_PARTY', 'INHOUSE_DYED', 'YARN_DYED'];

    /** A store requisition's purpose, as Brickland's Requisition Type lists it. */
    public const REQ_TYPES = [
        'FIN_FAB_SAMPLE', 'GREY_FAB_BULK_BATCH', 'GREY_FAB_SAMPLE_BATCH', 'DYED_YARN_SC_KNIT', 'GREY_FAB_SC_PROGRAM', 'SOLID_YARN_SC_KNIT_BULK',
        'YARN_DYE', 'LOAN_ISSUE', 'LOAN_RETURN_FROM_PARTY', 'LOAN_RETURN_TO_PARTY', 'STORE_TRANSFER', 'BATCH_DYEING', 'FINISHING_PROCESS',
        'KNIT_MC_OIL', 'KNIT_BULK', 'KNIT_DEVELOPMENT', 'KNIT_SAMPLE', 'LOAN_RECEIVE', 'MC_WASH', 'PURCHASE', 'YARN_TEST', 'COLLAR_CUFF_BULK',
    ];

    /** Validated wildcard rows in the order they were sent (validate() hands them back in rule order). */
    public static function inOrder(array $rows): array
    {
        ksort($rows);

        return array_values($rows);
    }

    /** The stock line a yarn's particulars name, made the first time they are met. */
    public static function lotFor(array $a, string $date, float $rate = 0.0): YarnLot
    {
        $parts = [$a['store_id'], $a['composition_id'] ?? '', $a['yarn_count_id'] ?? '', mb_strtolower(trim((string) ($a['colour'] ?? ''))), mb_strtolower(trim((string) ($a['brand'] ?? ''))),
            mb_strtoupper(trim((string) ($a['lot_no'] ?? ''))), $a['supplier_id'] ?? '', $a['buyer_id'] ?? '', empty($a['is_buffer']) ? 0 : 1];
        $key = implode('|', $parts);
        $lot = YarnLot::query()->where('lot_key', $key)->first();
        if ($lot) {
            if ($rate > 0) {
                $lot->update(['rate' => $rate]);
            }

            return $lot;
        }

        return YarnLot::query()->create([
            'lot_key' => $key, 'store_id' => $a['store_id'], 'composition_id' => $a['composition_id'] ?? null, 'yarn_count_id' => $a['yarn_count_id'] ?? null,
            'colour' => $a['colour'] ?? null, 'brand' => $a['brand'] ?? null, 'lot_no' => isset($a['lot_no']) ? mb_strtoupper(trim((string) $a['lot_no'])) ?: null : null,
            'supplier_id' => $a['supplier_id'] ?? null, 'buyer_id' => $a['buyer_id'] ?? null, 'order_id' => $a['order_id'] ?? null, 'is_buffer' => ! empty($a['is_buffer']),
            'source' => $a['source'] ?? null, 'uom' => $a['uom'] ?? 'kg', 'rate' => $rate, 'first_date' => $date,
        ]);
    }

    /**
     * Post a document's movement: what it posted before under this kind is
     * replaced by these entries.
     *
     * @param  list<array{lot_id: string, qty: float, value?: float, doc_line_id?: ?string}>  $entries
     */
    public static function post(string $kind, string $docNo, string $date, array $entries): void
    {
        self::unpost($kind, $docNo);
        foreach ($entries as $e) {
            if (abs((float) $e['qty']) < 0.00005) {
                continue;
            }
            $lot = YarnLot::query()->findOrFail($e['lot_id']);
            YarnMove::query()->create([
                'lot_id' => $lot->id, 'store_id' => $lot->store_id, 'move_date' => $date, 'kind' => $kind, 'doc_no' => $docNo,
                'doc_line_id' => $e['doc_line_id'] ?? null, 'qty' => round((float) $e['qty'], 4), 'value' => round((float) ($e['value'] ?? 0), 4),
            ]);
        }
    }

    public static function unpost(string $kind, string $docNo): void
    {
        YarnMove::query()->where('kind', $kind)->where('doc_no', $docNo)->delete();
    }

    /** A struck month takes no more movement. */
    public static function assertOpen(string $date, string $field = 'date'): void
    {
        $period = CarbonImmutable::parse($date)->format('Y-m');
        if (YarnMonthClose::query()->where('period', $period)->exists()) {
            throw ValidationException::withMessages([$field => __('knitting.ystore.v.month_closed', ['period' => CarbonImmutable::parse($date)->format('F Y')])]);
        }
    }

    public static function balance(string $lotId, ?string $before = null): float
    {
        return round((float) YarnMove::query()->where('lot_id', $lotId)->when($before, fn ($q) => $q->whereDate('move_date', '<', $before))->sum('qty'), 4);
    }

    /** Present stock of a yarn item and count across the stores: what a comparison sets beside a requisition line. */
    public static function stockOf(?string $compositionId, ?string $countId): float
    {
        $lots = YarnLot::query()->where('composition_id', $compositionId)->where('yarn_count_id', $countId)->pluck('id');

        return round((float) YarnMove::query()->whereIn('lot_id', $lots)->sum('qty'), 4);
    }

    /**
     * Stock lines with their balance (and, given, a store / only those holding
     * stock), named for pickers: "100% Cotton 30/1 Natural · Lot SQ-3011 · 520 kg".
     *
     * @return list<array<string, mixed>>
     */
    public static function lots(?string $storeId = null, bool $holding = true): array
    {
        $names = YarnProcurement::names();
        $lots = YarnLot::query()->when($storeId, fn ($q) => $q->where('store_id', $storeId))->orderBy('first_date')->get();
        $balances = YarnMove::query()->whereIn('lot_id', $lots->pluck('id'))->groupBy('lot_id')->selectRaw('lot_id, sum(qty) as qty')->pluck('qty', 'lot_id');
        $out = [];
        foreach ($lots as $l) {
            $qty = round((float) ($balances[$l->id] ?? 0), 4);
            if ($holding && $qty <= 0) {
                continue;
            }
            $out[] = self::describe($l, $names) + ['balance' => $qty];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public static function describe(YarnLot $l, ?array $names = null): array
    {
        $names ??= YarnProcurement::names();
        $yarn = trim(implode(' ', array_filter([$names['compositions'][$l->composition_id] ?? null, $names['counts'][$l->yarn_count_id] ?? null, $l->colour])));

        return [
            'lot_id' => $l->id, 'store_id' => $l->store_id, 'yarn' => $yarn, 'composition_id' => $l->composition_id, 'yarn_count_id' => $l->yarn_count_id, 'colour' => $l->colour,
            'brand' => $l->brand, 'lot_no' => $l->lot_no, 'supplier_id' => $l->supplier_id, 'buyer_id' => $l->buyer_id, 'is_buffer' => $l->is_buffer, 'source' => $l->source,
            'uom' => $l->uom, 'rate' => (float) $l->rate,
            'label' => $yarn.($l->brand ? " · {$l->brand}" : '').($l->lot_no ? " · Lot {$l->lot_no}" : ''),
        ];
    }

    /** What has gone back to the supplier on a receipt line, on issued return challans. */
    public static function returnedOf(string $receiptLineId): float
    {
        $issued = YarnReturn::query()->where('finalized', true)->pluck('id');

        return round((float) YarnReturnLine::query()->whereIn('return_id', $issued)->where('receipt_line_id', $receiptLineId)->sum('qty'), 4);
    }

    /**
     * A purchase order's received column, from the receipts taken against its
     * lines (cancelled ones aside) less what went back; and its status —
     * part received, received — while it is open.
     */
    public static function syncPo(?string $poId): void
    {
        if (! $poId) {
            return;
        }
        $po = YarnPurchaseOrder::query()->find($poId);
        if (! $po) {
            return;
        }
        $live = YarnReceipt::query()->where('po_id', $po->id)->where('status', '!=', 'CANCELLED')->pluck('id');
        $lines = YarnPurchaseOrderLine::query()->where('po_id', $po->id)->get();
        foreach ($lines as $l) {
            $taken = YarnReceiptLine::query()->whereIn('receipt_id', $live)->where('po_line_id', $l->id)->get();
            $received = $taken->sum(fn (YarnReceiptLine $r) => (float) $r->recv_qty - self::returnedOf($r->id));
            $l->update(['received_qty' => round(max(0, $received), 4)]);
        }
        if (in_array($po->status, ['PLACED', 'PART_RECEIVED', 'RECEIVED'], true)) {
            $ordered = (float) $lines->sum('qty');
            $received = (float) $lines->fresh()->sum('received_qty');
            $po->update(['status' => $received <= 0 ? 'PLACED' : ($received + 0.00005 >= $ordered ? 'RECEIVED' : 'PART_RECEIVED')]);
        }
    }

    /** What has come back to the store on an issue line, on accepted return challans. */
    public static function returnedToStore(string $issueLineId): float
    {
        $accepted = YarnIssueReturn::query()->where('status', 'ACCEPTED')->pluck('id');

        return round((float) YarnIssueReturnLine::query()->whereIn('return_id', $accepted)->where('issue_line_id', $issueLineId)->sum('recv_qty'), 4);
    }

    /**
     * A requisition's issued column — what went out on its lines less what
     * came back — and, while it is open, its status: Open, Partly Issued,
     * Fully Issued.
     */
    public static function syncIssueReq(?string $reqId): void
    {
        $req = $reqId ? YarnIssueReq::query()->find($reqId) : null;
        if (! $req) {
            return;
        }
        $lines = YarnIssueReqLine::query()->where('req_id', $req->id)->get();
        foreach ($lines as $l) {
            $issued = YarnIssueLine::query()->where('req_line_id', $l->id)->get()
                ->sum(fn (YarnIssueLine $i) => (float) $i->qty - self::returnedToStore($i->id));
            $l->update(['issued_qty' => round(max(0, $issued), 4)]);
        }
        if (in_array($req->status, ['OPEN', 'PARTLY_ISSUED', 'FULLY_ISSUED'], true)) {
            $asked = (float) $lines->sum('req_qty');
            $out = (float) $lines->fresh()->sum('issued_qty');
            $req->update(['status' => $out <= 0 ? 'OPEN' : ($out + 0.00005 >= $asked ? 'FULLY_ISSUED' : 'PARTLY_ISSUED')]);
        }
    }

    /** YOB-, YMRR-, YRC-, YADJ- numbers: the prefix, the year and month, the company's next that month. */
    public static function nextNumber(string $prefix, string $table, string $column, string $date): string
    {
        return YarnProcurement::nextNumber($prefix, $table, $column, $date);
    }

    /** @return Collection<int, object> */
    public static function stores(): Collection
    {
        return DB::table('stores')->where('company_id', app(TenantContext::class)->companyId())->orderBy('name')->get(['id', 'name', 'code']);
    }
}

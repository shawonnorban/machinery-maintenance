<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Support;

use App\Modules\Api\Support\ApiCaller;
use App\Modules\GeneralStore\Models\GsAuditEntry;
use App\Modules\GeneralStore\Models\GsDocument;
use App\Modules\GeneralStore\Models\GsDocumentLine;
use App\Modules\GeneralStore\Models\GsRequisition;
use App\Modules\GeneralStore\Models\GsRequisitionLine;
use App\Modules\GeneralStore\Models\GsSetting;
use App\Modules\GeneralStore\Models\GsStockMovement;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The general store's books (AnnotechERP 10): the number a document takes
 * (RC-2026-000001), an item's stock in each store (the sum of its
 * movements), its last purchase price (the latest priced receive or opening
 * stock, else the item's own price), and the audit trail — always written
 * in English, as Brickland keeps it.
 */
final class GsBook
{
    /** The next number for a document type, under a lock so two saves never share one. */
    public static function nextNumber(string $type, string $date): string
    {
        $head = self::head($type, $date);
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ["gs_documents:{$head}:".app(TenantContext::class)->companyId()]);

        return $head.str_pad((string) (1 + self::last($head)), 6, '0', STR_PAD_LEFT);
    }

    /** What the next number would be, before the save. */
    public static function previewNumber(string $type, string $date): string
    {
        $head = self::head($type, $date);

        return $head.str_pad((string) (1 + self::last($head)), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Each item's stock in each store.
     *
     * @return array<string, array<string, float>> item id => store id => quantity
     */
    public static function stock(?Collection $itemIds = null): array
    {
        $out = [];
        GsStockMovement::query()->when($itemIds, fn ($q) => $q->whereIn('gs_item_id', $itemIds->all()))
            ->groupBy('gs_item_id', 'gs_store_id')->select('gs_item_id', 'gs_store_id', DB::raw('sum(quantity) as q'))->get()
            ->each(function ($r) use (&$out): void {
                $out[$r->gs_item_id][$r->gs_store_id] = round((float) $r->q, 4);
            });

        return $out;
    }

    public static function stockIn(string $itemId, string $storeId): float
    {
        return round((float) GsStockMovement::query()->where('gs_item_id', $itemId)->where('gs_store_id', $storeId)->sum('quantity'), 4);
    }

    /**
     * Each item's last purchase price: the latest priced line of a standing
     * receive or opening stock, else the price on the item itself.
     *
     * @return array<string, float|null> item id => price
     */
    public static function lastPrices(Collection $items): array
    {
        $latest = DB::table('gs_document_lines as l')->join('gs_documents as d', 'd.id', '=', 'l.gs_document_id')
            ->where('d.company_id', app(TenantContext::class)->companyId())->whereIn('d.doc_type', ['RECEIVE', 'OPENING'])->where('d.status', 'POSTED')
            ->whereNotNull('l.unit_price')->whereIn('l.gs_item_id', $items->pluck('id')->all())
            ->orderBy('d.doc_date')->orderBy('d.created_at')->orderBy('l.sort_order')->get(['l.gs_item_id', 'l.unit_price'])
            ->pluck('unit_price', 'gs_item_id');

        return $items->mapWithKeys(fn ($i) => [$i->id => isset($latest[$i->id]) ? (float) $latest[$i->id] : ($i->default_price === null ? null : (float) $i->default_price)])->all();
    }

    /** A line on the audit trail; nothing is written when there is no caller (a seeder, the console). */
    public static function audit(string $action, string $screen, ?string $reference, string $summary): void
    {
        $caller = rescue(fn (): ApiCaller => app(ApiCaller::class), null, false);
        if (! $caller) {
            return;
        }
        GsAuditEntry::query()->create([
            'user_id' => $caller->auditUserId(), 'action' => $action, 'screen' => $screen, 'reference' => $reference,
            'summary' => mb_substr($summary, 0, 1000), 'created_at' => now(),
        ]);
    }

    /** "Stock Receive". */
    public static function typeName(string $type, ?string $locale = null): string
    {
        return __("store.inv.type.{$type}", [], $locale);
    }

    /** 40, 2.5, 0.125 — never 40.0000. */
    public static function qty(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
    }

    /** "৳2,280" — grouped, no decimals unless there are some. */
    public static function money(float $n): string
    {
        $text = number_format($n, 2, '.', ',');

        return '৳'.(str_ends_with($text, '.00') ? substr($text, 0, -3) : $text);
    }

    /** The next requisition number (REQ-2026-000001), under the same kind of lock. */
    public static function nextRequisitionNumber(string $date): string
    {
        $head = 'REQ-'.CarbonImmutable::parse($date)->format('Y').'-';
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ["gs_requisitions:{$head}:".app(TenantContext::class)->companyId()]);

        return $head.str_pad((string) (1 + self::last($head, 'gs_requisitions', 'req_no')), 6, '0', STR_PAD_LEFT);
    }

    /** The store's settings, its defaults until someone saves them. */
    public static function settings(): GsSetting
    {
        return GsSetting::query()->first() ?? new GsSetting;
    }

    /** @return array{name: string, footer: ?string} the name a voucher is headed with (the company's, unless Settings say otherwise) and its footer */
    public static function voucher(): array
    {
        $s = self::settings();

        return ['name' => $s->voucher_name ?: (string) Company::query()->whereKey(app(TenantContext::class)->companyId())->value('name'), 'footer' => $s->voucher_footer];
    }

    /**
     * What has been issued against a requisition, item by item, on issues that stand.
     *
     * @return array<string, float> item id => quantity
     */
    public static function issuedAgainst(GsRequisition $req): array
    {
        return GsDocumentLine::query()->whereIn('gs_document_id', GsDocument::query()->where('requisition_id', $req->id)->where('status', 'POSTED')->select('id'))
            ->get()->groupBy('gs_item_id')->map(fn ($set) => round((float) $set->sum('quantity'), 4))->all();
    }

    /** An approved requisition's status after an issue or a cancelled issue: Approved, Partially Issued or Issued. */
    public static function refreshRequisition(GsRequisition $req): void
    {
        if (! in_array($req->status, [...GsRequisition::ISSUABLE, 'ISSUED'], true)) {
            return;
        }
        $issued = self::issuedAgainst($req);
        $lines = GsRequisitionLine::query()->where('gs_requisition_id', $req->id)->where('approved_qty', '>', 0)->get();
        $left = $lines->sum(fn (GsRequisitionLine $l) => max(0, (float) $l->approved_qty - ($issued[$l->gs_item_id] ?? 0)));
        $any = array_sum($issued) > 0.00005;
        $req->update(['status' => $left <= 0.00005 && $lines->isNotEmpty() ? 'ISSUED' : ($any ? 'PARTIAL' : 'APPROVED')]);
    }

    private static function head(string $type, string $date): string
    {
        return GsDocument::PREFIX[$type].'-'.CarbonImmutable::parse($date)->format('Y').'-';
    }

    private static function last(string $head, string $table = 'gs_documents', string $column = 'doc_no'): int
    {
        return (int) DB::table($table)->where('company_id', app(TenantContext::class)->companyId())->where($column, 'like', $head.'%')->pluck($column)
            ->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();
    }
}

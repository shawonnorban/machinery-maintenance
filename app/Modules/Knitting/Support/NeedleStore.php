<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitNeedleBroken;
use App\Modules\Knitting\Models\KnitNeedleClosing;
use App\Modules\Knitting\Models\KnitNeedleIssue;
use App\Modules\Knitting\Models\KnitNeedleIssueLine;
use App\Modules\Knitting\Models\KnitNeedleLoanOpening;
use App\Modules\Knitting\Models\KnitNeedleLoanReceipt;
use App\Modules\Knitting\Models\KnitNeedleLoanReceiptLine;
use App\Modules\Knitting\Models\KnitNeedleLoanReq;
use App\Modules\Knitting\Models\KnitNeedleLoanReqLine;
use App\Modules\Knitting\Models\KnitNeedleLoanReturn;
use App\Modules\Knitting\Models\KnitNeedleLoanReturnLine;
use App\Modules\Knitting\Models\KnitNeedleMove;
use App\Modules\Knitting\Models\KnitNeedleReceipt;
use App\Modules\Knitting\Models\KnitNeedleReq;
use App\Modules\Knitting\Models\KnitNeedleReqLine;
use App\Modules\Knitting\Models\KnitNeedleReturn;
use App\Modules\Knitting\Models\KnitOilConsumption;
use App\Modules\Knitting\Models\KnitOilOpening;
use App\Modules\Knitting\Models\KnitOilReceipt;
use App\Modules\Knitting\Models\KnitOilReq;
use App\Modules\Knitting\Models\KnitOilReturn;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The needle and sinker book Knit Production → Needle Store (AnnotechERP
 * 15/07) keeps: its items, categories and stores are the General Store's;
 * a store and item's balance is the sum of its moves; every paper posts
 * signed quantities, replacing what it posted before; a struck month takes
 * no more movement; and a machine-wise indent's standing follows the
 * issues raised against it.
 */
final class NeedleStore
{
    /** The two ledgers the knitting store keeps on the same items and stores. */
    public const BOOKS = ['NEEDLE', 'OIL'];

    /** @return array<string, mixed> */
    public static function options(): array
    {
        $company = app(TenantContext::class)->companyId();
        $p = KnitProduction::options();
        $units = DB::table('units_of_measure')->where(fn ($q) => $q->where('company_id', $company)->orWhereNull('company_id'))->pluck('code', 'id');
        $offices = self::machineOffices();

        return collect($p)->only(['company', 'shifts', 'parties', 'employees'])->all() + [
            'offices' => DB::table('factories')->where('company_id', $company)->orderBy('name')->get(['id', 'name', 'code'])
                ->map(fn ($f) => ['value' => $f->id, 'label' => $f->code ? "{$f->name} ({$f->code})" : $f->name])->values()->all(),
            'departments' => DB::table('departments')->where('company_id', $company)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->values()->all(),
            'categories' => DB::table('item_categories')->where('company_id', $company)->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => "{$c->name} ({$c->code})"])->values()->all(),
            'items' => DB::table('gs_items')->where('company_id', $company)->where('active', true)->orderBy('item_code')
                ->get(['id', 'item_code', 'name', 'gs_category_id', 'gs_unit_id', 'default_price'])
                ->map(fn ($i) => ['value' => $i->id, 'label' => "{$i->item_code} · {$i->name}", 'category_id' => $i->gs_category_id,
                    'uom' => UomCodes::normalize($units[$i->gs_unit_id] ?? null), 'price' => $i->default_price !== null ? (float) $i->default_price : null])->values()->all(),
            'stores' => DB::table('stores')->where('company_id', $company)->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])
                ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name, 'code' => $s->code])->values()->all(),
            'machines' => DB::table('knit_machines')->where('company_id', $company)->where('active', true)->orderBy('machine_no')->get(['id', 'machine_no', 'machine_type_id'])
                ->map(fn ($m) => ['value' => $m->id, 'label' => $m->machine_no, 'type_id' => $m->machine_type_id, 'office_id' => $offices[$m->id] ?? null])->values()->all(),
            'machine_types' => DB::table('knit_ref_entries')->where('company_id', $company)->where('list', 'MC_TYPE')->where('active', true)->orderBy('sort_order')
                ->get(['id', 'name', 'code'])->map(fn ($t) => ['value' => $t->id, 'label' => "{$t->name} ({$t->code})"])->values()->all(),
            'uoms' => array_map(fn (array $u) => ['value' => $u['code'], 'label' => $u['code']], UomCodes::options($company)),
            'req_types' => YarnStore::REQ_TYPES,
        ];
    }

    /**
     * Each machine's unit: floor → building → factory.
     *
     * @return Collection<string, string>
     */
    public static function machineOffices(): Collection
    {
        return DB::table('knit_machines')->join('floors', 'floors.id', '=', 'knit_machines.floor_id')->join('buildings', 'buildings.id', '=', 'floors.building_id')
            ->where('knit_machines.company_id', app(TenantContext::class)->companyId())->pluck('buildings.factory_id', 'knit_machines.id');
    }

    /**
     * Post a paper's movement: what it posted before under this kind is replaced.
     *
     * @param  list<array{store_id: string, item_id: string, qty: float, value?: float, doc_line_id?: ?string}>  $entries
     */
    public static function post(string $kind, string $docNo, string $date, array $entries, string $book = 'NEEDLE'): void
    {
        self::unpost($kind, $docNo);
        foreach ($entries as $e) {
            if (abs((float) $e['qty']) < 0.00005) {
                continue;
            }
            KnitNeedleMove::query()->create([
                'book' => $book, 'store_id' => $e['store_id'], 'item_id' => $e['item_id'], 'move_date' => $date, 'kind' => $kind, 'doc_no' => $docNo,
                'doc_line_id' => $e['doc_line_id'] ?? null, 'qty' => round((float) $e['qty'], 4), 'value' => round((float) ($e['value'] ?? 0), 4),
            ]);
        }
    }

    public static function unpost(string $kind, string $docNo): void
    {
        KnitNeedleMove::query()->where('kind', $kind)->where('doc_no', $docNo)->delete();
    }

    /** A store and item's balance (before a date, given; a paper's own moves left out, given). */
    public static function balance(string $storeId, string $itemId, ?string $before = null, ?string $exceptDoc = null): float
    {
        return round((float) KnitNeedleMove::query()->where('store_id', $storeId)->where('item_id', $itemId)
            ->when($before, fn ($q) => $q->whereDate('move_date', '<', $before))->when($exceptDoc, fn ($q) => $q->where('doc_no', '!=', $exceptDoc))->sum('qty'), 4);
    }

    /** A struck month takes no more movement in that store. */
    public static function assertOpen(?string $storeId, string $date, string $field = 'date', string $book = 'NEEDLE'): void
    {
        if (! $storeId) {
            return;
        }
        $period = CarbonImmutable::parse($date)->format('Y-m');
        if (KnitNeedleClosing::query()->where('book', $book)->where('store_id', $storeId)->where('period', '>=', $period)->exists()) {
            throw ValidationException::withMessages([$field => __('knitting.nd.v.month_closed', [
                'period' => CarbonImmutable::parse($date)->format('F Y'), 'store' => DB::table('stores')->where('id', $storeId)->value('name'),
            ])]);
        }
    }

    /**
     * What has been released against each indent line on live issues.
     *
     * @return Collection<string, float>
     */
    public static function issued($lineIds, ?string $exceptIssueId = null): Collection
    {
        $live = KnitNeedleIssue::query()->where('status', 'ISSUED')->when($exceptIssueId, fn ($q) => $q->whereKeyNot($exceptIssueId))->select('id');

        return KnitNeedleIssueLine::query()->whereIn('req_line_id', collect($lineIds)->values())->whereIn('issue_id', $live)
            ->groupBy('req_line_id')->selectRaw('req_line_id, sum(qty) as q')->pluck('q', 'req_line_id')->map(fn ($q) => round((float) $q, 4));
    }

    /**
     * What has come back against each loan indent line: counted in on
     * received challans, or — to check a challan being written — claimed on
     * any live one (that challan aside).
     *
     * @return Collection<string, float>
     */
    public static function loanReturned($lineIds, ?string $exceptReturnId = null, bool $counted = true): Collection
    {
        $returns = KnitNeedleLoanReturn::query()->when($exceptReturnId, fn ($q) => $q->whereKeyNot($exceptReturnId))
            ->when($counted, fn ($q) => $q->whereIn('status', ['RECEIVED', 'FINALIZED']), fn ($q) => $q->where('status', '!=', 'CANCELLED'))->select('id');
        $col = $counted ? 'received_qty' : 'return_qty';

        return KnitNeedleLoanReturnLine::query()->whereIn('req_line_id', collect($lineIds)->values())->whereIn('return_id', $returns)
            ->groupBy('req_line_id')->selectRaw("req_line_id, sum({$col}) as q")->pluck('q', 'req_line_id')->map(fn ($q) => round((float) $q, 4));
    }

    /**
     * What has been borrowed from a party, per item: the book's opening
     * position's Loan Received and every posted loan receipt.
     *
     * @return Collection<string, float>
     */
    public static function borrowed(string $book, string $partyId): Collection
    {
        $posted = KnitNeedleLoanReceipt::query()->where('book', $book)->where('party_id', $partyId)->whereIn('status', KnitNeedleLoanReceipt::POSTED)->select('id');
        $in = KnitNeedleLoanReceiptLine::query()->whereIn('receipt_id', $posted)->groupBy('item_id')->selectRaw('item_id, sum(receive_qty) as q')->pluck('q', 'item_id');
        $out = collect();
        foreach ($in as $item => $q) {
            $out[$item] = round((float) $q, 4);
        }
        foreach (KnitNeedleLoanOpening::query()->where('book', $book)->where('party_id', $partyId)->where('loan_received', '>', 0)->get() as $o) {
            $out[$o->item_id] = round((float) ($out[$o->item_id] ?? 0) + $o->loan_received, 4);
        }

        return $out;
    }

    /**
     * What has gone back to a party on its loan-return indents, per item (one indent aside, given).
     *
     * @return Collection<string, float>
     */
    public static function returnedTo(string $book, string $partyId, ?string $exceptReqId = null): Collection
    {
        $reqs = KnitNeedleLoanReq::query()->where('book', $book)->where('party_id', $partyId)->where('kind', 'LOAN_RETURN')->where('status', '!=', 'CANCELLED')
            ->when($exceptReqId, fn ($q) => $q->whereKeyNot($exceptReqId))->select('id');

        return KnitNeedleLoanReqLine::query()->whereIn('req_id', $reqs)->groupBy('item_id')->selectRaw('item_id, sum(issue_qty) as q')->pluck('q', 'item_id')
            ->map(fn ($q) => round((float) $q, 4));
    }

    /** A machine-wise indent's standing follows its issues: Verified, Partly Issued, Issued. */
    public static function syncReq(KnitNeedleReq $req): void
    {
        if (! in_array($req->status, ['VERIFIED', 'PARTLY_ISSUED', 'ISSUED'], true)) {
            return;
        }
        $lines = KnitNeedleReqLine::query()->where('req_id', $req->id)->get();
        $asked = (float) $lines->sum('req_qty');
        $out = (float) self::issued($lines->pluck('id'))->sum();
        $req->update(['status' => $out <= 0 ? 'VERIFIED' : ($out + 0.00005 >= $asked ? 'ISSUED' : 'PARTLY_ISSUED')]);
    }

    /**
     * Unfinished paper in a store's month: what stops it being struck.
     *
     * @return list<array{doc: string, no: string, date: string, status: string}>
     */
    public static function exceptions(string $storeId, string $period, string $book = 'NEEDLE'): array
    {
        $from = CarbonImmutable::parse("{$period}-01");
        $to = $from->endOfMonth();
        $in = fn ($q, string $col) => $q->whereDate($col, '>=', $from->toDateString())->whereDate($col, '<=', $to->toDateString());
        $out = [];
        foreach (KnitNeedleLoanReturn::query()->where('book', $book)->where('store_id', $storeId)->where('status', 'DRAFT')->tap(fn ($q) => $in($q, 'ret_challan_date'))->get() as $r) {
            $out[] = ['doc' => 'loan_return', 'no' => $r->return_no, 'date' => $r->ret_challan_date->toDateString(), 'status' => $r->status];
        }
        foreach (KnitNeedleLoanReceipt::query()->where('book', $book)->where('store_id', $storeId)->whereIn('status', ['DRAFT', 'WAITING'])->tap(fn ($q) => $in($q, 'mrr_date'))->get() as $r) {
            $out[] = ['doc' => 'loan_receipt', 'no' => $r->mrr_no, 'date' => $r->mrr_date->toDateString(), 'status' => $r->status];
        }
        foreach (KnitNeedleLoanReq::query()->where('book', $book)->where('store_id', $storeId)->whereIn('status', ['DRAFT', 'WAITING'])->tap(fn ($q) => $in($q, 'req_date'))->get() as $r) {
            $out[] = ['doc' => 'loan', 'no' => $r->req_no, 'date' => $r->req_date->toDateString(), 'status' => $r->status];
        }
        if ($book !== 'NEEDLE') {
            return array_merge($out, self::oilExceptions($storeId, $from, $to));
        }
        foreach (KnitNeedleReceipt::query()->where('store_id', $storeId)->whereIn('status', ['DRAFT', 'WAITING'])->tap(fn ($q) => $in($q, 'mrr_date'))->get() as $r) {
            $out[] = ['doc' => 'receipt', 'no' => $r->mrr_no, 'date' => $r->mrr_date->toDateString(), 'status' => $r->status];
        }
        foreach (KnitNeedleReturn::query()->where('store_id', $storeId)->where('status', 'DRAFT')->tap(fn ($q) => $in($q, 'challan_date'))->get() as $r) {
            $out[] = ['doc' => 'return', 'no' => $r->challan_no, 'date' => $r->challan_date->toDateString(), 'status' => $r->status];
        }
        foreach (KnitNeedleReq::query()->where('to_store_id', $storeId)->whereIn('status', ['DRAFT', 'WAITING'])->tap(fn ($q) => $in($q, 'req_date'))->get() as $r) {
            $out[] = ['doc' => 'req', 'no' => $r->req_no, 'date' => $r->req_date->toDateString(), 'status' => $r->status];
        }
        $sheets = DB::table('knit_needle_openings')->where('store_id', $storeId)->where('finalized', false)
            ->whereBetween('ob_date', [$from->toDateString(), $to->toDateString()])->distinct()->pluck('ob_date');
        foreach ($sheets as $d) {
            $out[] = ['doc' => 'opening', 'no' => (string) $d, 'date' => (string) $d, 'status' => 'DRAFT'];
        }

        return $out;
    }

    /**
     * The oil book's unfinished paper in a store's month (15/10).
     *
     * @return list<array{doc: string, no: string, date: string, status: string}>
     */
    private static function oilExceptions(string $storeId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $span = [$from->toDateString(), $to->toDateString()];
        $out = [];
        foreach (KnitOilReceipt::query()->where('store_id', $storeId)->whereNull('finalized_at')->where('status', '!=', 'CANCELLED')->whereBetween('mrr_date', $span)->get() as $r) {
            $out[] = ['doc' => 'oil_receipt', 'no' => $r->mrr_no, 'date' => $r->mrr_date->toDateString(), 'status' => $r->status];
        }
        foreach (KnitOilReturn::query()->where('store_id', $storeId)->where('status', 'DRAFT')->whereBetween('challan_date', $span)->get() as $r) {
            $out[] = ['doc' => 'oil_return', 'no' => $r->challan_no, 'date' => $r->challan_date->toDateString(), 'status' => $r->status];
        }
        foreach (KnitOilReq::query()->where('to_store_id', $storeId)->whereIn('status', ['DRAFT', 'WAITING'])->whereBetween('req_date', $span)->get() as $r) {
            $out[] = ['doc' => 'oil_req', 'no' => $r->req_no, 'date' => $r->req_date->toDateString(), 'status' => $r->status];
        }
        foreach (KnitOilConsumption::query()->where('store_id', $storeId)->where('status', 'DRAFT')->whereBetween('cons_date', $span)->get() as $r) {
            $out[] = ['doc' => 'oil_consumption', 'no' => $r->cons_no, 'date' => $r->cons_date->toDateString(), 'status' => $r->status];
        }
        foreach (KnitOilOpening::query()->where('store_id', $storeId)->where('finalized', false)->whereBetween('ob_date', $span)->get() as $o) {
            $out[] = ['doc' => 'oil_opening', 'no' => $o->ob_date->toDateString(), 'date' => $o->ob_date->toDateString(), 'status' => 'DRAFT'];
        }

        return $out;
    }

    /** @return array{0: Collection<string, string>, 1: Collection<string, string>} item labels, store names */
    public static function names(): array
    {
        $company = app(TenantContext::class)->companyId();

        return [
            DB::table('gs_items')->where('company_id', $company)->get(['id', 'item_code', 'name'])->mapWithKeys(fn ($i) => [$i->id => "{$i->item_code} · {$i->name}"]),
            DB::table('stores')->where('company_id', $company)->pluck('name', 'id'),
        ];
    }

    public static function brokenLabel(KnitNeedleBroken $b): string
    {
        return $b->ref_no.' · '.$b->broken_date->format('d M Y');
    }
}

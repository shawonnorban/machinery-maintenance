<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitNeedleClosing;
use App\Modules\Knitting\Models\KnitNeedleLoanOpening;
use App\Modules\Knitting\Models\KnitNeedleMove;
use App\Modules\Knitting\Models\KnitNeedleOpening;
use App\Modules\Knitting\Models\KnitNeedleReceipt;
use App\Modules\Knitting\Models\KnitNeedleReceiptLine;
use App\Modules\Knitting\Models\KnitNeedleReturn;
use App\Modules\Knitting\Models\KnitNeedleReturnLine;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\NeedleStore;
use App\Modules\Knitting\Support\YarnStore;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Needle Store (AnnotechERP 15/07), the store's side, as
 * Brickland keeps it:
 *
 * - Needle Opening Balance: a sheet per store and date, loaded, typed and
 *   saved; Save & Finalize posts it and locks it.
 * - Needle Loan Opening Balance: one net position per party and item.
 * - Machine Needle Receipt (NMR-…): Draft → Submit → Verify (posted to the
 *   store) → Send to Accounts → Accounts Approve; Cancel before it posts;
 *   Item Wise Consumptions sums the book between two dates.
 * - Needle Receipt Return (…-R01): against a posted receipt, never more
 *   than is outstanding; Issue at Gate posts it, Finalize, Cancel.
 * - Needle Stock Closing: Show Exception lists unfinished paper, Start
 *   Closing strikes the month.
 */
class KnitNeedleStoreApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');

        return ApiResponse::ok(NeedleStore::options() + [
            'receipt_statuses' => KnitNeedleReceipt::STATUSES, 'pay_methods' => KnitNeedleReceipt::PAY_METHODS,
            'return_types' => KnitNeedleReturn::TYPES, 'return_statuses' => KnitNeedleReturn::STATUSES,
        ]);
    }

    /* ---------------- opening balance ---------------- */

    /** Opening Balance List: one row per store and date. */
    public function openings(): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        [, $stores] = NeedleStore::names();
        $rows = KnitNeedleOpening::query()->groupBy('store_id', 'ob_date')
            ->selectRaw('store_id, ob_date, count(*) as n, sum(qty) as q, sum(qty * rate) as v, bool_and(finalized) as fin')->orderByDesc('ob_date')->get();

        $codes = DB::table('stores')->whereIn('id', $rows->pluck('store_id'))->pluck('code', 'id');

        return ApiResponse::ok($rows->map(fn ($r) => ['store_id' => $r->store_id, 'store' => $stores[$r->store_id] ?? null, 'store_code' => $codes[$r->store_id] ?? null, 'ob_date' => substr((string) $r->ob_date, 0, 10),
            'items' => (int) $r->n, 'qty' => round((float) $r->q, 3), 'value' => round((float) $r->v, 2), 'finalized' => (bool) $r->fin])->values()->all());
    }

    /** Load: the store's items (a category's, given) with what is already entered on that date. */
    public function openingSheet(Request $request): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $company = app(TenantContext::class)->companyId();
        $f = $request->validate([
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)], 'ob_date' => ['required', 'date'],
            'category_id' => ['nullable', Rule::exists('item_categories', 'id')->where('company_id', $company)],
        ], ['store_id.required' => __('knitting.nd.v.store'), 'ob_date.required' => __('knitting.nd.v.ob_date')]);

        return ApiResponse::ok($this->sheet($f['store_id'], $f['ob_date'], $f['category_id'] ?? null));
    }

    public function saveOpening(Request $request): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)], 'ob_date' => ['required', 'date'],
            'category_id' => ['nullable', 'string'], 'finalize' => ['boolean'],
            'lines' => ['present', 'array', 'max:2000'], 'lines.*.item_id' => ['required', Rule::exists('gs_items', 'id')->where('company_id', $company)],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], ['store_id.required' => __('knitting.nd.v.store'), 'ob_date.required' => __('knitting.nd.v.ob_date')]);
        $date = CarbonImmutable::parse($data['ob_date'])->toDateString();
        $sheet = KnitNeedleOpening::query()->where('store_id', $data['store_id'])->whereDate('ob_date', $date);
        if ((clone $sheet)->where('finalized', true)->exists()) {
            throw ValidationException::withMessages(['ob_date' => __('knitting.nd.v.ob_final')]);
        }
        NeedleStore::assertOpen($data['store_id'], $date, 'ob_date');
        DB::transaction(function () use ($data, $date, $sheet): void {
            foreach (YarnStore::inOrder($data['lines']) as $l) {
                $qty = round((float) ($l['qty'] ?? 0), 4);
                $row = (clone $sheet)->where('item_id', $l['item_id'])->first();
                // A zero opening and no row at all are the same fact.
                if ($qty <= 0) {
                    $row?->delete();

                    continue;
                }
                $vals = ['qty' => $qty, 'rate' => round((float) ($l['rate'] ?? 0), 4)];
                $row ? $row->update($vals) : KnitNeedleOpening::query()->create($vals + ['store_id' => $data['store_id'], 'ob_date' => $date, 'item_id' => $l['item_id'],
                    'created_by' => $this->caller()->auditUserId()]);
            }
            if (! empty($data['finalize'])) {
                $rows = (clone $sheet)->get();
                if ($rows->isEmpty()) {
                    throw ValidationException::withMessages(['lines' => __('knitting.nd.v.ob_empty')]);
                }
                (clone $sheet)->update(['finalized' => true]);
                NeedleStore::post('OPENING', $this->obNo($data['store_id'], $date), $date, $rows->map(fn (KnitNeedleOpening $o) => [
                    'store_id' => $o->store_id, 'item_id' => $o->item_id, 'qty' => $o->qty, 'value' => $o->qty * $o->rate, 'doc_line_id' => $o->id,
                ])->all());
            }
        });

        return ApiResponse::ok($this->sheet($data['store_id'], $date, $data['category_id'] ?? null));
    }

    /* ---------------- loan opening ---------------- */

    public function loanOpenings(Request $request): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        [$items, $stores] = NeedleStore::names();
        $book = $this->book($request);
        $rows = KnitNeedleLoanOpening::query()->where('book', $book)->orderByDesc('ob_date')->limit(1000)->get();
        $parties = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('party_id'))->get(['id', 'name', 'code'])->keyBy('id');
        $codes = DB::table('gs_items')->whereIn('id', $rows->pluck('item_id'))->pluck('item_code', 'id');

        return ApiResponse::ok($rows->map(fn (KnitNeedleLoanOpening $o) => collect($o->only(['id', 'book', 'party_id', 'item_id', 'uom', 'store_id', 'is_final', 'remarks']))->all() + [
            'ob_date' => $o->ob_date->toDateString(), 'party' => $parties[$o->party_id]->name ?? null, 'party_code' => $parties[$o->party_id]->code ?? null,
            'item_code' => $codes[$o->item_id] ?? null, 'item' => $items[$o->item_id] ?? null, 'store' => $stores[$o->store_id] ?? null,
            'category_id' => DB::table('gs_items')->where('id', $o->item_id)->value('gs_category_id'),
            'loan_received' => round($o->loan_received, 3), 'loan_issued' => round($o->loan_issued, 3), 'net' => round($o->loan_received - $o->loan_issued, 3),
            'cost_price' => round($o->cost_price, 4),
        ])->values()->all());
    }

    public function saveLoanOpening(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = $id ? KnitNeedleLoanOpening::query()->findOrFail($id) : null;
        if ($row?->is_final) {
            throw ValidationException::withMessages(['is_final' => __('knitting.nd.v.loan_final')]);
        }
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'ob_date' => ['required', 'date'], 'party_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'item_id' => ['required', Rule::exists('gs_items', 'id')->where('company_id', $company)], 'uom' => ['nullable', 'string', 'max:16', UomCodes::rule($company)],
            'loan_received' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'loan_issued' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'store_id' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $company)], 'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'is_final' => ['boolean'], 'remarks' => ['nullable', 'string', 'max:500'],
        ], ['ob_date.required' => __('knitting.nd.v.ob_date'), 'party_id.required' => __('knitting.nd.v.party'), 'item_id.required' => __('knitting.nd.v.item')]);
        $book = $row->book ?? $this->book($request);
        if ((float) ($data['loan_received'] ?? 0) <= 0 && (float) ($data['loan_issued'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['loan_received' => __('knitting.nd.v.loan_qty')]);
        }
        if (KnitNeedleLoanOpening::query()->where('book', $book)->where('party_id', $data['party_id'])->where('item_id', $data['item_id'])->when($row, fn ($q) => $q->whereKeyNot($row->id))->exists()) {
            throw ValidationException::withMessages(['item_id' => __('knitting.nd.v.loan_taken')]);
        }
        $vals = ['ob_date' => $data['ob_date'], 'party_id' => $data['party_id'], 'item_id' => $data['item_id'], 'uom' => UomCodes::normalize($data['uom'] ?? null) ?: null,
            'loan_received' => round((float) ($data['loan_received'] ?? 0), 4), 'loan_issued' => round((float) ($data['loan_issued'] ?? 0), 4),
            'store_id' => $data['store_id'] ?? null, 'cost_price' => round((float) ($data['cost_price'] ?? 0), 4), 'is_final' => (bool) ($data['is_final'] ?? false),
            'remarks' => ($data['remarks'] ?? '') ?: null];
        $row ? $row->update($vals) : $row = KnitNeedleLoanOpening::query()->create($vals + ['book' => $book, 'created_by' => $this->caller()->auditUserId()]);

        return $id ? ApiResponse::ok(['id' => $row->id]) : ApiResponse::created(['id' => $row->id]);
    }

    /* ---------------- receipts ---------------- */

    public function receipts(): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $rows = KnitNeedleReceipt::query()->orderByDesc('mrr_date')->orderByDesc('mrr_no')->limit(500)->get();
        $lines = KnitNeedleReceiptLine::query()->whereIn('receipt_id', $rows->pluck('id'))->get();
        $back = $this->returned($lines->pluck('id'));
        [$items, $stores] = NeedleStore::names();
        $parties = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('supplier_id'))->pluck('name', 'id');
        $byRec = $lines->groupBy('receipt_id');

        return ApiResponse::ok($rows->map(function (KnitNeedleReceipt $r) use ($byRec, $back, $items, $stores, $parties) {
            $mine = $byRec[$r->id] ?? collect();

            return ['mrr_no' => $r->mrr_no, 'mrr_date' => $r->mrr_date->toDateString(), 'store_id' => $r->store_id, 'store' => $stores[$r->store_id] ?? null,
                'supplier_id' => $r->supplier_id, 'supplier' => $parties[$r->supplier_id] ?? null, 'challan_no' => $r->challan_no, 'req_type' => $r->req_type,
                'items' => $mine->map(fn ($l) => $items[$l->item_id] ?? '')->implode(', '), 'lines' => $mine->count(),
                'challan_qty' => round((float) $mine->sum('challan_qty'), 3), 'received_qty' => round((float) $mine->sum('received_qty'), 3),
                'amount' => round((float) $mine->sum('amount'), 2), 'returned' => round((float) $mine->sum(fn ($l) => $back[$l->id] ?? 0), 3),
                'outstanding' => round((float) $mine->sum(fn ($l) => $l->received_qty - ($back[$l->id] ?? 0)), 3), 'status' => $r->status];
        })->values()->all());
    }

    public function showReceipt(string $no): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');

        return ApiResponse::ok($this->presentReceipt(KnitNeedleReceipt::query()->where('mrr_no', $no)->firstOrFail()));
    }

    public function saveReceipt(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = $no ? KnitNeedleReceipt::query()->where('mrr_no', $no)->firstOrFail() : null;
        $row && $this->assertStatus($row->mrr_no, $row->status, ['DRAFT'], 'save', 'receipt_status');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'mrr_date' => ['required', 'date'], 'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'req_type' => ['nullable', Rule::in(YarnStore::REQ_TYPES)], 'supplier_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'challan_no' => ['nullable', 'string', 'max:60'], 'challan_date' => ['nullable', 'date'], 'source' => ['nullable', 'string', 'max:80'],
            'pay_method' => ['nullable', Rule::in(KnitNeedleReceipt::PAY_METHODS)], 'lc_no' => ['nullable', 'string', 'max:60'], 'remarks' => ['nullable', 'string', 'max:2000'],
        ] + $this->lineRules(['challan_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'received_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:99999999']]), [
                'mrr_date.required' => __('knitting.nd.v.mrr_date'), 'store_id.required' => __('knitting.nd.v.store'), 'supplier_id.required' => __('knitting.nd.v.supplier'),
                'lines.*.item_id.required' => __('knitting.nd.v.line_item'), 'lines.*.received_qty.required' => __('knitting.nd.v.received_qty'),
                'lines.*.received_qty.gt' => __('knitting.nd.v.received_qty'),
            ]);
        if (! empty($data['lc_no']) && ($data['pay_method'] ?? null) !== 'LC') {
            throw ValidationException::withMessages(['lc_no' => __('knitting.nd.v.lc_only')]);
        }
        $lines = $this->needLines($data, 'no_receipt_lines');
        $row = DB::transaction(function () use ($row, $data, $lines): KnitNeedleReceipt {
            $head = collect($data)->except('lines')->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['req_type' => null, 'challan_no' => null, 'challan_date' => null, 'source' => null, 'pay_method' => null, 'lc_no' => null, 'remarks' => null];
            $row ??= KnitNeedleReceipt::query()->create($head + ['mrr_no' => KnitProduction::nextNumber('NMR', 'knit_needle_receipts', 'mrr_no', $data['mrr_date']),
                'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitNeedleReceiptLine::query()->where('receipt_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                $qty = round((float) $l['received_qty'], 4);
                $rate = round((float) ($l['rate'] ?? 0), 4);
                KnitNeedleReceiptLine::query()->create(['receipt_id' => $row->id, 'company_id' => $row->company_id, 'sort_order' => $i + 1, 'item_id' => $l['item_id'],
                    'uom' => UomCodes::normalize($l['uom'] ?? null) ?: null, 'challan_qty' => round((float) ($l['challan_qty'] ?? 0), 4), 'received_qty' => $qty,
                    'rate' => $rate, 'amount' => round($qty * $rate, 4), 'remarks' => ($l['remarks'] ?? '') ?: null]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentReceipt($row->refresh())) : ApiResponse::created($this->presentReceipt($row->refresh()));
    }

    public function receiptTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = KnitNeedleReceipt::query()->where('mrr_no', $no)->firstOrFail();
        [$from, $to] = match ($action) {
            'submit' => [['DRAFT'], 'WAITING'],
            'verify' => [['WAITING'], 'VERIFIED'],
            'accounts' => [['VERIFIED'], 'WAIT_ACCOUNTS'],
            'approve' => [['WAIT_ACCOUNTS'], 'APPROVED'],
            'cancel' => [['DRAFT', 'WAITING'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row->mrr_no, $row->status, $from, $action, 'receipt_status');
        DB::transaction(function () use ($row, $action, $to): void {
            if ($action === 'verify') {
                // Verify is the store counting it in: the receipt posts.
                NeedleStore::assertOpen($row->store_id, $row->mrr_date->toDateString(), 'status');
                NeedleStore::post('RECEIPT', $row->mrr_no, $row->mrr_date->toDateString(), KnitNeedleReceiptLine::query()->where('receipt_id', $row->id)->get()
                    ->map(fn (KnitNeedleReceiptLine $l) => ['store_id' => $row->store_id, 'item_id' => $l->item_id, 'qty' => $l->received_qty, 'value' => $l->amount, 'doc_line_id' => $l->id])->all());
                $row->update(['verified_at' => now()]);
            }
            if ($action === 'approve') {
                $row->update(['approved_at' => now(), 'approved_by' => $this->caller()->auditUserId()]);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->presentReceipt($row->refresh()));
    }

    /** Item Wise Consumptions: what a store took in and let go of, needle by needle, between two dates. */
    public function consumption(Request $request): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $company = app(TenantContext::class)->companyId();
        $f = $request->validate([
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)], 'from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'],
        ], ['store_id.required' => __('knitting.nd.v.store'), 'from.required' => __('knitting.nd.v.from'), 'to.required' => __('knitting.nd.v.to')]);
        [$items] = NeedleStore::names();
        $open = KnitNeedleMove::query()->where('book', 'NEEDLE')->where('store_id', $f['store_id'])->whereDate('move_date', '<', $f['from'])->groupBy('item_id')->selectRaw('item_id, sum(qty) as q')->pluck('q', 'item_id');
        $moves = KnitNeedleMove::query()->where('book', 'NEEDLE')->where('store_id', $f['store_id'])->whereDate('move_date', '>=', $f['from'])->whereDate('move_date', '<=', $f['to'])
            ->groupBy('item_id', 'kind')->selectRaw('item_id, kind, sum(qty) as q')->get()->groupBy('item_id');
        $ids = $open->keys()->merge($moves->keys())->unique();
        $meta = DB::table('gs_items')->whereIn('id', $ids)->get(['id', 'gs_category_id', 'gs_unit_id'])->keyBy('id');
        $cats = DB::table('item_categories')->whereIn('id', $meta->pluck('gs_category_id'))->pluck('name', 'id');
        $units = DB::table('units_of_measure')->whereIn('id', $meta->pluck('gs_unit_id'))->pluck('code', 'id');
        // Outflows are stored negative; shown as plain amounts (never -0).
        $neg = fn ($v) => round(-(float) $v, 3) + 0.0;
        $out = [];
        foreach ($ids as $id) {
            $k = ($moves[$id] ?? collect())->pluck('q', 'kind')->map(fn ($q) => round((float) $q, 3));
            $row = ['item_id' => $id, 'item' => $items[$id] ?? null, 'category' => $cats[$meta[$id]->gs_category_id ?? null] ?? null, 'uom' => $units[$meta[$id]->gs_unit_id ?? null] ?? null,
                'opening' => round((float) ($open[$id] ?? 0) + (float) ($k['OPENING'] ?? 0), 3), 'received' => (float) ($k['RECEIPT'] ?? 0), 'returned' => $neg($k['RETURN'] ?? 0),
                'issued_in' => (float) ($k['ISSUE_IN'] ?? 0), 'issued_out' => $neg($k['ISSUE_OUT'] ?? 0), 'loan_out' => $neg($k['LOAN_OUT'] ?? 0)];
            $row['closing'] = round($row['opening'] + $row['received'] - $row['returned'] + $row['issued_in'] - $row['issued_out'] - $row['loan_out'], 3);
            $out[] = $row;
        }
        usort($out, fn ($a, $b) => strcmp((string) $a['item'], (string) $b['item']));

        return ApiResponse::ok($out);
    }

    /* ---------------- receipt returns ---------------- */

    public function returns(): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $rows = KnitNeedleReturn::query()->orderByDesc('challan_date')->orderByDesc('challan_no')->limit(500)->get();
        $sums = KnitNeedleReturnLine::query()->whereIn('return_id', $rows->pluck('id'))->groupBy('return_id')->selectRaw('return_id, count(*) as n, sum(qty) as q, sum(amount) as a')->get()->keyBy('return_id');
        $mrr = KnitNeedleReceipt::query()->whereIn('id', $rows->pluck('receipt_id'))->pluck('mrr_no', 'id');
        [, $stores] = NeedleStore::names();
        $parties = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('supplier_id'))->pluck('name', 'id');

        return ApiResponse::ok($rows->map(fn (KnitNeedleReturn $r) => [
            'challan_no' => $r->challan_no, 'challan_date' => $r->challan_date->toDateString(), 'mrr_no' => $mrr[$r->receipt_id] ?? null, 'challan_type' => $r->challan_type,
            'store_id' => $r->store_id, 'store' => $stores[$r->store_id] ?? null, 'supplier_id' => $r->supplier_id, 'supplier' => $parties[$r->supplier_id] ?? null,
            'lines' => (int) ($sums[$r->id]->n ?? 0), 'qty' => round((float) ($sums[$r->id]->q ?? 0), 3), 'amount' => round((float) ($sums[$r->id]->a ?? 0), 2), 'status' => $r->status,
        ])->values()->all());
    }

    public function showReturn(string $no): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');

        return ApiResponse::ok($this->presentReturn(KnitNeedleReturn::query()->where('challan_no', $no)->firstOrFail()));
    }

    /** A posted receipt's lines with what is still outstanding: what a return answers. */
    public function receiptSource(Request $request, string $no): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $r = KnitNeedleReceipt::query()->where('mrr_no', $no)->whereIn('status', KnitNeedleReceipt::POSTED)->firstOrFail();
        $except = $request->query('challan') ? KnitNeedleReturn::query()->where('challan_no', $request->query('challan'))->value('id') : null;
        $lines = KnitNeedleReceiptLine::query()->where('receipt_id', $r->id)->orderBy('sort_order')->get();
        $back = $this->returned($lines->pluck('id'), $except);
        [$items] = NeedleStore::names();

        return ApiResponse::ok(['mrr_no' => $r->mrr_no, 'store_id' => $r->store_id, 'supplier_id' => $r->supplier_id,
            'lines' => $lines->map(fn (KnitNeedleReceiptLine $l) => ['receipt_line_id' => $l->id, 'item_id' => $l->item_id, 'item' => $items[$l->item_id] ?? null, 'uom' => $l->uom,
                'received' => round($l->received_qty, 3), 'back' => round((float) ($back[$l->id] ?? 0), 3), 'outstanding' => round($l->received_qty - (float) ($back[$l->id] ?? 0), 3),
                'rate' => round($l->rate, 4)])->values()->all()]);
    }

    public function saveReturn(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = $no ? KnitNeedleReturn::query()->where('challan_no', $no)->firstOrFail() : null;
        $row && $this->assertStatus($row->challan_no, $row->status, ['DRAFT'], 'save', 'return_status');
        $data = $request->validate([
            'mrr_no' => ['required', 'string', 'max:24'], 'challan_no' => ['nullable', 'string', 'max:32', 'regex:/^[^\\/?#]+$/'], 'challan_date' => ['required', 'date'],
            'challan_type' => ['required', Rule::in(KnitNeedleReturn::TYPES)], 'gate_pass_no' => ['nullable', 'string', 'max:40'], 'vehicle_no' => ['nullable', 'string', 'max:40'],
            'reason' => ['nullable', 'string', 'max:2000'], 'lines' => ['present', 'array', 'max:300'], 'lines.*.receipt_line_id' => ['required', 'string'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], ['mrr_no.required' => __('knitting.nd.v.mrr'), 'challan_date.required' => __('knitting.nd.v.challan_date'), 'challan_type.required' => __('knitting.nd.v.return_type')]);
        $rec = KnitNeedleReceipt::query()->where('mrr_no', $data['mrr_no'])->whereIn('status', KnitNeedleReceipt::POSTED)->first();
        if (! $rec || ($row && $row->receipt_id !== $rec->id)) {
            throw ValidationException::withMessages(['mrr_no' => __('knitting.nd.v.mrr')]);
        }
        $src = KnitNeedleReceiptLine::query()->where('receipt_id', $rec->id)->get()->keyBy('id');
        $back = $this->returned($src->keys(), $row?->id);
        $lines = [];
        foreach (YarnStore::inOrder($data['lines']) as $i => $l) {
            $s = $src[$l['receipt_line_id']] ?? null;
            if (! $s) {
                throw ValidationException::withMessages(["lines.{$i}.receipt_line_id" => __('knitting.nd.v.return_line', ['n' => $i + 1])]);
            }
            $qty = round((float) ($l['qty'] ?? 0), 4);
            if ($qty <= 0) {
                continue;
            }
            $left = round($s->received_qty - (float) ($back[$s->id] ?? 0), 4);
            if ($qty > $left + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __('knitting.nd.v.return_over', ['n' => $i + 1, 'left' => $left])]);
            }
            $lines[] = ['receipt_line_id' => $s->id, 'item_id' => $s->item_id, 'uom' => $s->uom, 'qty' => $qty, 'rate' => $s->rate, 'amount' => round($qty * $s->rate, 4),
                'remarks' => ($l['remarks'] ?? '') ?: null];
        }
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.nd.v.no_return_lines')]);
        }
        $no2 = ($data['challan_no'] ?? '') ?: ($row?->challan_no ?? $this->returnNo($rec->mrr_no));
        if (KnitNeedleReturn::query()->where('challan_no', $no2)->when($row, fn ($q) => $q->whereKeyNot($row->id))->exists()) {
            throw ValidationException::withMessages(['challan_no' => __('knitting.nd.v.return_taken', ['no' => $no2])]);
        }
        $row = DB::transaction(function () use ($row, $data, $rec, $lines, $no2): KnitNeedleReturn {
            $head = ['challan_no' => $no2, 'receipt_id' => $rec->id, 'challan_date' => $data['challan_date'], 'challan_type' => $data['challan_type'], 'store_id' => $rec->store_id,
                'supplier_id' => $rec->supplier_id, 'gate_pass_no' => ($data['gate_pass_no'] ?? '') ?: null, 'vehicle_no' => ($data['vehicle_no'] ?? '') ?: null,
                'reason' => ($data['reason'] ?? '') ?: null];
            $row ??= KnitNeedleReturn::query()->create($head + ['status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitNeedleReturnLine::query()->where('return_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitNeedleReturnLine::query()->create($l + ['company_id' => $row->company_id, 'return_id' => $row->id, 'sort_order' => $i + 1]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->presentReturn($row->refresh())) : ApiResponse::created($this->presentReturn($row->refresh()));
    }

    public function returnTransition(string $no, string $action): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = KnitNeedleReturn::query()->where('challan_no', $no)->firstOrFail();
        [$from, $to] = match ($action) {
            'issue' => [['DRAFT'], 'ISSUED'],
            'finalize' => [['ISSUED'], 'FINALIZED'],
            'cancel' => [['DRAFT', 'ISSUED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row->challan_no, $row->status, $from, $action, 'return_status');
        DB::transaction(function () use ($row, $action, $to): void {
            $date = $row->challan_date->toDateString();
            if ($action === 'issue') {
                // Issue at Gate is what moves the store's balance.
                NeedleStore::assertOpen($row->store_id, $date, 'status');
                $lines = KnitNeedleReturnLine::query()->where('return_id', $row->id)->get();
                foreach ($lines->groupBy('item_id') as $item => $mine) {
                    $have = NeedleStore::balance($row->store_id, $item);
                    if ((float) $mine->sum('qty') > $have + 0.00005) {
                        throw ValidationException::withMessages(['status' => __('knitting.nd.v.stock_short', ['item' => NeedleStore::names()[0][$item] ?? '', 'have' => $have])]);
                    }
                }
                NeedleStore::post('RETURN', $row->challan_no, $date, $lines->map(fn (KnitNeedleReturnLine $l) => [
                    'store_id' => $row->store_id, 'item_id' => $l->item_id, 'qty' => -$l->qty, 'value' => -$l->amount, 'doc_line_id' => $l->id])->all());
            }
            if ($action === 'cancel' && $row->status === 'ISSUED') {
                NeedleStore::assertOpen($row->store_id, $date, 'status');
                NeedleStore::unpost('RETURN', $row->challan_no);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->presentReturn($row->refresh()));
    }

    /* ---------------- closing ---------------- */

    /** Every needle store's book: who last closed it, the month, the next due. */
    public function closings(): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $company = app(TenantContext::class)->companyId();
        $stores = DB::table('stores')->where('company_id', $company)->where('active', true)->orderBy('name')->get(['id', 'name']);
        $users = DB::table('users')->pluck('name', 'id');

        return ApiResponse::ok($stores->map(function ($s) use ($users, $company) {
            $last = KnitNeedleClosing::query()->where('book', 'NEEDLE')->where('store_id', $s->id)->orderByDesc('period')->first();

            return ['store_id' => $s->id, 'store' => $s->name, 'company' => DB::table('companies')->where('id', $company)->value('name'),
                'last_closed_by' => $last ? ($users[$last->closed_by] ?? null) : null, 'last_period' => $last?->period, 'next_period' => $this->nextPeriod($s->id),
                'lines_struck' => $last?->lines_struck, 'closed_at' => $last?->closed_at?->toDateTimeString(), 'status' => $last ? 'CLOSED' : 'NEVER'];
        })->values()->all());
    }

    public function closingExceptions(Request $request): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $f = $this->closingInput($request);
        $out = [];
        foreach ($f['stores'] as $s) {
            $period = $f['period'] ?? $this->nextPeriod($s->id);
            foreach (NeedleStore::exceptions($s->id, $period) as $e) {
                $out[] = $e + ['store' => $s->name, 'period' => $period];
            }
        }

        return ApiResponse::ok($out);
    }

    /** Start Closing: strikes each store's month if nothing stops it; a store with unfinished paper is skipped. */
    public function close(Request $request): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $f = $this->closingInput($request);
        $closed = [];
        $skipped = [];
        foreach ($f['stores'] as $s) {
            $period = $f['period'] ?? $this->nextPeriod($s->id);
            $last = KnitNeedleClosing::query()->where('book', 'NEEDLE')->where('store_id', $s->id)->max('period');
            if ($last && $period <= $last) {
                $skipped[] = ['store' => $s->name, 'period' => $period, 'reason' => __('knitting.nd.v.already_closed')];

                continue;
            }
            if (NeedleStore::exceptions($s->id, $period) !== []) {
                $skipped[] = ['store' => $s->name, 'period' => $period, 'reason' => __('knitting.nd.v.has_exceptions')];

                continue;
            }
            $from = CarbonImmutable::parse("{$period}-01");
            $n = KnitNeedleMove::query()->where('book', 'NEEDLE')->where('store_id', $s->id)->whereDate('move_date', '>=', $from->toDateString())->whereDate('move_date', '<=', $from->endOfMonth()->toDateString())->count();
            KnitNeedleClosing::query()->create(['book' => 'NEEDLE', 'store_id' => $s->id, 'period' => $period, 'lines_struck' => $n, 'closed_by' => $this->caller()->auditUserId(), 'closed_at' => now()]);
            $closed[] = ['store' => $s->name, 'period' => $period, 'lines' => $n];
        }
        if ($closed === [] && $skipped !== []) {
            throw ValidationException::withMessages(['store_id' => collect($skipped)->map(fn ($s) => "{$s['store']} ({$s['period']}): {$s['reason']}")->implode(' · ')]);
        }

        return ApiResponse::ok(['closed' => $closed, 'skipped' => $skipped]);
    }

    /* ---------------- helpers ---------------- */

    /** Which book a loan position belongs to: NEEDLE unless the screen says OIL. */
    private function book(Request $request): string
    {
        return strtoupper((string) $request->input('book', 'NEEDLE')) === 'OIL' ? 'OIL' : 'NEEDLE';
    }

    /** @return array<string, mixed> */
    private function lineRules(array $qty): array
    {
        $company = app(TenantContext::class)->companyId();

        return ['lines' => ['present', 'array', 'max:300'], 'lines.*.item_id' => ['required', Rule::exists('gs_items', 'id')->where('company_id', $company)],
            'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($company)], 'lines.*.remarks' => ['nullable', 'string', 'max:300']]
            + collect($qty)->mapWithKeys(fn ($r, $k) => ["lines.*.{$k}" => $r])->all();
    }

    private function needLines(array $data, string $msg): array
    {
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __("knitting.nd.v.{$msg}")]);
        }

        return $lines;
    }

    private function assertStatus(string $no, string $status, array $from, string $action, string $group): void
    {
        if (! in_array($status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.nd.v.cannot', [
                'no' => $no, 'status' => __("knitting.nd.{$group}.{$status}"), 'action' => __("knitting.nd.action.{$action}"),
            ])]);
        }
    }

    private function obNo(string $storeId, string $date): string
    {
        return 'OB-'.(DB::table('stores')->where('id', $storeId)->value('code') ?? 'ST').'-'.$date;
    }

    /** @return array<string, mixed> */
    private function sheet(string $storeId, string $date, ?string $categoryId): array
    {
        $company = app(TenantContext::class)->companyId();
        $have = KnitNeedleOpening::query()->where('store_id', $storeId)->whereDate('ob_date', $date)->get()->keyBy('item_id');
        $units = DB::table('units_of_measure')->where(fn ($q) => $q->where('company_id', $company)->orWhereNull('company_id'))->pluck('code', 'id');
        $cats = DB::table('item_categories')->where('company_id', $company)->pluck('name', 'id');
        $items = DB::table('gs_items')->where('company_id', $company)->where(fn ($q) => $q->where('active', true)->orWhereIn('id', $have->keys()))
            ->when($categoryId, fn ($q) => $q->where('gs_category_id', $categoryId))->orderBy('item_code')->get();

        return ['store_id' => $storeId, 'ob_date' => $date, 'finalized' => $have->contains(fn ($o) => $o->finalized),
            'lines' => $items->map(fn ($i) => ['item_id' => $i->id, 'item_code' => $i->item_code, 'item' => $i->name, 'category' => $cats[$i->gs_category_id] ?? null,
                'uom' => $units[$i->gs_unit_id] ?? null, 'qty' => isset($have[$i->id]) ? round($have[$i->id]->qty, 3) : 0.0,
                'rate' => isset($have[$i->id]) ? round($have[$i->id]->rate, 4) : ($i->default_price !== null ? round((float) $i->default_price, 4) : 0.0)])->values()->all()];
    }

    /** @return Collection<string, float> */
    private function returned($lineIds, ?string $exceptReturnId = null)
    {
        $live = KnitNeedleReturn::query()->where('status', '!=', 'CANCELLED')->when($exceptReturnId, fn ($q) => $q->whereKeyNot($exceptReturnId))->select('id');

        return KnitNeedleReturnLine::query()->whereIn('receipt_line_id', collect($lineIds)->values())->whereIn('return_id', $live)
            ->groupBy('receipt_line_id')->selectRaw('receipt_line_id, sum(qty) as q')->pluck('q', 'receipt_line_id')->map(fn ($q) => round((float) $q, 4));
    }

    /** Left blank, a return is numbered after its receipt: NMR-…-R01. */
    private function returnNo(string $mrrNo): string
    {
        $n = KnitNeedleReturn::query()->where('challan_no', 'like', $mrrNo.'-R%')->count();

        return $mrrNo.'-R'.str_pad((string) ($n + 1), 2, '0', STR_PAD_LEFT);
    }

    private function nextPeriod(string $storeId): string
    {
        $last = KnitNeedleClosing::query()->where('book', 'NEEDLE')->where('store_id', $storeId)->max('period');
        if ($last) {
            return CarbonImmutable::parse("{$last}-01")->addMonth()->format('Y-m');
        }
        $first = KnitNeedleMove::query()->where('book', 'NEEDLE')->where('store_id', $storeId)->min('move_date');

        return CarbonImmutable::parse($first ?? now())->format('Y-m');
    }

    /** @return array{stores: Collection<int, object>, period: ?string} */
    private function closingInput(Request $request): array
    {
        $company = app(TenantContext::class)->companyId();
        $f = $request->validate(['store_id' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $company)], 'date' => ['nullable', 'date']]);

        return ['stores' => DB::table('stores')->where('company_id', $company)->where('active', true)->when($f['store_id'] ?? null, fn ($q, $id) => $q->where('id', $id))->orderBy('name')->get(['id', 'name']),
            'period' => ! empty($f['date']) ? CarbonImmutable::parse($f['date'])->format('Y-m') : null];
    }

    /** @return array<string, mixed> */
    private function presentReceipt(KnitNeedleReceipt $r): array
    {
        $lines = KnitNeedleReceiptLine::query()->where('receipt_id', $r->id)->orderBy('sort_order')->get();
        $back = $this->returned($lines->pluck('id'));
        [$items, $stores] = NeedleStore::names();
        $cats = DB::table('gs_items')->whereIn('id', $lines->pluck('item_id'))->pluck('gs_category_id', 'id');
        $party = DB::table('mp_suppliers')->where('id', $r->supplier_id)->first(['name', 'address']);

        return collect($r->only(['id', 'mrr_no', 'store_id', 'req_type', 'supplier_id', 'challan_no', 'source', 'pay_method', 'lc_no', 'remarks', 'status']))->all() + [
            'mrr_date' => $r->mrr_date->toDateString(), 'challan_date' => $r->challan_date?->toDateString(), 'store' => $stores[$r->store_id] ?? null,
            'supplier' => $party?->name, 'supplier_address' => $party?->address, 'company' => DB::table('companies')->where('id', $r->company_id)->value('name'),
            'lines' => $lines->map(fn (KnitNeedleReceiptLine $l) => collect($l->only(['item_id', 'uom', 'remarks']))->all() + [
                'category_id' => $cats[$l->item_id] ?? null, 'item' => $items[$l->item_id] ?? null, 'challan_qty' => round($l->challan_qty, 3), 'received_qty' => round($l->received_qty, 3),
                'rate' => round($l->rate, 4), 'amount' => round($l->amount, 2), 'returned' => round((float) ($back[$l->id] ?? 0), 3)])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentReturn(KnitNeedleReturn $r): array
    {
        $lines = KnitNeedleReturnLine::query()->where('return_id', $r->id)->orderBy('sort_order')->get();
        $src = KnitNeedleReceiptLine::query()->whereIn('id', $lines->pluck('receipt_line_id'))->get()->keyBy('id');
        $back = $this->returned($lines->pluck('receipt_line_id'), $r->id);
        [$items, $stores] = NeedleStore::names();
        $party = DB::table('mp_suppliers')->where('id', $r->supplier_id)->first(['name', 'address']);

        return collect($r->only(['id', 'challan_no', 'challan_type', 'store_id', 'supplier_id', 'gate_pass_no', 'vehicle_no', 'reason', 'status']))->all() + [
            'challan_date' => $r->challan_date->toDateString(), 'mrr_no' => KnitNeedleReceipt::query()->whereKey($r->receipt_id)->value('mrr_no'), 'store' => $stores[$r->store_id] ?? null,
            'supplier' => $party?->name, 'supplier_address' => $party?->address, 'company' => DB::table('companies')->where('id', $r->company_id)->value('name'),
            'lines' => $lines->map(fn (KnitNeedleReturnLine $l) => ['receipt_line_id' => $l->receipt_line_id, 'item_id' => $l->item_id, 'item' => $items[$l->item_id] ?? null, 'uom' => $l->uom,
                'received' => round((float) ($src[$l->receipt_line_id]->received_qty ?? 0), 3), 'back' => round((float) ($back[$l->receipt_line_id] ?? 0), 3),
                'outstanding' => round((float) ($src[$l->receipt_line_id]->received_qty ?? 0) - (float) ($back[$l->receipt_line_id] ?? 0), 3),
                'qty' => round($l->qty, 3), 'rate' => round($l->rate, 4), 'amount' => round($l->amount, 2), 'remarks' => $l->remarks])->values()->all(),
        ];
    }
}

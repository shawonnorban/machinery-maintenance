<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitNeedleLoanReceipt;
use App\Modules\Knitting\Models\KnitNeedleLoanReceiptLine;
use App\Modules\Knitting\Models\KnitNeedleReceipt;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\NeedleStore;
use App\Modules\Knitting\Support\YarnStore;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Loan Rcv from Party (AnnotechERP 15/09), Borrowed Needle
 * / Oil Receipt (NLB- / OLB-…): what a party lends the factory, received on
 * the store's own MRR. Saving writes a draft; Submit → Verify (posted to
 * the book) → Send to Accounts → Accounts Approve; Cancel before it posts.
 * Returned is what has gone back to that party on its loan-return indents,
 * set against its receipts oldest first; Outstanding is the rest.
 */
class KnitLoanReceiptApiController extends ApiController
{
    private const BOOKS = ['needle' => 'NEEDLE', 'oil' => 'OIL'];

    private const PREFIX = ['NEEDLE' => 'NLB', 'OIL' => 'OLB'];

    public function index(string $book): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $b = $this->book($book);
        $rows = KnitNeedleLoanReceipt::query()->where('book', $b)->orderByDesc('mrr_date')->orderByDesc('mrr_no')->limit(500)->get();
        $lines = KnitNeedleLoanReceiptLine::query()->whereIn('receipt_id', $rows->pluck('id'))->get();
        $back = $this->returnedByLine($b, $rows->pluck('party_id')->unique());
        $byRec = $lines->groupBy('receipt_id');
        $parties = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('party_id'))->pluck('name', 'id');
        [, $stores] = NeedleStore::names();

        return ApiResponse::ok($rows->map(function (KnitNeedleLoanReceipt $r) use ($byRec, $back, $parties, $stores) {
            $mine = $byRec[$r->id] ?? collect();
            $returned = (float) $mine->sum(fn ($l) => $back[$l->id] ?? 0);

            return ['mrr_no' => $r->mrr_no, 'mrr_date' => $r->mrr_date->toDateString(), 'party_id' => $r->party_id, 'party' => $parties[$r->party_id] ?? null,
                'store_id' => $r->store_id, 'store' => $stores[$r->store_id] ?? null, 'pay_method' => $r->pay_method, 'challan_no' => $r->challan_no,
                'lines' => $mine->count(), 'receive_qty' => round((float) $mine->sum('receive_qty'), 3), 'returned' => round($returned, 3),
                'outstanding' => round((float) $mine->sum('receive_qty') - $returned, 3), 'status' => $r->status];
        })->values()->all());
    }

    public function show(string $book, string $no): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');

        return ApiResponse::ok($this->present($this->doc($book, $no)));
    }

    public function save(Request $request, string $book, ?string $no = null): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $b = $this->book($book);
        $row = $no ? $this->doc($book, $no) : null;
        $row && $this->assertStatus($row->mrr_no, $row->status, ['DRAFT'], 'save');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'mrr_date' => ['required', 'date'], 'req_type' => ['nullable', Rule::in(YarnStore::REQ_TYPES)],
            'party_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'pay_method' => ['required', Rule::in(KnitNeedleReceipt::PAY_METHODS)], 'source' => ['required', 'string', 'max:80'],
            'challan_no' => ['nullable', 'string', 'max:60'], 'challan_date' => ['nullable', 'date'], 'lc_no' => ['nullable', 'string', 'max:60'],
            'remarks' => ['nullable', 'string', 'max:2000'], 'lines' => ['present', 'array', 'max:300'],
            'lines.*.item_id' => ['required', Rule::exists('gs_items', 'id')->where('company_id', $company)], 'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($company)],
            'lines.*.receive_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'mrr_date.required' => __('knitting.nd.v.mrr_date'), 'party_id.required' => __('knitting.nd.v.party'), 'store_id.required' => __('knitting.nd.v.receiving_store'),
            'pay_method.required' => __('knitting.nd.v.pay_method'), 'source.required' => __('knitting.nd.v.source'),
            'lines.*.item_id.required' => __('knitting.nd.v.line_item'), 'lines.*.receive_qty.required' => __('knitting.nd.v.line_qty'),
            'lines.*.receive_qty.gt' => __('knitting.nd.v.line_qty'),
        ]);
        if (! empty($data['lc_no']) && $data['pay_method'] !== 'LC') {
            throw ValidationException::withMessages(['lc_no' => __('knitting.nd.v.lc_only')]);
        }
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __($b === 'OIL' ? 'knitting.nd.v.no_oil_receipt_lines' : 'knitting.nd.v.no_loan_receipt_lines')]);
        }
        $row = DB::transaction(function () use ($row, $data, $lines, $b): KnitNeedleLoanReceipt {
            $head = collect($data)->except('lines')->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['req_type' => null, 'challan_no' => null, 'challan_date' => null, 'lc_no' => null, 'remarks' => null];
            $row ??= KnitNeedleLoanReceipt::query()->create($head + ['book' => $b, 'mrr_no' => KnitProduction::nextNumber(self::PREFIX[$b], 'knit_needle_loan_receipts', 'mrr_no', $data['mrr_date']),
                'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitNeedleLoanReceiptLine::query()->where('receipt_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitNeedleLoanReceiptLine::query()->create(['company_id' => $row->company_id, 'receipt_id' => $row->id, 'sort_order' => $i + 1, 'item_id' => $l['item_id'],
                    'uom' => UomCodes::normalize($l['uom'] ?? null) ?: null, 'receive_qty' => round((float) $l['receive_qty'], 4),
                    'rate' => round((float) ($l['rate'] ?? 0), 4), 'remarks' => ($l['remarks'] ?? '') ?: null]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->present($row->refresh())) : ApiResponse::created($this->present($row->refresh()));
    }

    public function transition(string $book, string $no, string $action): JsonResponse
    {
        $this->allow('knitting.needle.manage');
        $row = $this->doc($book, $no);
        [$from, $to] = match ($action) {
            'submit' => [['DRAFT'], 'WAITING'],
            'verify' => [['WAITING'], 'VERIFIED'],
            'accounts' => [['VERIFIED'], 'WAIT_ACCOUNTS'],
            'approve' => [['WAIT_ACCOUNTS'], 'APPROVED'],
            'cancel' => [['DRAFT', 'WAITING'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row->mrr_no, $row->status, $from, $action);
        DB::transaction(function () use ($row, $action, $to): void {
            if ($action === 'verify') {
                // Verify is the store counting the borrowed stock in: it posts.
                NeedleStore::assertOpen($row->store_id, $row->mrr_date->toDateString(), 'status', $row->book);
                NeedleStore::post('LOAN_IN', $row->mrr_no, $row->mrr_date->toDateString(), KnitNeedleLoanReceiptLine::query()->where('receipt_id', $row->id)->get()
                    ->map(fn (KnitNeedleLoanReceiptLine $l) => ['store_id' => $row->store_id, 'item_id' => $l->item_id, 'qty' => $l->receive_qty,
                        'value' => $l->receive_qty * $l->rate, 'doc_line_id' => $l->id])->all(), $row->book);
                $row->update(['verified_at' => now()]);
            }
            if ($action === 'approve') {
                $row->update(['approved_at' => now(), 'approved_by' => $this->caller()->auditUserId()]);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /* ---------------- helpers ---------------- */

    private function book(string $slug): string
    {
        return self::BOOKS[$slug] ?? abort(404);
    }

    private function doc(string $book, string $no): KnitNeedleLoanReceipt
    {
        return KnitNeedleLoanReceipt::query()->where('book', $this->book($book))->where('mrr_no', $no)->firstOrFail();
    }

    private function assertStatus(string $no, string $status, array $from, string $action): void
    {
        if (! in_array($status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.nd.v.cannot', [
                'no' => $no, 'status' => __("knitting.nd.receipt_status.{$status}"), 'action' => __("knitting.nd.action.{$action}"),
            ])]);
        }
    }

    /**
     * What has gone back on each posted receipt line: a party's returns per
     * item, set against its receipts oldest first (the opening position's
     * Loan Received counted before any of them).
     *
     * @return Collection<string, float>
     */
    private function returnedByLine(string $book, $partyIds): Collection
    {
        $out = collect();
        foreach (collect($partyIds)->filter() as $party) {
            $left = NeedleStore::returnedTo($book, $party)->all();
            foreach (DB::table('knit_needle_loan_openings')->where('book', $book)->where('party_id', $party)->where('loan_received', '>', 0)->get() as $o) {
                $left[$o->item_id] = max(0, (float) ($left[$o->item_id] ?? 0) - (float) $o->loan_received);
            }
            $receipts = KnitNeedleLoanReceipt::query()->where('book', $book)->where('party_id', $party)->whereIn('status', KnitNeedleLoanReceipt::POSTED)
                ->orderBy('mrr_date')->orderBy('mrr_no')->pluck('id');
            $lines = KnitNeedleLoanReceiptLine::query()->whereIn('receipt_id', $receipts)->get()
                ->sortBy(fn ($l) => [$receipts->search($l->receipt_id), $l->sort_order]);
            foreach ($lines as $l) {
                $take = min($l->receive_qty, (float) ($left[$l->item_id] ?? 0));
                $out[$l->id] = round($take, 4);
                $left[$l->item_id] = (float) ($left[$l->item_id] ?? 0) - $take;
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function present(KnitNeedleLoanReceipt $r): array
    {
        $lines = KnitNeedleLoanReceiptLine::query()->where('receipt_id', $r->id)->orderBy('sort_order')->get();
        $back = $this->returnedByLine($r->book, [$r->party_id]);
        [$items, $stores] = NeedleStore::names();
        $cats = DB::table('gs_items')->whereIn('id', $lines->pluck('item_id'))->pluck('gs_category_id', 'id');
        $party = DB::table('mp_suppliers')->where('id', $r->party_id)->first(['name', 'address']);

        return collect($r->only(['id', 'book', 'mrr_no', 'req_type', 'party_id', 'store_id', 'pay_method', 'source', 'challan_no', 'lc_no', 'remarks', 'status']))->all() + [
            'mrr_date' => $r->mrr_date->toDateString(), 'challan_date' => $r->challan_date?->toDateString(), 'party' => $party?->name, 'party_address' => $party?->address,
            'store' => $stores[$r->store_id] ?? null, 'company' => DB::table('companies')->where('id', $r->company_id)->value('name'),
            'lines' => $lines->map(fn (KnitNeedleLoanReceiptLine $l) => collect($l->only(['item_id', 'uom', 'remarks']))->all() + [
                'category_id' => $cats[$l->item_id] ?? null, 'item' => $items[$l->item_id] ?? null, 'receive_qty' => round($l->receive_qty, 3),
                'returned_qty' => round((float) ($back[$l->id] ?? 0), 3), 'rate' => round($l->rate, 4)])->values()->all(),
        ];
    }
}

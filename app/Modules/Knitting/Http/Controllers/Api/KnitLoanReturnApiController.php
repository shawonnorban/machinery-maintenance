<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\KnitNeedleLoanReq;
use App\Modules\Knitting\Models\KnitNeedleLoanReqLine;
use App\Modules\Knitting\Models\KnitNeedleLoanReturn;
use App\Modules\Knitting\Models\KnitNeedleLoanReturnLine;
use App\Modules\Knitting\Support\KnitProduction;
use App\Modules\Knitting\Support\NeedleStore;
use App\Modules\Knitting\Support\YarnStore;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Loan Issue to Party (AnnotechERP 15/08), Needle / Oil
 * Loan Return from Party (NLT- / OLT-…): what a party hands back against a
 * loan issued to it, on its own challan. Saving writes a draft; Receive at
 * Store counts it in (Received Qty filled from the paper where left at
 * zero), dates it, posts it to the book and takes it off what the party
 * owes on the loan indent; Finalize, or Cancel (taking a received one back
 * off the book). A challan naming an indent pays back only what that
 * indent issued and has not had back.
 */
class KnitLoanReturnApiController extends ApiController
{
    private const BOOKS = ['needle' => 'NEEDLE', 'oil' => 'OIL'];

    private const PREFIX = ['NEEDLE' => 'NLT', 'OIL' => 'OLT'];

    public function index(string $book): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $rows = KnitNeedleLoanReturn::query()->where('book', $this->book($book))->orderByDesc('ret_challan_date')->orderByDesc('return_no')->limit(500)->get();
        $sums = KnitNeedleLoanReturnLine::query()->whereIn('return_id', $rows->pluck('id'))->groupBy('return_id')
            ->selectRaw('return_id, count(*) as n, sum(return_qty) as r, sum(received_qty) as c')->get()->keyBy('return_id');
        $parties = DB::table('mp_suppliers')->whereIn('id', $rows->pluck('party_id'))->pluck('name', 'id');
        $reqs = KnitNeedleLoanReq::query()->whereIn('id', $rows->pluck('req_id')->filter())->pluck('req_no', 'id');
        [, $stores] = NeedleStore::names();

        return ApiResponse::ok($rows->map(fn (KnitNeedleLoanReturn $r) => [
            'return_no' => $r->return_no, 'ret_challan_no' => $r->ret_challan_no, 'ret_challan_date' => $r->ret_challan_date->toDateString(),
            'rcv_date' => $r->rcv_date?->toDateString(), 'req_no' => $reqs[$r->req_id] ?? null, 'party_id' => $r->party_id, 'party' => $parties[$r->party_id] ?? null,
            'store_id' => $r->store_id, 'store' => $stores[$r->store_id] ?? null, 'lines' => (int) ($sums[$r->id]->n ?? 0),
            'return_qty' => round((float) ($sums[$r->id]->r ?? 0), 3), 'received_qty' => round((float) ($sums[$r->id]->c ?? 0), 3), 'status' => $r->status,
        ])->values()->all());
    }

    /** Against Requisition: the book's verified loan issues a party still owes on, line by line. */
    public function owing(string $book): JsonResponse
    {
        $this->allowEither('knitting.needle.view', 'knitting.needle.manage');
        $reqs = KnitNeedleLoanReq::query()->where('book', $this->book($book))->where('kind', 'LOAN_ISSUE')
            ->whereIn('status', ['PARTLY_ISSUED', 'ISSUED', 'CLOSED'])->orderByDesc('req_date')->get();
        $lines = KnitNeedleLoanReqLine::query()->whereIn('req_id', $reqs->pluck('id'))->orderBy('sort_order')->get();
        $back = NeedleStore::loanReturned($lines->pluck('id'), null, false);
        [$items] = NeedleStore::names();
        $cats = DB::table('gs_items')->whereIn('id', $lines->pluck('item_id'))->pluck('gs_category_id', 'id');
        $byReq = $lines->groupBy('req_id');

        return ApiResponse::ok($reqs->map(function (KnitNeedleLoanReq $r) use ($byReq, $back, $items, $cats) {
            $mine = ($byReq[$r->id] ?? collect())->map(fn (KnitNeedleLoanReqLine $l) => ['req_line_id' => $l->id, 'item_id' => $l->item_id, 'item' => $items[$l->item_id] ?? null,
                'category_id' => $cats[$l->item_id] ?? null, 'uom' => $l->uom, 'rate' => round($l->rate, 4), 'issued' => round($l->issue_qty, 3),
                'owes' => round($l->issue_qty - (float) ($back[$l->id] ?? 0), 3)])->filter(fn ($l) => $l['owes'] > 0)->values();

            return ['value' => $r->req_no, 'label' => $r->req_no.' · '.$r->req_date->format('d M Y'), 'party_id' => $r->party_id, 'store_id' => $r->store_id, 'lines' => $mine->all()];
        })->filter(fn ($r) => $r['lines'] !== [])->values()->all());
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
        $row && $this->assertStatus($row->return_no, $row->status, ['DRAFT'], 'save');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'ret_challan_no' => ['required', 'string', 'max:60'], 'ret_challan_date' => ['required', 'date'], 'req_no' => ['nullable', 'string', 'max:24'],
            'party_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'received_by' => ['nullable', Rule::exists('hr_employees', 'id')->where('company_id', $company)],
            'gate_pass_no' => ['nullable', 'string', 'max:40'], 'vehicle_no' => ['nullable', 'string', 'max:40'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:300'], 'lines.*.item_id' => ['required', Rule::exists('gs_items', 'id')->where('company_id', $company)],
            'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($company)], 'lines.*.return_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.received_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'ret_challan_no.required' => __('knitting.nd.v.ret_challan'), 'ret_challan_date.required' => __('knitting.nd.v.challan_date'),
            'party_id.required' => __('knitting.nd.v.party'), 'store_id.required' => __('knitting.nd.v.receiving_store'),
            'lines.*.item_id.required' => __('knitting.nd.v.line_item'), 'lines.*.return_qty.required' => __('knitting.nd.v.line_qty'),
            'lines.*.return_qty.gt' => __('knitting.nd.v.line_qty'),
        ]);
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __($b === 'OIL' ? 'knitting.nd.v.no_oil_return_lines' : 'knitting.nd.v.no_loan_return_lines')]);
        }
        if (KnitNeedleLoanReturn::query()->where('book', $b)->where('party_id', $data['party_id'])->where('ret_challan_no', $data['ret_challan_no'])
            ->when($row, fn ($q) => $q->whereKeyNot($row->id))->exists()) {
            throw ValidationException::withMessages(['ret_challan_no' => __('knitting.nd.v.ret_challan_taken', ['no' => $data['ret_challan_no']])]);
        }
        $req = null;
        $reqLines = collect();
        if (! empty($data['req_no'])) {
            $req = KnitNeedleLoanReq::query()->where('book', $b)->where('kind', 'LOAN_ISSUE')->where('req_no', $data['req_no'])->first();
            if (! $req || $req->party_id !== $data['party_id'] || ! in_array($req->status, ['PARTLY_ISSUED', 'ISSUED', 'CLOSED'], true)) {
                throw ValidationException::withMessages(['req_no' => __('knitting.nd.v.loan_req')]);
            }
            $reqLines = KnitNeedleLoanReqLine::query()->where('req_id', $req->id)->orderBy('sort_order')->get();
        }
        $back = NeedleStore::loanReturned($reqLines->pluck('id'), $row?->id, false);
        [$items] = NeedleStore::names();
        $taking = [];
        $out = [];
        foreach ($lines as $i => $l) {
            $n = $i + 1;
            $qty = round((float) $l['return_qty'], 4);
            $rl = null;
            if ($req) {
                // Matched to the indent's line for the same item; never more than it still owes.
                $rl = $reqLines->first(fn ($x) => $x->item_id === $l['item_id']);
                if (! $rl) {
                    throw ValidationException::withMessages(["lines.{$i}.item_id" => __('knitting.nd.v.loan_item', ['n' => $n, 'no' => $req->req_no])]);
                }
                $taking[$rl->id] = ($taking[$rl->id] ?? 0) + $qty;
                $owes = round($rl->issue_qty - (float) ($back[$rl->id] ?? 0), 4);
                if ($taking[$rl->id] > $owes + 0.00005) {
                    throw ValidationException::withMessages(["lines.{$i}.return_qty" => __('knitting.nd.v.loan_back_over', ['n' => $n, 'item' => $items[$l['item_id']] ?? '', 'owes' => $owes])]);
                }
            }
            $out[] = ['req_line_id' => $rl?->id, 'item_id' => $l['item_id'], 'uom' => UomCodes::normalize($l['uom'] ?? null) ?: ($rl?->uom),
                'return_qty' => $qty, 'received_qty' => round((float) ($l['received_qty'] ?? 0), 4), 'rate' => round((float) ($l['rate'] ?? ($rl?->rate ?? 0)), 4),
                'remarks' => ($l['remarks'] ?? '') ?: null];
        }
        $row = DB::transaction(function () use ($row, $data, $out, $req, $b): KnitNeedleLoanReturn {
            $head = ['ret_challan_no' => trim($data['ret_challan_no']), 'ret_challan_date' => $data['ret_challan_date'], 'req_id' => $req?->id, 'party_id' => $data['party_id'],
                'store_id' => $data['store_id'], 'received_by' => ($data['received_by'] ?? '') ?: null, 'gate_pass_no' => ($data['gate_pass_no'] ?? '') ?: null,
                'vehicle_no' => ($data['vehicle_no'] ?? '') ?: null, 'remarks' => ($data['remarks'] ?? '') ?: null];
            $row ??= KnitNeedleLoanReturn::query()->create($head + ['book' => $b, 'return_no' => KnitProduction::nextNumber(self::PREFIX[$b], 'knit_needle_loan_returns', 'return_no', $data['ret_challan_date']),
                'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            KnitNeedleLoanReturnLine::query()->where('return_id', $row->id)->delete();
            foreach ($out as $i => $l) {
                KnitNeedleLoanReturnLine::query()->create($l + ['company_id' => $row->company_id, 'return_id' => $row->id, 'sort_order' => $i + 1]);
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
            'receive' => [['DRAFT'], 'RECEIVED'],
            'finalize' => [['RECEIVED'], 'FINALIZED'],
            'cancel' => [['DRAFT', 'RECEIVED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row->return_no, $row->status, $from, $action);
        DB::transaction(function () use ($row, $action, $to): void {
            $today = now()->toDateString();
            if ($action === 'receive') {
                if (! $row->received_by) {
                    throw ValidationException::withMessages(['received_by' => __('knitting.nd.v.received_by')]);
                }
                NeedleStore::assertOpen($row->store_id, $today, 'status', $row->book);
                $lines = KnitNeedleLoanReturnLine::query()->where('return_id', $row->id)->get();
                foreach ($lines as $l) {
                    // Left at zero, Received Qty is the paper's.
                    if ($l->received_qty <= 0) {
                        $l->update(['received_qty' => $l->return_qty]);
                    }
                }
                NeedleStore::post('LOAN_BACK', $row->return_no, $today, $lines->map(fn (KnitNeedleLoanReturnLine $l) => [
                    'store_id' => $row->store_id, 'item_id' => $l->item_id, 'qty' => $l->received_qty, 'value' => $l->received_qty * $l->rate, 'doc_line_id' => $l->id])->all(), $row->book);
                $row->update(['rcv_date' => $today]);
            }
            if ($action === 'cancel' && $row->status === 'RECEIVED') {
                NeedleStore::assertOpen($row->store_id, $row->rcv_date->toDateString(), 'status', $row->book);
                NeedleStore::unpost('LOAN_BACK', $row->return_no);
                $row->update(['rcv_date' => null]);
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

    private function doc(string $book, string $no): KnitNeedleLoanReturn
    {
        return KnitNeedleLoanReturn::query()->where('book', $this->book($book))->where('return_no', $no)->firstOrFail();
    }

    private function assertStatus(string $no, string $status, array $from, string $action): void
    {
        if (! in_array($status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.nd.v.cannot', [
                'no' => $no, 'status' => __("knitting.nd.loan_return_status.{$status}"), 'action' => __("knitting.nd.action.{$action}"),
            ])]);
        }
    }

    /** @return array<string, mixed> */
    private function present(KnitNeedleLoanReturn $r): array
    {
        $lines = KnitNeedleLoanReturnLine::query()->where('return_id', $r->id)->orderBy('sort_order')->get();
        [$items, $stores] = NeedleStore::names();
        $cats = DB::table('gs_items')->whereIn('id', $lines->pluck('item_id'))->pluck('gs_category_id', 'id');
        $party = DB::table('mp_suppliers')->where('id', $r->party_id)->first(['name', 'address']);

        return collect($r->only(['id', 'book', 'return_no', 'ret_challan_no', 'party_id', 'store_id', 'received_by', 'gate_pass_no', 'vehicle_no', 'remarks', 'status']))->all() + [
            'ret_challan_date' => $r->ret_challan_date->toDateString(), 'rcv_date' => $r->rcv_date?->toDateString(),
            'req_no' => $r->req_id ? KnitNeedleLoanReq::query()->whereKey($r->req_id)->value('req_no') : null, 'party' => $party?->name, 'party_address' => $party?->address,
            'store' => $stores[$r->store_id] ?? null, 'receiver' => $r->received_by ? DB::table('hr_employees')->where('id', $r->received_by)->value('name') : null,
            'company' => DB::table('companies')->where('id', $r->company_id)->value('name'),
            'lines' => $lines->map(fn (KnitNeedleLoanReturnLine $l) => collect($l->only(['req_line_id', 'item_id', 'uom', 'remarks']))->all() + [
                'category_id' => $cats[$l->item_id] ?? null, 'item' => $items[$l->item_id] ?? null, 'return_qty' => round($l->return_qty, 3),
                'received_qty' => round($l->received_qty, 3), 'rate' => round($l->rate, 4)])->values()->all(),
        ];
    }
}

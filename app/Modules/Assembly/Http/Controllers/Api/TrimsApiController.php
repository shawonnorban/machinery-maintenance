<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Http\Controllers\Api;

use App\Modules\Assembly\Models\AsmTrimsIssue;
use App\Modules\Assembly\Models\AsmTrimsIssueLine;
use App\Modules\Assembly\Models\AsmTrimsReceipt;
use App\Modules\Assembly\Models\AsmTrimsReceiptLine;
use App\Modules\Assembly\Models\AsmTrimsReturn;
use App\Modules\Assembly\Models\AsmTrimsReturnLine;
use App\Modules\Assembly\Support\TrimsStore;
use App\Modules\Inventory\Support\UomCodes;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Assembly Floor → Accessories Store (AnnotechERP 20/08), as Brickland keeps
 * it: Trims & Accessories Receipt (MRR) — a store, the supplier, requisition
 * type, payment method, PR and PO, and the items received, counted into
 * stock once Completed; Trims & Accessories Issue — a store, the
 * requisition, the order / style and sewing line, and the items, submitted
 * and then approved (off stock, never more than the store holds) or
 * rejected; and the Trims Receipt Return Register — short, damaged or
 * over-supplied items going back against a completed receipt, never more
 * than it brought in, off stock once Completed. Every paper is addressed by
 * its number and rewritten freely while still a draft.
 */
class TrimsApiController extends ApiController
{
    private ?TrimsStore $store = null;

    /** What the papers pick from: stores, suppliers, trims items, orders, sewing lines, payment methods and return reasons. */
    public function options(): JsonResponse
    {
        $this->allow('assembly.trims.view');
        $n = $this->store()->names();
        $c = $this->caller()->companyId;

        return ApiResponse::ok([
            'company' => $n['company'],
            'stores' => DB::table('stores')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name.($s->code ? ' ('.$s->code.')' : '')])->all(),
            'suppliers' => DB::table('mp_suppliers')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all(),
            'items' => collect($n['items'])->where('active', true)->sortBy('name')->map(fn ($i) => ['value' => $i->id, 'label' => $this->store()->item($i->id), 'uom' => $i->uom, 'description' => $i->description])->values()->all(),
            'orders' => collect($n['orders'])->sortByDesc('order_no')->map(fn ($o) => ['value' => $o->id, 'label' => $this->store()->order($o->id)])->values()->all(),
            'lines' => collect($n['lines'])->sort()->map(fn ($label, $id) => ['value' => $id, 'label' => $label])->values()->all(),
            'pay_methods' => AsmTrimsReceipt::PAY_METHODS,
            'reasons' => AsmTrimsReturn::REASONS,
        ]);
    }

    /** A store's stock of every trims item it holds. */
    public function stock(Request $request): JsonResponse
    {
        $this->allow('assembly.trims.view');
        $q = $request->validate(['store_id' => ['required', 'string', 'max:26']]);

        return ApiResponse::ok(TrimsStore::stock($q['store_id'])->filter(fn ($v) => abs($v) > 0.00005)->map(fn ($v, $id) => ['item_id' => $id, 'item' => $this->store()->item($id), 'qty' => $v])->values()->all());
    }

    /* ── Receipts ─────────────────────────────────────────────────── */

    public function receipts(): JsonResponse
    {
        $this->allow('assembly.trims.view');

        return ApiResponse::ok($this->receiptRows(AsmTrimsReceipt::query()->orderByDesc('mrr_date')->orderByDesc('mrr_no')->get()));
    }

    public function receipt(string $no): JsonResponse
    {
        $this->allow('assembly.trims.view');

        return ApiResponse::ok($this->presentReceipt($this->findReceipt($no)));
    }

    /** A receipt and its items together: new, or rewritten while still a draft. */
    public function saveReceipt(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('assembly.trims.manage');
        $row = $no ? $this->findReceipt($no) : null;
        $this->assertDraft($row?->status, $row?->mrr_no);
        $c = $this->caller()->companyId;
        $data = $request->validate([
            'mrr_date' => ['required', 'date'], 'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $c)],
            'supplier_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $c)], 'req_type' => ['nullable', 'string', 'max:80'],
            'pay_method' => ['required', Rule::in(AsmTrimsReceipt::PAY_METHODS)], 'pr_no' => ['nullable', 'string', 'max:40'], 'po_no' => ['nullable', 'string', 'max:40'],
            'remarks' => ['nullable', 'string', 'max:2000'], ...$this->lineRules(),
        ], $this->messages() + ['mrr_date.required' => __('assembly.v.ta_mrr_date'), 'pay_method.required' => __('assembly.v.ta_pay_method')]);
        ksort($data['lines']);
        $head = $this->head($data, ['mrr_date', 'store_id', 'supplier_id', 'req_type', 'pay_method', 'pr_no', 'po_no', 'remarks']);
        $row = DB::transaction(function () use ($row, $head, $data) {
            if ($row) {
                $row->update($head);
                AsmTrimsReceiptLine::query()->where('receipt_id', $row->id)->delete();
            } else {
                $row = AsmTrimsReceipt::query()->create($head + ['mrr_no' => $this->store()->nextNo('MRR', 'asm_trims_receipts', 'mrr_no', $data['mrr_date']), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            }
            $this->writeLines(AsmTrimsReceiptLine::class, 'receipt_id', $row->id, $data['lines']);

            return $row;
        });

        return ApiResponse::ok($this->presentReceipt($row->refresh()), [], $no ? 200 : 201);
    }

    /** Complete counts the items into the store's stock; Cancel drops a draft. */
    public function receiptAction(string $no, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['complete', 'cancel'], true), 404);
        $this->allow('assembly.trims.manage');
        $row = DB::transaction(function () use ($no, $action) {
            $row = AsmTrimsReceipt::query()->where('mrr_no', $no)->lockForUpdate()->firstOrFail();
            $this->assertDraft($row->status, $row->mrr_no);
            if ($action === 'cancel') {
                $row->update(['status' => 'CANCELLED']);

                return $row;
            }
            $by = $this->caller()->auditUserId();
            foreach (AsmTrimsReceiptLine::query()->where('receipt_id', $row->id)->orderBy('sort_order')->get() as $l) {
                $this->store()->move($row->store_id, $l->item_id, 'RECEIPT', $row->mrr_no, $l->id, $row->mrr_date->toDateString(), (float) $l->qty, (float) $l->rate, $by);
            }
            $row->update(['status' => 'COMPLETED', 'completed_at' => now(), 'completed_by' => $by]);

            return $row;
        });

        return ApiResponse::ok($this->presentReceipt($row));
    }

    /* ── Issues ───────────────────────────────────────────────────── */

    public function issues(): JsonResponse
    {
        $this->allow('assembly.trims.view');

        return ApiResponse::ok($this->issueRows(AsmTrimsIssue::query()->orderByDesc('issue_date')->orderByDesc('issue_no')->get()));
    }

    public function issue(string $no): JsonResponse
    {
        $this->allow('assembly.trims.view');

        return ApiResponse::ok($this->presentIssue($this->findIssue($no)));
    }

    /** An issue and its items together: new, or rewritten while still a draft. */
    public function saveIssue(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('assembly.trims.manage');
        $row = $no ? $this->findIssue($no) : null;
        $this->assertDraft($row?->status, $row?->issue_no);
        $c = $this->caller()->companyId;
        $data = $request->validate([
            'issue_date' => ['required', 'date'], 'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $c)],
            'requisition_no' => ['nullable', 'string', 'max:40'], 'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $c)],
            'line_id' => ['nullable', Rule::exists('planning_lines', 'id')->where('company_id', $c)], 'remarks' => ['nullable', 'string', 'max:2000'], ...$this->lineRules(),
        ], $this->messages() + ['issue_date.required' => __('assembly.v.ta_issue_date')]);
        ksort($data['lines']);
        $head = $this->head($data, ['issue_date', 'store_id', 'requisition_no', 'order_id', 'line_id', 'remarks']);
        $row = DB::transaction(function () use ($row, $head, $data) {
            if ($row) {
                $row->update($head);
                AsmTrimsIssueLine::query()->where('issue_id', $row->id)->delete();
            } else {
                $row = AsmTrimsIssue::query()->create($head + ['issue_no' => $this->store()->nextNo('ISS', 'asm_trims_issues', 'issue_no', $data['issue_date']), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            }
            $this->writeLines(AsmTrimsIssueLine::class, 'issue_id', $row->id, $data['lines']);

            return $row;
        });

        return ApiResponse::ok($this->presentIssue($row->refresh()), [], $no ? 200 : 201);
    }

    /** Submit a draft; Approve takes it off stock (never more than the store holds) or Reject it with the reason — an approver's call. */
    public function issueAction(Request $request, string $no, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['submit', 'approve', 'reject'], true), 404);
        $action === 'submit' ? $this->allow('assembly.trims.manage') : $this->allow('assembly.trims.approve');
        $data = $request->validate(['reason' => [$action === 'reject' ? 'required' : 'nullable', 'string', 'max:300']], ['reason.required' => __('assembly.v.ta_reason')]);
        $row = DB::transaction(function () use ($no, $action, $data) {
            $row = AsmTrimsIssue::query()->where('issue_no', $no)->lockForUpdate()->firstOrFail();
            $from = $action === 'submit' ? 'DRAFT' : 'SUBMITTED';
            if ($row->status !== $from) {
                throw ValidationException::withMessages(['status' => __('assembly.v.ta_status', ['no' => $row->issue_no, 'status' => __('assembly.ta.status.'.$row->status)])]);
            }
            $by = $this->caller()->auditUserId();
            if ($action === 'submit') {
                $row->update(['status' => 'SUBMITTED', 'submitted_at' => now(), 'submitted_by' => $by]);
            } elseif ($action === 'reject') {
                $row->update(['status' => 'REJECTED', 'decided_at' => now(), 'decided_by' => $by, 'reject_reason' => trim($data['reason'])]);
            } else {
                foreach (AsmTrimsIssueLine::query()->where('issue_id', $row->id)->orderBy('sort_order')->get() as $l) {
                    $this->store()->move($row->store_id, $l->item_id, 'ISSUE', $row->issue_no, $l->id, $row->issue_date->toDateString(), -(float) $l->qty, (float) $l->rate, $by);
                }
                $row->update(['status' => 'APPROVED', 'decided_at' => now(), 'decided_by' => $by]);
            }

            return $row;
        });

        return ApiResponse::ok($this->presentIssue($row));
    }

    /* ── Receipt returns ──────────────────────────────────────────── */

    public function returns(): JsonResponse
    {
        $this->allow('assembly.trims.view');

        return ApiResponse::ok($this->returnRows(AsmTrimsReturn::query()->orderByDesc('return_date')->orderByDesc('return_no')->get()));
    }

    public function return(string $no): JsonResponse
    {
        $this->allow('assembly.trims.view');

        return ApiResponse::ok($this->presentReturn($this->findReturn($no)));
    }

    /** A completed receipt's items with what live returns already claim and what is left to send back. */
    public function returnSource(Request $request, string $mrr): JsonResponse
    {
        $this->allow('assembly.trims.view');
        $except = $request->query('except') ? AsmTrimsReturn::query()->where('return_no', $request->query('except'))->value('id') : null;

        return ApiResponse::ok($this->sourceOf($this->countedReceipt($mrr), $except));
    }

    /** A return and its items together: new, or rewritten while still a draft. */
    public function saveReturn(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('assembly.trims.manage');
        $row = $no ? $this->findReturn($no) : null;
        $this->assertDraft($row?->status, $row?->return_no);
        $data = $request->validate([
            'return_date' => ['required', 'date'], 'mrr_no' => ['required', 'string', 'max:24'], 'reason' => ['required', Rule::in(AsmTrimsReturn::REASONS)],
            'remarks' => ['nullable', 'string', 'max:2000'], 'lines' => ['required', 'array', 'min:1', 'max:200'], 'lines.*.receipt_line_id' => ['required', 'string', 'max:26'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ], ['return_date.required' => __('assembly.v.ta_return_date'), 'mrr_no.required' => __('assembly.v.ta_mrr'), 'reason.required' => __('assembly.v.ta_return_reason'),
            'lines.required' => __('assembly.v.ta_lines'), 'lines.min' => __('assembly.v.ta_lines'), 'lines.*.qty.gt' => __('assembly.v.ta_line_qty'), 'lines.*.qty.required' => __('assembly.v.ta_line_qty')]);
        ksort($data['lines']);
        $rec = $this->countedReceipt($data['mrr_no']);
        if ($data['return_date'] < $rec->mrr_date->toDateString()) {
            throw ValidationException::withMessages(['return_date' => __('assembly.v.ta_return_before', ['no' => $rec->mrr_no])]);
        }
        $source = collect($this->sourceOf($rec, $row?->id)['lines'])->keyBy('id');
        $asked = [];
        foreach ($data['lines'] as $k => $l) {
            $src = $source[$l['receipt_line_id']] ?? throw ValidationException::withMessages(["lines.{$k}.receipt_line_id" => __('assembly.v.ta_not_on_mrr', ['no' => $rec->mrr_no])]);
            $asked[$src['id']] = round(($asked[$src['id']] ?? 0) + (float) $l['qty'], 4);
            if ($asked[$src['id']] > $src['returnable'] + 0.00005) {
                throw ValidationException::withMessages(["lines.{$k}.qty" => __('assembly.v.ta_return_more', ['item' => $src['item'], 'left' => TrimsStore::fmt($src['returnable'])])]);
            }
        }
        $row = DB::transaction(function () use ($row, $data, $rec, $source) {
            $head = ['return_date' => $data['return_date'], 'receipt_id' => $rec->id, 'reason' => $data['reason'], 'remarks' => $this->text($data['remarks'] ?? null)];
            if ($row) {
                $row->update($head);
                AsmTrimsReturnLine::query()->where('return_id', $row->id)->delete();
            } else {
                $row = AsmTrimsReturn::query()->create($head + ['return_no' => $this->store()->nextNo('RTN', 'asm_trims_returns', 'return_no', $data['return_date']), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach (array_values($data['lines']) as $i => $l) {
                $src = $source[$l['receipt_line_id']];
                AsmTrimsReturnLine::query()->create(['return_id' => $row->id, 'receipt_line_id' => $src['id'], 'item_id' => $src['item_id'], 'description' => $src['description'], 'uom' => $src['uom'],
                    'qty' => $l['qty'], 'rate' => $src['rate'], 'remarks' => $this->text($l['remarks'] ?? null), 'sort_order' => ($i + 1) * 10]);
            }

            return $row;
        });

        return ApiResponse::ok($this->presentReturn($row->refresh()), [], $no ? 200 : 201);
    }

    /** Complete takes the items off the store's stock; Cancel drops a draft. */
    public function returnAction(string $no, string $action): JsonResponse
    {
        abort_unless(in_array($action, ['complete', 'cancel'], true), 404);
        $this->allow('assembly.trims.manage');
        $row = DB::transaction(function () use ($no, $action) {
            $row = AsmTrimsReturn::query()->where('return_no', $no)->lockForUpdate()->firstOrFail();
            $this->assertDraft($row->status, $row->return_no);
            if ($action === 'cancel') {
                $row->update(['status' => 'CANCELLED']);

                return $row;
            }
            $rec = AsmTrimsReceipt::query()->findOrFail($row->receipt_id);
            $by = $this->caller()->auditUserId();
            foreach (AsmTrimsReturnLine::query()->where('return_id', $row->id)->orderBy('sort_order')->get() as $l) {
                $this->store()->move($rec->store_id, $l->item_id, 'RETURN', $row->return_no, $l->id, $row->return_date->toDateString(), -(float) $l->qty, (float) $l->rate, $by);
            }
            $row->update(['status' => 'COMPLETED', 'completed_at' => now(), 'completed_by' => $by]);

            return $row;
        });

        return ApiResponse::ok($this->presentReturn($row));
    }

    /* ── Shared ───────────────────────────────────────────────────── */

    /** @return array<string, mixed> */
    private function lineRules(): array
    {
        $c = $this->caller()->companyId;

        return [
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.item_id' => ['required', Rule::exists('mp_items', 'id')->where('company_id', $c)->where('kind', 'TRIMS')],
            'lines.*.description' => ['nullable', 'string', 'max:255'], 'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($c)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'store_id.required' => __('assembly.v.ta_store'), 'lines.required' => __('assembly.v.ta_lines'), 'lines.min' => __('assembly.v.ta_lines'),
            'lines.*.item_id.required' => __('assembly.v.ta_line_item'), 'lines.*.qty.required' => __('assembly.v.ta_line_qty'), 'lines.*.qty.gt' => __('assembly.v.ta_line_qty'),
        ];
    }

    private function writeLines(string $model, string $parent, string $parentId, array $lines): void
    {
        foreach (array_values($lines) as $i => $l) {
            $model::query()->create([$parent => $parentId, 'item_id' => $l['item_id'], 'description' => $this->text($l['description'] ?? null), 'uom' => UomCodes::normalize($l['uom'] ?? null),
                'qty' => $l['qty'], 'rate' => (float) ($l['rate'] ?? 0), 'remarks' => $this->text($l['remarks'] ?? null), 'sort_order' => ($i + 1) * 10]);
        }
    }

    private function head(array $data, array $keys): array
    {
        return collect($data)->only($keys)->map(fn ($v) => is_string($v) ? $this->text($v) : $v)->all();
    }

    private function assertDraft(?string $status, ?string $no): void
    {
        if ($status !== null && $status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => __('assembly.v.ta_not_draft', ['no' => $no])]);
        }
    }

    private function findReceipt(string $no): AsmTrimsReceipt
    {
        return AsmTrimsReceipt::query()->where('mrr_no', $no)->firstOrFail();
    }

    private function findIssue(string $no): AsmTrimsIssue
    {
        return AsmTrimsIssue::query()->where('issue_no', $no)->firstOrFail();
    }

    private function findReturn(string $no): AsmTrimsReturn
    {
        return AsmTrimsReturn::query()->where('return_no', $no)->firstOrFail();
    }

    private function countedReceipt(string $no): AsmTrimsReceipt
    {
        $rec = AsmTrimsReceipt::query()->where('mrr_no', $no)->first();
        if (! $rec || $rec->status !== 'COMPLETED') {
            throw ValidationException::withMessages(['mrr_no' => __('assembly.v.ta_mrr_not_done', ['no' => $no])]);
        }

        return $rec;
    }

    /** @return array<string, mixed> */
    private function sourceOf(AsmTrimsReceipt $rec, ?string $exceptReturnId): array
    {
        $lines = AsmTrimsReceiptLine::query()->where('receipt_id', $rec->id)->orderBy('sort_order')->get();
        $live = AsmTrimsReturn::query()->where('receipt_id', $rec->id)->where('status', '!=', 'CANCELLED')->when($exceptReturnId, fn ($q) => $q->whereKeyNot($exceptReturnId))->select('id');
        $claimed = AsmTrimsReturnLine::query()->whereIn('return_id', $live)->groupBy('receipt_line_id')->selectRaw('receipt_line_id, sum(qty) as q')->pluck('q', 'receipt_line_id');
        $n = $this->store()->names();

        return [
            'mrr_no' => $rec->mrr_no, 'mrr_date' => $rec->mrr_date->toDateString(), 'store' => $n['stores'][$rec->store_id] ?? null, 'supplier' => $n['suppliers'][$rec->supplier_id] ?? null,
            'lines' => $lines->map(fn ($l) => [
                'id' => $l->id, 'item_id' => $l->item_id, 'item' => $this->store()->item($l->item_id), 'description' => $l->description, 'uom' => $l->uom, 'rate' => (float) $l->rate,
                'received' => (float) $l->qty, 'returned' => round((float) ($claimed[$l->id] ?? 0), 4), 'returnable' => round((float) $l->qty - (float) ($claimed[$l->id] ?? 0), 4),
            ])->values()->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function receiptRows(Collection $rows): array
    {
        $n = $this->store()->names();
        $sums = AsmTrimsReceiptLine::query()->whereIn('receipt_id', $rows->pluck('id'))->groupBy('receipt_id')->selectRaw('receipt_id, count(*) as items, sum(qty) as qty, sum(qty * rate) as value')->get()->keyBy('receipt_id');

        return $rows->map(fn ($r) => [
            'id' => $r->id, 'mrr_no' => $r->mrr_no, 'mrr_date' => $r->mrr_date->toDateString(), 'company' => $n['company'], 'store_id' => $r->store_id, 'store' => $n['stores'][$r->store_id] ?? null,
            'supplier_id' => $r->supplier_id, 'supplier' => $n['suppliers'][$r->supplier_id] ?? null, 'req_type' => $r->req_type, 'pay_method' => $r->pay_method, 'pr_no' => $r->pr_no, 'po_no' => $r->po_no,
            'remarks' => $r->remarks, 'status' => $r->status, 'items' => (int) ($sums[$r->id]->items ?? 0), 'qty' => round((float) ($sums[$r->id]->qty ?? 0), 3), 'value' => round((float) ($sums[$r->id]->value ?? 0), 2),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function presentReceipt(AsmTrimsReceipt $r): array
    {
        return $this->receiptRows(collect([$r]))[0] + ['lines' => $this->lines(AsmTrimsReceiptLine::query()->where('receipt_id', $r->id)->orderBy('sort_order')->get())];
    }

    /** @return list<array<string, mixed>> */
    private function issueRows(Collection $rows): array
    {
        $n = $this->store()->names();
        $sums = AsmTrimsIssueLine::query()->whereIn('issue_id', $rows->pluck('id'))->groupBy('issue_id')->selectRaw('issue_id, count(*) as items, sum(qty) as qty, sum(qty * rate) as value')->get()->keyBy('issue_id');
        $users = DB::table('users')->whereIn('id', $rows->pluck('decided_by')->filter()->unique())->pluck('name', 'id');

        return $rows->map(fn ($r) => [
            'id' => $r->id, 'issue_no' => $r->issue_no, 'issue_date' => $r->issue_date->toDateString(), 'company' => $n['company'], 'store_id' => $r->store_id, 'store' => $n['stores'][$r->store_id] ?? null,
            'requisition_no' => $r->requisition_no, 'order_id' => $r->order_id, 'order' => $this->store()->order($r->order_id), 'line_id' => $r->line_id, 'line' => $n['lines'][$r->line_id] ?? null,
            'remarks' => $r->remarks, 'status' => $r->status, 'reject_reason' => $r->reject_reason, 'decided_by' => $users[$r->decided_by] ?? null,
            'items' => (int) ($sums[$r->id]->items ?? 0), 'qty' => round((float) ($sums[$r->id]->qty ?? 0), 3), 'value' => round((float) ($sums[$r->id]->value ?? 0), 2),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function presentIssue(AsmTrimsIssue $r): array
    {
        $lines = AsmTrimsIssueLine::query()->where('issue_id', $r->id)->orderBy('sort_order')->get();
        $stock = TrimsStore::stock($r->store_id, $lines->pluck('item_id')->all());

        return $this->issueRows(collect([$r]))[0] + ['lines' => collect($this->lines($lines))->map(fn ($l) => $l + ['in_store' => (float) ($stock[$l['item_id']] ?? 0)])->all()];
    }

    /** @return list<array<string, mixed>> */
    private function returnRows(Collection $rows): array
    {
        $n = $this->store()->names();
        $recs = AsmTrimsReceipt::query()->whereIn('id', $rows->pluck('receipt_id'))->get()->keyBy('id');
        $sums = AsmTrimsReturnLine::query()->whereIn('return_id', $rows->pluck('id'))->groupBy('return_id')->selectRaw('return_id, count(*) as items, sum(qty) as qty')->get()->keyBy('return_id');

        return $rows->map(fn ($r) => [
            'id' => $r->id, 'return_no' => $r->return_no, 'return_date' => $r->return_date->toDateString(), 'mrr_no' => $recs[$r->receipt_id]->mrr_no ?? null,
            'store' => $n['stores'][$recs[$r->receipt_id]->store_id ?? ''] ?? null, 'supplier' => $n['suppliers'][$recs[$r->receipt_id]->supplier_id ?? ''] ?? null,
            'reason' => $r->reason, 'remarks' => $r->remarks, 'status' => $r->status, 'items' => (int) ($sums[$r->id]->items ?? 0), 'qty' => round((float) ($sums[$r->id]->qty ?? 0), 3),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function presentReturn(AsmTrimsReturn $r): array
    {
        return $this->returnRows(collect([$r]))[0] + ['lines' => AsmTrimsReturnLine::query()->where('return_id', $r->id)->orderBy('sort_order')->get()->map(fn ($l) => [
            'receipt_line_id' => $l->receipt_line_id, 'item_id' => $l->item_id, 'item' => $this->store()->item($l->item_id), 'description' => $l->description, 'uom' => $l->uom,
            'qty' => (float) $l->qty, 'remarks' => $l->remarks,
        ])->values()->all()];
    }

    /** @return list<array<string, mixed>> */
    private function lines(Collection $lines): array
    {
        return $lines->map(fn ($l) => ['id' => $l->id, 'item_id' => $l->item_id, 'item' => $this->store()->item($l->item_id), 'description' => $l->description, 'uom' => $l->uom,
            'qty' => (float) $l->qty, 'rate' => (float) $l->rate, 'remarks' => $l->remarks])->values()->all();
    }

    private function text(?string $v): ?string
    {
        return $v !== null && trim($v) !== '' ? trim($v) : null;
    }

    private function store(): TrimsStore
    {
        return $this->store ??= new TrimsStore($this->caller()->companyId);
    }
}

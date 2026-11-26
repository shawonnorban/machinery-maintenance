<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers\Api;

use App\Modules\Accounting\Support\AccountingDefaults;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Finance & Ledger → Cheque Management (AnnotechERP 23/05).
 *
 * - Cheque Issue Entry: a cheque taken off a book registered on the chosen
 *   bank account — an unused, active leaf, which leaves the pool the moment
 *   it is used, so one cheque is never issued twice — made out to a payee,
 *   with what it settles beneath it by control head. The lines together never
 *   come to more than the cheque; a line against a requisition or bill
 *   reference never takes more than that reference has left. The reference
 *   is issued on save (CHQ-2026-000001). Cheque Print is recorded once;
 *   a printed cheque is never removed, only cancelled.
 * - Cheque Cancellation Entry: a leaf taken out of circulation — unused, or
 *   an issued cheque voided — deactivated and kept, so the book's numbering
 *   still reconciles. Removing the cancellation puts the leaf back.
 */
class ChequeApiController extends ApiController
{
    public const TYPES = ['MONEY_REQUISITION', 'BILL_PAYMENT', 'SUPPLIER_PAYMENT', 'ADVANCE', 'SALARY', 'UTILITY', 'OTHERS'];

    public function options(): JsonResponse
    {
        $this->allowEither('accounting.cheque.view', 'accounting.cheque.manage');
        $c = $this->caller()->companyId;
        AccountingDefaults::accounts($c);
        AccountingDefaults::fiscalYears($c);

        return ApiResponse::ok([
            'today' => CarbonImmutable::now('Asia/Dhaka')->toDateString(),
            'companies' => [['value' => $c, 'label' => $this->company()]],
            'accounts' => $this->bankAccounts()->map(fn ($a) => ['value' => $a->id, 'label' => "{$a->account_no} — {$a->account_name}", 'bank' => $a->bank, 'branch' => $a->branch, 'bank_id' => $a->bank_id, 'branch_id' => $a->branch_id])->values()->all(),
            'heads' => DB::table('acc_accounts')->where('company_id', $c)->where('postable', true)->where('active', true)->orderBy('code')->get(['id', 'code', 'name'])->map(fn ($a) => ['value' => $a->id, 'label' => "{$a->code} — {$a->name}"])->all(),
            'years' => DB::table('acc_fiscal_years')->where('company_id', $c)->orderByDesc('fy_start')->get(['id', 'name'])->map(fn ($f) => ['value' => $f->id, 'label' => $f->name])->all(),
            'users' => DB::table('users as u')->join('company_users as cu', 'cu.user_id', '=', 'u.id')->where('cu.company_id', $c)->orderBy('u.name')->get(['u.id', 'u.name'])->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all(),
            'banks' => DB::table('tx_banks')->where('company_id', $c)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($b) => ['value' => $b->id, 'label' => $b->name.($b->code ? " ({$b->code})" : '')])->all(),
            'branches' => DB::table('tx_bank_branches as b')->join('tx_banks as k', 'k.id', '=', 'b.bank_id')->where('b.company_id', $c)->orderBy('k.name')->orderBy('b.name')->get(['b.id', 'b.name', 'b.bank_id', 'k.name as bank'])->map(fn ($b) => ['value' => $b->id, 'label' => "{$b->bank} — {$b->name}", 'bank_id' => $b->bank_id])->all(),
        ]);
    }

    /** The account's unused, active leaves (and the cheque's own leaf when editing). */
    public function leaves(Request $request): JsonResponse
    {
        $this->allowEither('accounting.cheque.view', 'accounting.cheque.manage');
        $d = $request->validate(['account' => ['required', 'string'], 'keep' => ['nullable', 'string']]);

        return ApiResponse::ok($this->leafQuery()->where('b.bank_account_id', $d['account'])->where(fn ($w) => $w->where(fn ($x) => $x->where('l.status', 'UNUSED')->where('l.active', true))->when($d['keep'] ?? null, fn ($x, $v) => $x->orWhere('l.id', $v)))
            ->get()->map(fn ($l) => ['value' => $l->id, 'label' => $l->cheque_no.' · '.$l->book_no])->values()->all());
    }

    /** What a requisition or bill reference has already been settled for, on other cheques not cancelled. */
    public function used(Request $request): JsonResponse
    {
        $this->allowEither('accounting.cheque.view', 'accounting.cheque.manage');
        $d = $request->validate(['ref' => ['required', 'string', 'max:48'], 'except' => ['nullable', 'string']]);

        return ApiResponse::ok(['used' => $this->usedFor($d['ref'], $d['except'] ?? null)]);
    }

    public function index(): JsonResponse
    {
        $this->allowEither('accounting.cheque.view', 'accounting.cheque.manage');

        return ApiResponse::ok($this->rows(DB::table('acc_cheque_issues')->where('company_id', $this->caller()->companyId)->orderByDesc('issue_date')->orderByDesc('issue_ref')->get()));
    }

    public function show(string $id): JsonResponse
    {
        $this->allowEither('accounting.cheque.view', 'accounting.cheque.manage');

        return ApiResponse::ok($this->body($this->issue($id)));
    }

    public function save(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('accounting.cheque.manage');
        $c = $this->caller()->companyId;
        $me = $this->caller()->auditUserId();
        $old = $id ? $this->issue($id) : null;
        if ($old) {
            $this->assertEditable($old);
        }
        $head = Rule::exists('acc_accounts', 'id')->where('company_id', $c)->where('postable', true);
        $d = $request->validate([
            'bank_account_id' => ['required', Rule::exists('acc_bank_accounts', 'id')->where('company_id', $c)],
            'cheque_leaf_id' => ['required', 'string'],
            'issue_date' => ['required', 'date'],
            'place_date' => ['nullable', 'date'],
            'issue_type' => ['required', Rule::in(self::TYPES)],
            'issue_to_head_id' => ['required', $head],
            'pay_to' => ['required', 'string', 'max:160'],
            'issue_for' => ['nullable', 'string', 'max:60'],
            'charges' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'delivered' => ['boolean'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'acc_payee' => ['boolean'],
            'fiscal_year_id' => ['nullable', Rule::exists('acc_fiscal_years', 'id')->where('company_id', $c)],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'narration' => ['nullable', 'string', 'max:2000'],
            'issued_by' => ['nullable', Rule::exists('users', 'id')],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.control_head_id' => ['required', $head],
            'lines.*.issue_for' => ['nullable', 'string', 'max:120'],
            'lines.*.issue_ref' => ['nullable', 'string', 'max:48'],
            'lines.*.req_amt' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], ['lines.required' => __('accounting.vo.v.cq_lines'), 'lines.min' => __('accounting.vo.v.cq_lines')], self::attrs());
        ksort($d['lines']);
        $lines = array_values($d['lines']);

        $leaf = $this->leafQuery()->where('l.id', $d['cheque_leaf_id'])->first();
        $own = $old && $old->cheque_leaf_id === $d['cheque_leaf_id'];
        if (! $leaf || $leaf->bank_account_id !== $d['bank_account_id'] || (! $own && ($leaf->status !== 'UNUSED' || ! $leaf->active))) {
            throw ValidationException::withMessages(['cheque_leaf_id' => __('accounting.vo.v.cq_leaf')]);
        }
        $total = round(array_sum(array_map(fn ($l) => (float) $l['amount'], $lines)), 4);
        if ($total > (float) $d['amount'] + 0.005) {
            throw ValidationException::withMessages(['lines' => __('accounting.vo.v.cq_over', ['lines' => number_format($total, 2), 'amount' => number_format((float) $d['amount'], 2)])]);
        }
        foreach ($lines as $n => $l) {
            if (! empty($l['issue_ref']) && isset($l['req_amt']) && $l['req_amt'] !== '' && $l['req_amt'] !== null) {
                $left = round((float) $l['req_amt'] - $this->usedFor($l['issue_ref'], $old?->id), 4);
                if ((float) $l['amount'] > $left + 0.005) {
                    throw ValidationException::withMessages(["lines.{$n}.amount" => __('accounting.vo.v.cq_balance', ['ref' => $l['issue_ref'], 'left' => number_format(max(0, $left), 2)])]);
                }
            }
        }

        $saved = DB::transaction(function () use ($d, $old, $c, $me, $lines) {
            $values = collect($d)->except('lines')->all() + ['charges' => (float) ($d['charges'] ?? 0), 'delivered' => (bool) ($d['delivered'] ?? false), 'acc_payee' => (bool) ($d['acc_payee'] ?? false), 'updated_by' => $me, 'updated_at' => now()];
            if ($old) {
                if ($old->cheque_leaf_id !== $d['cheque_leaf_id']) {
                    DB::table('acc_cheque_leaves')->where('id', $old->cheque_leaf_id)->update(['status' => 'UNUSED', 'updated_at' => now()]);
                }
                DB::table('acc_cheque_issues')->where('id', $old->id)->update($values);
                DB::table('acc_cheque_issue_lines')->where('cheque_issue_id', $old->id)->delete();
                $id = $old->id;
            } else {
                $id = strtolower((string) Str::ulid());
                DB::table('acc_cheque_issues')->insert($values + ['id' => $id, 'company_id' => $c, 'issue_ref' => $this->nextRef($d['issue_date']), 'created_by' => $me, 'created_at' => now()]);
            }
            DB::table('acc_cheque_leaves')->where('id', $d['cheque_leaf_id'])->update(['status' => 'ISSUED', 'updated_at' => now()]);
            DB::table('acc_cheque_issue_lines')->insert(array_map(fn ($l, $i) => ['id' => strtolower((string) Str::ulid()), 'company_id' => $c, 'cheque_issue_id' => $id, 'control_head_id' => $l['control_head_id'], 'issue_for' => $l['issue_for'] ?? null, 'issue_ref' => $l['issue_ref'] ?? null,
                'currency' => 'BDT', 'req_amt' => isset($l['req_amt']) && $l['req_amt'] !== '' ? (float) $l['req_amt'] : null, 'amount' => round((float) $l['amount'], 4), 'remarks' => $l['remarks'] ?? null, 'sort_order' => $i + 1, 'created_at' => now(), 'updated_at' => now()], $lines, array_keys($lines)));

            return $this->issue($id);
        });

        return $id ? ApiResponse::ok($this->body($saved)) : ApiResponse::created($this->body($saved));
    }

    /** Cheque Print: recorded once; after that the cheque is never removed, only cancelled. */
    public function print(string $id): JsonResponse
    {
        $this->allow('accounting.cheque.manage');
        $q = $this->issue($id);
        if ($q->printed) {
            throw ValidationException::withMessages(['id' => __('accounting.vo.v.cq_printed_once', ['no' => $q->issue_ref])]);
        }
        if ($q->cancelled) {
            throw ValidationException::withMessages(['id' => __('accounting.vo.v.cq_cancelled', ['no' => $q->issue_ref])]);
        }
        DB::table('acc_cheque_issues')->where('id', $id)->update(['printed' => true, 'printed_at' => now(), 'updated_by' => $this->caller()->auditUserId(), 'updated_at' => now()]);

        return ApiResponse::ok($this->body($this->issue($id)));
    }

    public function destroy(string $id): JsonResponse
    {
        $this->allow('accounting.cheque.manage');
        $q = $this->issue($id);
        $this->assertEditable($q);
        DB::transaction(function () use ($q) {
            DB::table('acc_cheque_leaves')->where('id', $q->cheque_leaf_id)->update(['status' => 'UNUSED', 'updated_at' => now()]);
            DB::table('acc_cheque_issues')->where('id', $q->id)->delete();
        });

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Cheque Cancellation ───────────────────────────────────────── */

    public function cancellations(): JsonResponse
    {
        $this->allowEither('accounting.cheque.view', 'accounting.cheque.manage');
        $rows = DB::table('acc_cheque_cancellations as x')->join('acc_cheque_leaves as l', 'l.id', '=', 'x.cheque_leaf_id')->join('acc_cheque_books as b', 'b.id', '=', 'l.book_id')->join('acc_bank_accounts as a', 'a.id', '=', 'b.bank_account_id')
            ->where('x.company_id', $this->caller()->companyId)->orderByDesc('x.created_at')
            ->get(['x.id', 'x.issue_ref', 'x.remarks', 'x.created_at', 'l.cheque_no', 'l.active', 'b.book_no', 'b.receive_date', 'a.account_no', 'a.account_type', 'a.bank_id', 'a.branch_id']);

        return ApiResponse::ok($rows->map(fn ($r) => ['id' => $r->id, 'book_no' => $r->book_no, 'cheque_no' => $r->cheque_no, 'cancelled_no' => $r->cheque_no, 'issue_ref' => $r->issue_ref, 'account_no' => $r->account_no, 'account_type' => $r->account_type,
            'remarks' => $r->remarks, 'active' => (bool) $r->active, 'bank_id' => $r->bank_id, 'branch_id' => $r->branch_id, 'receive_date' => substr((string) $r->receive_date, 0, 10)])->values()->all());
    }

    /** Leaves that can still be taken out: unused, or carrying a cheque not yet cancelled. */
    public function cancellable(): JsonResponse
    {
        $this->allowEither('accounting.cheque.view', 'accounting.cheque.manage');
        $issues = DB::table('acc_cheque_issues')->where('company_id', $this->caller()->companyId)->where('cancelled', false)->pluck('issue_ref', 'cheque_leaf_id');

        return ApiResponse::ok($this->leafQuery()->where('l.active', true)->whereIn('l.status', ['UNUSED', 'ISSUED'])->get()
            ->map(fn ($l) => ['value' => $l->id, 'label' => "{$l->cheque_no} · {$l->book_no} · {$l->account_no}".(isset($issues[$l->id]) ? " · {$issues[$l->id]}" : ''), 'issue_ref' => $issues[$l->id] ?? null])->values()->all());
    }

    public function cancel(Request $request): JsonResponse
    {
        $this->allow('accounting.cheque.manage');
        $c = $this->caller()->companyId;
        $d = $request->validate(['cheque_leaf_id' => ['required', 'string'], 'issue_ref' => ['nullable', 'string', 'max:48'], 'remarks' => ['required', 'string', 'max:2000']], ['cheque_leaf_id.required' => __('accounting.vo.v.cq_leaf_required')], self::attrs());
        $leaf = $this->leafQuery()->where('l.id', $d['cheque_leaf_id'])->first();
        if (! $leaf || ! $leaf->active || ! in_array($leaf->status, ['UNUSED', 'ISSUED'], true)) {
            throw ValidationException::withMessages(['cheque_leaf_id' => __('accounting.vo.v.cq_not_cancellable')]);
        }
        $issue = DB::table('acc_cheque_issues')->where('company_id', $c)->where('cheque_leaf_id', $leaf->id)->first();
        DB::transaction(function () use ($c, $d, $leaf, $issue) {
            DB::table('acc_cheque_cancellations')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $c, 'cheque_leaf_id' => $leaf->id, 'cheque_issue_id' => $issue?->id, 'issue_ref' => $d['issue_ref'] ?? $issue?->issue_ref, 'remarks' => $d['remarks'],
                'created_by' => $this->caller()->auditUserId(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('acc_cheque_leaves')->where('id', $leaf->id)->update(['status' => 'CANCELLED', 'active' => false, 'updated_at' => now()]);
            if ($issue) {
                DB::table('acc_cheque_issues')->where('id', $issue->id)->update(['cancelled' => true, 'updated_at' => now()]);
            }
        });

        return ApiResponse::created(['cheque_no' => $leaf->cheque_no]);
    }

    /** Removing a cancellation puts the leaf back as it was. */
    public function uncancel(string $id): JsonResponse
    {
        $this->allow('accounting.cheque.manage');
        $x = DB::table('acc_cheque_cancellations')->where('company_id', $this->caller()->companyId)->where('id', $id)->first() ?? abort(404);
        DB::transaction(function () use ($x) {
            DB::table('acc_cheque_leaves')->where('id', $x->cheque_leaf_id)->update(['status' => $x->cheque_issue_id ? 'ISSUED' : 'UNUSED', 'active' => true, 'updated_at' => now()]);
            if ($x->cheque_issue_id) {
                DB::table('acc_cheque_issues')->where('id', $x->cheque_issue_id)->update(['cancelled' => false, 'updated_at' => now()]);
            }
            DB::table('acc_cheque_cancellations')->where('id', $x->id)->delete();
        });

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    private function issue(string $id): object
    {
        return DB::table('acc_cheque_issues')->where('company_id', $this->caller()->companyId)->where('id', $id)->first() ?? abort(404);
    }

    private function assertEditable(object $q): void
    {
        if ($q->printed) {
            throw ValidationException::withMessages(['id' => __('accounting.vo.v.cq_printed', ['no' => $q->issue_ref])]);
        }
        if ($q->cancelled) {
            throw ValidationException::withMessages(['id' => __('accounting.vo.v.cq_cancelled', ['no' => $q->issue_ref])]);
        }
    }

    private function usedFor(string $ref, ?string $except): float
    {
        return round((float) DB::table('acc_cheque_issue_lines as l')->join('acc_cheque_issues as q', 'q.id', '=', 'l.cheque_issue_id')->where('l.company_id', $this->caller()->companyId)
            ->where('l.issue_ref', $ref)->where('q.cancelled', false)->when($except, fn ($w, $v) => $w->where('q.id', '!=', $v))->sum('l.amount'), 4);
    }

    private function leafQuery()
    {
        return DB::table('acc_cheque_leaves as l')->join('acc_cheque_books as b', 'b.id', '=', 'l.book_id')->join('acc_bank_accounts as a', 'a.id', '=', 'b.bank_account_id')
            ->where('l.company_id', $this->caller()->companyId)->orderBy('b.book_no')->orderBy('l.number')
            ->select('l.id', 'l.cheque_no', 'l.status', 'l.active', 'b.book_no', 'b.bank_account_id', 'a.account_no');
    }

    private function bankAccounts(): Collection
    {
        return DB::table('acc_bank_accounts as a')->leftJoin('tx_banks as k', 'k.id', '=', 'a.bank_id')->leftJoin('tx_bank_branches as r', 'r.id', '=', 'a.branch_id')->where('a.company_id', $this->caller()->companyId)->where('a.active', true)->orderBy('a.account_no')
            ->get(['a.id', 'a.account_no', 'a.account_name', 'a.bank_id', 'a.branch_id', 'k.name as bank', 'r.name as branch']);
    }

    private function nextRef(string $date): string
    {
        $base = 'CHQ-'.substr($date, 0, 4).'-';
        $last = DB::table('acc_cheque_issues')->where('company_id', $this->caller()->companyId)->where('issue_ref', 'like', $base.'%')->orderByDesc('issue_ref')->value('issue_ref');

        return $base.str_pad((string) ((int) substr((string) $last, strlen($base)) + 1), 6, '0', STR_PAD_LEFT);
    }

    private function rows(Collection $issues): array
    {
        $accounts = $this->bankAccounts()->keyBy('id');
        $leaves = DB::table('acc_cheque_leaves')->whereIn('id', $issues->pluck('cheque_leaf_id'))->pluck('cheque_no', 'id');
        $refs = DB::table('acc_cheque_issue_lines')->whereIn('cheque_issue_id', $issues->pluck('id'))->whereNotNull('issue_ref')->orderBy('sort_order')->get(['cheque_issue_id', 'issue_ref'])->groupBy('cheque_issue_id');
        $users = DB::table('users')->whereIn('id', $issues->pluck('issued_by')->merge($issues->pluck('created_by'))->merge($issues->pluck('updated_by'))->filter()->unique())->pluck('name', 'id');
        $company = $this->company();

        return $issues->map(function ($q) use ($accounts, $leaves, $refs, $users, $company) {
            $a = $accounts[$q->bank_account_id] ?? null;

            return ['id' => $q->id, 'issue_ref' => $q->issue_ref, 'printed' => (bool) $q->printed, 'acc_payee' => (bool) $q->acc_payee, 'final' => (bool) $q->printed, 'cancelled' => (bool) $q->cancelled, 'issue_refs' => $refs->get($q->id, collect())->pluck('issue_ref')->unique()->implode(', ') ?: null,
                'issue_date' => substr((string) $q->issue_date, 0, 10), 'account_no' => $a?->account_no, 'cheque_no' => $leaves[$q->cheque_leaf_id] ?? null, 'company' => $company, 'bank' => $a?->bank, 'branch' => $a?->branch, 'bank_id' => $a?->bank_id, 'branch_id' => $a?->branch_id,
                'pay_to' => $q->pay_to, 'amount' => (float) $q->amount, 'purpose' => $q->purpose, 'narration' => $q->narration, 'issued_by' => $users[$q->issued_by ?? $q->created_by] ?? null, 'updated_by' => $users[$q->updated_by] ?? null, 'locked' => (bool) ($q->printed || $q->cancelled)];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function body(object $q): array
    {
        $lines = DB::table('acc_cheque_issue_lines')->where('cheque_issue_id', $q->id)->orderBy('sort_order')->get();

        return array_merge($this->rows(collect([$q]))[0], ['bank_account_id' => $q->bank_account_id, 'cheque_leaf_id' => $q->cheque_leaf_id, 'place_date' => $q->place_date ? substr((string) $q->place_date, 0, 10) : null, 'issue_type' => $q->issue_type, 'issue_to_head_id' => $q->issue_to_head_id,
            'issue_for' => $q->issue_for, 'charges' => (float) $q->charges, 'delivered' => (bool) $q->delivered, 'fiscal_year_id' => $q->fiscal_year_id, 'issued_by_id' => $q->issued_by,
            'lines' => $lines->map(fn ($l) => ['control_head_id' => $l->control_head_id, 'issue_for' => $l->issue_for, 'issue_ref' => $l->issue_ref, 'currency' => $l->currency, 'req_amt' => $l->req_amt === null ? null : (float) $l->req_amt, 'used_amt' => $l->issue_ref ? $this->usedFor($l->issue_ref, $q->id) : 0.0, 'amount' => (float) $l->amount, 'remarks' => $l->remarks])->values()->all()]);
    }

    private function company(): string
    {
        return (string) DB::table('companies')->where('id', $this->caller()->companyId)->value('name');
    }

    /** @return array<string, string> */
    public static function attrs(): array
    {
        $a = [];
        foreach (['bank_account_id', 'cheque_leaf_id', 'issue_date', 'place_date', 'issue_type', 'issue_to_head_id', 'pay_to', 'issue_for', 'charges', 'amount', 'fiscal_year_id', 'purpose', 'narration', 'issued_by', 'lines', 'issue_ref', 'remarks'] as $k) {
            $a[$k] = __("accounting.vo.attr.cq_{$k}");
        }
        foreach (['control_head_id', 'issue_for', 'issue_ref', 'req_amt', 'amount', 'remarks'] as $k) {
            $a["lines.*.{$k}"] = __("accounting.vo.attr.cq_line_{$k}");
        }

        return $a;
    }
}

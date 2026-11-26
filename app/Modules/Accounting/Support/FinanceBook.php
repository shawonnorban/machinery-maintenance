<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The posted ledger as the Finance & Ledger screens read it (AnnotechERP 23):
 * the chart as a tree with its layers and classes, posted voucher lines,
 * opening balances (balance-sheet heads from the start, income and expense
 * heads from the start of the fiscal year) and the bank reconciliation lines
 * (23/04), shared by Period Close and the Reports (23/07).
 *
 * A balance is debit less credit: positive is a debit balance, negative a
 * credit balance.
 */
final class FinanceBook
{
    public const LAYERS = ['MAIN_CLASS' => 1, 'SUB_CLASS' => 2, 'CONTROL' => 3, 'LEDGER' => 4];

    private ?Collection $accounts = null;

    public function __construct(private readonly string $companyId) {}

    /** @return Collection<string, object{id: string, code: string, name: string, type: string, parent_id: ?string, postable: bool, active: bool, depth: int}> */
    public function accounts(): Collection
    {
        if ($this->accounts) {
            return $this->accounts;
        }
        AccountingDefaults::accounts($this->companyId);
        $rows = DB::table('acc_accounts')->where('company_id', $this->companyId)->orderBy('code')->get(['id', 'code', 'name', 'type', 'parent_id', 'postable', 'active'])->keyBy('id');
        $depth = function (string $id) use (&$depth, $rows): int {
            $p = $rows[$id]->parent_id ?? null;

            return $p && isset($rows[$p]) ? $depth($p) + 1 : 1;
        };

        return $this->accounts = $rows->map(function ($a) use ($depth) {
            $a->postable = (bool) $a->postable;
            $a->active = (bool) $a->active;
            $a->depth = $depth($a->id);

            return $a;
        });
    }

    public function label(?string $id): string
    {
        $a = $id ? ($this->accounts()[$id] ?? null) : null;

        return $a ? "{$a->code} — {$a->name}" : '—';
    }

    /** @return list<string> The account and everything filed under it. */
    public function descendants(string $id): array
    {
        $children = $this->accounts()->groupBy('parent_id');
        $out = [];
        $walk = function (string $x) use (&$walk, &$out, $children) {
            $out[] = $x;
            foreach ($children->get($x, collect()) as $c) {
                $walk($c->id);
            }
        };
        $walk($id);

        return $out;
    }

    /** The account's ancestor (or itself) at a layer of the chart. */
    public function atLayer(string $id, int $depth): ?object
    {
        $a = $this->accounts()[$id] ?? null;
        while ($a && $a->depth > $depth) {
            $a = $this->accounts()[$a->parent_id] ?? null;
        }

        return $a;
    }

    /** @return list<string> Accounts classed with a type (Account Type Configuration) and everything under them. */
    public function classed(string $type): array
    {
        $roots = DB::table('acc_account_classes')->where('company_id', $this->companyId)->where('account_type', $type)->pluck('account_id');

        return $roots->flatMap(fn ($id) => $this->descendants($id))->unique()->values()->all();
    }

    /** Posted voucher lines, by date and accounts. */
    public function lines(?string $from, ?string $to, ?array $accountIds = null, string|array $status = 'POSTED'): Builder
    {
        return DB::table('acc_voucher_lines as l')->join('acc_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('v.company_id', $this->companyId)->whereIn('v.posting_status', (array) $status)
            ->when($from, fn ($w, $x) => $w->where('v.voucher_date', '>=', $x))->when($to, fn ($w, $x) => $w->where('v.voucher_date', '<=', $x))
            ->when($accountIds !== null, fn ($w) => $w->whereIn('l.account_id', $accountIds))
            ->orderBy('v.voucher_date')->orderBy('v.voucher_no')->orderBy('l.sort_order')
            ->select('l.id', 'l.voucher_id', 'l.account_id', 'l.cost_center_id', 'l.bill_no as line_bill_no', 'l.note', 'l.ref_no', 'l.currency', 'l.value', 'l.debit', 'l.credit',
                'v.voucher_no', 'v.voucher_ref_no', 'v.voucher_type', 'v.voucher_date', 'v.bill_no', 'v.narration', 'v.voucher_for_type', 'v.voucher_for', 'v.posting_status');
    }

    /**
     * Each account's balance before a date: balance-sheet heads from the
     * start, income and expense heads from the start of that date's fiscal year.
     *
     * @return array<string, float>
     */
    public function opening(?string $from, ?array $accountIds = null, ?string $currency = null): array
    {
        if (! $from) {
            return [];
        }
        $fyStart = $this->fyStart($from) ?? '0001-01-01';
        $types = $this->accounts()->map(fn ($a) => $a->type);
        $out = [];
        foreach ($this->lines(null, date('Y-m-d', strtotime($from.' -1 day')), $accountIds)->when($currency, fn ($w, $x) => $w->where('l.currency', $x))->get() as $l) {
            if (in_array($types[$l->account_id] ?? '', ['REVENUE', 'EXPENSE'], true) && substr((string) $l->voucher_date, 0, 10) < $fyStart) {
                continue;
            }
            $out[$l->account_id] = ($out[$l->account_id] ?? 0.0) + (float) $l->debit - (float) $l->credit;
        }

        return $out;
    }

    /** The first day of the fiscal year a date falls in, if one is on file. */
    public function fyStart(string $date): ?string
    {
        $start = DB::table('acc_fiscal_years')->where('company_id', $this->companyId)->where('fy_start', '<=', $date)->where('fy_end', '>=', $date)->value('fy_start');

        return $start ? substr((string) $start, 0, 10) : null;
    }

    /* ── Bank reconciliation (23/04) ───────────────────────────────── */

    /** @return Collection<int, object{id: string, label: string, ledger_id: ?string}> */
    public function bankAccounts(): Collection
    {
        return DB::table('acc_bank_accounts as b')->leftJoin('tx_banks as k', 'k.id', '=', 'b.bank_id')->where('b.company_id', $this->companyId)->where('b.active', true)->orderBy('k.name')->orderBy('b.account_no')
            ->get(['b.id', 'b.account_no', 'b.account_name', 'b.ledger_id', 'k.name as bank'])->map(fn ($a) => (object) ['id' => $a->id, 'ledger_id' => $a->ledger_id, 'label' => trim(($a->bank ? "{$a->bank} — " : '').$a->account_no.' · '.$a->account_name)]);
    }

    /** @return list<string> Accounts classed BANK, and bank accounts' linked ledgers. */
    public function bankLedgerIds(): array
    {
        return DB::table('acc_account_classes')->where('company_id', $this->companyId)->where('account_type', 'BANK')->pluck('account_id')
            ->merge($this->bankAccounts()->pluck('ledger_id')->filter())->unique()->values()->all();
    }

    public function postedLines(): Builder
    {
        return DB::table('acc_voucher_lines as l')->join('acc_vouchers as v', 'v.id', '=', 'l.voucher_id')->join('acc_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('v.company_id', $this->companyId)->where('v.posting_status', 'POSTED')->orderBy('v.voucher_date')->orderBy('v.voucher_no')->orderBy('l.sort_order')
            ->select('l.id', 'l.account_id', 'l.debit', 'l.credit', 'l.ref_no', 'v.voucher_no', 'v.voucher_type', 'v.voucher_date', 'a.code');
    }

    /** Every line on a reconciliation: brought on by hand, or posted on an account's linked ledger. */
    public function bankLines(): Collection
    {
        $accounts = $this->bankAccounts()->keyBy('id');
        $byLedger = $accounts->filter(fn ($a) => $a->ledger_id)->mapWithKeys(fn ($a) => [$a->ledger_id => $a->id]);
        $stored = DB::table('acc_bank_lines')->where('company_id', $this->companyId)->get()->keyBy('voucher_line_id');
        $lines = $this->postedLines()->where(fn ($w) => $w->whereIn('l.id', $stored->keys())->orWhereIn('l.account_id', $byLedger->keys()))->get();

        return $lines->map(function ($l) use ($stored, $byLedger, $accounts) {
            $b = $stored[$l->id] ?? null;
            $account = $b?->bank_account_id ?? $byLedger[$l->account_id] ?? null;

            return ['voucher_line_id' => $l->id, 'bank_account_id' => $account, 'account' => $accounts[$account]->label ?? '—', 'voucher_no' => $l->voucher_no, 'voucher_type' => $l->voucher_type, 'voucher_date' => substr((string) $l->voucher_date, 0, 10),
                'dr' => (float) $l->debit, 'cr' => (float) $l->credit, 'cheque_no' => $b?->cheque_no ?? $l->ref_no, 'bank_date' => $b?->bank_date ? substr((string) $b->bank_date, 0, 10) : null, 'cleared' => (bool) ($b?->cleared ?? false), 'remarks' => $b?->remarks, 'on_file' => (bool) $b];
        })->filter(fn ($l) => $l['bank_account_id'])->values();
    }

    /** @return array{book: float, bank: float, not_in_bank: float} */
    public function bankBalances(Collection $lines): array
    {
        $book = round((float) $lines->sum(fn ($l) => $l['dr'] - $l['cr']), 2);
        $bank = round((float) $lines->where('cleared', true)->sum(fn ($l) => $l['dr'] - $l['cr']), 2);

        return ['book' => $book, 'bank' => $bank, 'not_in_bank' => round($book - $bank, 2)];
    }
}

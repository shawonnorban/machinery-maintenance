<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers\Api;

use App\Modules\Accounting\Support\FinanceBook;
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
 * Finance & Ledger → Reports (AnnotechERP 23/07): Brickland's five report
 * screens, each a Run History of the runs made on it and a New report run
 * form; Financial Statement Reports also has Selection of Parameters — the
 * 22 reports as one list, the boxes each report reads, and Preview.
 *
 * Every figure is read from the posted ledger at the moment the report is
 * run (FinanceBook) — nothing is cached; only the run is kept
 * (acc_report_runs): the report, the period, the boxes it was narrowed by,
 * how many rows it gave and whether it went to a workbook.
 *
 * Balances are debit less credit. An opening balance runs from the start for
 * balance-sheet heads, and from the start of the fiscal year for income and
 * expense heads. The chart's layers are its depth: main class, sub class,
 * control account, then ledger.
 */
class FinanceReportApiController extends ApiController
{
    private const ALL = ['company', 'from', 'to'];

    /** Financial Statement Reports: each report and the boxes it reads. */
    public const STATEMENTS = [
        'ACC-BILL-LEDGER' => ['company', 'account', 'bill_status', 'bill_no', 'from', 'to'],
        'ACC-BILL-STATUS' => ['company', 'account', 'bill_status', 'bill_no', 'from', 'to'],
        'ACC-DAY-BOOK-DRAFT' => self::ALL,
        'ACC-BANK-RECONCILE' => ['from', 'to'],
        'ACC-LEDGER-SUMMARY' => ['company', 'account', 'from', 'to'],
        'ACC-MONTHLY-LEDGER' => ['company', 'account', 'currency', 'from_month', 'to_month'],
        'ACC-SUBSIDIARY' => ['company', 'layer', 'account', 'currency', 'from', 'to'],
        'ACC-CHART' => ['company'],
        'ACC-TRIAL-BALANCE' => self::ALL,
        'ACC-CONTROL-LEDGER' => ['company', 'main_class', 'layer', 'currency', 'from', 'to'],
        'ACC-CASH-BOOK' => ['company', 'account', 'from', 'to'],
        'ACC-BANK-BOOK' => ['company', 'account', 'from', 'to'],
        'ACC-DAY-BOOK' => self::ALL,
        'ACC-DAY-BOOK-SUM' => self::ALL,
        'ACC-VOUCHER-STATEMENT' => ['company', 'posted', 'from', 'to'],
        'ACC-VOUCHER-SUM' => self::ALL,
        'ACC-INCOME-DETAIL' => self::ALL,
        'ACC-INCOME-STATEMENT' => self::ALL,
        'ACC-BALANCE-SHEET' => self::ALL,
        'ACC-LEDGER-VOUCHER-SUM' => ['layer', 'account', 'currency', 'from', 'to'],
        'ACC-SUBSIDIARY-STMT' => ['company', 'layer', 'account', 'from', 'to'],
        'ACC-STOCK-CLOSING' => self::ALL,
    ];

    /** The five screens: their reports, and the boxes of their New report run form (and Run History search). */
    public const SCREENS = [
        'statements' => ['reports' => null, 'form' => ['company', 'account', 'bill_no', 'from', 'to'], 'search' => ['company', 'account', 'from', 'to']],
        'ledger-cc' => ['reports' => ['ACC-CC-MOVEMENT', 'ACC-LEDGER-CC'], 'form' => ['company', 'account', 'cost_center', 'from', 'to'], 'search' => ['company', 'cost_center', 'account', 'from', 'to']],
        'cc-ledger' => ['reports' => ['ACC-CC-MOVEMENT', 'ACC-LEDGER-CC'], 'form' => ['company', 'cost_center', 'from', 'to'], 'search' => ['company', 'cost_center', 'from', 'to']],
        'receive-payment' => ['reports' => ['ACC-RECEIVE-PAYMENT'], 'form' => self::ALL, 'search' => self::ALL],
        'misc' => ['reports' => ['ACC-VOUCHER-AUDIT'], 'form' => self::ALL, 'search' => self::ALL],
    ];

    public const MAIN_CLASSES = ['CASH', 'BANK', 'RECEIVABLE', 'PAYABLE', 'INVENTORY', 'GENERAL'];

    private const MAX_MONTHS = 24;

    /** Columns whose total means nothing. */
    private const NO_TOTAL = ['balance', 'opening', 'closing', 'amount_bal'];

    private FinanceBook $b;

    private array $q = [];

    private string $screen = 'statements';

    public function options(Request $request): JsonResponse
    {
        $this->allowEither('accounting.report.view', 'accounting.report.manage');
        $screen = $this->screenOf($request);
        $c = $this->caller()->companyId;
        $b = $this->book();
        $today = CarbonImmutable::now('Asia/Dhaka');
        $currencies = DB::table('acc_voucher_lines')->where('company_id', $c)->distinct()->pluck('currency')->push('BDT')->unique()->sort()->values();

        return ApiResponse::ok([
            'screen' => $screen,
            'today' => $today->toDateString(),
            'month' => $today->format('Y-m'),
            'fy_start' => $b->fyStart($today->toDateString()) ?? $today->startOfYear()->toDateString(),
            'reports' => collect($this->codes($screen))->map(fn ($code) => ['value' => $code, 'label' => __("accounting.vo.rp.kind.{$code}"), 'params' => $this->params($screen, $code)])->values()->all(),
            'form' => self::SCREENS[$screen]['form'],
            'search' => self::SCREENS[$screen]['search'],
            'companies' => [['value' => $c, 'label' => $this->company()]],
            'ledgers' => $b->accounts()->filter(fn ($a) => $a->active)->map(fn ($a) => ['value' => $a->id, 'label' => "{$a->code} — {$a->name}"])->values()->all(),
            'cost_centers' => DB::table('acc_cost_centers')->where('company_id', $c)->where('active', true)->orderBy('code')->get(['id', 'code', 'name'])->map(fn ($x) => ['value' => $x->id, 'label' => "{$x->code} — {$x->name}"])->values()->all(),
            'main_classes' => array_map(fn ($k) => ['value' => $k, 'label' => __("accounting.vo.rp.opt.main_class.{$k}")], self::MAIN_CLASSES),
            'layers' => array_map(fn ($k) => ['value' => $k, 'label' => __("accounting.vo.rp.layer.{$k}")], array_keys(FinanceBook::LAYERS)),
            'currencies' => $currencies->map(fn ($x) => ['value' => $x, 'label' => $x])->all(),
        ]);
    }

    /** Run a report: read it off the ledger, keep the run, hand back the rows. */
    public function run(Request $request): JsonResponse
    {
        $this->allowEither('accounting.report.view', 'accounting.report.manage');
        $screen = $this->screenOf($request);
        $code = $request->validate(['report' => ['required', Rule::in($this->codes($screen))]])['report'];
        $c = $this->caller()->companyId;
        $q = $request->validate([
            'company' => ['nullable', Rule::in([$c])],
            'account' => ['nullable', Rule::exists('acc_accounts', 'id')->where('company_id', $c)],
            'cost_center' => ['nullable', Rule::exists('acc_cost_centers', 'id')->where('company_id', $c)],
            'main_class' => ['nullable', Rule::in(self::MAIN_CLASSES)],
            'layer' => ['nullable', Rule::in(array_keys(FinanceBook::LAYERS))],
            'bill_status' => ['nullable', Rule::in(['UNPAID', 'PARTIAL', 'PAID'])],
            'bill_no' => ['nullable', 'string', 'max:48'],
            'currency' => ['nullable', 'string', 'size:3'],
            'posted' => ['nullable', Rule::in(['YES', 'NO'])],
            'from_month' => ['nullable', 'date_format:Y-m'], 'to_month' => ['nullable', 'date_format:Y-m'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
            'excel' => ['nullable', 'boolean'],
        ]);
        $excel = (bool) ($q['excel'] ?? false);
        $q = array_filter(array_intersect_key($q, array_flip($this->params($screen, $code))), fn ($v) => $v !== null && $v !== '');
        foreach ([['from', 'to'], ['from_month', 'to_month']] as [$a, $z]) {
            if (! empty($q[$a]) && ! empty($q[$z]) && $q[$z] < $q[$a]) {
                throw ValidationException::withMessages([$z => __('accounting.vo.rp.v.range')]);
            }
        }
        $this->q = $q;
        $this->screen = $screen;
        $out = $this->{'r'.Str::studly(strtolower(substr($code, 4)))}();
        $columns = array_map(function ($col) {
            [$key, $type] = array_pad(explode(':', $col, 2), 2, 'text');

            return ['key' => $key, 'type' => $type, 'numeric' => in_array($type, ['money', 'num'], true)];
        }, $out['columns']);
        $rows = array_values($out['rows'] instanceof Collection ? $out['rows']->all() : $out['rows']);
        $totals = [];
        if (($out['totals'] ?? true) && $rows) {
            foreach ($columns as $col) {
                if ($col['numeric'] && ! in_array($col['key'], self::NO_TOTAL, true)) {
                    $totals[$col['key']] = round((float) collect($rows)->sum(fn ($r) => (float) ($r[$col['key']] ?? 0)), 2);
                }
            }
        }
        [$from, $to] = $this->period($code);
        DB::table('acc_report_runs')->insert([
            'id' => $run = strtolower((string) Str::ulid()), 'company_id' => $c, 'screen' => $screen, 'report_code' => $code, 'from_date' => $from, 'to_date' => $to,
            'filters' => json_encode(array_diff_key($q, array_flip(['company', 'from', 'to']))), 'account_id' => $q['account'] ?? null, 'cost_center_id' => $q['cost_center'] ?? null,
            'rows' => count($rows), 'export_excel' => $excel, 'created_by' => $this->caller()->auditUserId(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ApiResponse::ok(['run_id' => $run, 'report' => $code, 'title' => __("accounting.vo.rp.kind.{$code}"), 'columns' => $columns, 'rows' => $rows, 'totals' => $totals, 'summary' => $out['summary'] ?? []]);
    }

    /** Run History: the runs made on a screen, narrowed by the period they covered. */
    public function runs(Request $request): JsonResponse
    {
        $this->allowEither('accounting.report.view', 'accounting.report.manage');
        $screen = $this->screenOf($request);
        $c = $this->caller()->companyId;
        $f = $request->validate(['company' => ['nullable', Rule::in([$c])], 'account' => ['nullable', 'string'], 'cost_center' => ['nullable', 'string'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $company = $this->company();
        $b = $this->book();
        $centres = DB::table('acc_cost_centers')->where('company_id', $c)->get(['id', 'code', 'name'])->keyBy('id');
        $rows = DB::table('acc_report_runs as r')->leftJoin('users as u', 'u.id', '=', 'r.created_by')->where('r.company_id', $c)->where('r.screen', $screen)
            ->when($f['account'] ?? null, fn ($w, $x) => $w->where('r.account_id', $x))->when($f['cost_center'] ?? null, fn ($w, $x) => $w->where('r.cost_center_id', $x))
            ->when($f['from'] ?? null, fn ($w, $x) => $w->where(fn ($o) => $o->whereNull('r.to_date')->orWhere('r.to_date', '>=', $x)))
            ->when($f['to'] ?? null, fn ($w, $x) => $w->where(fn ($o) => $o->whereNull('r.from_date')->orWhere('r.from_date', '<=', $x)))
            ->orderByDesc('r.created_at')->orderByDesc('r.id')->limit(500)->get(['r.*', 'u.name as by']);

        return ApiResponse::ok($rows->map(function ($r) use ($company, $b, $centres) {
            $filters = json_decode($r->filters, true) ?: [];
            $from = $r->from_date ? substr((string) $r->from_date, 0, 10) : null;
            $to = $r->to_date ? substr((string) $r->to_date, 0, 10) : null;

            return ['id' => $r->id, 'report' => __("accounting.vo.rp.kind.{$r->report_code}"), 'report_code' => $r->report_code, 'company' => $company, 'account_id' => $r->account_id, 'cost_center_id' => $r->cost_center_id, 'from' => $from, 'to' => $to,
                'filters' => collect($filters)->map(fn ($v, $k) => __("accounting.vo.rp.p.{$k}").': '.$this->shown($k, $v, $b, $centres))->values()->implode(' · '),
                'rows' => (int) $r->rows, 'excel' => (bool) $r->export_excel, 'by' => $r->by, 'at' => CarbonImmutable::parse($r->created_at)->timezone('Asia/Dhaka')->toDateTimeString()];
        })->values()->all());
    }

    public function destroyRun(string $id): JsonResponse
    {
        $this->allow('accounting.report.manage');
        $n = DB::table('acc_report_runs')->where('company_id', $this->caller()->companyId)->where('id', $id)->delete();
        abort_if($n === 0, 404);

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Screens and boxes ─────────────────────────────────────────── */

    private function screenOf(Request $request): string
    {
        return $request->validate(['screen' => ['required', Rule::in(array_keys(self::SCREENS))]])['screen'];
    }

    /** @return list<string> */
    private function codes(string $screen): array
    {
        return self::SCREENS[$screen]['reports'] ?? array_keys(self::STATEMENTS);
    }

    /** @return list<string> The boxes a report reads on a screen. */
    private function params(string $screen, string $code): array
    {
        return $screen === 'statements' ? self::STATEMENTS[$code] : self::SCREENS[$screen]['form'];
    }

    /** @return array{0: ?string, 1: ?string} The period a run covered. */
    private function period(string $code): array
    {
        if ($code === 'ACC-MONTHLY-LEDGER') {
            [$a, $z] = $this->months();

            return [$a.'-01', CarbonImmutable::parse($z.'-01')->endOfMonth()->toDateString()];
        }

        return [$this->q['from'] ?? null, $this->q['to'] ?? null];
    }

    private function shown(string $k, mixed $v, FinanceBook $b, Collection $centres): string
    {
        return match ($k) {
            'account' => $b->label((string) $v),
            'cost_center' => isset($centres[$v]) ? "{$centres[$v]->code} — {$centres[$v]->name}" : '—',
            'layer' => __("accounting.vo.rp.layer.{$v}"),
            'main_class', 'bill_status', 'posted' => __("accounting.vo.rp.opt.{$k}.{$v}"),
            default => (string) $v,
        };
    }

    private function book(): FinanceBook
    {
        return $this->b ??= new FinanceBook($this->caller()->companyId);
    }

    private function company(): string
    {
        return (string) DB::table('companies')->where('id', $this->caller()->companyId)->value('name');
    }

    /* ── Reading the ledger ────────────────────────────────────────── */

    /** @return list<string>|null The account picked and everything under it, or null for the whole ledger. */
    private function under(?string $account = null): ?array
    {
        $account ??= $this->q['account'] ?? null;

        return $account ? $this->book()->descendants($account) : null;
    }

    private function from(): ?string
    {
        return $this->q['from'] ?? null;
    }

    private function to(): ?string
    {
        return $this->q['to'] ?? null;
    }

    private function posted(?array $ids = null, string|array $status = 'POSTED'): Collection
    {
        return $this->book()->lines($this->from(), $this->to(), $ids, $status)->when($this->q['currency'] ?? null, fn ($w, $x) => $w->where('l.currency', $x))->get();
    }

    /** @return array<string, array{dr: float, cr: float}> */
    private function movement(Collection $lines): array
    {
        $out = [];
        foreach ($lines as $l) {
            $out[$l->account_id]['dr'] = ($out[$l->account_id]['dr'] ?? 0.0) + (float) $l->debit;
            $out[$l->account_id]['cr'] = ($out[$l->account_id]['cr'] ?? 0.0) + (float) $l->credit;
        }

        return $out;
    }

    private function d(mixed $date): ?string
    {
        return $date ? substr((string) $date, 0, 10) : null;
    }

    private function m(float $v): float
    {
        return round($v, 2);
    }

    private function layerOf(int $depth): string
    {
        return array_search(min($depth, 4), FinanceBook::LAYERS, true) ?: 'LEDGER';
    }

    /**
     * Opening, debit, credit and closing per head, rolled up to a layer of the chart.
     *
     * @return list<array<string, mixed>>
     */
    private function rollUp(?array $ids, int $depth): array
    {
        $b = $this->book();
        $open = $b->opening($this->from(), $ids, $this->q['currency'] ?? null);
        $move = $this->movement($this->posted($ids));
        $heads = [];
        foreach (array_unique([...array_keys($open), ...array_keys($move)]) as $id) {
            $h = $b->atLayer($id, $depth);
            if (! $h) {
                continue;
            }
            $heads[$h->id] ??= ['code' => $h->code, 'account' => $h->name, 'opening' => 0.0, 'debit' => 0.0, 'credit' => 0.0];
            $heads[$h->id]['opening'] += $open[$id] ?? 0.0;
            $heads[$h->id]['debit'] += $move[$id]['dr'] ?? 0.0;
            $heads[$h->id]['credit'] += $move[$id]['cr'] ?? 0.0;
        }
        uasort($heads, fn ($a, $z) => strcmp($a['code'], $z['code']));

        return array_values(array_filter(array_map(fn ($h) => ['code' => $h['code'], 'account' => $h['account'], 'opening' => $this->m($h['opening']), 'debit' => $this->m($h['debit']), 'credit' => $this->m($h['credit']), 'closing' => $this->m($h['opening'] + $h['debit'] - $h['credit'])], $heads),
            fn ($h) => $h['opening'] != 0 || $h['debit'] != 0 || $h['credit'] != 0));
    }

    private function depth(): int
    {
        return FinanceBook::LAYERS[$this->q['layer'] ?? 'LEDGER'];
    }

    /** @return array<string, string> Each voucher's other heads — the "particulars" of a cash or bank line. */
    private function contra(Collection $lines, array $ids): array
    {
        $b = $this->book();

        return DB::table('acc_voucher_lines')->whereIn('voucher_id', $lines->pluck('voucher_id')->unique())->whereNotIn('account_id', $ids)->orderBy('sort_order')->get(['voucher_id', 'account_id'])
            ->groupBy('voucher_id')->map(fn ($g) => $g->pluck('account_id')->unique()->map(fn ($a) => $b->accounts()[$a]->name ?? '—')->implode(', '))->all();
    }

    /** @return array{0: string, 1: string} */
    private function months(): array
    {
        $today = CarbonImmutable::now('Asia/Dhaka');
        $a = $this->q['from_month'] ?? substr($this->book()->fyStart($today->toDateString()) ?? $today->startOfYear()->toDateString(), 0, 7);
        $z = $this->q['to_month'] ?? $today->format('Y-m');

        return [$a, $z];
    }

    /* ── Financial Statement Reports ───────────────────────────────── */

    /** Bills on an account: every line carrying a bill number, with the bill's running balance and state. */
    private function rBillLedger(): array
    {
        $bills = $this->bills();
        $rows = [];
        foreach ($bills as $bill) {
            $bal = 0.0;
            foreach ($bill['lines'] as $l) {
                $bal += (float) $l->debit - (float) $l->credit;
                if ($this->from() && $this->d($l->voucher_date) < $this->from()) {
                    continue;
                }
                $rows[] = ['account' => $bill['account'], 'bill_no' => $bill['bill_no'], 'voucher_date' => $this->d($l->voucher_date), 'voucher_no' => $l->voucher_no, 'voucher_type' => $l->voucher_type, 'note' => $l->note ?: $l->narration, 'debit' => $this->m((float) $l->debit), 'credit' => $this->m((float) $l->credit), 'balance' => $this->m($bal), 'status' => __("accounting.vo.rp.opt.bill_status.{$bill['status']}")];
            }
        }

        return ['columns' => ['account', 'bill_no', 'voucher_date:date', 'voucher_no', 'voucher_type', 'note', 'debit:money', 'credit:money', 'balance:money', 'status'], 'rows' => $rows];
    }

    /** Each bill on an account: billed, settled, what is left and its state. */
    private function rBillStatus(): array
    {
        $rows = array_map(fn ($b) => ['account' => $b['account'], 'bill_no' => $b['bill_no'], 'first_date' => $b['first'], 'last_date' => $b['last'], 'billed' => $this->m($b['billed']), 'settled' => $this->m($b['settled']), 'outstanding' => $this->m($b['billed'] - $b['settled']), 'status' => __("accounting.vo.rp.opt.bill_status.{$b['status']}")], $this->bills());

        return ['columns' => ['account', 'bill_no', 'first_date:date', 'last_date:date', 'billed:money', 'settled:money', 'outstanding:money', 'status'], 'rows' => $rows];
    }

    /**
     * Bills to the To date — a bill's state reads its whole life, whatever
     * the From date; the bigger side is what was billed, the smaller what
     * settled it.
     *
     * @return list<array<string, mixed>>
     */
    private function bills(): array
    {
        $b = $this->book();
        $lines = $b->lines(null, $this->to(), $this->under())->where(fn ($w) => $w->whereNotNull('l.bill_no')->orWhereNotNull('v.bill_no'))->get();
        $out = [];
        foreach ($lines as $l) {
            $no = trim((string) ($l->line_bill_no ?: $l->bill_no));
            if ($no === '' || (! empty($this->q['bill_no']) && ! Str::contains(Str::lower($no), Str::lower($this->q['bill_no'])))) {
                continue;
            }
            $out[$l->account_id.'|'.$no]['lines'][] = $l;
        }
        $bills = [];
        foreach ($out as $key => $bill) {
            [$account, $no] = explode('|', $key, 2);
            $dr = array_sum(array_map(fn ($l) => (float) $l->debit, $bill['lines']));
            $cr = array_sum(array_map(fn ($l) => (float) $l->credit, $bill['lines']));
            [$billed, $settled] = [max($dr, $cr), min($dr, $cr)];
            $status = round($billed - $settled, 2) == 0 ? 'PAID' : (round($settled, 2) == 0 ? 'UNPAID' : 'PARTIAL');
            $last = $this->d(end($bill['lines'])->voucher_date);
            if (! empty($this->q['bill_status']) && $this->q['bill_status'] !== $status) {
                continue;
            }
            if ($this->from() && $last < $this->from()) {
                continue;
            }
            $bills[] = ['account' => $b->label($account), 'bill_no' => $no, 'lines' => $bill['lines'], 'first' => $this->d($bill['lines'][0]->voucher_date), 'last' => $last, 'billed' => $billed, 'settled' => $settled, 'status' => $status];
        }
        usort($bills, fn ($a, $z) => [$a['account'], $a['first'], $a['bill_no']] <=> [$z['account'], $z['first'], $z['bill_no']]);

        return $bills;
    }

    /** The day book of vouchers not yet posted — drafts and numbered but unposted. */
    private function rDayBookDraft(): array
    {
        $b = $this->book();
        $rows = $this->posted(null, ['DRAFT', 'UNPOSTED'])->map(fn ($l) => ['voucher_date' => $this->d($l->voucher_date), 'voucher_ref_no' => $l->voucher_ref_no, 'voucher_no' => $l->voucher_no, 'voucher_type' => $l->voucher_type, 'posting_status' => __("accounting.vo.posting.{$l->posting_status}"), 'account' => $b->label($l->account_id), 'note' => $l->note ?: $l->narration, 'debit' => $this->m((float) $l->debit), 'credit' => $this->m((float) $l->credit)]);

        return ['columns' => ['voucher_date:date', 'voucher_ref_no', 'voucher_no', 'voucher_type', 'posting_status', 'account', 'note', 'debit:money', 'credit:money'], 'rows' => $rows];
    }

    /** The bank reconciliation lines (23/04) in the period: what the book says, what the bank has cleared. */
    private function rBankReconcile(): array
    {
        $lines = $this->book()->bankLines()->filter(fn ($l) => (! $this->from() || $l['voucher_date'] >= $this->from()) && (! $this->to() || $l['voucher_date'] <= $this->to()))->values();
        $bal = $this->book()->bankBalances($lines);
        $rows = $lines->map(fn ($l) => ['account' => $l['account'], 'voucher_date' => $l['voucher_date'], 'voucher_no' => $l['voucher_no'], 'voucher_type' => $l['voucher_type'], 'cheque_no' => $l['cheque_no'], 'debit' => $this->m($l['dr']), 'credit' => $this->m($l['cr']), 'bank_date' => $l['bank_date'], 'cleared' => __($l['cleared'] ? 'accounting.vo.rp.yes' : 'accounting.vo.rp.no')]);

        return ['columns' => ['account', 'voucher_date:date', 'voucher_no', 'voucher_type', 'cheque_no', 'debit:money', 'credit:money', 'bank_date:date', 'cleared'], 'rows' => $rows,
            'summary' => [['label' => __('accounting.vo.rp.sum.book'), 'value' => $bal['book']], ['label' => __('accounting.vo.rp.sum.bank'), 'value' => $bal['bank']], ['label' => __('accounting.vo.rp.sum.not_in_bank'), 'value' => $bal['not_in_bank']]]];
    }

    /** Each ledger's opening, movement in the period and closing. */
    private function rLedgerSummary(): array
    {
        return ['columns' => ['code', 'account', 'opening:money', 'debit:money', 'credit:money', 'closing:money'], 'rows' => $this->rollUp($this->under(), 99)];
    }

    /** One head (or the whole ledger) month by month. */
    private function rMonthlyLedger(): array
    {
        [$a, $z] = $this->months();
        $start = CarbonImmutable::parse($a.'-01');
        $end = CarbonImmutable::parse($z.'-01');
        if ($start->diffInMonths($end) + 1 > self::MAX_MONTHS) {
            throw ValidationException::withMessages(['to_month' => __('accounting.vo.rp.v.months', ['n' => self::MAX_MONTHS])]);
        }
        $ids = $this->under();
        $cur = $this->q['currency'] ?? null;
        $rows = [];
        for ($m = $start; $m <= $end; $m = $m->addMonth()) {
            $open = array_sum($this->book()->opening($m->toDateString(), $ids, $cur));
            $lines = $this->book()->lines($m->toDateString(), $m->endOfMonth()->toDateString(), $ids)->when($cur, fn ($w, $x) => $w->where('l.currency', $x))->get(['l.debit', 'l.credit']);
            $dr = (float) $lines->sum('debit');
            $cr = (float) $lines->sum('credit');
            $rows[] = ['month' => $m->format('M/Y'), 'opening' => $this->m($open), 'debit' => $this->m($dr), 'credit' => $this->m($cr), 'closing' => $this->m($open + $dr - $cr)];
        }

        return ['columns' => ['month', 'opening:money', 'debit:money', 'credit:money', 'closing:money'], 'rows' => $rows];
    }

    /** The heads under an account, rolled up to the layer picked. */
    private function rSubsidiary(): array
    {
        return ['columns' => ['code', 'account', 'opening:money', 'debit:money', 'credit:money', 'closing:money'], 'rows' => $this->rollUp($this->under(), $this->depth())];
    }

    /** The chart of accounts as a tree. */
    private function rChart(): array
    {
        $b = $this->book();
        $kids = $b->accounts()->groupBy(fn ($a) => $a->parent_id ?? '');
        $rows = [];
        $walk = function (string $parent) use (&$walk, &$rows, $kids) {
            foreach ($kids->get($parent, collect())->sortBy('code') as $a) {
                $rows[] = ['code' => $a->code, 'account' => str_repeat("\u{2003}", $a->depth - 1).$a->name, 'layer' => __("accounting.vo.rp.layer.{$this->layerOf($a->depth)}"), 'type' => __("accounting.vo.rp.type.{$a->type}"),
                    'postable' => __($a->postable ? 'accounting.vo.rp.yes' : 'accounting.vo.rp.no'), 'active' => __($a->active ? 'accounting.vo.rp.yes' : 'accounting.vo.rp.no')];
                $walk($a->id);
            }
        };
        $walk('');

        return ['columns' => ['code', 'account', 'layer', 'type', 'postable', 'active'], 'rows' => $rows, 'totals' => false];
    }

    /** Every ledger with a balance: opening, movement, and the closing balance on its debit or credit side. */
    private function rTrialBalance(): array
    {
        $rows = array_map(fn ($h) => ['code' => $h['code'], 'account' => $h['account'], 'opening' => $h['opening'], 'debit' => $h['debit'], 'credit' => $h['credit'], 'closing_dr' => $h['closing'] > 0 ? $h['closing'] : 0.0, 'closing_cr' => $h['closing'] < 0 ? -$h['closing'] : 0.0], $this->rollUp(null, 99));

        return ['columns' => ['code', 'account', 'opening:money', 'debit:money', 'credit:money', 'closing_dr:money', 'closing_cr:money'], 'rows' => $rows];
    }

    /** The heads classed with an account type (Account Type Configuration), rolled up to the layer picked. */
    private function rControlLedger(): array
    {
        $ids = empty($this->q['main_class']) ? array_values(array_unique(array_merge(...array_map(fn ($t) => $this->book()->classed($t), self::MAIN_CLASSES)))) : $this->book()->classed($this->q['main_class']);

        return ['columns' => ['code', 'account', 'opening:money', 'debit:money', 'credit:money', 'closing:money'], 'rows' => $ids ? $this->rollUp($ids, $this->depth()) : []];
    }

    private function rCashBook(): array
    {
        return $this->cashBankBook('CASH');
    }

    private function rBankBook(): array
    {
        return $this->cashBankBook('BANK');
    }

    /** A cash or bank book: the opening, each receipt and payment with what it was for, the running balance. */
    private function cashBankBook(string $type): array
    {
        $b = $this->book();
        $ids = $this->under() ?? ($type === 'BANK' ? array_values(array_unique(array_merge($b->classed('BANK'), ...array_map(fn ($x) => $b->descendants($x), $b->bankLedgerIds())))) : $b->classed('CASH'));
        if (! $ids) {
            return ['columns' => [], 'rows' => []];
        }
        $bal = array_sum($b->opening($this->from(), $ids));
        $lines = $this->posted($ids);
        $contra = $this->contra($lines, $ids);
        $rows = [['voucher_date' => $this->from(), 'voucher_no' => null, 'voucher_type' => null, 'account' => null, 'particulars' => __('accounting.vo.rp.opening'), 'note' => null, 'receipt' => null, 'payment' => null, 'balance' => $this->m($bal)]];
        foreach ($lines as $l) {
            $bal += (float) $l->debit - (float) $l->credit;
            $rows[] = ['voucher_date' => $this->d($l->voucher_date), 'voucher_no' => $l->voucher_no, 'voucher_type' => $l->voucher_type, 'account' => $b->label($l->account_id), 'particulars' => $contra[$l->voucher_id] ?? '—', 'note' => $l->note ?: $l->narration, 'receipt' => $this->m((float) $l->debit), 'payment' => $this->m((float) $l->credit), 'balance' => $this->m($bal)];
        }

        return ['columns' => ['voucher_date:date', 'voucher_no', 'voucher_type', 'account', 'particulars', 'note', 'receipt:money', 'payment:money', 'balance:money'], 'rows' => $rows, 'summary' => [['label' => __('accounting.vo.rp.sum.closing'), 'value' => $this->m($bal)]]];
    }

    /** Every posted line in the period. */
    private function rDayBook(): array
    {
        $b = $this->book();
        $rows = $this->posted()->map(fn ($l) => ['voucher_date' => $this->d($l->voucher_date), 'voucher_no' => $l->voucher_no, 'voucher_type' => $l->voucher_type, 'account' => $b->label($l->account_id), 'note' => $l->note ?: $l->narration, 'debit' => $this->m((float) $l->debit), 'credit' => $this->m((float) $l->credit)]);

        return ['columns' => ['voucher_date:date', 'voucher_no', 'voucher_type', 'account', 'note', 'debit:money', 'credit:money'], 'rows' => $rows];
    }

    /** The day book a day to a line. */
    private function rDayBookSum(): array
    {
        $rows = $this->posted()->groupBy(fn ($l) => $this->d($l->voucher_date))->map(fn ($g, $day) => ['voucher_date' => $day, 'vouchers' => $g->pluck('voucher_id')->unique()->count(), 'lines' => $g->count(), 'debit' => $this->m((float) $g->sum('debit')), 'credit' => $this->m((float) $g->sum('credit'))]);

        return ['columns' => ['voucher_date:date', 'vouchers:num', 'lines:num', 'debit:money', 'credit:money'], 'rows' => $rows->values()];
    }

    /** The vouchers of the period, posted or not yet posted. */
    private function rVoucherStatement(): array
    {
        $status = ($this->q['posted'] ?? 'YES') === 'YES' ? ['POSTED'] : ['DRAFT', 'UNPOSTED'];
        $rows = $this->vouchers($status)->map(fn ($v) => ['voucher_date' => $this->d($v->voucher_date), 'voucher_no' => $v->voucher_no ?? $v->voucher_ref_no, 'voucher_type' => $v->voucher_type, 'bill_no' => $v->bill_no, 'voucher_for' => $v->voucher_for, 'narration' => $v->narration, 'posting_status' => __("accounting.vo.posting.{$v->posting_status}"), 'amount' => $this->m((float) $v->total)]);

        return ['columns' => ['voucher_date:date', 'voucher_no', 'voucher_type', 'bill_no', 'voucher_for', 'narration', 'posting_status', 'amount:money'], 'rows' => $rows];
    }

    /** Posted vouchers a type to a line. */
    private function rVoucherSum(): array
    {
        $rows = $this->vouchers(['POSTED'])->groupBy('voucher_type')->map(fn ($g, $type) => ['voucher_type' => $type, 'type_name' => __("accounting.vo.vtype.{$type}"), 'vouchers' => $g->count(), 'amount' => $this->m((float) $g->sum('total'))])->sortKeys();

        return ['columns' => ['voucher_type', 'type_name', 'vouchers:num', 'amount:money'], 'rows' => $rows->values()];
    }

    private function vouchers(array $status): Collection
    {
        return DB::table('acc_vouchers')->where('company_id', $this->caller()->companyId)->whereIn('posting_status', $status)
            ->when($this->from(), fn ($w, $x) => $w->where('voucher_date', '>=', $x))->when($this->to(), fn ($w, $x) => $w->where('voucher_date', '<=', $x))
            ->orderBy('voucher_date')->orderBy('voucher_no')->orderBy('voucher_ref_no')->get();
    }

    private function rIncomeDetail(): array
    {
        return $this->income(99);
    }

    private function rIncomeStatement(): array
    {
        return $this->income(FinanceBook::LAYERS['SUB_CLASS']);
    }

    /**
     * Income and expense in the period (from the start of the fiscal year
     * when no From date is given), by ledger or rolled up to the sub class.
     */
    private function income(int $depth): array
    {
        $b = $this->book();
        $from = $this->from() ?? $b->fyStart($this->to() ?? CarbonImmutable::now('Asia/Dhaka')->toDateString());
        $ids = $b->accounts()->filter(fn ($a) => in_array($a->type, ['REVENUE', 'EXPENSE'], true))->keys()->all();
        $move = $this->movement($b->lines($from, $this->to(), $ids)->get());
        $heads = [];
        foreach ($move as $id => $m) {
            $h = $b->atLayer($id, $depth);
            $type = $b->accounts()[$id]->type;
            $heads[$h->id] ??= ['section' => $type, 'code' => $h->code, 'account' => $h->name, 'income' => 0.0, 'expense' => 0.0];
            if ($type === 'REVENUE') {
                $heads[$h->id]['income'] += $m['cr'] - $m['dr'];
            } else {
                $heads[$h->id]['expense'] += $m['dr'] - $m['cr'];
            }
        }
        uasort($heads, fn ($a, $z) => [$a['section'] === 'REVENUE' ? 0 : 1, $a['code']] <=> [$z['section'] === 'REVENUE' ? 0 : 1, $z['code']]);
        $rows = array_values(array_map(fn ($h) => ['section' => __("accounting.vo.rp.type.{$h['section']}"), 'code' => $h['code'], 'account' => $h['account'], 'income' => $h['section'] === 'REVENUE' ? $this->m($h['income']) : null, 'expense' => $h['section'] === 'EXPENSE' ? $this->m($h['expense']) : null], $heads));
        $in = array_sum(array_column($heads, 'income'));
        $ex = array_sum(array_column($heads, 'expense'));

        return ['columns' => ['section', 'code', 'account', 'income:money', 'expense:money'], 'rows' => $rows,
            'summary' => [['label' => __('accounting.vo.rp.sum.income'), 'value' => $this->m($in)], ['label' => __('accounting.vo.rp.sum.expense'), 'value' => $this->m($ex)], ['label' => __('accounting.vo.rp.sum.net'), 'value' => $this->m($in - $ex)]]];
    }

    /**
     * Assets against liabilities and equity at the To date, by sub class;
     * the year's result to date stands in equity as profit or loss.
     */
    private function rBalanceSheet(): array
    {
        $b = $this->book();
        $at = $this->to() ?? CarbonImmutable::now('Asia/Dhaka')->toDateString();
        $bal = $b->opening(CarbonImmutable::parse($at)->addDay()->toDateString());
        $heads = [];
        $result = 0.0;
        foreach ($bal as $id => $v) {
            $type = $b->accounts()[$id]->type ?? null;
            if (in_array($type, ['REVENUE', 'EXPENSE'], true)) {
                $result -= $v;

                continue;
            }
            $h = $b->atLayer($id, FinanceBook::LAYERS['SUB_CLASS']);
            $heads[$h->id] ??= ['section' => $type, 'code' => $h->code, 'account' => $h->name, 'amount' => 0.0];
            $heads[$h->id]['amount'] += $type === 'ASSET' ? $v : -$v;
        }
        $order = ['ASSET' => 0, 'LIABILITY' => 1, 'EQUITY' => 2];
        uasort($heads, fn ($a, $z) => [$order[$a['section']] ?? 9, $a['code']] <=> [$order[$z['section']] ?? 9, $z['code']]);
        $rows = array_values(array_map(fn ($h) => ['section' => __("accounting.vo.rp.type.{$h['section']}"), 'code' => $h['code'], 'account' => $h['account'], 'amount_bal' => $this->m($h['amount'])], array_filter($heads, fn ($h) => round($h['amount'], 2) != 0)));
        $rows[] = ['section' => __('accounting.vo.rp.type.EQUITY'), 'code' => null, 'account' => __('accounting.vo.rp.result'), 'amount_bal' => $this->m($result)];
        $assets = array_sum(array_map(fn ($h) => $h['section'] === 'ASSET' ? $h['amount'] : 0, $heads));
        $claims = array_sum(array_map(fn ($h) => $h['section'] !== 'ASSET' ? $h['amount'] : 0, $heads)) + $result;

        return ['columns' => ['section', 'code', 'account', 'amount_bal:money'], 'rows' => $rows, 'totals' => false,
            'summary' => [['label' => __('accounting.vo.rp.sum.assets'), 'value' => $this->m($assets)], ['label' => __('accounting.vo.rp.sum.claims'), 'value' => $this->m($claims)], ['label' => __('accounting.vo.rp.sum.difference'), 'value' => $this->m($assets - $claims)]]];
    }

    /** Heads at a layer, a voucher type to a line: how many vouchers, what they moved. */
    private function rLedgerVoucherSum(): array
    {
        $b = $this->book();
        $out = [];
        foreach ($this->posted($this->under()) as $l) {
            $h = $b->atLayer($l->account_id, $this->depth());
            $k = $h->code.'|'.$l->voucher_type;
            $out[$k] ??= ['code' => $h->code, 'account' => $h->name, 'voucher_type' => $l->voucher_type, 'v' => [], 'debit' => 0.0, 'credit' => 0.0];
            $out[$k]['v'][$l->voucher_id] = true;
            $out[$k]['debit'] += (float) $l->debit;
            $out[$k]['credit'] += (float) $l->credit;
        }
        ksort($out);
        $rows = array_values(array_map(fn ($r) => ['code' => $r['code'], 'account' => $r['account'], 'voucher_type' => $r['voucher_type'], 'vouchers' => count($r['v']), 'debit' => $this->m($r['debit']), 'credit' => $this->m($r['credit'])], $out));

        return ['columns' => ['code', 'account', 'voucher_type', 'vouchers:num', 'debit:money', 'credit:money'], 'rows' => $rows];
    }

    /** A statement per head at a layer: its opening, each line, the running balance. */
    private function rSubsidiaryStmt(): array
    {
        $b = $this->book();
        $ids = $this->under();
        $open = $b->opening($this->from(), $ids);
        $groups = [];
        foreach ($open as $id => $v) {
            $h = $b->atLayer($id, $this->depth());
            $groups[$h->code]['head'] = $h;
            $groups[$h->code]['open'] = ($groups[$h->code]['open'] ?? 0.0) + $v;
        }
        foreach ($this->posted($ids) as $l) {
            $h = $b->atLayer($l->account_id, $this->depth());
            $groups[$h->code]['head'] = $h;
            $groups[$h->code]['lines'][] = $l;
        }
        ksort($groups);
        $rows = [];
        foreach ($groups as $g) {
            $bal = $g['open'] ?? 0.0;
            if (round($bal, 2) == 0 && empty($g['lines'])) {
                continue;
            }
            $rows[] = ['code' => $g['head']->code, 'account' => $g['head']->name, 'voucher_date' => $this->from(), 'voucher_no' => null, 'voucher_type' => null, 'note' => __('accounting.vo.rp.opening'), 'debit' => null, 'credit' => null, 'balance' => $this->m($bal)];
            foreach ($g['lines'] ?? [] as $l) {
                $bal += (float) $l->debit - (float) $l->credit;
                $rows[] = ['code' => $g['head']->code, 'account' => $b->accounts()[$l->account_id]->name ?? '—', 'voucher_date' => $this->d($l->voucher_date), 'voucher_no' => $l->voucher_no, 'voucher_type' => $l->voucher_type, 'note' => $l->note ?: $l->narration, 'debit' => $this->m((float) $l->debit), 'credit' => $this->m((float) $l->credit), 'balance' => $this->m($bal)];
            }
        }

        return ['columns' => ['code', 'account', 'voucher_date:date', 'voucher_no', 'voucher_type', 'note', 'debit:money', 'credit:money', 'balance:money'], 'rows' => $rows];
    }

    /** The closing stock values recorded (23/04) for the months in the period. */
    private function rStockClosing(): array
    {
        $key = fn (?string $d) => $d ? (int) substr($d, 0, 4) * 100 + (int) substr($d, 5, 2) : null;
        [$a, $z] = [$key($this->from()), $key($this->to())];
        $rows = DB::table('acc_stock_closings')->where('company_id', $this->caller()->companyId)
            ->when($a, fn ($w) => $w->whereRaw('(year * 100 + month) >= ?', [$a]))->when($z, fn ($w) => $w->whereRaw('(year * 100 + month) <= ?', [$z]))
            ->orderBy('year')->orderBy('month')->orderBy('code')->get()
            ->map(fn ($s) => ['month' => CarbonImmutable::create($s->year, $s->month, 1)->format('M/Y'), 'code' => $s->code, 'particular' => $s->particular, 'closing' => $this->m((float) $s->closing)]);

        return ['columns' => ['month', 'code', 'particular', 'closing:money'], 'rows' => $rows, 'totals' => false];
    }

    /* ── Cost centre reports, Receive Payment, Misc. ──────────────── */

    /** Every posted line charged to a cost centre in the period. */
    private function rCcMovement(): array
    {
        $b = $this->book();
        $cc = $this->centres();
        $rows = $this->centreLines()->map(fn ($l) => ['voucher_date' => $this->d($l->voucher_date), 'voucher_no' => $l->voucher_no, 'voucher_type' => $l->voucher_type, 'cost_center' => $cc[$l->cost_center_id] ?? '—', 'account' => $b->label($l->account_id), 'note' => $l->note ?: $l->narration, 'debit' => $this->m((float) $l->debit), 'credit' => $this->m((float) $l->credit)]);

        return ['columns' => ['voucher_date:date', 'voucher_no', 'voucher_type', 'cost_center', 'account', 'note', 'debit:money', 'credit:money'], 'rows' => $rows];
    }

    /**
     * Ledger-wise: an account head broken down by the cost centres it was
     * charged to. Cost-centre-wise (the mirror screen): a cost centre broken
     * down by the heads charged to it.
     */
    private function rLedgerCc(): array
    {
        $b = $this->book();
        $cc = $this->centres();
        $byLedger = $this->screen === 'ledger-cc';
        $rows = $this->centreLines()->groupBy(fn ($l) => $byLedger ? $l->account_id.'|'.$l->cost_center_id : $l->cost_center_id.'|'.$l->account_id)
            ->map(function ($g) use ($b, $cc, $byLedger) {
                $l = $g->first();
                [$dr, $cr] = [(float) $g->sum('debit'), (float) $g->sum('credit')];
                $pair = $byLedger ? ['account' => $b->label($l->account_id), 'cost_center' => $cc[$l->cost_center_id] ?? '—'] : ['cost_center' => $cc[$l->cost_center_id] ?? '—', 'account' => $b->label($l->account_id)];

                return $pair + ['lines' => $g->count(), 'debit' => $this->m($dr), 'credit' => $this->m($cr), 'net' => $this->m($dr - $cr)];
            })->sortBy(fn ($r) => $byLedger ? $r['account'].$r['cost_center'] : $r['cost_center'].$r['account'])->values();

        return ['columns' => [...($byLedger ? ['account', 'cost_center'] : ['cost_center', 'account']), 'lines:num', 'debit:money', 'credit:money', 'net:money'], 'rows' => $rows];
    }

    private function centreLines(): Collection
    {
        return $this->book()->lines($this->from(), $this->to(), $this->under())->whereNotNull('l.cost_center_id')
            ->when($this->q['cost_center'] ?? null, fn ($w, $x) => $w->where('l.cost_center_id', $x))->get();
    }

    /** @return array<string, string> */
    private function centres(): array
    {
        return DB::table('acc_cost_centers')->where('company_id', $this->caller()->companyId)->get(['id', 'code', 'name'])->mapWithKeys(fn ($x) => [$x->id => "{$x->code} — {$x->name}"])->all();
    }

    /** Money received and paid in the period — the cash and bank heads, with who or what each was for. */
    private function rReceivePayment(): array
    {
        $b = $this->book();
        $ids = array_values(array_unique(array_merge($b->classed('CASH'), $b->classed('BANK'), ...array_map(fn ($x) => $b->descendants($x), $b->bankLedgerIds()))));
        $lines = $ids ? $this->posted($ids) : collect();
        $contra = $this->contra($lines, $ids);
        $rows = $lines->map(fn ($l) => ['voucher_date' => $this->d($l->voucher_date), 'voucher_no' => $l->voucher_no, 'voucher_type' => $l->voucher_type, 'account' => $b->label($l->account_id), 'particulars' => $contra[$l->voucher_id] ?? '—', 'voucher_for' => $l->voucher_for, 'note' => $l->note ?: $l->narration, 'received' => $this->m((float) $l->debit), 'paid' => $this->m((float) $l->credit)]);
        [$in, $out] = [(float) $lines->sum('debit'), (float) $lines->sum('credit')];

        return ['columns' => ['voucher_date:date', 'voucher_no', 'voucher_type', 'account', 'particulars', 'voucher_for', 'note', 'received:money', 'paid:money'], 'rows' => $rows,
            'summary' => [['label' => __('accounting.vo.rp.sum.received'), 'value' => $this->m($in)], ['label' => __('accounting.vo.rp.sum.paid'), 'value' => $this->m($out)], ['label' => __('accounting.vo.rp.sum.net_cash'), 'value' => $this->m($in - $out)]]];
    }

    /** The voucher log (23/03) in the period: every step taken on a voucher, by whom and when. */
    private function rVoucherAudit(): array
    {
        $tz = 'Asia/Dhaka';
        $rows = DB::table('acc_voucher_logs as g')->leftJoin('users as u', 'u.id', '=', 'g.user_id')->where('g.company_id', $this->caller()->companyId)
            ->when($this->from(), fn ($w, $x) => $w->where('g.created_at', '>=', CarbonImmutable::parse($x, $tz)->startOfDay()->utc()))
            ->when($this->to(), fn ($w, $x) => $w->where('g.created_at', '<=', CarbonImmutable::parse($x, $tz)->endOfDay()->utc()))
            ->orderBy('g.created_at')->orderBy('g.id')->get(['g.*', 'u.name as by'])
            ->map(function ($g) use ($tz) {
                $snap = json_decode($g->snapshot, true) ?: [];

                return ['at' => CarbonImmutable::parse($g->created_at)->timezone($tz)->format('Y-m-d H:i'), 'voucher_no' => $g->voucher_no ?? $g->voucher_ref_no, 'voucher_type' => $snap['voucher_type'] ?? null, 'action' => __("accounting.vo.au.action.{$g->action}"), 'posting_status' => __("accounting.vo.posting.{$g->posting_status}"), 'by' => $g->by, 'narration' => $snap['narration'] ?? null, 'amount' => $this->m((float) ($snap['amount'] ?? 0))];
            });

        return ['columns' => ['at', 'voucher_no', 'voucher_type', 'action', 'posting_status', 'by', 'narration', 'amount:money'], 'rows' => $rows, 'totals' => false];
    }
}

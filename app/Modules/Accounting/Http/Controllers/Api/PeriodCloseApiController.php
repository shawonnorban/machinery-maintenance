<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers\Api;

use App\Modules\Accounting\Support\AccountingDefaults;
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
 * Finance & Ledger → Period Close (AnnotechERP 23/04).
 *
 * - Stock Value Closing: one closing figure per stock head, company and
 *   month; the worksheet lists every stock head (accounts classed INVENTORY,
 *   or, with none classed, those under an Inventories group, plus any code
 *   entered by hand) and writes only the figures that changed.
 * - Bank Statement Reconciliation: posted lines on a bank account — those on
 *   its linked ledger come by themselves, others are brought on by hand —
 *   ticked as cleared with the date the bank saw them. Book balance is every
 *   line, bank balance the cleared ones; a cleared line's voucher can no
 *   longer be unposted.
 * - Fiscal Year Closing: counts what was posted in the year and its result
 *   (revenue less expense) and closes it; a closed year takes no more
 *   postings. A year with vouchers still waiting to be posted is not closed.
 */
class PeriodCloseApiController extends ApiController
{
    /* ── Stock Value Closing ───────────────────────────────────────── */

    public function stockOptions(): JsonResponse
    {
        $this->allowEither('accounting.period.view', 'accounting.period.manage');
        $c = $this->caller()->companyId;
        AccountingDefaults::fiscalYears($c);
        $years = DB::table('acc_fiscal_years')->where('company_id', $c)->get(['fy_start', 'fy_end'])
            ->flatMap(fn ($f) => range((int) substr((string) $f->fy_start, 0, 4), (int) substr((string) $f->fy_end, 0, 4)))->unique()->sortDesc()->values();

        return ApiResponse::ok(['companies' => [['value' => $c, 'label' => $this->company()]], 'years' => $years->map(fn ($y) => ['value' => (string) $y, 'label' => (string) $y])->all(), 'today' => CarbonImmutable::now('Asia/Dhaka')->toDateString()]);
    }

    public function stockIndex(): JsonResponse
    {
        $this->allowEither('accounting.period.view', 'accounting.period.manage');
        $company = $this->company();

        return ApiResponse::ok(DB::table('acc_stock_closings')->where('company_id', $this->caller()->companyId)->orderByDesc('year')->orderByDesc('month')->orderBy('code')->get()
            ->map(fn ($r) => ['id' => $r->id, 'company' => $company, 'year' => (int) $r->year, 'month' => (int) $r->month, 'period' => CarbonImmutable::create((int) $r->year, (int) $r->month, 1)->format('M Y'), 'code' => $r->code, 'particular' => $r->particular, 'closing' => (float) $r->closing])->values()->all());
    }

    /** Every stock head with its figure for the month, if any. */
    public function worksheet(Request $request): JsonResponse
    {
        $this->allowEither('accounting.period.view', 'accounting.period.manage');
        $d = $request->validate(['year' => ['required', 'integer', 'between:2000,2100'], 'month' => ['required', 'integer', 'between:1,12']]);
        $c = $this->caller()->companyId;
        $saved = DB::table('acc_stock_closings')->where('company_id', $c)->where('year', $d['year'])->where('month', $d['month'])->get()->keyBy('code');
        $heads = $this->stockHeads();
        foreach ($saved as $code => $r) {
            $heads[$code] ??= $r->particular;
        }
        ksort($heads);

        return ApiResponse::ok(collect($heads)->map(fn ($name, $code) => ['code' => (string) $code, 'particular' => $name, 'closing' => isset($saved[$code]) ? (float) $saved[$code]->closing : null])->values()->all());
    }

    /** The worksheet saved together: only the figures that changed are written. */
    public function saveWorksheet(Request $request): JsonResponse
    {
        $this->allow('accounting.period.manage');
        $d = $request->validate(['year' => ['required', 'integer', 'between:2000,2100'], 'month' => ['required', 'integer', 'between:1,12'], 'rows' => ['required', 'array'], 'rows.*.code' => ['required', 'string', 'max:24'], 'rows.*.particular' => ['required', 'string', 'max:160'], 'rows.*.closing' => ['nullable', 'numeric', 'min:0', 'max:999999999999']], [], self::attrs());
        $this->assertOpenMonth((int) $d['year'], (int) $d['month']);
        $changed = 0;
        DB::transaction(function () use ($d, &$changed) {
            foreach ($d['rows'] as $row) {
                if (($row['closing'] ?? null) === null || $row['closing'] === '') {
                    continue;
                }
                $changed += $this->putClosing((int) $d['year'], (int) $d['month'], $row['code'], $row['particular'], (float) $row['closing']);
            }
        });

        return ApiResponse::ok(['changed' => $changed]);
    }

    /** New stock closing: one figure; the same company, year, month and code replaces it. */
    public function stockStore(Request $request): JsonResponse
    {
        $this->allow('accounting.period.manage');
        $d = $request->validate(['year' => ['required', 'integer', 'between:2000,2100'], 'month' => ['required', 'integer', 'between:1,12'], 'code' => ['required', 'string', 'max:24'], 'particular' => ['required', 'string', 'max:160'], 'closing' => ['required', 'numeric', 'min:0', 'max:999999999999']],
            ['closing.required' => __('accounting.vo.v.sc_closing')], self::attrs());
        $this->assertOpenMonth((int) $d['year'], (int) $d['month']);
        $this->putClosing((int) $d['year'], (int) $d['month'], $d['code'], $d['particular'], (float) $d['closing']);

        return ApiResponse::created(['code' => $d['code']]);
    }

    public function stockUpdate(Request $request, string $id): JsonResponse
    {
        $this->allow('accounting.period.manage');
        $r = DB::table('acc_stock_closings')->where('company_id', $this->caller()->companyId)->where('id', $id)->first() ?? abort(404);
        $d = $request->validate(['particular' => ['required', 'string', 'max:160'], 'closing' => ['required', 'numeric', 'min:0', 'max:999999999999']], ['closing.required' => __('accounting.vo.v.sc_closing')], self::attrs());
        $this->assertOpenMonth((int) $r->year, (int) $r->month);
        DB::table('acc_stock_closings')->where('id', $id)->update(['particular' => $d['particular'], 'closing' => round((float) $d['closing'], 4), 'updated_by' => $this->caller()->auditUserId(), 'updated_at' => now()]);

        return ApiResponse::ok(['id' => $id]);
    }

    public function stockDestroy(string $id): JsonResponse
    {
        $this->allow('accounting.period.manage');
        $r = DB::table('acc_stock_closings')->where('company_id', $this->caller()->companyId)->where('id', $id)->first() ?? abort(404);
        $this->assertOpenMonth((int) $r->year, (int) $r->month);
        DB::table('acc_stock_closings')->where('id', $id)->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Bank Statement Reconciliation ─────────────────────────────── */

    public function bankOptions(): JsonResponse
    {
        $this->allowEither('accounting.period.view', 'accounting.period.manage');

        return ApiResponse::ok(['companies' => [['value' => $this->caller()->companyId, 'label' => $this->company()]], 'accounts' => $this->bankAccounts()->map(fn ($a) => ['value' => $a->id, 'label' => $a->label])->values()->all(), 'today' => CarbonImmutable::now('Asia/Dhaka')->toDateString()]);
    }

    /** Every bank line, on every account. */
    public function bankIndex(): JsonResponse
    {
        $this->allowEither('accounting.period.view', 'accounting.period.manage');

        return ApiResponse::ok($this->bankLines()->values()->all());
    }

    /** The bank accounts, each with its lines and balances (master-detail). */
    public function bankBoard(): JsonResponse
    {
        $this->allowEither('accounting.period.view', 'accounting.period.manage');
        $lines = $this->bankLines()->groupBy('bank_account_id');

        return ApiResponse::ok($this->bankAccounts()->map(function ($a) use ($lines) {
            $g = $lines->get($a->id, collect());

            return ['id' => $a->id, 'label' => $a->label, 'count' => $g->count(), 'lines' => $g->values()->all()] + $this->balances($g);
        })->values()->all());
    }

    /** One account's lines saved together: cheque, bank date, cleared and remarks. */
    public function saveBank(Request $request, string $account): JsonResponse
    {
        $this->allow('accounting.period.manage');
        $c = $this->caller()->companyId;
        $acc = $this->bankAccounts()->firstWhere('id', $account) ?? abort(404);
        $d = $request->validate(['rows' => ['required', 'array'], 'rows.*.voucher_line_id' => ['required', 'string'], 'rows.*.cheque_no' => ['nullable', 'string', 'max:48'], 'rows.*.bank_date' => ['nullable', 'date_format:Y-m-d'], 'rows.*.cleared' => ['boolean'], 'rows.*.remarks' => ['nullable', 'string', 'max:300']], [], self::attrs());
        $mine = $this->bankLines()->where('bank_account_id', $acc->id)->keyBy('voucher_line_id');
        foreach ($d['rows'] as $n => $row) {
            $line = $mine[$row['voucher_line_id']] ?? null;
            if (! $line) {
                throw ValidationException::withMessages(["rows.{$n}.voucher_line_id" => __('accounting.vo.v.br_line')]);
            }
            $this->checkBankRow($row, $line['voucher_date'], $line['voucher_no'], "rows.{$n}");
        }
        DB::transaction(function () use ($d, $c, $acc) {
            foreach ($d['rows'] as $row) {
                $this->putBankLine($c, $row['voucher_line_id'], $acc->id, $row);
            }
        });

        return ApiResponse::ok(['saved' => count($d['rows'])]);
    }

    /** Posted lines on bank-class accounts or bank ledgers not yet on any reconciliation. */
    public function candidates(): JsonResponse
    {
        $this->allowEither('accounting.period.view', 'accounting.period.manage');
        $c = $this->caller()->companyId;
        $ledgers = $this->bankLedgerIds();

        return ApiResponse::ok($this->postedLines()->whereIn('l.account_id', $ledgers)->whereNotExists(fn ($q) => $q->from('acc_bank_lines as b')->whereColumn('b.voucher_line_id', 'l.id'))->get()
            ->map(fn ($l) => ['value' => $l->id, 'label' => "{$l->voucher_no} · ".substr((string) $l->voucher_date, 0, 10)." · {$l->code} · ".((float) $l->debit > 0 ? 'Dr '.number_format((float) $l->debit, 2) : 'Cr '.number_format((float) $l->credit, 2))])->values()->all());
    }

    /** New bank line: a posted voucher line brought onto an account's reconciliation. */
    public function addBankLine(Request $request): JsonResponse
    {
        $this->allow('accounting.period.manage');
        $c = $this->caller()->companyId;
        $d = $request->validate(['voucher_line_id' => ['required', 'string'], 'bank_account_id' => ['required', Rule::exists('acc_bank_accounts', 'id')->where('company_id', $c)], 'cheque_no' => ['nullable', 'string', 'max:48'], 'bank_date' => ['nullable', 'date_format:Y-m-d'], 'cleared' => ['boolean'], 'remarks' => ['nullable', 'string', 'max:300']],
            ['voucher_line_id.required' => __('accounting.vo.v.br_line_required')], self::attrs());
        $line = $this->postedLines()->where('l.id', $d['voucher_line_id'])->first();
        if (! $line) {
            throw ValidationException::withMessages(['voucher_line_id' => __('accounting.vo.v.br_line')]);
        }
        if (DB::table('acc_bank_lines')->where('voucher_line_id', $line->id)->exists()) {
            throw ValidationException::withMessages(['voucher_line_id' => __('accounting.vo.v.br_taken', ['no' => $line->voucher_no])]);
        }
        $this->checkBankRow($d, substr((string) $line->voucher_date, 0, 10), $line->voucher_no, '');
        $this->putBankLine($c, $line->id, $d['bank_account_id'], $d);

        return ApiResponse::created(['voucher_line_id' => $line->id]);
    }

    /** Takes a line off the reconciliation — never one the bank has cleared. */
    public function removeBankLine(string $line): JsonResponse
    {
        $this->allow('accounting.period.manage');
        $b = DB::table('acc_bank_lines')->where('company_id', $this->caller()->companyId)->where('voucher_line_id', $line)->first() ?? abort(404);
        if ($b->cleared) {
            throw ValidationException::withMessages(['id' => __('accounting.vo.v.br_cleared_keep')]);
        }
        DB::table('acc_bank_lines')->where('id', $b->id)->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Fiscal Year Closing ───────────────────────────────────────── */

    public function fyIndex(): JsonResponse
    {
        $this->allowEither('accounting.period.view', 'accounting.period.manage');
        $c = $this->caller()->companyId;
        $company = $this->company();
        $rows = DB::table('acc_fiscal_year_closings as x')->join('acc_fiscal_years as f', 'f.id', '=', 'x.fiscal_year_id')->leftJoin('users as u', 'u.id', '=', 'x.closed_by')->where('x.company_id', $c)->orderByDesc('f.fy_start')
            ->get(['x.id', 'x.vouchers_posted', 'x.closing_balance', 'x.remarks', 'x.closed_at', 'f.name', 'f.fy_start', 'f.fy_end', 'u.name as by']);

        return ApiResponse::ok($rows->map(fn ($r) => ['id' => $r->id, 'company' => $company, 'period' => $this->fyLabel($r), 'vouchers' => (int) $r->vouchers_posted, 'balance' => (float) $r->closing_balance, 'remarks' => $r->remarks, 'closed_by' => $r->by,
            'closed_at' => $r->closed_at ? CarbonImmutable::parse($r->closed_at)->timezone('Asia/Dhaka')->toDateTimeString() : null])->values()->all());
    }

    /** The fiscal years still open, as the closing process picks them. */
    public function fyOptions(): JsonResponse
    {
        $this->allowEither('accounting.period.view', 'accounting.period.manage');
        $c = $this->caller()->companyId;
        AccountingDefaults::fiscalYears($c);

        return ApiResponse::ok(['periods' => DB::table('acc_fiscal_years')->where('company_id', $c)->where('closed', false)->orderBy('fy_start')->get(['id', 'name', 'fy_start', 'fy_end'])->map(fn ($f) => ['value' => $f->id, 'label' => $this->company().' — '.$this->fyLabel($f)])->values()->all()]);
    }

    public function closeYear(Request $request): JsonResponse
    {
        $this->allow('accounting.period.manage');
        $c = $this->caller()->companyId;
        $d = $request->validate(['fiscal_year_id' => ['required', Rule::exists('acc_fiscal_years', 'id')->where('company_id', $c)], 'remarks' => ['nullable', 'string', 'max:2000']], [], self::attrs());
        $fy = DB::table('acc_fiscal_years')->where('id', $d['fiscal_year_id'])->first();
        if ($fy->closed) {
            throw ValidationException::withMessages(['fiscal_year_id' => __('accounting.vo.v.fy_closed', ['fy' => $fy->name])]);
        }
        $range = fn ($q) => $q->where('company_id', $c)->whereBetween('voucher_date', [$fy->fy_start, $fy->fy_end]);
        $waiting = DB::table('acc_vouchers')->tap($range)->whereIn('posting_status', ['DRAFT', 'UNPOSTED'])->count();
        if ($waiting > 0) {
            throw ValidationException::withMessages(['fiscal_year_id' => __('accounting.vo.v.fy_waiting', ['fy' => $fy->name, 'n' => $waiting])]);
        }
        $posted = DB::table('acc_vouchers')->tap($range)->where('posting_status', 'POSTED')->count();
        $result = (float) DB::table('acc_voucher_lines as l')->join('acc_vouchers as v', 'v.id', '=', 'l.voucher_id')->join('acc_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('v.company_id', $c)->where('v.posting_status', 'POSTED')->whereBetween('v.voucher_date', [$fy->fy_start, $fy->fy_end])->whereIn('a.type', ['REVENUE', 'EXPENSE'])->selectRaw('coalesce(sum(l.credit - l.debit), 0) as r')->value('r');
        DB::transaction(function () use ($c, $fy, $d, $posted, $result) {
            DB::table('acc_fiscal_year_closings')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $c, 'fiscal_year_id' => $fy->id, 'vouchers_posted' => $posted, 'closing_balance' => round($result, 4), 'remarks' => $d['remarks'] ?? null,
                'closed_by' => $this->caller()->auditUserId(), 'closed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('acc_fiscal_years')->where('id', $fy->id)->update(['closed' => true, 'updated_at' => now()]);
        });

        return ApiResponse::created(['period' => $this->fyLabel($fy), 'vouchers' => $posted, 'balance' => round($result, 2)]);
    }

    /** Removing a closing reopens its year. */
    public function reopenYear(string $id): JsonResponse
    {
        $this->allow('accounting.period.manage');
        $x = DB::table('acc_fiscal_year_closings')->where('company_id', $this->caller()->companyId)->where('id', $id)->first() ?? abort(404);
        DB::transaction(function () use ($x) {
            DB::table('acc_fiscal_years')->where('id', $x->fiscal_year_id)->update(['closed' => false, 'updated_at' => now()]);
            DB::table('acc_fiscal_year_closings')->where('id', $x->id)->delete();
        });

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Helpers ───────────────────────────────────────────────────── */

    /** @return array<string, string> code => name, the company's stock heads. */
    private function stockHeads(): array
    {
        $c = $this->caller()->companyId;
        AccountingDefaults::accounts($c);
        $accounts = DB::table('acc_accounts')->where('company_id', $c)->where('active', true)->get(['id', 'code', 'name', 'parent_id', 'postable']);
        $roots = DB::table('acc_account_classes')->where('company_id', $c)->where('account_type', 'INVENTORY')->pluck('account_id')->all();
        if (! $roots) {
            $roots = $accounts->filter(fn ($a) => ! $a->postable && str_starts_with(mb_strtolower($a->name), 'inventor'))->pluck('id')->all();
        }
        $children = $accounts->groupBy('parent_id');
        $out = [];
        $walk = function (string $id) use (&$walk, &$out, $children, $accounts) {
            $a = $accounts->firstWhere('id', $id);
            if ($a && $a->postable) {
                $out[$a->code] = $a->name;
            }
            foreach ($children->get($id, collect()) as $k) {
                $walk($k->id);
            }
        };
        foreach ($roots as $r) {
            $walk($r);
        }

        return $out;
    }

    private function putClosing(int $year, int $month, string $code, string $particular, float $closing): int
    {
        $c = $this->caller()->companyId;
        $now = now();
        $old = DB::table('acc_stock_closings')->where('company_id', $c)->where('year', $year)->where('month', $month)->where('code', $code)->first();
        if ($old && abs((float) $old->closing - $closing) < 0.00005 && $old->particular === $particular) {
            return 0;
        }
        if ($old) {
            DB::table('acc_stock_closings')->where('id', $old->id)->update(['particular' => $particular, 'closing' => round($closing, 4), 'updated_by' => $this->caller()->auditUserId(), 'updated_at' => $now]);
        } else {
            DB::table('acc_stock_closings')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $c, 'year' => $year, 'month' => $month, 'code' => $code, 'particular' => $particular, 'closing' => round($closing, 4), 'updated_by' => $this->caller()->auditUserId(), 'created_at' => $now, 'updated_at' => $now]);
        }

        return 1;
    }

    /** A month whose fiscal year is closed takes no more figures. */
    private function assertOpenMonth(int $year, int $month): void
    {
        $date = sprintf('%04d-%02d-01', $year, $month);
        $fy = DB::table('acc_fiscal_years')->where('company_id', $this->caller()->companyId)->where('fy_start', '<=', $date)->where('fy_end', '>=', $date)->first(['name', 'closed']);
        if ($fy?->closed) {
            throw ValidationException::withMessages(['month' => __('accounting.vo.v.fy_closed', ['fy' => $fy->name])]);
        }
    }

    private function book(): FinanceBook
    {
        return new FinanceBook($this->caller()->companyId);
    }

    private function bankAccounts(): Collection
    {
        return $this->book()->bankAccounts();
    }

    private function bankLedgerIds(): array
    {
        return $this->book()->bankLedgerIds();
    }

    private function postedLines()
    {
        return $this->book()->postedLines();
    }

    private function bankLines(): Collection
    {
        return $this->book()->bankLines();
    }

    private function balances(Collection $lines): array
    {
        return $this->book()->bankBalances($lines);
    }

    private function checkBankRow(array $row, string $voucherDate, ?string $no, string $prefix): void
    {
        $key = fn ($k) => $prefix === '' ? $k : "{$prefix}.{$k}";
        if (! empty($row['cleared']) && empty($row['bank_date'])) {
            throw ValidationException::withMessages([$key('bank_date') => __('accounting.vo.v.br_date', ['no' => $no])]);
        }
        if (! empty($row['bank_date']) && $row['bank_date'] < $voucherDate) {
            throw ValidationException::withMessages([$key('bank_date') => __('accounting.vo.v.br_before', ['no' => $no])]);
        }
    }

    private function putBankLine(string $c, string $lineId, string $accountId, array $row): void
    {
        $values = ['bank_account_id' => $accountId, 'cheque_no' => $row['cheque_no'] ?? null, 'bank_date' => $row['bank_date'] ?? null, 'cleared' => (bool) ($row['cleared'] ?? false), 'remarks' => $row['remarks'] ?? null, 'updated_by' => $this->caller()->auditUserId(), 'updated_at' => now()];
        if (DB::table('acc_bank_lines')->where('voucher_line_id', $lineId)->exists()) {
            DB::table('acc_bank_lines')->where('voucher_line_id', $lineId)->update($values);
        } else {
            DB::table('acc_bank_lines')->insert($values + ['id' => strtolower((string) Str::ulid()), 'company_id' => $c, 'voucher_line_id' => $lineId, 'created_at' => now()]);
        }
    }

    private function fyLabel(object $f): string
    {
        return $f->name.' ('.CarbonImmutable::parse($f->fy_start)->format('d M Y').' to '.CarbonImmutable::parse($f->fy_end)->format('d M Y').')';
    }

    private function company(): string
    {
        return (string) DB::table('companies')->where('id', $this->caller()->companyId)->value('name');
    }

    /** @return array<string, string> */
    public static function attrs(): array
    {
        $a = [];
        foreach (['year', 'month', 'code', 'particular', 'closing', 'voucher_line_id', 'bank_account_id', 'cheque_no', 'bank_date', 'remarks', 'fiscal_year_id'] as $k) {
            $a[$k] = __("accounting.vo.attr.pc_{$k}");
            $a["rows.*.{$k}"] = __("accounting.vo.attr.pc_{$k}");
        }

        return $a;
    }
}

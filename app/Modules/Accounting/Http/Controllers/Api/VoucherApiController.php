<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers\Api;

use App\Modules\Accounting\Models\AccVoucher;
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
 * Finance & Ledger → Voucher Entry (AnnotechERP 23/02): Draft Voucher Entry,
 * Voucher Posting Control and the Statutory Voucher Register.
 *
 * A voucher is a header and its lines, saved together; every line carries a
 * debit or a credit (never both) and the two sides must agree. A draft gets
 * its reference on save (VR-2026-000001) and stays editable; approval
 * commits it — numbered by type (JV-2026-000001), out of the draft list and
 * waiting to be posted. Posting checks the balance again; unposting takes it
 * back out of the ledger. The voucher date must fall in an open fiscal year.
 *
 * Maker-Checker (23/01): where a voucher type has grants, only a maker may
 * raise it and only a checker may approve it — never the one who raised it.
 *
 * Voucher Entry (23/03) raises a voucher straight into the ledger's register
 * (`commit`): numbered on save, unposted, amendable until it is posted, and
 * posted only once approved. Every change is logged with the voucher as it
 * then stood (acc_voucher_logs) for Voucher Audit (23/06).
 */
class VoucherApiController extends ApiController
{
    public const TYPES = ['BPV', 'BRV', 'CPV', 'CRV', 'CV', 'BV', 'JV'];

    public const SOURCES = ['FINANCE', 'COMMERCIAL', 'STORE', 'HR', 'SYSTEM'];

    public const ACTIONS = ['CREATE_VOUCHER', 'SUBMIT_FOR_APPROVAL', 'APPROVED', 'REJECTED', 'ON_HOLD'];

    public const FOR_TYPES = ['GENERAL', 'SUPPLIER', 'BUYER', 'EMPLOYEE', 'PARTY'];

    public const POSTING = ['DRAFT', 'UNPOSTED', 'POSTED', 'CANCELLED'];

    public function options(): JsonResponse
    {
        $this->allowEither('accounting.voucher.view', 'accounting.voucher.manage');
        $c = $this->caller()->companyId;
        AccountingDefaults::accounts($c);
        AccountingDefaults::fiscalYears($c);
        $rates = DB::table('tx_exchange_rates')->where('company_id', $c)->orderBy('effective_date')->pluck('rate', 'currency')->map(fn ($r) => (float) $r);
        $currencies = DB::table('currencies')->where('company_id', $c)->where('active', true)->orderBy('code')->pluck('code')->push('BDT')->unique()->sort()->values();

        return ApiResponse::ok([
            'today' => CarbonImmutable::now('Asia/Dhaka')->toDateString(),
            'companies' => [['value' => $c, 'label' => (string) DB::table('companies')->where('id', $c)->value('name')]],
            'accounts' => DB::table('acc_accounts')->where('company_id', $c)->where('postable', true)->where('active', true)->orderBy('code')->get(['id', 'code', 'name'])->map(fn ($a) => ['value' => $a->id, 'label' => "{$a->code} — {$a->name}"])->all(),
            'cost_centers' => DB::table('acc_cost_centers')->where('company_id', $c)->where('active', true)->orderBy('code')->get(['id', 'code', 'name'])->map(fn ($a) => ['value' => $a->id, 'label' => "{$a->code} — {$a->name}"])->all(),
            'currencies' => $currencies->map(fn ($code) => ['value' => $code, 'label' => $code, 'rate' => $code === 'BDT' ? 1.0 : ($rates[$code] ?? null)])->all(),
        ]);
    }

    /** Drafts (`?scope=draft`), or the committed vouchers. */
    public function index(Request $request): JsonResponse
    {
        $this->allowEither('accounting.voucher.view', 'accounting.voucher.manage');
        $draft = $request->query('scope') === 'draft';
        $rows = AccVoucher::query()->when($draft, fn ($q) => $q->where('posting_status', 'DRAFT'), fn ($q) => $q->where('posting_status', '!=', 'DRAFT'))->orderByDesc('voucher_date')->orderByDesc('created_at')->get();

        return ApiResponse::ok($this->rows($rows));
    }

    public function show(string $id): JsonResponse
    {
        $this->allowEither('accounting.voucher.view', 'accounting.voucher.manage');

        return ApiResponse::ok($this->body(AccVoucher::query()->findOrFail($id)));
    }

    /** The last voucher number issued for a type. */
    public function lastNo(Request $request): JsonResponse
    {
        $this->allowEither('accounting.voucher.view', 'accounting.voucher.manage');
        $type = (string) $request->query('type');

        return ApiResponse::ok(['voucher_no' => AccVoucher::query()->where('voucher_type', $type)->whereNotNull('voucher_no')->orderByDesc('voucher_no')->value('voucher_no')]);
    }

    public function save(Request $request, ?string $id = null): JsonResponse
    {
        $this->allow('accounting.voucher.manage');
        $c = $this->caller()->companyId;
        $me = $this->caller()->auditUserId();
        $v = $id ? AccVoucher::query()->findOrFail($id) : null;
        // A draft is amended on Draft Voucher Entry; a committed voucher on Voucher Entry until it is posted.
        $commit = $v ? $v->posting_status === 'UNPOSTED' : $request->boolean('commit');
        if ($v && ! in_array($v->posting_status, ['DRAFT', 'UNPOSTED'], true)) {
            throw ValidationException::withMessages(['voucher_date' => __('accounting.vo.v.locked', ['no' => $v->voucher_no ?? $v->voucher_ref_no])]);
        }
        $d = $request->validate([
            'voucher_date' => ['required', 'date'],
            'voucher_type' => ['required', Rule::in(self::TYPES)],
            'trx_source' => ['nullable', Rule::in(self::SOURCES)],
            'bill_no' => ['nullable', 'string', 'max:48'],
            'voucher_for_type' => ['nullable', Rule::in(self::FOR_TYPES)],
            'voucher_for' => ['nullable', 'string', 'max:160'],
            'narration' => ['nullable', 'string', 'max:2000'],
            'action_status' => ['required', Rule::in(self::ACTIONS)],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['required', Rule::exists('acc_accounts', 'id')->where('company_id', $c)->where('postable', true)->where('active', true)],
            'lines.*.cost_center_id' => ['nullable', Rule::exists('acc_cost_centers', 'id')->where('company_id', $c)],
            'lines.*.bill_no' => ['nullable', 'string', 'max:48'],
            'lines.*.note' => ['nullable', 'string', 'max:500'],
            'lines.*.ref_no' => ['nullable', 'string', 'max:48'],
            'lines.*.currency' => ['nullable', 'string', 'size:3'],
            'lines.*.rate' => ['nullable', 'numeric', 'gt:0', 'max:99999'],
            'lines.*.value' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
        ], [], self::attrs());
        ksort($d['lines']);
        $lines = array_values($d['lines']);
        [$dr, $cr] = $this->checkLines($lines);
        $this->assertOpenYear($d['voucher_date']);
        if ($v?->voucher_no && $d['voucher_type'] !== $v->voucher_type) {
            throw ValidationException::withMessages(['voucher_type' => __('accounting.vo.v.type_fixed', ['no' => $v->voucher_no])]);
        }
        // Approving is the checker's act; anything else on a draft is the maker's.
        if ($d['action_status'] === 'APPROVED') {
            $this->assertChecker($d['voucher_type'], $me, $v?->created_by ?? $me);
        } else {
            $this->assertMaker($d['voucher_type'], $me);
        }

        $saved = DB::transaction(function () use ($d, $v, $c, $me, $lines, $dr, $commit) {
            $head = collect($d)->except('lines')->all() + ['company_id' => $c, 'total' => round($dr, 4), 'voucher_for_type' => $d['voucher_for_type'] ?? 'GENERAL', 'updated_by' => $me];
            $approving = $d['action_status'] === 'APPROVED' && $v?->action_status !== 'APPROVED';
            if ($approving) {
                $head += ['approved_by' => $me, 'approved_at' => now()];
            } elseif ($d['action_status'] !== 'APPROVED') {
                $head += ['approved_by' => null, 'approved_at' => null];
            }
            // Committed on approval (a draft) or on save (Voucher Entry): numbered by type, waiting to be posted.
            if (($commit || $d['action_status'] === 'APPROVED') && ! $v?->voucher_no) {
                $head += ['posting_status' => 'UNPOSTED', 'voucher_no' => $this->nextNo($d['voucher_type'], 'voucher_no', $d['voucher_date'])];
            }
            $created = ! $v;
            if ($v) {
                $v->update($head);
                DB::table('acc_voucher_lines')->where('voucher_id', $v->id)->delete();
            } else {
                $v = AccVoucher::query()->create($head + ['voucher_ref_no' => $this->nextNo('VR', 'voucher_ref_no', $d['voucher_date']), 'created_by' => $me]);
            }
            $now = now();
            DB::table('acc_voucher_lines')->insert(array_map(fn ($l, $i) => ['id' => strtolower((string) Str::ulid()), 'company_id' => $c, 'voucher_id' => $v->id, 'account_id' => $l['account_id'], 'cost_center_id' => $l['cost_center_id'] ?? null,
                'bill_no' => $l['bill_no'] ?? null, 'note' => $l['note'] ?? null, 'ref_no' => $l['ref_no'] ?? null, 'currency' => strtoupper($l['currency'] ?? 'BDT'), 'rate' => (float) ($l['rate'] ?? 1), 'value' => isset($l['value']) && $l['value'] !== '' ? (float) $l['value'] : null,
                'debit' => round((float) ($l['debit'] ?? 0), 4), 'credit' => round((float) ($l['credit'] ?? 0), 4), 'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now], $lines, array_keys($lines)));
            $v = $v->fresh();
            $this->log($v, $created ? 'CREATE' : 'UPDATE');
            if ($approving) {
                $this->log($v, 'APPROVE');
            }

            return $v;
        });

        return $id ? ApiResponse::ok($this->body($saved)) : ApiResponse::created($this->body($saved));
    }

    public function destroy(string $id): JsonResponse
    {
        $this->allow('accounting.voucher.manage');
        $v = AccVoucher::query()->findOrFail($id);
        if (! in_array($v->posting_status, ['DRAFT', 'UNPOSTED'], true)) {
            throw ValidationException::withMessages(['voucher_date' => __('accounting.vo.v.locked', ['no' => $v->voucher_no ?? $v->voucher_ref_no])]);
        }
        DB::transaction(function () use ($v) {
            $this->log($v, 'DELETE');
            $v->delete();
        });

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ── Voucher Posting Control ───────────────────────────────────── */

    /** The company list with its committed vouchers (master-detail). */
    public function posting(): JsonResponse
    {
        $this->allowEither('accounting.voucher.view', 'accounting.voucher.post');
        $c = $this->caller()->companyId;
        $rows = AccVoucher::query()->where('posting_status', '!=', 'DRAFT')->orderByDesc('voucher_date')->orderByDesc('voucher_no')->get();
        $sums = $this->sides($rows->pluck('id')->all());

        return ApiResponse::ok(['companies' => [['id' => $c, 'label' => (string) DB::table('companies')->where('id', $c)->value('name'), 'count' => $rows->count()]],
            'vouchers' => $rows->map(fn (AccVoucher $v) => ['id' => $v->id, 'voucher_no' => $v->voucher_no, 'ref_no' => $v->voucher_ref_no, 'bill_no' => $v->bill_no ?? $sums[$v->id]['bill_no'] ?? null, 'date' => $v->voucher_date->toDateString(), 'type' => $v->voucher_type,
                'debit' => $sums[$v->id]['debit'] ?? 0.0, 'credit' => $sums[$v->id]['credit'] ?? 0.0, 'status' => $v->posting_status, 'prepared_by' => $this->userName($v->created_by)])->values()->all()]);
    }

    public function post(string $id): JsonResponse
    {
        $this->allow('accounting.voucher.post');
        $v = AccVoucher::query()->findOrFail($id);
        if ($v->posting_status !== 'UNPOSTED') {
            throw ValidationException::withMessages(['id' => __('accounting.vo.v.not_unposted', ['no' => $v->voucher_no ?? $v->voucher_ref_no])]);
        }
        $s = $this->sides([$v->id])[$v->id] ?? ['debit' => 0, 'credit' => 0];
        if (abs($s['debit'] - $s['credit']) > 0.005 || $s['debit'] <= 0) {
            throw ValidationException::withMessages(['id' => __('accounting.vo.v.unbalanced', ['dr' => number_format($s['debit'], 2), 'cr' => number_format($s['credit'], 2)])]);
        }
        if ($v->action_status !== 'APPROVED') {
            throw ValidationException::withMessages(['id' => __('accounting.vo.v.not_approved', ['no' => $v->voucher_no])]);
        }
        $this->assertOpenYear($v->voucher_date->toDateString());
        $v->update(['posting_status' => 'POSTED', 'posted_by' => $this->caller()->auditUserId(), 'posted_at' => now(), 'updated_by' => $this->caller()->auditUserId()]);
        $this->log($v->fresh(), 'POST');

        return ApiResponse::ok(['id' => $v->id, 'posting_status' => 'POSTED']);
    }

    public function unpost(string $id): JsonResponse
    {
        $this->allow('accounting.voucher.post');
        $v = AccVoucher::query()->findOrFail($id);
        if ($v->posting_status !== 'POSTED') {
            throw ValidationException::withMessages(['id' => __('accounting.vo.v.not_posted', ['no' => $v->voucher_no ?? $v->voucher_ref_no])]);
        }
        // The bank has seen that money (Bank Statement Reconciliation, 23/04).
        if (DB::table('acc_bank_lines as b')->join('acc_voucher_lines as l', 'l.id', '=', 'b.voucher_line_id')->where('l.voucher_id', $v->id)->where('b.cleared', true)->exists()) {
            throw ValidationException::withMessages(['id' => __('accounting.vo.v.bank_cleared', ['no' => $v->voucher_no])]);
        }
        $this->assertOpenYear($v->voucher_date->toDateString());
        $v->update(['posting_status' => 'UNPOSTED', 'posted_by' => null, 'posted_at' => null, 'updated_by' => $this->caller()->auditUserId()]);
        $this->log($v->fresh(), 'UNPOST');

        return ApiResponse::ok(['id' => $v->id, 'posting_status' => 'UNPOSTED']);
    }

    /* ── Statutory Voucher Register ────────────────────────────────── */

    public function statutory(): JsonResponse
    {
        $this->allowEither('accounting.voucher.view', 'accounting.voucher.manage');
        $c = $this->caller()->companyId;
        $company = (string) DB::table('companies')->where('id', $c)->value('name');
        $rows = DB::table('acc_voucher_lines as l')->join('acc_vouchers as v', 'v.id', '=', 'l.voucher_id')->join('acc_compliance_rules as r', 'r.account_id', '=', 'l.account_id')->join('acc_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('v.company_id', $c)->where('v.posting_status', 'POSTED')->orderByDesc('v.voucher_date')->orderBy('v.voucher_no')->orderBy('l.sort_order')
            ->get(['v.voucher_no', 'v.voucher_date', 'a.code', 'a.name', 'l.debit', 'l.credit', 'r.pct']);

        return ApiResponse::ok($rows->map(function ($r) use ($company) {
            $amount = round((float) $r->debit + (float) $r->credit, 2);

            return ['voucher' => $r->voucher_no, 'date' => substr((string) $r->voucher_date, 0, 10), 'company' => $company, 'ledger' => "{$r->code} — {$r->name}", 'amount' => $amount, 'pct' => (float) $r->pct, 'qualifying' => round($amount * (float) $r->pct / 100, 2)];
        })->values()->all());
    }

    /* ── Rules ─────────────────────────────────────────────────────── */

    /** @return array{0: float, 1: float} Debits and credits, each line one side, the two in agreement. */
    private function checkLines(array $lines): array
    {
        $dr = 0.0;
        $cr = 0.0;
        foreach ($lines as $n => $l) {
            $d = round((float) ($l['debit'] ?? 0), 4);
            $c = round((float) ($l['credit'] ?? 0), 4);
            if (($d > 0) === ($c > 0)) {
                throw ValidationException::withMessages(["lines.{$n}.debit" => __('accounting.vo.v.line_side', ['n' => $n + 1])]);
            }
            $dr += $d;
            $cr += $c;
        }
        if (abs($dr - $cr) > 0.005) {
            throw ValidationException::withMessages(['lines' => __('accounting.vo.v.unbalanced', ['dr' => number_format($dr, 2), 'cr' => number_format($cr, 2)])]);
        }

        return [$dr, $cr];
    }

    private function assertOpenYear(string $date): void
    {
        AccountingDefaults::fiscalYears($this->caller()->companyId);
        $fy = DB::table('acc_fiscal_years')->where('company_id', $this->caller()->companyId)->where('fy_start', '<=', $date)->where('fy_end', '>=', $date)->first(['name', 'closed']);
        if (! $fy) {
            throw ValidationException::withMessages(['voucher_date' => __('accounting.vo.v.fy_none', ['date' => $date])]);
        }
        if ($fy->closed) {
            throw ValidationException::withMessages(['voucher_date' => __('accounting.vo.v.fy_closed', ['fy' => $fy->name])]);
        }
    }

    /** Where the type has grants, only a maker may raise it. */
    private function assertMaker(string $type, ?string $user): void
    {
        $grants = $this->grants($type);
        if ($grants->isNotEmpty() && ! $grants->contains(fn ($g) => $g->user_id === $user && $g->can_make)) {
            throw ValidationException::withMessages(['voucher_type' => __('accounting.vo.v.not_maker', ['type' => __("accounting.vo.vtype.{$type}")])]);
        }
    }

    /** Where the type has grants, only a checker may approve it, and never the one who raised it. */
    private function assertChecker(string $type, ?string $user, ?string $maker): void
    {
        $grants = $this->grants($type);
        if ($grants->isEmpty()) {
            return;
        }
        if (! $grants->contains(fn ($g) => $g->user_id === $user && $g->can_check)) {
            throw ValidationException::withMessages(['action_status' => __('accounting.vo.v.not_checker', ['type' => __("accounting.vo.vtype.{$type}")])]);
        }
        if ($user === $maker) {
            throw ValidationException::withMessages(['action_status' => __('accounting.vo.v.self_check')]);
        }
    }

    private function grants(string $type): Collection
    {
        return DB::table('acc_voucher_grants')->where('company_id', $this->caller()->companyId)->where('voucher_type', $type)->where(fn ($w) => $w->where('can_make', true)->orWhere('can_check', true))->get(['user_id', 'can_make', 'can_check']);
    }

    /** The voucher as it now stands, header and lines, kept against what was done to it. */
    private function log(AccVoucher $v, string $action): void
    {
        DB::table('acc_voucher_logs')->insert(['id' => strtolower((string) Str::ulid()), 'company_id' => $v->company_id, 'voucher_id' => $v->id, 'voucher_no' => $v->voucher_no, 'voucher_ref_no' => $v->voucher_ref_no, 'action' => $action,
            'posting_status' => $v->posting_status, 'snapshot' => json_encode($this->body($v), JSON_UNESCAPED_UNICODE), 'user_id' => $this->caller()->auditUserId(), 'created_at' => now()]);
    }

    /** PREFIX-YYYY-000001, per company, prefix and year. */
    private function nextNo(string $prefix, string $column, string $date): string
    {
        $base = $prefix.'-'.substr($date, 0, 4).'-';
        $last = AccVoucher::query()->where($column, 'like', $base.'%')->orderByDesc($column)->value($column);

        return $base.str_pad((string) ((int) substr((string) $last, strlen($base)) + 1), 6, '0', STR_PAD_LEFT);
    }

    /* ── Bodies ────────────────────────────────────────────────────── */

    /** @return array<string, array{debit: float, credit: float, bill_no: ?string}> */
    private function sides(array $ids): array
    {
        return DB::table('acc_voucher_lines')->whereIn('voucher_id', $ids)->groupBy('voucher_id')->selectRaw('voucher_id, sum(debit) as dr, sum(credit) as cr, max(bill_no) as bill')->get()
            ->mapWithKeys(fn ($r) => [$r->voucher_id => ['debit' => round((float) $r->dr, 2), 'credit' => round((float) $r->cr, 2), 'bill_no' => $r->bill]])->all();
    }

    private function rows(Collection $rows): array
    {
        $sums = $this->sides($rows->pluck('id')->all());
        $company = (string) DB::table('companies')->where('id', $this->caller()->companyId)->value('name');

        return $rows->map(fn (AccVoucher $v) => ['id' => $v->id, 'voucher_no' => $v->voucher_no, 'voucher_ref_no' => $v->voucher_ref_no, 'voucher_date' => $v->voucher_date->toDateString(), 'company' => $company, 'voucher_type' => $v->voucher_type, 'trx_source' => $v->trx_source,
            'bill_no' => $v->bill_no ?? $sums[$v->id]['bill_no'] ?? null, 'voucher_for_type' => $v->voucher_for_type, 'voucher_for' => $v->voucher_for, 'amount' => $v->total, 'posting_status' => $v->posting_status, 'action_status' => $v->action_status,
            'balanced' => isset($sums[$v->id]) && abs($sums[$v->id]['debit'] - $sums[$v->id]['credit']) <= 0.005, 'prepared_by' => $this->userName($v->created_by), 'updated_by' => $this->userName($v->updated_by),
            'created_at' => $v->created_at?->timezone('Asia/Dhaka')->toDateTimeString(), 'updated_at' => $v->updated_at?->timezone('Asia/Dhaka')->toDateTimeString(), 'posted_at' => $v->posted_at?->timezone('Asia/Dhaka')->toDateTimeString(),
            'locked' => $v->posting_status !== 'DRAFT'])->values()->all();
    }

    /** @return array<string, mixed> */
    private function body(AccVoucher $v): array
    {
        $lines = DB::table('acc_voucher_lines')->where('voucher_id', $v->id)->orderBy('sort_order')->get();

        return array_merge($this->rows(collect([$v]))[0], ['narration' => $v->narration, 'lines' => $lines->map(fn ($l) => ['account_id' => $l->account_id, 'cost_center_id' => $l->cost_center_id, 'bill_no' => $l->bill_no, 'note' => $l->note, 'ref_no' => $l->ref_no,
            'currency' => $l->currency, 'rate' => (float) $l->rate, 'value' => $l->value === null ? null : (float) $l->value, 'debit' => (float) $l->debit, 'credit' => (float) $l->credit])->values()->all()]);
    }

    private function userName(?string $id): ?string
    {
        static $names = [];

        return $id ? ($names[$id] ??= DB::table('users')->where('id', $id)->value('name')) : null;
    }

    /** @return array<string, string> */
    public static function attrs(): array
    {
        $a = [];
        foreach (['voucher_date', 'voucher_type', 'trx_source', 'bill_no', 'voucher_for_type', 'voucher_for', 'narration', 'action_status', 'lines'] as $k) {
            $a[$k] = __("accounting.vo.attr.{$k}");
        }
        foreach (['account_id', 'cost_center_id', 'bill_no', 'note', 'ref_no', 'currency', 'rate', 'value', 'debit', 'credit'] as $k) {
            $a["lines.*.{$k}"] = __("accounting.vo.attr.line_{$k}");
        }

        return $a;
    }
}

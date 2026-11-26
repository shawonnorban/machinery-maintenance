<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers\Api;

use App\Modules\Accounting\Models\AccAccount;
use App\Modules\Accounting\Models\AccBankAccount;
use App\Modules\Accounting\Models\AccChequeBook;
use App\Modules\Accounting\Models\AccChequeLeaf;
use App\Modules\Accounting\Models\AccCostCenterGroup;
use App\Modules\Accounting\Models\AccPaymentMode;
use App\Modules\Accounting\Models\AccVoucherGrant;
use App\Modules\Accounting\Support\AccountingDefaults;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Settings\Models\Currency;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\TradeExport\Models\TxBank;
use App\Modules\TradeExport\Models\TxBankBranch;
use App\Modules\TradeExport\Support\TradeExportDefaults;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Finance & Ledger → Master Data (AnnotechERP 23/01): what its forms pick
 * from; the chart's tree; maker-checker grants per voucher type; a bank's
 * branches; and a cheque book's leaves — each grid saved as a whole.
 */
class AccountingApiController extends ApiController
{
    public function lookups(): JsonResponse
    {
        $this->allow('accounting.master.view');
        $company = $this->caller()->companyId;
        AccountingDefaults::accounts($company);
        TradeExportDefaults::banks($company);
        $accounts = AccAccount::query()->where('active', true)->orderBy('code')->get(['id', 'code', 'name', 'type', 'postable']);
        $branches = TxBankBranch::query()->where('active', true)->orderBy('name')->get(['id', 'bank_id', 'name']);
        $members = CompanyUser::query()->where('company_id', $company)->where('status', 'ACTIVE')->pluck('user_id');
        $banks = TxBank::query()->where('active', true)->orderBy('name')->get(['id', 'code', 'name']);
        $bankAccounts = AccBankAccount::query()->where('active', true)->orderBy('account_no')->get(['id', 'account_no', 'account_name', 'account_type', 'bank_id', 'branch_id']);
        $bankNames = $banks->pluck('name', 'id');
        $branchNames = $branches->pluck('name', 'id');

        return ApiResponse::ok([
            'company' => ['value' => $company, 'label' => Company::query()->whereKey($company)->value('name')],
            'factories' => Factory::query()->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($f) => ['value' => $f->id, 'label' => "{$f->name} ({$f->code})"])->all(),
            'groups' => AccCostCenterGroup::query()->where('active', true)->orderBy('display_order')->orderBy('name')->get(['id', 'name'])->map(fn ($g) => ['value' => $g->id, 'label' => $g->name])->all(),
            'parents' => $accounts->where('postable', false)->values()->map(fn ($a) => ['value' => $a->id, 'label' => "{$a->code} — {$a->name}", 'type' => $a->type])->all(),
            'ledgers' => $accounts->where('postable', true)->values()->map(fn ($a) => ['value' => $a->id, 'label' => "{$a->code} — {$a->name}"])->all(),
            'accounts' => $accounts->map(fn ($a) => ['value' => $a->id, 'label' => "{$a->code} — {$a->name}"])->all(),
            'banks' => $banks->map(fn ($b) => ['value' => $b->id, 'label' => "{$b->code} — {$b->name}"])->all(),
            'branches' => $branches->groupBy('bank_id')->map(fn ($b) => $b->map(fn ($x) => ['value' => $x->id, 'label' => $x->name])->values())->all(),
            'all_branches' => $branches->map(fn ($x) => ['value' => $x->id, 'label' => $x->name])->all(),
            'bank_accounts' => $bankAccounts->map(fn ($a) => ['value' => $a->id, 'label' => "{$a->account_no} — {$a->account_name}", 'type' => $a->account_type, 'bank' => $bankNames[$a->bank_id] ?? '', 'branch' => $branchNames[$a->branch_id] ?? ''])->all(),
            'countries' => OriginCountry::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            'currencies' => ($own = Currency::query()->where('active', true)->orderBy('code')->get(['code', 'name']))->isNotEmpty()
                ? $own->map(fn ($c) => ['value' => $c->code, 'label' => "{$c->code} — {$c->name}"])->all()
                : array_map(fn (string $code) => ['value' => $code, 'label' => $code], Currency::CODES),
            'users' => User::query()->whereIn('id', $members)->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all(),
            'references' => [
                'BUYER' => Buyer::query()->where('active', true)->orderBy('short_name')->get(['id', 'short_name'])->map(fn ($b) => ['value' => $b->id, 'label' => $b->short_name])->all(),
                'SUPPLIER' => MaterialSupplier::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->all(),
                'EMPLOYEE' => User::query()->whereIn('id', $members)->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all(),
                'BANK' => $banks->map(fn ($b) => ['value' => $b->id, 'label' => $b->name])->all(),
                'BANK_ACCOUNT' => $bankAccounts->map(fn ($a) => ['value' => $a->id, 'label' => "{$a->account_no} — {$a->account_name}"])->all(),
            ],
        ]);
    }

    /** The chart's tree: every group, and every top-level head, with how many accounts sit under it. */
    public function tree(): JsonResponse
    {
        $this->allow('accounting.master.view');
        AccountingDefaults::accounts($this->caller()->companyId);
        $all = AccAccount::query()->orderBy('code')->get(['id', 'code', 'name', 'parent_id', 'postable']);
        $children = $all->groupBy('parent_id')->map->count();
        $depth = function ($node) use ($all, &$depth): int {
            return $node->parent_id ? 1 + $depth($all->firstWhere('id', $node->parent_id)) : 0;
        };

        return ApiResponse::ok($all->filter(fn ($a) => ! $a->postable || ! $a->parent_id)->values()->map(fn ($a) => [
            'key' => $a->id, 'label' => str_repeat('— ', $depth($a))."{$a->code} · {$a->name}", 'hint' => $a->postable ? 'ledger head' : 'group', 'count' => (int) ($children[$a->id] ?? 0),
        ])->all());
    }

    public function childrenOf(string $account): JsonResponse
    {
        $this->allow('accounting.master.view');
        $node = AccAccount::query()->whereKey($account)->firstOrFail();
        $children = AccAccount::query()->where('parent_id', $node->id)->selectRaw('parent_id, count(*) as n')->groupBy('parent_id');

        return ApiResponse::ok(AccAccount::query()->where('parent_id', $node->id)->orderBy('code')->get()->map(fn ($a) => [
            'id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'type' => $a->type, 'postable' => $a->postable, 'active' => $a->active,
        ])->all());
    }

    /** Voucher types with how many users hold a grant on each. */
    public function voucherTypes(): JsonResponse
    {
        $this->allow('accounting.master.view');
        $counts = AccVoucherGrant::query()->selectRaw('voucher_type, count(*) as n')->groupBy('voucher_type')->pluck('n', 'voucher_type');

        return ApiResponse::ok(array_map(fn ($t) => ['key' => $t, 'count' => (int) ($counts[$t] ?? 0)], AccPaymentMode::VOUCHER_TYPES));
    }

    public function grantsOf(string $type): JsonResponse
    {
        $this->allow('accounting.master.view');
        abort_unless(in_array($type, AccPaymentMode::VOUCHER_TYPES, true), 404);

        return ApiResponse::ok(AccVoucherGrant::query()->where('voucher_type', $type)->orderBy('created_at')->get()->map(fn ($g) => ['user_id' => $g->user_id, 'can_make' => $g->can_make, 'can_check' => $g->can_check])->all());
    }

    /** Maker-Checker Setup: a voucher type's grants as a whole; each line raises, approves, or both. */
    public function saveGrants(Request $request, string $type): JsonResponse
    {
        $this->allow('accounting.master.manage');
        abort_unless(in_array($type, AccPaymentMode::VOUCHER_TYPES, true), 404);
        $company = $this->caller()->companyId;
        $members = CompanyUser::query()->where('company_id', $company)->where('status', 'ACTIVE')->pluck('user_id')->all();
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.user_id' => ['required', 'distinct', Rule::in($members)],
            'rows.*.can_make' => ['sometimes', 'boolean'],
            'rows.*.can_check' => ['sometimes', 'boolean'],
        ], ['rows.*.user_id.required' => __('accounting.master.line_needs_user'), 'rows.*.user_id.distinct' => __('accounting.master.user_once')]);
        foreach ($data['rows'] as $row) {
            if (! ($row['can_make'] ?? false) && ! ($row['can_check'] ?? false)) {
                throw ValidationException::withMessages(['rows' => __('accounting.master.grant_needed')]);
            }
        }

        DB::transaction(function () use ($data, $type, $company): void {
            AccVoucherGrant::query()->where('voucher_type', $type)->delete();
            foreach ($data['rows'] as $row) {
                AccVoucherGrant::query()->create(['company_id' => $company, 'voucher_type' => $type, 'user_id' => $row['user_id'], 'can_make' => (bool) ($row['can_make'] ?? false), 'can_check' => (bool) ($row['can_check'] ?? false)]);
            }
        });

        return $this->grantsOf($type);
    }

    /** Banks with how many branches each holds — the Branches tab. */
    public function banks(): JsonResponse
    {
        $this->allow('accounting.master.view');
        TradeExportDefaults::banks($this->caller()->companyId);
        $counts = TxBankBranch::query()->selectRaw('bank_id, count(*) as n')->groupBy('bank_id')->pluck('n', 'bank_id');

        return ApiResponse::ok(TxBank::query()->orderBy('name')->get(['id', 'code', 'name', 'active'])->map(fn ($b) => ['key' => $b->id, 'label' => "{$b->code} — {$b->name}", 'hint' => $b->active ? 'active' : 'inactive', 'count' => (int) ($counts[$b->id] ?? 0)])->all());
    }

    public function branchesOf(string $bank): JsonResponse
    {
        $this->allow('accounting.master.view');
        $row = TxBank::query()->whereKey($bank)->firstOrFail();
        $uses = AccBankAccount::query()->whereNotNull('branch_id')->selectRaw('branch_id, count(*) as n')->groupBy('branch_id')->pluck('n', 'branch_id');

        return ApiResponse::ok(TxBankBranch::query()->where('bank_id', $row->id)->orderBy('name')->get()
            ->map(fn ($b) => $b->only(['id', 'code', 'name', 'address', 'swift_code', 'routing_no', 'contact_person', 'phone', 'email', 'active']) + ['accounts' => (int) ($uses[$b->id] ?? 0)])->all());
    }

    /** A bank's branches as a whole: edited, added, and those no account is held at removed. */
    public function saveBranchesOf(Request $request, string $bank): JsonResponse
    {
        $this->allow('accounting.master.manage');
        $row = TxBank::query()->whereKey($bank)->firstOrFail();
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.id' => ['nullable', Rule::exists('tx_bank_branches', 'id')->where('bank_id', $row->id)],
            'rows.*.code' => ['required', 'string', 'max:24'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.address' => ['nullable', 'string', 'max:255'],
            'rows.*.swift_code' => ['nullable', 'string', 'max:16'],
            'rows.*.routing_no' => ['nullable', 'string', 'max:16'],
            'rows.*.contact_person' => ['nullable', 'string', 'max:255'],
            'rows.*.phone' => ['nullable', 'string', 'max:32'],
            'rows.*.email' => ['nullable', 'email', 'max:255'],
            'rows.*.active' => ['sometimes', 'boolean'],
        ], ['rows.*.code.required' => __('accounting.master.branch_needs_code'), 'rows.*.name.required' => __('accounting.master.branch_needs_name')]);
        $codes = array_map(fn ($r) => strtoupper(trim($r['code'])), $data['rows']);
        if (count($codes) !== count(array_unique($codes))) {
            throw ValidationException::withMessages(['rows' => __('accounting.master.branch_code_twice')]);
        }
        $kept = array_filter(array_column($data['rows'], 'id'));
        $dropped = TxBankBranch::query()->where('bank_id', $row->id)->whereNotIn('id', $kept)->pluck('id');
        if ($dropped->isNotEmpty() && AccBankAccount::query()->whereIn('branch_id', $dropped)->exists()) {
            throw ValidationException::withMessages(['rows' => __('accounting.master.branch_in_use')]);
        }

        DB::transaction(function () use ($data, $row, $company, $dropped): void {
            TxBankBranch::query()->whereIn('id', $dropped)->delete();
            foreach ($data['rows'] as $line) {
                $values = ['code' => strtoupper(trim($line['code'])), 'name' => trim($line['name'])] + array_intersect_key($line, array_flip(['address', 'swift_code', 'routing_no', 'contact_person', 'phone', 'email'])) + ['active' => (bool) ($line['active'] ?? true)];
                empty($line['id'])
                    ? TxBankBranch::query()->create($values + ['company_id' => $company, 'bank_id' => $row->id])
                    : TxBankBranch::query()->whereKey($line['id'])->firstOrFail()->update($values);
            }
        });

        return $this->branchesOf($row->id);
    }

    /** Cheque books with how many leaves each holds — the Cheque Leaf Info tab. */
    public function chequeBooks(): JsonResponse
    {
        $this->allow('accounting.master.view');
        $accounts = AccBankAccount::query()->pluck('account_no', 'id');
        $counts = AccChequeLeaf::query()->selectRaw('book_id, count(*) as n')->groupBy('book_id')->pluck('n', 'book_id');

        return ApiResponse::ok(AccChequeBook::query()->orderByDesc('receive_date')->get(['id', 'book_no', 'bank_account_id', 'finalized'])->map(fn ($b) => [
            'key' => $b->id, 'label' => "{$b->book_no} — ".($accounts[$b->bank_account_id] ?? '?'), 'hint' => $b->finalized ? 'finalised' : 'open', 'count' => (int) ($counts[$b->id] ?? 0),
        ])->all());
    }

    public function leavesOf(string $book): JsonResponse
    {
        $this->allow('accounting.master.view');
        $row = AccChequeBook::query()->whereKey($book)->firstOrFail();

        return ApiResponse::ok(AccChequeLeaf::query()->where('book_id', $row->id)->orderBy('number')->get()->map(fn ($l) => ['id' => $l->id, 'cheque_no' => $l->cheque_no, 'status' => $l->status, 'active' => $l->active])->all());
    }

    /** A book's leaves as a whole: only Active? changes — an issued leaf is deactivated, never deleted. */
    public function saveLeavesOf(Request $request, string $book): JsonResponse
    {
        $this->allow('accounting.master.manage');
        $row = AccChequeBook::query()->whereKey($book)->firstOrFail();
        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.id' => ['required', Rule::exists('acc_cheque_leaves', 'id')->where('book_id', $row->id)],
            'rows.*.active' => ['required', 'boolean'],
        ]);
        if (count($data['rows']) !== AccChequeLeaf::query()->where('book_id', $row->id)->count()) {
            throw ValidationException::withMessages(['rows' => __('accounting.master.leaf_not_deleted')]);
        }
        DB::transaction(function () use ($data): void {
            foreach ($data['rows'] as $line) {
                AccChequeLeaf::query()->whereKey($line['id'])->update(['active' => (bool) $line['active']]);
            }
        });

        return $this->leavesOf($row->id);
    }
}

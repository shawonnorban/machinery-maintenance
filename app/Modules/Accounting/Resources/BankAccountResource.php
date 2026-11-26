<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Resources;

use App\Modules\Accounting\Models\AccAccount;
use App\Modules\Accounting\Models\AccBankAccount;
use App\Modules\TradeExport\Models\TxBank;
use App\Modules\TradeExport\Models\TxBankBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Bank Account Master → the company's own accounts: number and name, the bank
 * and branch they are held at, type, currency and the ledger head they post
 * to. Cheque books are issued against them. Finance & Ledger → Master Data
 * (AnnotechERP 23/01).
 */
class BankAccountResource extends AccountingResource
{
    public function key(): string
    {
        return 'acc-bank-accounts';
    }

    public function model(): string
    {
        return AccBankAccount::class;
    }

    public function columns(): array
    {
        return ['bank_id', 'branch_id', 'account_no', 'account_name', 'account_type', 'currency', 'ledger_id', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true];
    }

    public function query(): Builder
    {
        return AccBankAccount::query()->orderBy('account_no');
    }

    public function messages(): array
    {
        return ['account_no.required' => __('accounting.master.account_no_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();

        return [
            'bank_id' => [$required, Rule::exists('tx_banks', 'id')->where('company_id', $company)],
            'branch_id' => ['nullable', Rule::exists('tx_bank_branches', 'id')->where('company_id', $company)],
            'account_no' => [$required, 'string', 'max:32', $this->uniqueText(AccBankAccount::class, 'account_no', $row, __('accounting.master.account_no_taken'))],
            'account_name' => [$required, 'string', 'max:255'],
            'account_type' => [$required, Rule::in(AccBankAccount::TYPES)],
            'currency' => [$required, 'string', 'size:3'],
            'ledger_id' => ['nullable', Rule::exists('acc_accounts', 'id')->where('company_id', $company)->where('postable', true)],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $p = array_merge($row?->only(['bank_id', 'branch_id']) ?? [], $data);
        if (! empty($p['branch_id']) && TxBankBranch::query()->whereKey($p['branch_id'])->value('bank_id') !== $p['bank_id']) {
            throw ValidationException::withMessages(['branch_id' => __('accounting.master.branch_other_bank')]);
        }
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['bank'] = TxBank::query()->whereKey($row->getAttribute('bank_id'))->value('name');
        $out['branch'] = $row->getAttribute('branch_id') ? TxBankBranch::query()->whereKey($row->getAttribute('branch_id'))->value('name') : null;
        $ledger = $row->getAttribute('ledger_id') ? AccAccount::query()->whereKey($row->getAttribute('ledger_id'))->first(['code', 'name']) : null;
        $out['ledger'] = $ledger ? "{$ledger->code} — {$ledger->name}" : null;

        return $out;
    }
}

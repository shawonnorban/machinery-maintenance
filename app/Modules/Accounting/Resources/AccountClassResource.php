<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Resources;

use App\Modules\Accounting\Models\AccAccount;
use App\Modules\Accounting\Models\AccAccountClass;
use App\Modules\Accounting\Support\AccountingDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Account Type Configuration: which account type (cash, bank, receivable …)
 * and layer each chart-of-accounts node is classified under — one
 * classification per account per layer, so a report never has two to choose
 * between. Finance & Ledger → Master Data (AnnotechERP 23/01).
 */
class AccountClassResource extends AccountingResource
{
    public function key(): string
    {
        return 'acc-account-classes';
    }

    public function model(): string
    {
        return AccAccountClass::class;
    }

    public function columns(): array
    {
        return ['account_type', 'layer', 'account_id'];
    }

    public function deletable(): bool
    {
        return true;
    }

    public function query(): Builder
    {
        AccountingDefaults::accounts($this->company());

        return AccAccountClass::query()->orderBy('created_at')->orderBy('id');
    }

    public function messages(): array
    {
        return ['account_type.required' => __('accounting.master.account_type_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'account_type' => [$required, Rule::in(AccAccountClass::ACCOUNT_TYPES)],
            'layer' => [$required, Rule::in(AccAccountClass::LAYERS)],
            'account_id' => [$required, Rule::exists('acc_accounts', 'id')->where('company_id', $this->company())],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $p = array_merge($row?->only(['layer', 'account_id']) ?? [], $data);
        if (AccAccountClass::query()->where('account_id', $p['account_id'])->where('layer', $p['layer'])->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
            throw ValidationException::withMessages(['account_id' => __('accounting.master.class_taken')]);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $account = AccAccount::query()->whereKey($row->getAttribute('account_id'))->first(['code', 'name']);
        $out['account'] = $account ? "{$account->code} — {$account->name}" : null;

        return $out;
    }
}

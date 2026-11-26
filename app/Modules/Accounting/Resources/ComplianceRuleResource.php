<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Resources;

use App\Modules\Accounting\Models\AccAccount;
use App\Modules\Accounting\Models\AccComplianceRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Statutory Ledger Rules: the share of a ledger head a compliance voucher may
 * touch. One rule per head — saving the same head again replaces its
 * percentage rather than adding a second rule. Finance & Ledger → Master Data
 * (AnnotechERP 23/01).
 */
class ComplianceRuleResource extends AccountingResource
{
    public function key(): string
    {
        return 'acc-compliance-rules';
    }

    public function model(): string
    {
        return AccComplianceRule::class;
    }

    public function columns(): array
    {
        return ['account_id', 'pct'];
    }

    public function deletable(): bool
    {
        return true;
    }

    public function query(): Builder
    {
        return AccComplianceRule::query()->orderBy('created_at');
    }

    public function messages(): array
    {
        return ['pct.required' => __('accounting.master.pct_required'), 'account_id.required' => __('accounting.master.ledger_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'pct' => [$required, 'numeric', 'min:0', 'max:100'],
            'account_id' => [$required, Rule::exists('acc_accounts', 'id')->where('company_id', $this->company())->where('postable', true)],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $account = $data['account_id'] ?? $row?->account_id;
        // The same head again replaces its rule.
        AccComplianceRule::query()->where('account_id', $account)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->delete();

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

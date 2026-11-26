<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Accounting\Models\AccAccount;
use App\Modules\Accounting\Models\AccPaymentMode;
use App\Modules\Merchandise\Models\AccReceipt;
use App\Modules\Merchandise\Models\ProcPostingSetup;
use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Procurement Posting Setup: per company, the voucher type a finalised
 * accessories receipt's draft voucher is numbered on and its ledger heads —
 * Inventory and VAT Receivable on the debit side, Supplier, TDS and VDS
 * Payable on the credit side. One setup per company; a receipt for a company
 * with no active setup is not finalised. Trims & Accessories (AnnotechERP 06/06).
 */
class ProcPostingSetupResource extends MasterResource
{
    private const LABELS = ['unit_company_id' => 'company', 'voucher_type' => 'voucher_type', 'inventory_ledger_id' => 'inventory', 'vat_ledger_id' => 'vat',
        'payable_ledger_id' => 'payable', 'tds_ledger_id' => 'tds', 'vds_ledger_id' => 'vds'];

    public function key(): string
    {
        return 'proc-posting-setups';
    }

    public function model(): string
    {
        return ProcPostingSetup::class;
    }

    public function viewPermission(): string
    {
        return 'merchandise.accessories.view';
    }

    public function managePermission(): string
    {
        return 'merchandise.accessories.manage';
    }

    public function columns(): array
    {
        return ['unit_company_id', 'voucher_type', ...ProcPostingSetup::LEDGERS, 'active'];
    }

    public function query(): Builder
    {
        return ProcPostingSetup::query()->orderBy('created_at');
    }

    protected function upperCase(): array
    {
        return [];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();
        $ledger = [$required, Rule::exists('acc_accounts', 'id')->where('company_id', $company)->where('postable', true)];

        return [
            'unit_company_id' => [$required, Rule::in([$company]), function (string $attribute, mixed $value, \Closure $fail) use ($row): void {
                if (ProcPostingSetup::query()->where('unit_company_id', $value)->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists()) {
                    $fail(__('merchandise.master.pps.one_per_company'));
                }
            }],
            'voucher_type' => [$required, Rule::in(AccPaymentMode::VOUCHER_TYPES)],
            'inventory_ledger_id' => $ledger,
            'vat_ledger_id' => $ledger,
            'payable_ledger_id' => $ledger,
            'tds_ledger_id' => $ledger,
            'vds_ledger_id' => $ledger,
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return collect(self::LABELS)->mapWithKeys(fn (string $label, string $field) => ["{$field}.required" => __('merchandise.acc_po.required', ['field' => __("merchandise.master.pps.f.{$label}")])])->all();
    }

    /** The company's setup is what its receipts post to: kept, never removed, once a receipt stands on it — switch it off instead. */
    public function beforeDelete(Model $row): void
    {
        if (AccReceipt::query()->where('unit_company_id', $row->getAttribute('unit_company_id'))->where('status', 'FINALIZED')->exists()) {
            throw ValidationException::withMessages(['id' => __('merchandise.master.in_use')]);
        }
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $ledgers = AccAccount::query()->whereIn('id', collect(ProcPostingSetup::LEDGERS)->map(fn ($c) => $row->getAttribute($c))->filter())->get(['id', 'code', 'name'])->keyBy('id');
        foreach (ProcPostingSetup::LEDGERS as $column) {
            $l = $ledgers[$row->getAttribute($column)] ?? null;
            $out[str_replace('_id', '', $column)] = $l ? "{$l->code} — {$l->name}" : null;
        }
        $out['company'] = Company::query()->whereKey($row->getAttribute('unit_company_id'))->value('name');

        return $out;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Resources;

use App\Modules\TradeExport\Models\TxBankBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * A bank's branch in the Bank Directory: its code, name, address, SWIFT and
 * routing number and who to contact. Trade & Export → Master Data
 * (AnnotechERP 22/01).
 */
class BankBranchResource extends TradeExportResource
{
    public function key(): string
    {
        return 'tx-bank-branches';
    }

    public function model(): string
    {
        return TxBankBranch::class;
    }

    public function columns(): array
    {
        return ['bank_id', 'code', 'name', 'address', 'swift_code', 'routing_no', 'contact_person', 'phone', 'email', 'active'];
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
        return TxBankBranch::query()->orderBy('name');
    }

    public function messages(): array
    {
        return ['code.required' => __('tradeexport.master.branch_code_required'), 'name.required' => __('tradeexport.master.branch_name_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $bank = request()->input('bank_id', $row?->getAttribute('bank_id'));

        return [
            'bank_id' => [$row ? 'prohibited' : 'required', Rule::exists('tx_banks', 'id')->where('company_id', $this->company())],
            'code' => [$required, 'string', 'max:24', $this->uniqueText(TxBankBranch::class, 'code', $row, __('tradeexport.master.branch_code_taken'), ['bank_id' => $bank])],
            'name' => [$required, 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'swift_code' => ['nullable', 'string', 'max:16'],
            'routing_no' => ['nullable', 'string', 'max:16'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}

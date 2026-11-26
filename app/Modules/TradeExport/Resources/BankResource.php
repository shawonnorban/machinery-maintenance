<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Resources;

use App\Modules\TradeExport\Models\TxBank;
use App\Modules\TradeExport\Models\TxBankBranch;
use App\Modules\TradeExport\Support\TradeExportDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Bank Directory: the banks the commercial documents name — code, names,
 * prefix, whether local, country and SWIFT — each with its branches. Trade &
 * Export → Master Data (AnnotechERP 22/01).
 */
class BankResource extends TradeExportResource
{
    public function key(): string
    {
        return 'tx-banks';
    }

    public function model(): string
    {
        return TxBank::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'name_bn', 'prefix', 'is_local', 'country_id', 'swift_code', 'iban', 'call_center', 'web_url', 'active'];
    }

    public function booleans(): array
    {
        return ['is_local', 'active'];
    }

    public function defaults(): array
    {
        return ['is_local' => true, 'active' => true];
    }

    public function query(): Builder
    {
        TradeExportDefaults::banks($this->company());

        return TxBank::query()->orderBy('name');
    }

    public function messages(): array
    {
        return ['code.required' => __('tradeexport.master.bank_code_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:24', $this->uniqueText(TxBank::class, 'code', $row, __('tradeexport.master.bank_code_taken'))],
            'name' => [$required, 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'prefix' => [$required, 'string', 'max:16'],
            'is_local' => ['sometimes', 'boolean'],
            'country_id' => [$required, Rule::exists('origin_countries', 'id')->where('company_id', $this->company())],
            'swift_code' => [$required, 'string', 'max:16'],
            'iban' => ['nullable', 'string', 'max:40'],
            'call_center' => ['nullable', 'string', 'max:32'],
            'web_url' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        foreach (['code', 'swift_code'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = strtoupper(trim($data[$key]));
            }
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['branches'] = TxBankBranch::query()->where('bank_id', $row->getKey())->orderBy('name')->get()->map(fn ($b) => $b->only(['id', 'code', 'name', 'address', 'swift_code', 'routing_no', 'contact_person', 'phone', 'email', 'active']))->all();

        return $out;
    }
}

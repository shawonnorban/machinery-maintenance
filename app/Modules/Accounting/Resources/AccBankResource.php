<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Resources;

use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\TradeExport\Models\TxBank;
use App\Modules\TradeExport\Models\TxBankBranch;
use App\Modules\TradeExport\Resources\ReferenceEntryResource;
use App\Modules\TradeExport\Support\TradeExportDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Bank Account Master → the bank register, as Finance keeps it: the same
 * banks Trade & Export's Bank Directory lists (Brickland holds one register),
 * with whether a bank is the treasury's. Here the bank code is issued on save
 * and SWIFT may wait. Finance & Ledger → Master Data (AnnotechERP 23/01).
 */
class AccBankResource extends AccountingResource
{
    public function key(): string
    {
        return 'acc-banks';
    }

    /** Brickland removes a row here; one still in use is refused (deactivate it instead). */
    public function deletable(): bool
    {
        return true;
    }

    public function model(): string
    {
        return TxBank::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'name_bn', 'prefix', 'is_local', 'is_treasury', 'country_id', 'swift_code', 'iban', 'call_center', 'web_url', 'active'];
    }

    public function booleans(): array
    {
        return ['is_local', 'is_treasury', 'active'];
    }

    public function defaults(): array
    {
        return ['is_local' => true, 'is_treasury' => false, 'active' => true];
    }

    public function query(): Builder
    {
        TradeExportDefaults::banks($this->company());

        return TxBank::query()->orderBy('name');
    }

    public function messages(): array
    {
        return ['name.required' => __('accounting.master.bank_name_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'prefix' => [$required, 'string', 'max:16'],
            'country_id' => [$required, Rule::exists('origin_countries', 'id')->where('company_id', $this->company())],
            'swift_code' => ['nullable', 'string', 'max:16'],
            'iban' => ['nullable', 'string', 'max:40'],
            'call_center' => ['nullable', 'string', 'max:32'],
            'web_url' => ['nullable', 'string', 'max:255'],
            'is_local' => ['sometimes', 'boolean'],
            'is_treasury' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        unset($data['code']);
        if (! $row) {
            $data['code'] = ReferenceEntryResource::nextCode(TxBank::class, [], $data['prefix']);
        }
        if (isset($data['swift_code'])) {
            $data['swift_code'] = strtoupper(trim($data['swift_code']));
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['country'] = $row->getAttribute('country_id') ? OriginCountry::query()->whereKey($row->getAttribute('country_id'))->value('name') : null;
        $out['branches'] = TxBankBranch::query()->where('bank_id', $row->getKey())->count();

        return $out;
    }
}

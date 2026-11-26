<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Resources;

use App\Modules\Settings\Models\Currency;
use App\Modules\Tenancy\Models\Company;
use App\Modules\TradeExport\Models\TxExchangeRate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Currency Exchange Rates: the local rate a currency is converted at, for a
 * purpose (export realization, import payment …), from a day. The rate is the
 * currency's own rate on Settings when the entry is saved — Brickland shows it
 * read only — so a later change to the currency does not restate the entry.
 * One rate per currency, purpose and day. Trade & Export → Master Data
 * (AnnotechERP 22/01).
 */
class ExchangeRateResource extends TradeExportResource
{
    public function key(): string
    {
        return 'tx-exchange-rates';
    }

    public function model(): string
    {
        return TxExchangeRate::class;
    }

    public function columns(): array
    {
        return ['rate_for', 'currency', 'rate', 'effective_date', 'remarks'];
    }

    public function query(): Builder
    {
        return TxExchangeRate::query()->orderByDesc('effective_date')->orderByDesc('created_at');
    }

    public function messages(): array
    {
        return ['currency.required' => __('tradeexport.master.rate_by_required'), 'effective_date.required' => __('tradeexport.master.effective_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'rate_for' => ['nullable', Rule::in(TxExchangeRate::PURPOSES)],
            'currency' => [$required, 'string', 'size:3', Rule::exists('currencies', 'code')->where('company_id', $this->company())],
            'effective_date' => [$required, 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        unset($data['rate']);
        $p = array_merge($row ? ['currency' => $row->currency, 'rate_for' => $row->rate_for, 'effective_date' => $row->effective_date?->toDateString()] : [], $data);
        $taken = TxExchangeRate::query()->where('currency', $p['currency'])->whereDate('effective_date', $p['effective_date'])
            ->where(fn ($q) => empty($p['rate_for']) ? $q->whereNull('rate_for') : $q->where('rate_for', $p['rate_for']))
            ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['effective_date' => __('tradeexport.master.rate_taken')]);
        }
        if (! $row || array_key_exists('currency', $data)) {
            $rate = Currency::query()->where('code', $p['currency'])->value('exchange_rate');
            if (! $rate || (float) $rate <= 0) {
                throw ValidationException::withMessages(['currency' => __('tradeexport.master.currency_no_rate')]);
            }
            $data['rate'] = $rate;
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['company'] = Company::query()->whereKey($row->getAttribute('company_id'))->value('name');

        return $out;
    }
}

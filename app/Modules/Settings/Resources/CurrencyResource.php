<?php

declare(strict_types=1);

namespace App\Modules\Settings\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\Settings\Models\Currency;
use App\Modules\Settings\Services\SettingsResolver;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Settings → Date, Currency & Localization → Currencies (AnnotechERP 03/03).
 * The base currency (setting `currency.base`) always rates 1.
 */
class CurrencyResource extends ResourceDefinition
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SettingsResolver $settings,
    ) {}

    public function key(): string
    {
        return 'currencies';
    }

    public function model(): string
    {
        return Currency::class;
    }

    public function viewPermission(): string
    {
        return 'settings.company.manage';
    }

    public function columns(): array
    {
        return ['code', 'name', 'symbol', 'decimal_places', 'exchange_rate', 'rate_date', 'active'];
    }

    public function booleans(): array
    {
        return ['active'];
    }

    public function defaults(): array
    {
        return ['active' => true, 'decimal_places' => 2];
    }

    public function query(): Builder
    {
        return Currency::query()->orderBy('code');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, Rule::in(Currency::CODES),
                Rule::unique('currencies', 'code')->where('company_id', $this->context->companyId())->ignore($row?->getKey())],
            'name' => [$required, 'string', 'max:64'],
            'symbol' => ['nullable', 'string', 'max:8'],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6'],
            'exchange_rate' => [$required, 'numeric', 'gt:0'],
            'rate_date' => ['nullable', 'date'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $code = $data['code'] ?? $row?->code;
        if ($code !== null && $code === $this->settings->string('currency.base')) {
            $data['exchange_rate'] = 1;
        }

        return $data;
    }

    public function present(Model $row): array
    {
        return parent::present($row) + ['is_base' => $row->code === $this->settings->string('currency.base')];
    }
}

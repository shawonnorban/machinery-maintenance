<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\CountryConfig;
use App\Modules\Merchandise\Models\OriginCountry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Country Configuration: currency, dial code and shipping destinations. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class CountryConfigResource extends MasterResource
{
    public function key(): string
    {
        return 'country-configs';
    }

    public function model(): string
    {
        return CountryConfig::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'region', 'currency', 'dial_code', 'is_ship_to', 'active'];
    }

    public function booleans(): array
    {
        return ['active', 'is_ship_to'];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:16', $this->uniqueCode($row)],
            'name' => [$required, 'string', 'max:255'],
            'region' => ['nullable', Rule::in(OriginCountry::REGIONS)],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'dial_code' => ['nullable', 'string', 'max:8', 'regex:/^\+?[0-9]{1,6}$/'],
            'is_ship_to' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    protected function upperCase(): array
    {
        return ['code', 'currency'];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\OriginCountry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Country & Origin Master: where goods are made. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class OriginCountryResource extends MasterResource
{
    public function key(): string
    {
        return 'origin-countries';
    }

    public function model(): string
    {
        return OriginCountry::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'name_bn', 'region', 'active'];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:16', $this->uniqueCode($row)],
            'name' => [$required, 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', Rule::in(OriginCountry::REGIONS)],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\BuyerOfficeLink;
use App\Modules\Merchandise\Models\CountryConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Buyer Office Directory: liaison and head offices of each buyer, with their correspondence details. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class BuyerOfficeResource extends MasterResource
{
    public function key(): string
    {
        return 'buyer-offices';
    }

    public function model(): string
    {
        return BuyerOffice::class;
    }

    public function columns(): array
    {
        return ['name', 'office_type', 'address', 'country_id', 'contact_person', 'email', 'phone', 'active'];
    }

    protected function orderBy(): string
    {
        return 'name';
    }

    protected function upperCase(): array
    {
        return ['name'];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255', $this->uniqueCode($row, 'name')],
            'office_type' => [$required, Rule::in(BuyerOffice::TYPES)],
            'address' => ['nullable', 'string', 'max:2000'],
            'country_id' => ['nullable', Rule::exists('country_configs', 'id')->where('company_id', $this->company())],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['country'] = $row->getAttribute('country_id') ? CountryConfig::query()->whereKey($row->getAttribute('country_id'))->value('name') : null;
        $out['buyers'] = Buyer::query()->whereIn('id', BuyerOfficeLink::query()->where('buyer_office_id', $row->getKey())->pluck('buyer_id'))->orderBy('short_name')->pluck('short_name')->all();

        return $out;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Resources;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\TradeExport\Models\TxNotifyParty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Buyer-Wise Notify Party: the parties a buyer's shipping documents notify,
 * with their address and contacts. The code is issued on save. Trade & Export
 * → Master Data (AnnotechERP 22/01).
 */
class NotifyPartyResource extends TradeExportResource
{
    public function key(): string
    {
        return 'tx-notify-parties';
    }

    public function model(): string
    {
        return TxNotifyParty::class;
    }

    public function columns(): array
    {
        return ['buyer_id', 'code', 'name', 'name_bn', 'address', 'country_id', 'post_code', 'po_box', 'phone', 'mobile', 'fax', 'email', 'web_url', 'active'];
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
        return TxNotifyParty::query()->orderBy('name');
    }

    public function messages(): array
    {
        return ['buyer_id.required' => __('tradeexport.master.buyer_required'), 'name.required' => __('tradeexport.master.party_name_required'), 'address.required' => __('tradeexport.master.address_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();

        return [
            'buyer_id' => [$required, Rule::exists('buyers', 'id')->where('company_id', $company)],
            'name' => [$required, 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'address' => [$required, 'string', 'max:1000'],
            'country_id' => ['nullable', Rule::exists('origin_countries', 'id')->where('company_id', $company)],
            'post_code' => ['nullable', 'string', 'max:16'],
            'po_box' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:32'],
            'mobile' => ['nullable', 'string', 'max:32'],
            'fax' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'web_url' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        if (! $row) {
            $last = TxNotifyParty::query()->where('code', 'like', 'NP-%')->orderByDesc('code')->value('code');
            $data['code'] = sprintf('NP-%04d', $last ? ((int) substr($last, 3)) + 1 : 1);
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['buyer'] = Buyer::query()->whereKey($row->getAttribute('buyer_id'))->value('short_name');
        $out['country'] = $row->getAttribute('country_id') ? OriginCountry::query()->whereKey($row->getAttribute('country_id'))->value('name') : null;

        return $out;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\BuyerOfficeLink;
use App\Modules\Merchandise\Models\BuyerSampleType;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\TradeExport\Models\TxBank;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Buyer Account Master: the buyer every order, style and costing sheet is filed against. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class BuyerResource extends MasterResource
{
    /** The office Brickland's form picks, linked first. */
    private ?string $primary = null;

    public function key(): string
    {
        return 'buyers';
    }

    public function model(): string
    {
        return Buyer::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'name_bn', 'short_name', 'profile', 'pi_address', 'contact_person', 'country_id', 'bank_name', 'bank_id', 'supplier_id', 'registered_from', 'registered_to', 'display_order', 'active'];
    }

    /** @var list<string>|null the offices of the row being saved */
    private ?array $offices = null;

    protected function orderBy(): string
    {
        return 'display_order';
    }

    public function defaults(): array
    {
        return ['active' => true, 'display_order' => 0];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:16', $this->uniqueCode($row)],
            'name' => [$required, 'string', 'max:255'],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'short_name' => [$required, 'string', 'max:64'],
            'profile' => ['nullable', 'string', 'max:4000'],
            'pi_address' => [$required, 'string', 'max:2000'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'country_id' => [$required, Rule::exists('origin_countries', 'id')->where('company_id', $this->company())],
            'bank_id' => ['nullable', Rule::exists('tx_banks', 'id')->where('company_id', $this->company())],
            'supplier_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $this->company())],
            'registered_from' => [$required, 'date'],
            'registered_to' => ['nullable', 'date', 'after_or_equal:registered_from'],
            'display_order' => ['sometimes', 'integer', 'min:0', 'max:99999'],
            'active' => ['sometimes', 'boolean'],
            // A new buyer is always linked to at least one office; editing may leave the links alone.
            // Brickland's form picks the primary office; the Link to Office panel keeps the whole set.
            'office_id' => [$row ? 'sometimes' : 'required_without:office_ids', 'nullable', Rule::exists('buyer_offices', 'id')->where('company_id', $this->company())],
            'office_ids' => [$row ? 'sometimes' : 'required_without:office_id', 'array', 'min:1'],
            'office_ids.*' => ['distinct', Rule::exists('buyer_offices', 'id')->where('company_id', $this->company())],
        ];
    }

    public function messages(): array
    {
        return ['office_ids.required' => __('merchandise.master.office_required'), 'office_ids.min' => __('merchandise.master.office_required'), 'country_id.required' => __('merchandise.master.country_required')];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $data = parent::beforeSave($data, $row);
        if (array_key_exists('office_ids', $data)) {
            $this->offices = array_values(array_unique($data['office_ids']));
            unset($data['office_ids']);
        }
        if (array_key_exists('office_id', $data)) {
            $this->primary = $data['office_id'];
            unset($data['office_id']);
        }

        return $data;
    }

    public function afterSave(Model $row, bool $created): void
    {
        if ($this->primary) {
            BuyerOfficeLink::query()->firstOrCreate(['buyer_id' => $row->getKey(), 'buyer_office_id' => $this->primary], ['company_id' => $row->getAttribute('company_id')]);
            $this->primary = null;
        }
        if ($this->offices === null) {
            return;
        }
        BuyerOfficeLink::query()->where('buyer_id', $row->getKey())->whereNotIn('buyer_office_id', $this->offices)->delete();
        foreach ($this->offices as $officeId) {
            BuyerOfficeLink::query()->firstOrCreate(['buyer_id' => $row->getKey(), 'buyer_office_id' => $officeId], ['company_id' => $row->getAttribute('company_id')]);
        }
        $this->offices = null;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $links = BuyerOfficeLink::query()->where('buyer_id', $row->getKey())->pluck('buyer_office_id');
        $out['office_ids'] = $links->values()->all();
        $out['office_id'] = BuyerOfficeLink::query()->where('buyer_id', $row->getKey())->orderBy('created_at')->value('buyer_office_id');
        $out['country'] = $row->getAttribute('country_id') ? OriginCountry::query()->whereKey($row->getAttribute('country_id'))->value('name') : null;
        $bank = $row->getAttribute('bank_id') ? TxBank::query()->whereKey($row->getAttribute('bank_id'))->value('name') : null;
        $out['bank'] = $bank ?? $row->getAttribute('bank_name');
        $out['offices'] = BuyerOffice::query()->whereIn('id', $links)->orderBy('name')->pluck('name')->all();
        $out['sample_count'] = BuyerSampleType::query()->where('buyer_id', $row->getKey())->count();

        return $out;
    }
}

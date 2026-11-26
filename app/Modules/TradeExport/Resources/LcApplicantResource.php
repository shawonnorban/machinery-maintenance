<?php

declare(strict_types=1);

namespace App\Modules\TradeExport\Resources;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\TradeExport\Models\TxLcApplicant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * L/C Applicant Master: the applicant a buyer's L/C names, in English and
 * Bangla, and the address it carries. Trade & Export → Master Data
 * (AnnotechERP 22/01).
 */
class LcApplicantResource extends TradeExportResource
{
    public function key(): string
    {
        return 'tx-lc-applicants';
    }

    public function model(): string
    {
        return TxLcApplicant::class;
    }

    public function columns(): array
    {
        return ['buyer_id', 'name', 'name_bn', 'address', 'active'];
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
        return TxLcApplicant::query()->orderBy('name');
    }

    public function messages(): array
    {
        return ['buyer_id.required' => __('tradeexport.master.buyer_required'), 'name.required' => __('tradeexport.master.applicant_required'), 'address.required' => __('tradeexport.master.lc_address_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $buyer = request()->input('buyer_id', $row?->getAttribute('buyer_id'));

        return [
            'buyer_id' => [$required, Rule::exists('buyers', 'id')->where('company_id', $this->company())],
            'name' => [$required, 'string', 'max:255', $this->uniqueText(TxLcApplicant::class, 'name', $row, __('tradeexport.master.applicant_taken'), ['buyer_id' => $buyer])],
            'name_bn' => ['nullable', 'string', 'max:255'],
            'address' => [$required, 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['buyer'] = Buyer::query()->whereKey($row->getAttribute('buyer_id'))->value('short_name');

        return $out;
    }
}

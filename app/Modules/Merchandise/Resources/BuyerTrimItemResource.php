<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerTrimItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Buyer-Wise Trims & Accessories: the labels, tags and trims each buyer specifies. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class BuyerTrimItemResource extends MasterResource
{
    public function key(): string
    {
        return 'buyer-trim-items';
    }

    public function model(): string
    {
        return BuyerTrimItem::class;
    }

    public function columns(): array
    {
        return ['buyer_id', 'item_code', 'item_name', 'category', 'item_category_id', 'specification', 'material', 'color', 'size', 'uom', 'description', 'approved_by', 'active'];
    }

    public function query(): Builder
    {
        return BuyerTrimItem::query()->orderBy('buyer_id')->orderBy('item_code');
    }

    protected function upperCase(): array
    {
        return ['item_code'];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $buyer = request()->input('buyer_id', $row?->getAttribute('buyer_id'));

        return [
            'buyer_id' => [$required, Rule::exists('buyers', 'id')->where('company_id', $this->company())],
            // An item code is unique within its buyer.
            'item_code' => [$required, 'string', 'max:32', $this->uniqueCode($row, 'item_code', ['buyer_id' => $buyer])],
            'item_name' => [$required, 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'item_category_id' => ['nullable', Rule::exists('item_categories', 'id')->where('company_id', $this->company())],
            'specification' => ['nullable', 'string', 'max:255'],
            'material' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:64'],
            'size' => ['nullable', 'string', 'max:64'],
            'uom' => ['nullable', 'string', 'max:16', UomCodes::rule($this->company())],
            'description' => ['nullable', 'string', 'max:2000'],
            'approved_by' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['buyer'] = Buyer::query()->whereKey($row->getAttribute('buyer_id'))->value('short_name');
        $out['category_name'] = $row->getAttribute('item_category_id') ? DB::table('item_categories')->where('id', $row->getAttribute('item_category_id'))->value('name') : $row->getAttribute('category');

        return $out;
    }
}

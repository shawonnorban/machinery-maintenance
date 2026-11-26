<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerSupplierConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Buyer-Wise Supplier Configuration: the nominated and alternative supplier each
 * buyer uses, per item category — a blank category is the buyer's default. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class BuyerSupplierConfigResource extends MasterResource
{
    public function key(): string
    {
        return 'buyer-supplier-configs';
    }

    public function model(): string
    {
        return BuyerSupplierConfig::class;
    }

    public function columns(): array
    {
        return ['buyer_id', 'item_category_id', 'nominated_supplier_id', 'alternative_supplier_id', 'remarks', 'active'];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();
        $buyer = request()->input('buyer_id', $row?->getAttribute('buyer_id'));
        $category = request()->has('item_category_id') ? request()->input('item_category_id') : $row?->getAttribute('item_category_id');
        $supplier = fn () => Rule::exists('mp_suppliers', 'id')->where('company_id', $company);

        return [
            // One row per buyer and item category; a blank category is the buyer's default.
            'buyer_id' => [$required, Rule::exists('buyers', 'id')->where('company_id', $company), function (string $a, mixed $v, \Closure $fail) use ($row, $buyer, $category): void {
                $taken = BuyerSupplierConfig::query()->where('buyer_id', $buyer)->where(fn ($q) => $category ? $q->where('item_category_id', $category) : $q->whereNull('item_category_id'))
                    ->when($row, fn ($q) => $q->whereKeyNot($row->getKey()))->exists();
                if ($taken) {
                    $fail(__('merchandise.master.supplier_config_taken'));
                }
            }],
            'item_category_id' => ['nullable', Rule::exists('item_categories', 'id')->where('company_id', $company)],
            'nominated_supplier_id' => ['nullable', $supplier()],
            'alternative_supplier_id' => ['nullable', $supplier(), 'different:nominated_supplier_id'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $name = fn (?string $id) => $id ? MaterialSupplier::query()->whereKey($id)->value('name') : null;
        $out['buyer'] = Buyer::query()->whereKey($row->getAttribute('buyer_id'))->value('short_name');
        $out['item_category'] = $row->getAttribute('item_category_id') ? DB::table('item_categories')->where('id', $row->getAttribute('item_category_id'))->value('name') : null;
        $out['nominated_supplier'] = $name($row->getAttribute('nominated_supplier_id'));
        $out['alternative_supplier'] = $name($row->getAttribute('alternative_supplier_id'));

        return $out;
    }
}

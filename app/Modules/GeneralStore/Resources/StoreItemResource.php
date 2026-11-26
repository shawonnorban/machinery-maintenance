<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Resources;

use App\Modules\GeneralStore\Models\GsCategory;
use App\Modules\GeneralStore\Models\GsItem;
use App\Modules\GeneralStore\Models\GsStockMovement;
use App\Modules\GeneralStore\Models\GsUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Item Master: the general-store items — category, unit, brand, minimum stock,
 * price and rack — with the stock on hand and whether it has run low. Opening
 * Stock, given on a new item, is the item's first stock movement, into the
 * chosen store at the default price. General Store → Master Data (AnnotechERP
 * 10/01).
 */
class StoreItemResource extends StoreMasterResource
{
    /** @var array{quantity: string, store_id: string}|null */
    private ?array $opening = null;

    public function key(): string
    {
        return 'store-items';
    }

    public function model(): string
    {
        return GsItem::class;
    }

    protected function codeColumn(): string
    {
        return 'item_code';
    }

    public function columns(): array
    {
        return ['item_code', 'name', 'gs_category_id', 'sub_category', 'gs_unit_id', 'brand', 'minimum_stock', 'reorder_level', 'default_price', 'rack_location', 'specification', 'active'];
    }

    /** Category, unit and stock come with the rows, not a query per row. */
    public function query(): Builder
    {
        return GsItem::query()->select('gs_items.*')->addSelect([
            'category_name' => GsCategory::query()->select('name')->whereColumn('item_categories.id', 'gs_items.gs_category_id'),
            'unit_code' => GsUnit::query()->select('code')->whereColumn('units_of_measure.id', 'gs_items.gs_unit_id'),
            'stock_on_hand' => GsStockMovement::query()->selectRaw('coalesce(sum(quantity), 0)')->whereColumn('gs_stock_movements.gs_item_id', 'gs_items.id'),
        ])->orderBy('item_code');
    }

    public function messages(): array
    {
        return ['item_code.required' => __('store.master.item_code_required')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();

        return [
            'item_code' => [$required, 'string', 'max:64', $this->uniqueCode($row)],
            'name' => [$required, 'string', 'max:255'],
            'gs_category_id' => [$required, Rule::exists('item_categories', 'id')->where('company_id', $company)],
            'sub_category' => ['nullable', 'string', 'max:255'],
            'gs_unit_id' => [$required, Rule::exists('units_of_measure', 'id')->where(fn ($q) => $q->where('company_id', $company)->orWhereNull('company_id'))],
            'brand' => ['nullable', 'string', 'max:255'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'default_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'rack_location' => ['nullable', 'string', 'max:64'],
            'specification' => ['nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
            // Only on a new item: its first stock movement.
            'opening_stock' => [$row ? 'prohibited' : 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'opening_store_id' => ['nullable', Rule::exists('stores', 'id')->where('company_id', $company)],
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $data = parent::beforeSave($data, $row);
        $quantity = $data['opening_stock'] ?? null;
        if ($quantity !== null && (float) $quantity > 0) {
            if (empty($data['opening_store_id'])) {
                throw ValidationException::withMessages(['opening_store_id' => __('store.master.opening_needs_store')]);
            }
            $this->opening = ['quantity' => (string) $quantity, 'store_id' => $data['opening_store_id']];
        }
        unset($data['opening_stock'], $data['opening_store_id']);
        foreach (['minimum_stock', 'reorder_level'] as $level) {
            if (array_key_exists($level, $data) && $data[$level] === null) {
                $data[$level] = 0;
            }
        }
        if ($row === null) {
            $data['created_by'] = $this->actingUserId();
        }

        return $data;
    }

    public function afterSave(Model $row, bool $created): void
    {
        if (! $created || $this->opening === null) {
            return;
        }
        GsStockMovement::query()->create([
            'company_id' => $row->getAttribute('company_id'), 'gs_item_id' => $row->getKey(), 'gs_store_id' => $this->opening['store_id'],
            'movement_type' => 'OPENING', 'quantity' => $this->opening['quantity'], 'unit_price' => $row->getAttribute('default_price'),
            'moved_on' => now()->toDateString(), 'reference' => 'Opening stock', 'created_by' => $this->actingUserId(),
        ]);
        $this->opening = null;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['category'] = $row->getAttribute('category_name') ?? GsCategory::query()->whereKey($row->getAttribute('gs_category_id'))->value('name');
        $out['unit'] = $row->getAttribute('unit_code') ?? GsUnit::query()->whereKey($row->getAttribute('gs_unit_id'))->value('code');
        $stock = (float) ($row->getAttribute('stock_on_hand') ?? GsStockMovement::query()->where('gs_item_id', $row->getKey())->sum('quantity'));
        $out['stock'] = $stock;
        $out['stock_status'] = $stock <= 0 ? 'OUT' : ($stock <= (float) $row->getAttribute('minimum_stock') ? 'LOW' : 'OK');

        return $out;
    }
}

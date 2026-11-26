<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\ItemType;
use App\Modules\Merchandise\Models\StyleItem;
use App\Modules\Merchandise\Support\MerchandiseDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Item Type Master: T-Shirt, Polo Shirt, Trouser, Hoodie — the item types the
 * Garment Items step offers under each item group; the name is the text a
 * style item carries. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class ItemTypeResource extends MasterResource
{
    public function key(): string
    {
        return 'item-types';
    }

    public function model(): string
    {
        return ItemType::class;
    }

    public function columns(): array
    {
        return ['item_group', 'code', 'name', 'remarks', 'sort_order', 'active'];
    }

    public function defaults(): array
    {
        return ['active' => true, 'sort_order' => 0];
    }

    public function query(): Builder
    {
        MerchandiseDefaults::ensureItemTypes($this->company());

        return ItemType::query()->orderBy('item_group')->orderBy('sort_order')->orderBy('name');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $group = request()->input('item_group', $row?->item_group);

        return [
            'item_group' => [$required, Rule::in(ItemType::GROUPS)],
            'code' => [$required, 'string', 'max:32', $this->uniqueCode($row)],
            'name' => [$required, 'string', 'max:120', Rule::unique('item_types', 'name')->where('company_id', $this->company())->where('item_group', $group)->ignore($row?->getKey())],
            'remarks' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'item_group.required' => __('merchandise.acc_po.required', ['field' => __('merchandise.master.ity.f.item_group')]),
            'code.required' => __('merchandise.acc_po.required', ['field' => __('merchandise.master.ity.f.code')]),
            'name.required' => __('merchandise.acc_po.required', ['field' => __('merchandise.master.ity.f.name')]),
            'name.unique' => __('merchandise.master.ity.name_taken'),
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $data = parent::beforeSave($data, $row);
        $data['sort_order'] = (int) ($data['sort_order'] ?? $row?->getAttribute('sort_order') ?? 0);

        return $data;
    }

    /** A type a style item carries stays; switch it off instead. */
    public function beforeDelete(Model $row): void
    {
        if (StyleItem::query()->where('item_group', $row->getAttribute('item_group'))->where('item_type', $row->getAttribute('name'))->exists()) {
            throw ValidationException::withMessages(['id' => __('merchandise.master.in_use')]);
        }
    }
}

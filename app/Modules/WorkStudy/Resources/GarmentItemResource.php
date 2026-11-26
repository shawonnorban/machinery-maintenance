<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\WorkStudy\Models\WsGarmentItem;
use App\Modules\WorkStudy\Models\WsItemPart;
use App\Modules\WorkStudy\Support\WorkStudyDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The garment items Item & Process Grouping maps parts to — Knit Tops: Basic
 * T-Shirt, Polo Shirt … Brickland keeps them on its Garments Item
 * Specification screen; until Work Study's specification screens are built,
 * they are added here. Work Study → Master Data (AnnotechERP 14/01).
 */
class GarmentItemResource extends WorkStudyResource
{
    public function key(): string
    {
        return 'ws-garment-items';
    }

    public function model(): string
    {
        return WsGarmentItem::class;
    }

    public function columns(): array
    {
        return ['item_group', 'name', 'active'];
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
        WorkStudyDefaults::garmentItems($this->company());

        return WsGarmentItem::query()->orderBy('item_group')->orderBy('name');
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $group = request()->input('item_group', $row?->getAttribute('item_group'));

        return [
            'item_group' => [$required, 'string', 'max:255'],
            'name' => [$required, 'string', 'max:255', Rule::unique('ws_garment_items', 'name')->where('company_id', $this->company())->where('item_group', $group)->ignore($row?->getKey())],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function beforeDelete(Model $row): void
    {
        if (WsItemPart::query()->where('garment_item_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['id' => __('workstudy.master.in_use')]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Merchandise\Models\GarmentPart;
use App\Modules\WorkStudy\Models\WsGarmentItem;
use App\Modules\WorkStudy\Models\WsItemPart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Item & Process Grouping: which components make up each garment item, in
 * printed order, and whether each is included. A part may appear on many
 * items; its Process Group and GMT Body flags are the Garment Part Master's.
 * Work Study → Master Data (AnnotechERP 14/01).
 */
class ItemPartResource extends WorkStudyResource
{
    public function key(): string
    {
        return 'ws-item-parts';
    }

    /** Brickland removes a row here; one still in use is refused (deactivate it instead). */
    public function deletable(): bool
    {
        return true;
    }

    public function model(): string
    {
        return WsItemPart::class;
    }

    public function columns(): array
    {
        return ['garment_item_id', 'garment_part_id', 'component_sl', 'included'];
    }

    public function booleans(): array
    {
        return ['included'];
    }

    public function defaults(): array
    {
        return ['included' => true];
    }

    public function query(): Builder
    {
        return WsItemPart::query()->orderBy('garment_item_id')->orderBy('component_sl');
    }

    public function messages(): array
    {
        return ['garment_item_id.required' => __('workstudy.master.item_required'), 'garment_part_id.unique' => __('workstudy.master.part_taken')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = $this->company();
        $item = request()->input('garment_item_id', $row?->getAttribute('garment_item_id'));

        return [
            'garment_item_id' => [$required, Rule::exists('ws_garment_items', 'id')->where('company_id', $company)],
            'garment_part_id' => [$required, Rule::exists('garment_parts', 'id')->where('company_id', $company), Rule::unique('ws_item_parts', 'garment_part_id')->where('garment_item_id', $item)->ignore($row?->getKey())],
            'component_sl' => [$required, 'integer', 'min:1', 'max:999'],
            'included' => ['sometimes', 'boolean'],
        ];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $item = WsGarmentItem::query()->whereKey($row->getAttribute('garment_item_id'))->first(['item_group', 'name']);
        $part = GarmentPart::query()->whereKey($row->getAttribute('garment_part_id'))->first(['name', 'is_process_group', 'is_body']);
        $out['item_group'] = $item?->item_group;
        $out['item'] = $item?->name;
        $out['part'] = $part?->name;
        $out['is_process_group'] = (bool) $part?->is_process_group;
        $out['is_body'] = (bool) $part?->is_body;

        return $out;
    }
}

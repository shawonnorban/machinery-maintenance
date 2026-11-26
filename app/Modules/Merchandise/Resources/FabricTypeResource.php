<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Resources;

use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\FabricTypeGarmentPart;
use App\Modules\Merchandise\Models\GarmentPart;
use App\Modules\Merchandise\Models\YarnCountConfig;
use App\Modules\Merchandise\Support\MerchandiseDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Fabric Type Master: fabric constructions and the parts each may be cut for. Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class FabricTypeResource extends MasterResource
{
    public function key(): string
    {
        return 'fabric-types';
    }

    public function model(): string
    {
        return FabricType::class;
    }

    public function columns(): array
    {
        return ['code', 'name', 'short_name', 'fabric_group', 'category', 'construction', 'is_base', 'parent_id', 'is_rib', 'sort_order', 'active'];
    }

    public function booleans(): array
    {
        return ['active', 'is_base', 'is_rib'];
    }

    protected function orderBy(): string
    {
        return 'sort_order';
    }

    public function defaults(): array
    {
        return ['active' => true, 'sort_order' => 0, 'is_base' => false, 'is_rib' => false];
    }

    public function query(): Builder
    {
        MerchandiseDefaults::ensure($this->company());

        return parent::query();
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:32', $this->uniqueCode($row)],
            'name' => [$required, 'string', 'max:255'],
            'short_name' => [$required, 'string', 'max:32'],
            'fabric_group' => ['nullable', Rule::in(GarmentPart::FABRIC_GROUPS)],
            'category' => ['nullable', Rule::in(FabricType::CATEGORIES)],
            'construction' => [$required, Rule::in(FabricType::CONSTRUCTIONS)],
            'is_base' => ['sometimes', 'boolean'],
            // A derived type points at a base type of the same company, never at itself.
            'parent_id' => ['nullable', Rule::exists('fabric_types', 'id')->where('company_id', $this->company())->where('is_base', true), Rule::notIn(array_filter([$row?->getKey()]))],
            'is_rib' => ['sometimes', 'boolean'],
            'sort_order' => [$required, 'integer', 'min:0', 'max:9999'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $out['garment_parts'] = GarmentPart::query()
            ->whereIn('id', FabricTypeGarmentPart::query()->where('fabric_type_id', $row->getKey())->pluck('garment_part_id'))
            ->orderBy('name')->pluck('name')->all();
        $out['parent'] = $row->getAttribute('parent_id') ? FabricType::query()->whereKey($row->getAttribute('parent_id'))->value('name') : null;

        return $out;
    }

    /** A type the yarn count chart still uses stays; switch it off instead. */
    public function beforeDelete(Model $row): void
    {
        if (YarnCountConfig::query()->where('fabric_type_id', $row->getKey())->exists()) {
            throw ValidationException::withMessages(['id' => __('merchandise.master.in_use')]);
        }
    }
}

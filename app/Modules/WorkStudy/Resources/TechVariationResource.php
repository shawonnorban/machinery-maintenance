<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Resources;

use App\Modules\Erp\Support\ResourceDefinition;
use App\Modules\WorkStudy\Models\WsGarmentItem;
use App\Modules\WorkStudy\Models\WsTechVariation;
use App\Modules\WorkStudy\Support\WsSpecs;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Garment Item Specification → Records and New tech variation: a garment
 * category's technical variation with its manpower, SMV and the SMV each
 * order-quantity band attracts. The Itm Group box narrows the categories; a
 * category of another group is refused. Work Study → Item Specification
 * (AnnotechERP 14/04).
 */
class TechVariationResource extends ResourceDefinition
{
    public function key(): string
    {
        return 'ws-tech-variations';
    }

    public function model(): string
    {
        return WsTechVariation::class;
    }

    public function viewPermission(): string
    {
        return 'workstudy.spec.view';
    }

    public function managePermission(): string
    {
        return 'workstudy.spec.manage';
    }

    public function columns(): array
    {
        return ['garment_item_id', 'tech_spec', 'op', 'aso', 'smv', 'band1', 'band2', 'band3', 'band4', 'band5', 'band6', 'sort_order'];
    }

    public function query(): Builder
    {
        WsSpecs::ensure(app(TenantContext::class)->companyId());

        return WsTechVariation::query()->orderBy('garment_item_id')->orderBy('sort_order');
    }

    public function messages(): array
    {
        return ['garment_item_id.required' => __('workstudy.spec.v.category'), 'tech_spec.required' => __('workstudy.spec.v.tech_spec')];
    }

    public function rules(?Model $row): array
    {
        $required = $row ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->companyId();
        $smv = ['nullable', 'numeric', 'min:0', 'max:9999'];

        return [
            'item_group' => ['nullable', 'string'],
            'garment_item_id' => [$required, Rule::exists('ws_garment_items', 'id')->where('company_id', $company)],
            'tech_spec' => [$required, 'string', 'max:200'],
            'op' => $smv, 'aso' => $smv, 'smv' => $smv,
            'band1' => $smv, 'band2' => $smv, 'band3' => $smv, 'band4' => $smv, 'band5' => $smv, 'band6' => $smv,
        ];
    }

    public function beforeSave(array $data, ?Model $row): array
    {
        $group = $data['item_group'] ?? null;
        unset($data['item_group']);
        $item = WsGarmentItem::query()->find($data['garment_item_id'] ?? $row?->getAttribute('garment_item_id'));
        if ($group && $item && $item->item_group !== $group) {
            throw ValidationException::withMessages(['garment_item_id' => __('workstudy.spec.v.category_group', ['group' => $group])]);
        }
        if (! $row) {
            $data['sort_order'] = (int) WsTechVariation::query()->where('garment_item_id', $item?->id)->max('sort_order') + 1;
        }

        return $data;
    }

    public function present(Model $row): array
    {
        $out = parent::present($row);
        $item = WsGarmentItem::query()->find($row->getAttribute('garment_item_id'));
        $out['item_group'] = $item?->item_group;
        $out['category'] = $item?->name;
        $out['category_code'] = $item?->code;

        return $out;
    }
}

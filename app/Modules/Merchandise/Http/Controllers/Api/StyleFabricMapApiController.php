<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\GarmentPart;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleColour;
use App\Modules\Merchandise\Models\StyleFabric;
use App\Modules\Merchandise\Models\StyleFabricMap;
use App\Modules\Merchandise\Models\StyleItem;
use App\Modules\Merchandise\Models\StyleItemStyle;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Item/Combo/Model → Yarn/Fabric Mapping (AnnotechERP 06/05): Garment Part vs
 * Fabric. Each line says which of the style's fabrics a garment part of one
 * of its items is cut from — for one garment colour or all of them, in a
 * fabric colour (the garment's own when left empty) and at a share of the
 * part — and for which production categories. The list is saved whole; the
 * shares of one part, item and garment colour never pass 100%.
 */
class StyleFabricMapApiController extends ApiController
{
    public function index(string $style): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $row = Style::query()->whereKey($style)->firstOrFail();
        $own = StyleItemStyle::query()->where('style_id', $row->id)->pluck('style_item_id');

        return ApiResponse::ok([
            'rows' => $this->rows($row),
            'options' => [
                'prod_categories' => StyleFabricMap::PROD_CATEGORIES,
                'items' => StyleItem::query()->whereIn('id', $own)->orderBy('full_name')->get(['id', 'full_name', 'short_name'])
                    ->map(fn (StyleItem $i): array => ['value' => $i->id, 'label' => $i->full_name ?: $i->short_name])->values()->all(),
                'parts' => GarmentPart::query()->where('active', true)->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($p): array => ['value' => $p->id, 'label' => $p->name])->values()->all(),
                'fabrics' => StyleFabric::query()->where('style_id', $row->id)->orderBy('sort_order')->get(['id', 'description', 'fabric_code', 'item_ids'])
                    ->map(fn (StyleFabric $f): array => ['value' => $f->id, 'label' => $f->description ?: ($f->fabric_code ?: '—'), 'item_ids' => $f->item_ids ?? []])->values()->all(),
                'colours' => $this->colours($row),
            ],
        ]);
    }

    public function save(Request $request, string $style): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = Style::query()->whereKey($style)->firstOrFail();
        $company = $this->caller()->companyId;
        $own = StyleItemStyle::query()->where('style_id', $row->id)->pluck('style_item_id')->all();
        $colour = Rule::exists('merch_style_colours', 'id')->where('style_id', $row->id);
        $data = $request->validate([
            'rows' => ['present', 'array', 'max:200'],
            'rows.*.prod_categories' => ['required', 'array', 'min:1'],
            'rows.*.prod_categories.*' => [Rule::in(StyleFabricMap::PROD_CATEGORIES)],
            'rows.*.item_id' => ['required', Rule::in($own)],
            'rows.*.garment_part_id' => ['required', Rule::exists('garment_parts', 'id')->where('company_id', $company)],
            'rows.*.fabric_id' => ['required', Rule::exists('merch_style_fabrics', 'id')->where('style_id', $row->id)],
            'rows.*.all_colours' => ['sometimes', 'boolean'],
            'rows.*.gmt_colour_id' => ['nullable', 'required_unless:rows.*.all_colours,true,1', $colour],
            'rows.*.fab_colour_id' => ['nullable', $colour],
            'rows.*.ratio' => ['required', 'numeric', 'gt:0', 'max:100'],
        ], [
            'rows.*.prod_categories.required' => __('merchandise.style_fabric_map.category_required'),
            'rows.*.item_id.required' => __('merchandise.style_fabric_map.item_required'),
            'rows.*.garment_part_id.required' => __('merchandise.style_fabric_map.part_required'),
            'rows.*.fabric_id.required' => __('merchandise.style_fabric_map.fabric_required'),
            'rows.*.gmt_colour_id.required_unless' => __('merchandise.style_fabric_map.colour_required'),
        ]);

        // A part of an item, for a garment colour, is not cut from more than all of it.
        $shares = [];
        foreach ($data['rows'] as $r) {
            $key = $r['item_id'].'|'.$r['garment_part_id'].'|'.(($r['all_colours'] ?? false) ? '*' : $r['gmt_colour_id']);
            $shares[$key] = ($shares[$key] ?? 0) + (float) $r['ratio'];
            if ($shares[$key] > 100.0001) {
                throw ValidationException::withMessages(['rows' => __('merchandise.style_fabric_map.over_100')]);
            }
        }

        DB::transaction(function () use ($row, $data): void {
            StyleFabricMap::query()->where('style_id', $row->id)->delete();
            foreach (array_values($data['rows']) as $i => $r) {
                $all = (bool) ($r['all_colours'] ?? false);
                StyleFabricMap::query()->create([
                    'style_id' => $row->id,
                    'prod_categories' => array_values(array_intersect(StyleFabricMap::PROD_CATEGORIES, $r['prod_categories'])),
                    'item_id' => $r['item_id'],
                    'garment_part_id' => $r['garment_part_id'],
                    'fabric_id' => $r['fabric_id'],
                    'all_colours' => $all,
                    'gmt_colour_id' => $all ? null : $r['gmt_colour_id'],
                    'fab_colour_id' => $r['fab_colour_id'] ?? null,
                    'ratio' => $r['ratio'],
                    'sort_order' => ($i + 1) * 10,
                ]);
            }
        });

        return ApiResponse::ok($this->rows($row));
    }

    /** @return list<array{value: string, label: string}> the style's mapped colours */
    private function colours(Style $style): array
    {
        $names = Colour::query()->pluck('name', 'id');

        return StyleColour::query()->where('style_id', $style->id)->orderBy('sort_order')->get(['id', 'colour_id'])
            ->map(fn (StyleColour $c): array => ['value' => $c->id, 'label' => $names[$c->colour_id] ?? '—'])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function rows(Style $style): array
    {
        $colours = collect($this->colours($style))->pluck('label', 'value');
        $parts = GarmentPart::query()->pluck('name', 'id');
        $items = StyleItem::query()->get(['id', 'full_name', 'short_name'])->mapWithKeys(fn (StyleItem $i): array => [$i->id => $i->full_name ?: $i->short_name]);
        $fabrics = StyleFabric::query()->where('style_id', $style->id)->get(['id', 'description', 'fabric_code'])->mapWithKeys(fn (StyleFabric $f): array => [$f->id => $f->description ?: ($f->fabric_code ?: '—')]);

        return StyleFabricMap::query()->where('style_id', $style->id)->orderBy('sort_order')->get()->map(fn (StyleFabricMap $m): array => [
            'id' => $m->id, 'prod_categories' => $m->prod_categories ?? [], 'item_id' => $m->item_id, 'item' => $items[$m->item_id] ?? null,
            'garment_part_id' => $m->garment_part_id, 'part' => $parts[$m->garment_part_id] ?? null, 'fabric_id' => $m->fabric_id, 'fabric' => $fabrics[$m->fabric_id] ?? null,
            'all_colours' => $m->all_colours, 'gmt_colour_id' => $m->gmt_colour_id, 'gmt_colour' => $m->gmt_colour_id ? ($colours[$m->gmt_colour_id] ?? null) : null,
            'fab_colour_id' => $m->fab_colour_id, 'fab_colour' => $m->fab_colour_id ? ($colours[$m->fab_colour_id] ?? null) : null, 'ratio' => $m->ratio,
        ])->values()->all();
    }
}

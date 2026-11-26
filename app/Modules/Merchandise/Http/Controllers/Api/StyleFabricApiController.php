<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Knitting\Models\KnitYarnCount;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Merchandise\Models\GarmentPart;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleFabric;
use App\Modules\Merchandise\Models\StyleItem;
use App\Modules\Merchandise\Models\StyleItemStyle;
use App\Modules\Merchandise\Models\StyleKnitInstruction;
use App\Modules\Merchandise\Models\YarnCountConfig;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Item/Combo/Model → Fabrication (AnnotechERP 06/05): a style's fabrication
 * list and its knitting / yarn-dyed instructions, edited on the desk and
 * saved together. A fabric names the style's own items; its counts may be
 * suggested from the GSM-wise yarn count chart (the screen does that from
 * the chart this endpoint sends).
 */
class StyleFabricApiController extends ApiController
{
    public function index(string $style): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $row = Style::query()->whereKey($style)->firstOrFail();
        $itemIds = StyleItemStyle::query()->where('style_id', $row->id)->pluck('style_item_id');

        return ApiResponse::ok([
            'fabrics' => $this->fabrics($row),
            'instructions' => $this->instructions($row),
            'options' => [
                'items' => StyleItem::query()->whereIn('id', $itemIds)->orderBy('sort_order')->get(['id', 'full_name', 'short_name'])->map(fn ($i): array => ['value' => $i->id, 'label' => $i->full_name])->values()->all(),
                'parts' => GarmentPart::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($p): array => ['value' => $p->id, 'label' => $p->name])->values()->all(),
                'compositions' => FiberComposition::query()->where('active', true)->orderBy('description')->get(['id', 'code', 'description'])->map(fn ($c): array => ['value' => $c->id, 'label' => $c->description ?: $c->code])->values()->all(),
                'fabric_types' => FabricType::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])->map(fn ($f): array => ['value' => $f->id, 'label' => $f->name])->values()->all(),
                'counts' => KnitYarnCount::query()->where('active', true)->orderBy('display_order')->orderBy('count_no')->pluck('count_no')->values()->all(),
                'count_chart' => YarnCountConfig::query()->get(['fabric_type_id', 'gsm_min', 'gsm_max', 'count_top', 'count_binding', 'count_back'])->map(fn ($c): array => [
                    'fabric_type_id' => $c->fabric_type_id, 'gsm_min' => (float) $c->gsm_min, 'gsm_max' => (float) $c->gsm_max, 'top' => $c->count_top, 'binding' => $c->count_binding, 'back' => $c->count_back,
                ])->values()->all(),
                'colours' => Colour::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($c): array => ['value' => $c->id, 'label' => $c->name])->values()->all(),
                'categories' => StyleFabric::CATEGORIES,
                'spin_types' => StyleFabric::SPIN_TYPES,
                'weight_units' => StyleFabric::WEIGHT_UNITS,
                'washes' => StyleFabric::WASHES,
                'units' => StyleKnitInstruction::UNITS,
            ],
        ]);
    }

    public function save(Request $request, string $style): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = Style::query()->whereKey($style)->firstOrFail();
        $company = $this->caller()->companyId;
        $own = StyleItemStyle::query()->where('style_id', $row->id)->pluck('style_item_id')->all();
        $data = $request->validate([
            'fabrics' => ['present', 'array', 'max:60'],
            'fabrics.*.id' => ['nullable', Rule::exists('merch_style_fabrics', 'id')->where('style_id', $row->id)],
            'fabrics.*.item_ids' => ['required', 'array', 'min:1'],
            'fabrics.*.item_ids.*' => [Rule::in($own)],
            'fabrics.*.garment_part_ids' => ['nullable', 'array'],
            'fabrics.*.garment_part_ids.*' => [Rule::exists('garment_parts', 'id')->where('company_id', $company)],
            'fabrics.*.fabric_code' => ['nullable', 'string', 'max:32'],
            'fabrics.*.fabric_category' => ['required', Rule::in(StyleFabric::CATEGORIES)],
            'fabrics.*.source' => ['required', Rule::in(StyleFabric::SOURCES)],
            'fabrics.*.fiber_composition_id' => ['required', Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            'fabrics.*.spin_type' => ['nullable', Rule::in(StyleFabric::SPIN_TYPES)],
            'fabrics.*.is_slub' => ['sometimes', 'boolean'],
            'fabrics.*.is_melange' => ['sometimes', 'boolean'],
            'fabrics.*.is_main' => ['sometimes', 'boolean'],
            'fabrics.*.fabric_type_id' => ['required', Rule::exists('fabric_types', 'id')->where('company_id', $company)],
            'fabrics.*.weight_from' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'fabrics.*.weight_to' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'fabrics.*.weight_unit' => ['nullable', Rule::in(StyleFabric::WEIGHT_UNITS)],
            'fabrics.*.width' => ['nullable', 'string', 'max:64'],
            'fabrics.*.supplier' => ['nullable', 'string', 'max:128'],
            'fabrics.*.remarks' => ['nullable', 'string', 'max:500'],
            'fabrics.*.counts' => ['nullable', 'array'],
            'fabrics.*.counts.*.count' => ['nullable', 'string', 'max:32'],
            'fabrics.*.counts.*.pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fabrics.*.wash_types' => ['nullable', 'array'],
            'fabrics.*.wash_types.*' => [Rule::in(StyleFabric::WASHES)],
            'fabrics.*.wash_na' => ['sometimes', 'boolean'],
            'fabrics.*.finish_types' => ['nullable', 'array'],
            'fabrics.*.finish_types.*' => [Rule::in(StyleFabric::WASHES)],
            'fabrics.*.finish_na' => ['sometimes', 'boolean'],
            'fabrics.*.other_spec' => ['nullable', 'string', 'max:2000'],
            'fabrics.*.description' => ['nullable', 'string', 'max:500'],
            'instructions' => ['present', 'array', 'max:30'],
            'instructions.*.image_title' => ['nullable', 'string', 'max:128'],
            'instructions.*.image_ids' => ['nullable', 'array', 'max:10'],
            'instructions.*.image_ids.*' => ['ulid'],
            'instructions.*.gmt_colour_ids' => ['nullable', 'array'],
            'instructions.*.gmt_colour_ids.*' => [Rule::exists('colours', 'id')->where('company_id', $company)],
            'instructions.*.lines' => ['nullable', 'array', 'max:50'],
            'instructions.*.lines.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)],
            'instructions.*.lines.*.value' => ['nullable', 'string', 'max:32'],
            'instructions.*.lines.*.unit' => ['nullable', Rule::in(StyleKnitInstruction::UNITS)],
        ], [
            'fabrics.*.item_ids.required' => __('merchandise.style_fabric.items_required'),
            'fabrics.*.fiber_composition_id.required' => __('merchandise.style_fabric.composition_required'),
            'fabrics.*.fabric_type_id.required' => __('merchandise.style_fabric.type_required'),
        ]);

        try {
            DB::transaction(fn () => $this->store($row, $data));
        } catch (QueryException $e) {
            // A fabric the yarn/fabric mapping cuts a garment part from stays.
            if ($e->getCode() === '23503') {
                throw ValidationException::withMessages(['fabrics' => __('erp.in_use')]);
            }
            throw $e;
        }

        return ApiResponse::ok(['fabrics' => $this->fabrics($row), 'instructions' => $this->instructions($row)]);
    }

    /** Fabrics kept by id are updated, new ones added and the rest dropped; instructions are rewritten. */
    private function store(Style $row, array $data): void
    {
        $keep = array_values(array_filter(array_column($data['fabrics'], 'id')));
        StyleFabric::query()->where('style_id', $row->id)->whereNotIn('id', $keep)->delete();
        foreach (array_values($data['fabrics']) as $i => $f) {
            $counts = [];
            foreach (StyleFabric::COUNT_PARTS as $part) {
                $c = $f['counts'][$part] ?? [];
                $counts[$part] = ['count' => $c['count'] ?? null, 'pct' => isset($c['pct']) ? (float) $c['pct'] : null];
            }
            $fabric = isset($f['id']) ? StyleFabric::query()->whereKey($f['id'])->firstOrFail() : new StyleFabric(['style_id' => $row->id]);
            $fabric->fill([
                'item_ids' => array_values($f['item_ids']),
                'garment_part_ids' => array_values($f['garment_part_ids'] ?? []),
                'fabric_code' => $this->blank($f['fabric_code'] ?? null),
                'fabric_category' => $f['fabric_category'],
                'source' => $f['source'],
                'fiber_composition_id' => $f['fiber_composition_id'],
                'spin_type' => $f['spin_type'] ?? null,
                'is_slub' => (bool) ($f['is_slub'] ?? false),
                'is_melange' => (bool) ($f['is_melange'] ?? false),
                'is_main' => (bool) ($f['is_main'] ?? false),
                'fabric_type_id' => $f['fabric_type_id'],
                'weight_from' => $f['weight_from'] ?? null,
                'weight_to' => $f['weight_to'] ?? null,
                'weight_unit' => $f['weight_unit'] ?? null,
                'width' => $this->blank($f['width'] ?? null),
                'supplier' => $this->blank($f['supplier'] ?? null),
                'remarks' => $this->blank($f['remarks'] ?? null),
                'counts' => $counts,
                'wash_types' => ($f['wash_na'] ?? false) ? [] : array_values($f['wash_types'] ?? []),
                'wash_na' => (bool) ($f['wash_na'] ?? false),
                'finish_types' => ($f['finish_na'] ?? false) ? [] : array_values($f['finish_types'] ?? []),
                'finish_na' => (bool) ($f['finish_na'] ?? false),
                'other_spec' => $this->blank($f['other_spec'] ?? null),
                'description' => $this->blank($f['description'] ?? null),
                'sort_order' => ($i + 1) * 10,
            ])->save();
        }
        StyleKnitInstruction::query()->where('style_id', $row->id)->delete();
        foreach (array_values($data['instructions']) as $i => $k) {
            StyleKnitInstruction::query()->create([
                'style_id' => $row->id,
                'image_title' => $this->blank($k['image_title'] ?? null),
                'image_ids' => array_values($k['image_ids'] ?? []),
                'gmt_colour_ids' => array_values($k['gmt_colour_ids'] ?? []),
                // A line with no colour and no value is a blank row, not kept.
                'lines' => array_values(array_filter(array_map(fn (array $l): array => ['colour_id' => $l['colour_id'] ?? null, 'value' => $this->blank($l['value'] ?? null), 'unit' => $l['unit'] ?? null], $k['lines'] ?? []), fn (array $l): bool => $l['colour_id'] !== null || $l['value'] !== null)),
                'sort_order' => ($i + 1) * 10,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function fabrics(Style $style): array
    {
        $types = FabricType::query()->pluck('name', 'id');
        $comps = FiberComposition::query()->get(['id', 'code', 'description'])->mapWithKeys(fn ($c): array => [$c->id => $c->description ?: $c->code]);

        return StyleFabric::query()->where('style_id', $style->id)->orderBy('sort_order')->get()->map(fn (StyleFabric $f): array => [
            ...collect($f->getAttributes())->except(['company_id', 'created_at', 'updated_at'])->all(),
            'item_ids' => $f->item_ids ?? [], 'garment_part_ids' => $f->garment_part_ids ?? [], 'counts' => $f->counts ?? [], 'wash_types' => $f->wash_types ?? [], 'finish_types' => $f->finish_types ?? [],
            'is_slub' => $f->is_slub, 'is_melange' => $f->is_melange, 'is_main' => $f->is_main, 'wash_na' => $f->wash_na, 'finish_na' => $f->finish_na,
            'weight_from' => $f->weight_from === null ? null : (float) $f->weight_from, 'weight_to' => $f->weight_to === null ? null : (float) $f->weight_to,
            'fabric_type' => $f->fabric_type_id ? ($types[$f->fabric_type_id] ?? null) : null,
            'composition' => $f->fiber_composition_id ? ($comps[$f->fiber_composition_id] ?? null) : null,
        ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function instructions(Style $style): array
    {
        return StyleKnitInstruction::query()->where('style_id', $style->id)->orderBy('sort_order')->get()->map(fn (StyleKnitInstruction $k): array => [
            'id' => $k->id, 'image_title' => $k->image_title, 'image_ids' => $k->image_ids ?? [],
            'images' => array_map(fn (string $id): array => ['id' => $id, 'path' => "/api/files/{$id}/download"], $k->image_ids ?? []),
            'gmt_colour_ids' => $k->gmt_colour_ids ?? [], 'lines' => $k->lines ?? [],
        ])->values()->all();
    }

    private function blank(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}

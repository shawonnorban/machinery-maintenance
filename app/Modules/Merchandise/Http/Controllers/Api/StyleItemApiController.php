<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Merchandise\Models\ItemType;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleItem;
use App\Modules\Merchandise\Models\StyleItemStyle;
use App\Modules\Merchandise\Models\WorkNature;
use App\Modules\Merchandise\Support\MerchandiseDefaults;
use App\Modules\Merchandise\Support\StyleItemOptions;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Style Development Sheet → Item/Combo/Model (Fresh / Copy) → Item/Combo/Model
 * Registration (AnnotechERP 06/05): the items serving a style, one at a time
 * (Add New, Item List, Previous / Next), each saved with the styles it serves,
 * its care label lines and its pictures. A Copy desk may start an item from
 * any style's item ("Same as"); a Fresh desk from this style's own.
 */
class StyleItemApiController extends ApiController
{
    public function index(string $style): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $row = Style::query()->whereKey($style)->firstOrFail();
        $ids = StyleItemStyle::query()->where('style_id', $row->id)->pluck('style_item_id');
        $items = StyleItem::query()->whereIn('id', $ids)->orderBy('sort_order')->orderBy('created_at')->get();
        $links = StyleItemStyle::query()->whereIn('style_item_id', $items->pluck('id'))->get()->groupBy('style_item_id');
        $styleNos = Style::query()->get(['id', 'style_ref', 'style_ext'])->mapWithKeys(fn (Style $s): array => [$s->id => $s->styleNo()]);

        return ApiResponse::ok([
            'items' => $items->map(fn (StyleItem $i): array => $this->present($i, $links[$i->id] ?? collect(), $styleNos))->values()->all(),
            // "Same as": every registered item, for a Copy desk; the client narrows a Fresh desk to this style's.
            'copy_from' => StyleItem::query()->orderBy('full_name')->get()->map(fn (StyleItem $i): array => [
                'value' => $i->id, 'label' => trim($i->short_name.' — '.$i->full_name).' ('.($styleNos[$i->style_id] ?? '').')', 'style_id' => $i->style_id,
                'item' => $this->present($i, collect(), $styleNos),
            ])->values()->all(),
            'styles' => $styleNos->map(fn (string $no, string $id): array => ['value' => $id, 'label' => $no])->values()->all(),
            'sets' => Style::query()->where('is_set', true)->get(['id', 'description'])->map(fn (Style $s): array => ['value' => $s->id, 'label' => "Set - {$s->description}"])->values()->all(),
            'options' => [
                'groups' => StyleItemOptions::GROUPS,
                'types' => $this->itemTypes(),
                'divisions' => StyleItemOptions::DIVISIONS,
                'neck_types' => StyleItemOptions::NECK_TYPES,
                'sleeve_types' => StyleItemOptions::SLEEVE_TYPES,
                'genders' => StyleItemOptions::GENDERS,
                'age_groups' => StyleItemOptions::AGE_GROUPS,
                'nature_of_work' => $this->workNatures(),
                'care_services' => StyleItemOptions::CARE_SERVICES,
            ],
        ]);
    }

    public function store(Request $request, string $style): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = Style::query()->whereKey($style)->firstOrFail();

        return ApiResponse::created($this->save($request, $row, null));
    }

    public function update(Request $request, string $item): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = StyleItem::query()->whereKey($item)->firstOrFail();

        return ApiResponse::ok($this->save($request, Style::query()->whereKey($row->style_id)->firstOrFail(), $row));
    }

    public function destroy(string $item): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        StyleItem::query()->whereKey($item)->firstOrFail()->delete();

        return response()->json(null, 204);
    }

    /** A picture for an item (key, 2nd, item image or a certificate): stored privately, its download path returned. */
    public function upload(Request $request, StoreFileAttachment $files): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192']]);
        $file = $files->handle($request->file('file'), 'merch_style_item_image', $this->caller()->companyId, $this->caller()->auditUserId());

        return ApiResponse::created(['id' => $file->id, 'path' => "/api/files/{$file->id}/download"]);
    }

    /** @return array<string, mixed> */
    private function save(Request $request, Style $style, ?StyleItem $row): array
    {
        $company = $this->caller()->companyId;
        $group = (string) $request->input('item_group');
        // Natures of work, several at a time: a list of names (an old code, or a comma-joined text, is read as its names).
        $natures = $request->input('nature_of_work');
        $natures = is_array($natures) ? $natures : array_filter(array_map('trim', explode(',', (string) $natures)));
        $request->merge(['nature_of_work' => array_values(array_map(fn ($v) => self::LEGACY_WORK[$v] ?? $v, $natures))]);
        // What the masters offer, and what this item already carries (a type or nature since switched off stays valid on it).
        $types = $this->itemTypes()[$group] ?? [];
        $offered = $this->workNatures();
        if ($row !== null) {
            $types[] = $row->item_type;
            $offered = array_merge($offered, array_map('trim', explode(',', (string) $row->nature_of_work)));
        }
        $data = $request->validate([
            'for_style_ids' => ['required', 'array', 'min:1'],
            'for_style_ids.*' => ['distinct', Rule::exists('merch_styles', 'id')->where('company_id', $company)],
            'set_style_id' => ['nullable', Rule::exists('merch_styles', 'id')->where('company_id', $company)->where('is_set', true)],
            'model_no' => ['nullable', 'string', 'max:64'],
            'item_group' => ['required', Rule::in(StyleItemOptions::GROUPS)],
            // A type of the chosen group; "Other" has none.
            'item_type' => [$group === 'OTHER' ? 'nullable' : 'required', Rule::in($types)],
            'division' => ['required', Rule::in(StyleItemOptions::DIVISIONS)],
            'neck_type' => ['nullable', Rule::in(StyleItemOptions::NECK_TYPES)],
            'sleeve_type' => ['nullable', Rule::in(StyleItemOptions::SLEEVE_TYPES)],
            'short_name' => ['required', 'string', 'max:32'],
            'full_name' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'gender' => ['nullable', Rule::in(StyleItemOptions::GENDERS)],
            'age_group' => ['nullable', Rule::in(StyleItemOptions::AGE_GROUPS)],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
            ...array_fill_keys(StyleItemOptions::SERVICE_FLAGS, ['sometimes', 'boolean']),
            'nature_of_work' => ['required', 'array', 'min:1', 'max:20'],
            'nature_of_work.*' => ['distinct', Rule::in($offered)],
            'no_of_mc' => ['nullable', 'string', 'max:16'],
            'exp_prod_hr' => ['nullable', 'string', 'max:16'],
            'smv_marketing' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'tech_spec' => ['nullable', 'string', 'max:5000'],
            'key_image_id' => ['nullable', 'ulid'],
            'second_image_id' => ['nullable', 'ulid'],
            'item_image_id' => ['nullable', 'ulid'],
            'certificates' => ['nullable', 'array', 'max:4'],
            'certificates.*.checked' => ['sometimes', 'boolean'],
            'certificates.*.file_id' => ['nullable', 'ulid'],
            'care_labels' => ['nullable', 'array', 'max:30'],
            'care_labels.*.garment_part' => ['nullable', 'string', 'max:64'],
            'care_labels.*.service' => ['nullable', Rule::in(StyleItemOptions::CARE_SERVICES)],
        ], [
            'item_group.required' => __('merchandise.style_item.group_required'),
            'item_type.required' => __('merchandise.style_item.type_required'),
            'division.required' => __('merchandise.style_item.division_required'),
            'short_name.required' => __('merchandise.style_item.short_required'),
            'full_name.required' => __('merchandise.style_item.full_required'),
            'nature_of_work.required' => __('merchandise.style_item.work_required'),
            'for_style_ids.required' => __('merchandise.style_item.styles_required'),
        ]);

        $values = collect($data)->except(['for_style_ids', 'certificates', 'care_labels'])->all();
        foreach (StyleItemOptions::SERVICE_FLAGS as $flag) {
            $values[$flag] = (bool) ($data[$flag] ?? false);
        }
        $values['nature_of_work'] = implode(', ', $data['nature_of_work']);
        $values['short_name'] = strtoupper(trim($data['short_name']));
        $values['full_name'] = strtoupper(trim($data['full_name']));
        $values['certificates'] = array_values(array_map(fn (array $c): array => ['checked' => (bool) ($c['checked'] ?? false), 'file_id' => $c['file_id'] ?? null], $data['certificates'] ?? []));
        // A care label line with neither part nor service is a blank row, not kept.
        $values['care_labels'] = array_values(array_filter(array_map(fn (array $l): array => ['garment_part' => trim((string) ($l['garment_part'] ?? '')) ?: null, 'service' => $l['service'] ?? null], $data['care_labels'] ?? []), fn (array $l): bool => $l['garment_part'] !== null || $l['service'] !== null));

        $item = DB::transaction(function () use ($style, $row, $values, $data): StyleItem {
            if ($row === null) {
                $values['sort_order'] = (int) StyleItem::query()->where('style_id', $style->id)->max('sort_order') + 1;
                $row = StyleItem::query()->create($values + ['style_id' => $style->id, 'created_by' => $this->caller()->auditUserId()]);
            } else {
                $row->update($values);
            }
            StyleItemStyle::query()->where('style_item_id', $row->id)->whereNotIn('style_id', $data['for_style_ids'])->delete();
            foreach ($data['for_style_ids'] as $id) {
                StyleItemStyle::query()->firstOrCreate(['style_item_id' => $row->id, 'style_id' => $id]);
            }

            return $row->fresh();
        });

        $styleNos = Style::query()->get(['id', 'style_ref', 'style_ext'])->mapWithKeys(fn (Style $s): array => [$s->id => $s->styleNo()]);

        return $this->present($item, StyleItemStyle::query()->where('style_item_id', $item->id)->get(), $styleNos);
    }

    /** The codes an item carried before the Nature of Work Master, and the names they stood for. */
    private const LEGACY_WORK = ['SEWING' => 'Sewing', 'SEWING_PRINT' => 'Sewing + Print', 'SEWING_EMBROIDERY' => 'Sewing + Embroidery', 'CUT_MAKE' => 'Cut & Make'];

    /**
     * The item types the Item Type Master offers, by group ("Other" has none).
     *
     * @return array<string, list<string>>
     */
    private function itemTypes(): array
    {
        MerchandiseDefaults::ensureItemTypes($this->caller()->companyId);
        $types = ItemType::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['item_group', 'name'])->groupBy('item_group');

        return collect(StyleItemOptions::GROUPS)->mapWithKeys(fn (string $g): array => [$g => ($types[$g] ?? collect())->pluck('name')->values()->all()])->all();
    }

    /** @return list<string> the natures of work the Nature of Work Master offers */
    private function workNatures(): array
    {
        MerchandiseDefaults::ensureWorkNatures($this->caller()->companyId);

        return WorkNature::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->pluck('name')->all();
    }

    /**
     * @param  Collection<int, StyleItemStyle>  $links
     * @param  Collection<string, string>  $styleNos
     * @return array<string, mixed>
     */
    private function present(StyleItem $item, $links, $styleNos): array
    {
        $path = fn (?string $id): ?string => $id ? "/api/files/{$id}/download" : null;
        $out = collect($item->getAttributes())->except(['company_id', 'created_by', 'certificates', 'care_labels'])->all();
        foreach (StyleItemOptions::SERVICE_FLAGS as $flag) {
            $out[$flag] = (bool) $item->{$flag};
        }
        $out['smv_marketing'] = $item->smv_marketing === null ? null : (float) $item->smv_marketing;
        $out['for_style_ids'] = $links->pluck('style_id')->values()->all();
        $out['for_styles'] = $links->map(fn (StyleItemStyle $l): ?string => $styleNos[$l->style_id] ?? null)->filter()->values()->all();
        $out['certificates'] = array_map(fn (array $c): array => $c + ['path' => $path($c['file_id'] ?? null)], $item->certificates ?? []);
        $out['care_labels'] = $item->care_labels ?? [];
        $out['key_image'] = $path($item->key_image_id);
        $out['second_image'] = $path($item->second_image_id);
        $out['item_image'] = $path($item->item_image_id);

        return $out;
    }
}

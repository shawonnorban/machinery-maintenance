<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\DyeHouse\Models\DyeColourGroup;
use App\Modules\DyeHouse\Models\DyeMethod;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleColour;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Item/Combo/Model → Colour Mapping (AnnotechERP 06/05): the colours a style is
 * made in, edited as one grid and saved together. A colour picked from Colour
 * Master brings its type, Pantone, colour group and swatch along (the screen
 * fills them in); each colour appears once on a style.
 */
class StyleColourApiController extends ApiController
{
    public function index(string $style): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $row = Style::query()->whereKey($style)->firstOrFail();

        return ApiResponse::ok([
            'rows' => $this->rows($row),
            'colours' => Colour::query()->where('active', true)->orderBy('name')->get()->map(fn (Colour $c): array => [
                'value' => $c->id, 'label' => $c->name, 'type' => $c->colour_type, 'pantone' => $c->pantone_no, 'group_id' => $c->colour_group_id, 'swatch' => (bool) $c->swatch_provided,
            ])->values()->all(),
            'groups' => DyeColourGroup::query()->where('active', true)->orderBy('display_order')->orderBy('name')->get(['id', 'name'])->map(fn ($g): array => ['value' => $g->id, 'label' => $g->name])->values()->all(),
            'methods' => DyeMethod::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($d): array => ['value' => $d->id, 'label' => $d->name])->values()->all(),
            'compositions' => FiberComposition::query()->where('active', true)->orderBy('description')->get(['id', 'code', 'description'])->map(fn ($c): array => ['value' => $c->id, 'label' => $c->description ?: $c->code])->values()->all(),
            'types' => StyleColour::TYPES,
        ]);
    }

    /** The whole grid at once: rows kept, changed, added and dropped together. */
    public function save(Request $request, string $style): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = Style::query()->whereKey($style)->firstOrFail();
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'rows' => ['present', 'array', 'max:100'],
            'rows.*.colour_id' => ['required', 'distinct', Rule::exists('colours', 'id')->where('company_id', $company)],
            'rows.*.colour_type' => ['nullable', Rule::in(StyleColour::TYPES)],
            'rows.*.is_gmt_colour' => ['sometimes', 'boolean'],
            'rows.*.colour_group_id' => ['nullable', Rule::exists('dye_colour_groups', 'id')->where('company_id', $company)],
            'rows.*.dye_method_id' => ['nullable', Rule::exists('dye_methods', 'id')->where('company_id', $company)],
            'rows.*.fiber_composition_id' => ['nullable', Rule::exists('fiber_compositions', 'id')->where('company_id', $company)],
            'rows.*.colour_ref' => ['nullable', 'string', 'max:64'],
            'rows.*.shade_no' => ['nullable', 'string', 'max:32'],
            'rows.*.swatch' => ['sometimes', 'boolean'],
            'rows.*.pantone_no' => ['nullable', 'string', 'max:32'],
            'rows.*.buyer_colour_name' => ['nullable', 'string', 'max:128'],
            'rows.*.remarks' => ['nullable', 'string', 'max:500'],
            'rows.*.image_id' => ['nullable', 'ulid'],
        ], [
            'rows.*.colour_id.required' => __('merchandise.style_colour.colour_required'),
            'rows.*.colour_id.distinct' => __('merchandise.style_colour.colour_twice'),
        ]);

        try {
            DB::transaction(function () use ($row, $data): void {
                $keep = array_column($data['rows'], 'colour_id');
                StyleColour::query()->where('style_id', $row->id)->whereNotIn('colour_id', $keep)->delete();
                foreach (array_values($data['rows']) as $i => $r) {
                    StyleColour::query()->updateOrCreate(['style_id' => $row->id, 'colour_id' => $r['colour_id']], [
                        'colour_type' => $r['colour_type'] ?? null,
                        'is_gmt_colour' => (bool) ($r['is_gmt_colour'] ?? false),
                        'colour_group_id' => $r['colour_group_id'] ?? null,
                        'dye_method_id' => $r['dye_method_id'] ?? null,
                        'fiber_composition_id' => $r['fiber_composition_id'] ?? null,
                        'colour_ref' => $this->blank($r['colour_ref'] ?? null),
                        'shade_no' => $this->blank($r['shade_no'] ?? null),
                        'swatch' => (bool) ($r['swatch'] ?? false),
                        'pantone_no' => $this->blank($r['pantone_no'] ?? null),
                        'buyer_colour_name' => $this->blank($r['buyer_colour_name'] ?? null),
                        'remarks' => $this->blank($r['remarks'] ?? null),
                        'image_id' => $r['image_id'] ?? null,
                        'sort_order' => ($i + 1) * 10,
                    ]);
                }
            });
        } catch (QueryException $e) {
            // A colour others are recorded against (services, fabric mapping) stays.
            if ($e->getCode() === '23503') {
                throw ValidationException::withMessages(['rows' => __('erp.in_use')]);
            }
            throw $e;
        }

        return ApiResponse::ok($this->rows($row));
    }

    /** @return list<array<string, mixed>> */
    private function rows(Style $style): array
    {
        $names = Colour::query()->pluck('name', 'id');

        return StyleColour::query()->where('style_id', $style->id)->orderBy('sort_order')->get()->map(fn (StyleColour $c): array => [
            'id' => $c->id, 'colour_id' => $c->colour_id, 'colour' => $names[$c->colour_id] ?? null, 'colour_type' => $c->colour_type, 'is_gmt_colour' => $c->is_gmt_colour,
            'colour_group_id' => $c->colour_group_id, 'dye_method_id' => $c->dye_method_id, 'fiber_composition_id' => $c->fiber_composition_id,
            'colour_ref' => $c->colour_ref, 'shade_no' => $c->shade_no, 'swatch' => $c->swatch, 'pantone_no' => $c->pantone_no, 'buyer_colour_name' => $c->buyer_colour_name,
            'remarks' => $c->remarks, 'image_id' => $c->image_id, 'image' => $c->image_id ? "/api/files/{$c->image_id}/download" : null,
        ])->values()->all();
    }

    private function blank(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}

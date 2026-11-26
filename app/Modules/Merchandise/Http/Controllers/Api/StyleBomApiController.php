<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleBom;
use App\Modules\Merchandise\Models\StyleBomLine;
use App\Modules\Merchandise\Models\StyleBomVersion;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Item/Combo/Model → BOM (AnnotechERP 06/05). The first save numbers the
 * style's BOM (BOM-000001) and opens V1 as a draft. Saving a draft rewrites
 * it; saving once the newest version is approved opens the next version as a
 * draft, the approved one staying in use until the draft is approved.
 */
class StyleBomApiController extends ApiController
{
    public function index(string $style): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $row = Style::query()->whereKey($style)->firstOrFail();

        return ApiResponse::ok($this->present($row) + [
            'options' => ['cons_types' => StyleBomLine::CONS_TYPES, 'units' => StyleBomLine::UNITS, 'categories' => StyleBomLine::CATEGORIES],
        ]);
    }

    public function save(Request $request, string $style): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = Style::query()->whereKey($style)->firstOrFail();
        $data = $request->validate([
            'order_qty' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'addl_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines' => ['present', 'array', 'max:200'],
            'lines.*.item_name' => ['required', 'string', 'max:191'],
            'lines.*.cons_type' => ['nullable', Rule::in(StyleBomLine::CONS_TYPES)],
            'lines.*.consumption' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'lines.*.cons_unit' => ['nullable', Rule::in(StyleBomLine::UNITS)],
            'lines.*.per_gmt' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.purchase_unit' => ['nullable', Rule::in(StyleBomLine::UNITS)],
            'lines.*.conversion' => ['nullable', 'numeric', 'gt:0', 'max:9999999999'],
            'lines.*.apply_addl' => ['sometimes', 'boolean'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
            'lines.*.category' => ['nullable', Rule::in(StyleBomLine::CATEGORIES)],
            'lines.*.item_code' => ['nullable', 'string', 'max:64'],
            'lines.*.allow_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.supplier' => ['nullable', 'string', 'max:191'],
        ], [
            'lines.*.item_name.required' => __('merchandise.style_bom.item_required'),
        ]);

        DB::transaction(function () use ($row, $data): void {
            $bom = $this->bomOf($row, create: true);
            $latest = $this->latest($bom);
            $version = $latest?->status === StyleBomVersion::DRAFT
                ? $latest
                : StyleBomVersion::query()->create(['bom_id' => $bom->id, 'version' => ($latest->version ?? 0) + 1, 'status' => StyleBomVersion::DRAFT]);
            $version->update(['order_qty' => $data['order_qty'] ?? null, 'addl_pct' => $data['addl_pct'] ?? null]);

            StyleBomLine::query()->where('version_id', $version->id)->delete();
            foreach (array_values($data['lines']) as $i => $l) {
                StyleBomLine::query()->create([
                    'version_id' => $version->id,
                    'item_name' => trim($l['item_name']),
                    'cons_type' => $l['cons_type'] ?? null,
                    'consumption' => $l['consumption'] ?? null,
                    'cons_unit' => $l['cons_unit'] ?? null,
                    'per_gmt' => $l['per_gmt'] ?? null,
                    'purchase_unit' => $l['purchase_unit'] ?? null,
                    'conversion' => $l['conversion'] ?? null,
                    'apply_addl' => (bool) ($l['apply_addl'] ?? false),
                    'rate' => $l['rate'] ?? null,
                    'remarks' => $this->blank($l['remarks'] ?? null),
                    'category' => $l['category'] ?? null,
                    'item_code' => $this->blank($l['item_code'] ?? null),
                    'allow_pct' => $l['allow_pct'] ?? null,
                    'supplier' => $this->blank($l['supplier'] ?? null),
                    'sort_order' => ($i + 1) * 10,
                ]);
            }
        });

        return ApiResponse::ok($this->present($row));
    }

    /** The newest version becomes the one in use; only a draft with lines is approved. */
    public function approve(string $style): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = Style::query()->whereKey($style)->firstOrFail();

        DB::transaction(function () use ($row): void {
            $bom = $this->bomOf($row, create: false);
            $latest = $bom ? $this->latest($bom) : null;
            if (! $latest || $latest->status !== StyleBomVersion::DRAFT) {
                throw ValidationException::withMessages(['bom' => __('merchandise.style_bom.nothing_to_approve')]);
            }
            if (! StyleBomLine::query()->where('version_id', $latest->id)->exists()) {
                throw ValidationException::withMessages(['bom' => __('merchandise.style_bom.empty')]);
            }
            $latest->update(['status' => StyleBomVersion::APPROVED, 'approved_at' => now(), 'approved_by' => $this->caller()->auditUserId()]);
        });

        return ApiResponse::ok($this->present($row));
    }

    /** The style's BOM, numbered on first use; the style is locked for the rest of the transaction. */
    private function bomOf(Style $style, bool $create): ?StyleBom
    {
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_style_bom:'.$style->id]);
        $bom = StyleBom::query()->where('style_id', $style->id)->first();
        if ($bom || ! $create) {
            return $bom;
        }
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_style_boms:'.$style->company_id]);
        $last = StyleBom::query()->orderByDesc('bom_no')->value('bom_no');
        $n = $last ? ((int) substr($last, 4)) + 1 : 1;

        return StyleBom::query()->create(['style_id' => $style->id, 'bom_no' => 'BOM-'.str_pad((string) $n, 6, '0', STR_PAD_LEFT)]);
    }

    private function latest(StyleBom $bom): ?StyleBomVersion
    {
        return StyleBomVersion::query()->where('bom_id', $bom->id)->orderByDesc('version')->first();
    }

    /** @return array<string, mixed> */
    private function present(Style $style): array
    {
        $bom = StyleBom::query()->where('style_id', $style->id)->first();
        $latest = $bom ? $this->latest($bom) : null;
        $inUse = $bom ? StyleBomVersion::query()->where('bom_id', $bom->id)->where('status', StyleBomVersion::APPROVED)->max('version') : null;

        return [
            'bom_no' => $bom?->bom_no,
            'version' => $latest?->version,
            'status' => $latest?->status,
            'in_use' => $inUse ? (int) $inUse : null,
            'order_qty' => $latest?->order_qty,
            'addl_pct' => $latest?->addl_pct,
            'lines' => $latest ? StyleBomLine::query()->where('version_id', $latest->id)->orderBy('sort_order')->get()->map(fn (StyleBomLine $l): array => $l->only([
                'id', 'item_name', 'cons_type', 'consumption', 'cons_unit', 'per_gmt', 'purchase_unit', 'conversion', 'apply_addl', 'rate', 'remarks', 'category', 'item_code', 'allow_pct', 'supplier',
            ]))->values()->all() : [],
        ];
    }

    private function blank(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}

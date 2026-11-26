<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\BuyerOfficeLink;
use App\Modules\Merchandise\Models\Inquiry;
use App\Modules\Merchandise\Models\OriginCountry;
use App\Modules\Merchandise\Models\Size;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleSizeRange;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Style Development Sheet (AnnotechERP 06/05): the development styles with
 * their filters, the registration form (header and size range lines) for a
 * new style, its edit and its sheet, removing one, and the Size Range panel
 * where a style's size sets are added and removed on their own.
 */
class StyleApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $q = Style::query()->latest('created_at');
        foreach (['buyer_id', 'buyer_office_id', 'season', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $q->where($filter, $request->string($filter)->toString());
            }
        }
        if ($request->filled('ref')) {
            $q->where('style_ref', 'ilike', '%'.$request->string('ref')->toString().'%');
        }
        $styles = $q->get();

        return ApiResponse::ok($this->present($styles));
    }

    /** Everything the registration form picks from. */
    public function lookups(): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $offices = BuyerOffice::query()->orderBy('name')->pluck('name', 'id');
        $links = BuyerOfficeLink::query()->get(['buyer_id', 'buyer_office_id'])->groupBy('buyer_id')
            ->map(fn (Collection $rows): array => $rows->map(fn ($l): array => ['value' => $l->buyer_office_id, 'label' => $offices[$l->buyer_office_id] ?? ''])->values()->all());

        return ApiResponse::ok([
            'buyers' => Buyer::query()->where('active', true)->orderBy('short_name')->get(['id', 'code', 'short_name', 'name'])
                ->map(fn (Buyer $b): array => ['value' => $b->id, 'label' => ($b->short_name ?: $b->name)." ({$b->code})", 'short' => $b->short_name ?: $b->name])->all(),
            'offices' => $offices->map(fn (string $name, string $id): array => ['value' => $id, 'label' => $name])->values()->all(),
            'buyer_offices' => $links,
            // A style is registered against a confirmed inquiry of its buyer.
            'inquiries' => Inquiry::query()->where('status', 'CONFIRMED')->orderByDesc('inquiry_no')->get(['id', 'inquiry_no', 'style_no', 'buyer_id'])
                ->map(fn (Inquiry $i): array => ['value' => $i->id, 'label' => "{$i->inquiry_no} ({$i->style_no})", 'buyer_id' => $i->buyer_id])->all(),
            'styles' => Style::query()->orderBy('style_ref')->get(['id', 'style_ref', 'style_ext'])->map(fn (Style $s): array => ['value' => $s->id, 'label' => $s->styleNo()])->all(),
            'suppliers' => MaterialSupplier::query()->where('active', true)->orderBy('name')->get(['id', 'code', 'name'])->map(fn ($s): array => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->all(),
            'countries' => OriginCountry::query()->where('active', true)->orderBy('name')->get(['id', 'code', 'name'])->map(fn ($c): array => ['value' => $c->id, 'label' => "{$c->name} ({$c->code})"])->all(),
            'sizes' => Size::query()->where('active', true)->orderBy('size_group')->orderBy('display_order')->orderBy('name')->get(['id', 'name', 'size_group'])
                ->map(fn (Size $s): array => ['value' => $s->id, 'label' => $s->name, 'group' => $s->size_group])->all(),
            'seasons' => self::seasons(),
        ]);
    }

    public function show(string $style): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $row = Style::query()->whereKey($style)->firstOrFail();

        return ApiResponse::ok($this->present(collect([$row]))[0] + ['lines' => $this->lines($row)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = $this->save($request, null);

        return ApiResponse::created($this->present(collect([$row]))[0] + ['lines' => $this->lines($row)]);
    }

    public function update(Request $request, string $style): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = $this->save($request, Style::query()->whereKey($style)->firstOrFail());

        return ApiResponse::ok($this->present(collect([$row]))[0] + ['lines' => $this->lines($row)]);
    }

    public function destroy(string $style): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = Style::query()->whereKey($style)->firstOrFail();
        try {
            DB::transaction(fn () => $row->delete());
        } catch (QueryException $e) {
            if ($e->getCode() === '23503') {
                throw ValidationException::withMessages(['id' => __('erp.in_use')]);
            }
            throw $e;
        }

        return response()->json(null, 204);
    }

    /** Size Range panel: a style's lines. */
    public function sizeRanges(string $style): JsonResponse
    {
        $this->allow('merchandise.style.view');

        return ApiResponse::ok($this->lines(Style::query()->whereKey($style)->firstOrFail()));
    }

    public function addSizeRange(Request $request, string $style): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = Style::query()->whereKey($style)->firstOrFail();
        $data = $request->validate([
            'size_ids' => ['required', 'array', 'min:1'],
            'size_ids.*' => ['distinct', Rule::exists('sizes', 'id')->where('company_id', $this->caller()->companyId)],
            'sub_style' => ['nullable', 'string', 'max:64'],
            'sl_no' => ['nullable', 'integer', 'min:1', 'max:999', Rule::unique('merch_style_size_ranges', 'sl_no')->where('style_id', $row->id)],
        ], ['size_ids.required' => __('merchandise.style.line_needs_size'), 'size_ids.min' => __('merchandise.style.line_needs_size')]);
        StyleSizeRange::query()->create([
            'style_id' => $row->id,
            'sl_no' => $data['sl_no'] ?? ((int) StyleSizeRange::query()->where('style_id', $row->id)->max('sl_no')) + 1,
            'size_ids' => array_values($data['size_ids']),
            'sub_style' => $this->blank($data['sub_style'] ?? null),
        ]);

        return ApiResponse::created($this->lines($row));
    }

    public function removeSizeRange(string $range): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        StyleSizeRange::query()->whereKey($range)->firstOrFail()->delete();

        return response()->json(null, 204);
    }

    /** Spring/Summer and Autumn/Winter of last year, this year and the next two. @return list<string> */
    public static function seasons(): array
    {
        $year = (int) now()->format('Y');
        $out = [];
        foreach (range($year - 1, $year + 2) as $y) {
            $out[] = "Spring/Summer {$y}";
            $out[] = "Autumn/Winter {$y}";
        }

        return $out;
    }

    private function save(Request $request, ?Style $row): Style
    {
        $company = $this->caller()->companyId;
        $statuses = $row ? Style::STATUSES : Style::FORM_STATUSES;
        $data = $request->validate([
            'buyer_id' => ['required', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'buyer_office_id' => ['nullable', Rule::exists('buyer_office_links', 'buyer_office_id')->where('buyer_id', $request->input('buyer_id'))],
            'inquiry_id' => ['nullable', Rule::exists('merch_inquiries', 'id')->where('company_id', $company)->where('buyer_id', $request->input('buyer_id'))->where('status', 'CONFIRMED')],
            'style_ref' => ['required', 'string', 'max:64'],
            'style_ext' => ['nullable', 'string', 'max:32'],
            'description' => ['required', 'string', 'max:255'],
            'is_set' => ['sometimes', 'boolean'],
            'is_model' => ['sometimes', 'boolean'],
            'is_combo' => ['sometimes', 'boolean'],
            'is_colour_pair' => ['sometimes', 'boolean'],
            'prev_style_id' => ['nullable', Rule::exists('merch_styles', 'id')->where('company_id', $company), Rule::notIn([$row?->id])],
            'qty_unit' => ['required', Rule::in(Style::QTY_UNITS)],
            'pcs_per_unit' => ['nullable', 'integer', 'min:1', 'max:999'],
            'season' => ['nullable', Rule::in(self::seasons())],
            'ref_no' => ['nullable', 'string', 'max:32'],
            'nature_of_work' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'origin_country_id' => ['nullable', Rule::exists('origin_countries', 'id')->where('company_id', $company)],
            'status' => ['required', Rule::in($statuses)],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.size_ids' => ['required', 'array', 'min:1'],
            'lines.*.size_ids.*' => [Rule::exists('sizes', 'id')->where('company_id', $company)],
            'lines.*.sub_style' => ['nullable', 'string', 'max:64'],
            'lines.*.sl_no' => ['nullable', 'integer', 'min:1', 'max:999', 'distinct'],
        ], [
            'buyer_id.required' => __('merchandise.style.buyer_required'),
            'style_ref.required' => __('merchandise.style.ref_required'),
            'description.required' => __('merchandise.style.desc_required'),
            'lines.required' => __('merchandise.style.line_needs_size'),
            'lines.*.size_ids.required' => __('merchandise.style.line_needs_size'),
            'lines.*.size_ids.min' => __('merchandise.style.line_needs_size'),
        ]);

        $ref = trim($data['style_ref']);
        $ext = $this->blank($data['style_ext'] ?? null);
        $taken = Style::query()->where('style_ref', $ref)->whereRaw("COALESCE(style_ext, '') = ?", [$ext ?? ''])->when($row, fn ($q) => $q->whereKeyNot($row->id))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['style_ref' => __('merchandise.style.ref_taken')]);
        }

        $header = [
            'buyer_id' => $data['buyer_id'],
            'buyer_office_id' => $data['buyer_office_id'] ?? null,
            'inquiry_id' => $data['inquiry_id'] ?? null,
            'style_ref' => $ref,
            'style_ext' => $ext,
            'description' => trim($data['description']),
            'is_set' => (bool) ($data['is_set'] ?? false),
            'is_model' => (bool) ($data['is_model'] ?? false),
            'is_combo' => (bool) ($data['is_combo'] ?? false),
            'is_colour_pair' => (bool) ($data['is_colour_pair'] ?? false),
            'prev_style_id' => $data['prev_style_id'] ?? null,
            'qty_unit' => $data['qty_unit'],
            // A piece is one piece; a set or a dozen counts how many pieces make one.
            'pcs_per_unit' => $data['qty_unit'] === 'PCS' ? 1 : (int) ($data['pcs_per_unit'] ?? ($data['qty_unit'] === 'DZN' ? 12 : 1)),
            'season' => $data['season'] ?? null,
            'ref_no' => $this->blank($data['ref_no'] ?? null),
            'nature_of_work' => $this->blank($data['nature_of_work'] ?? null),
            'supplier_id' => $data['supplier_id'] ?? null,
            'origin_country_id' => $data['origin_country_id'] ?? null,
            'status' => $data['status'],
        ];

        return DB::transaction(function () use ($row, $header, $data): Style {
            if ($row === null) {
                // A development style is numbered as it is raised (DEV-000001); the lock is held to the end of the save.
                DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_styles_dev:'.$this->caller()->companyId]);
                $row = Style::query()->create($header + ['created_by' => $this->caller()->auditUserId(), 'dev_no' => 1 + (int) Style::query()->max('dev_no')]);
            } else {
                $row->update($header);
            }
            StyleSizeRange::query()->where('style_id', $row->id)->delete();
            $next = 1;
            foreach (array_values($data['lines']) as $line) {
                $sl = (int) ($line['sl_no'] ?? $next);
                StyleSizeRange::query()->create(['style_id' => $row->id, 'sl_no' => $sl, 'size_ids' => array_values(array_unique($line['size_ids'])), 'sub_style' => $this->blank($line['sub_style'] ?? null)]);
                $next = max($next, $sl) + 1;
            }

            return $row->fresh();
        });
    }

    /** @return list<array<string, mixed>> */
    private function lines(Style $style): array
    {
        $sizes = Size::query()->get(['id', 'name', 'size_group'])->keyBy('id');

        return StyleSizeRange::query()->where('style_id', $style->id)->orderBy('sl_no')->get()->map(function (StyleSizeRange $l) use ($sizes, $style): array {
            $picked = collect($l->size_ids)->map(fn (string $id) => $sizes[$id] ?? null)->filter();

            return [
                'id' => $l->id,
                'sl_no' => $l->sl_no,
                'size_ids' => $l->size_ids,
                'sizes' => $picked->pluck('name')->implode(', '),
                'groups' => $picked->pluck('size_group')->unique()->values()->all(),
                'sub_style' => $l->sub_style,
                'style' => $l->sub_style ?: $style->styleNo(),
            ];
        })->all();
    }

    /** @param  Collection<int, Style>  $styles @return list<array<string, mixed>> */
    private function present(Collection $styles): array
    {
        $buyers = Buyer::query()->whereIn('id', $styles->pluck('buyer_id'))->get(['id', 'short_name', 'name'])->keyBy('id');
        $offices = BuyerOffice::query()->whereIn('id', $styles->pluck('buyer_office_id')->filter())->pluck('name', 'id');
        $inquiries = Inquiry::query()->whereIn('id', $styles->pluck('inquiry_id')->filter())->pluck('inquiry_no', 'id');
        $countries = OriginCountry::query()->whereIn('id', $styles->pluck('origin_country_id')->filter())->pluck('name', 'id');
        $ranges = StyleSizeRange::query()->whereIn('style_id', $styles->pluck('id'))->selectRaw('style_id, count(*) as n')->groupBy('style_id')->pluck('n', 'style_id');

        return $styles->map(fn (Style $s): array => [
            'id' => $s->id,
            'style_no' => $s->styleNo(),
            'style_ref' => $s->style_ref,
            'style_ext' => $s->style_ext,
            'description' => $s->description,
            'buyer_id' => $s->buyer_id,
            'buyer' => $buyers[$s->buyer_id]->short_name ?? $buyers[$s->buyer_id]->name ?? null,
            'buyer_office_id' => $s->buyer_office_id,
            'buyer_office' => $s->buyer_office_id ? ($offices[$s->buyer_office_id] ?? null) : null,
            'inquiry_id' => $s->inquiry_id,
            'inquiry_no' => $s->inquiry_id ? ($inquiries[$s->inquiry_id] ?? null) : null,
            'is_set' => $s->is_set,
            'is_model' => $s->is_model,
            'is_combo' => $s->is_combo,
            'is_colour_pair' => $s->is_colour_pair,
            'prev_style_id' => $s->prev_style_id,
            'qty_unit' => $s->qty_unit,
            'pcs_per_unit' => $s->pcs_per_unit,
            'season' => $s->season,
            'ref_no' => $s->ref_no,
            'nature_of_work' => $s->nature_of_work,
            'supplier_id' => $s->supplier_id,
            'origin_country_id' => $s->origin_country_id,
            'origin' => $s->origin_country_id ? ($countries[$s->origin_country_id] ?? null) : null,
            // Orders are placed on a style once Style & Order is built (AnnotechERP 06/07).
            'orders' => 0,
            'size_ranges' => (int) ($ranges[$s->id] ?? 0),
            'status' => $s->status,
            'created_at' => $s->created_at?->toIso8601String(),
        ])->values()->all();
    }

    private function blank(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\GarmentPart;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleItem;
use App\Modules\Merchandise\Models\StyleItemStyle;
use App\Modules\Merchandise\Models\YarnSpec;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;

/**
 * Fabric Booking → Style-Wise Yarn Spec Confirmation (AnnotechERP 06/11), as
 * Brickland works it: pick a style (within a search category) and its yarn /
 * fabric mappings list beside the mapping form. Save writes one mapping per
 * production category ticked — the fabric, the garment item, its parts, the
 * garment colours (or all) and the fabric colours. Each mapping is Pending
 * until confirmed; a confirmed one is what knitting books yarn against, so
 * it is released before it is removed. Spec Confirmation lists the styles,
 * pending or confirmed, to open one.
 */
class YarnSpecApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('merchandise.booking.view');
        $styles = Style::query()->orderBy('style_ref')->get();
        $buyers = Buyer::query()->whereIn('id', $styles->pluck('buyer_id')->filter())->get(['id', 'name', 'short_name', 'code'])->keyBy('id');
        $links = StyleItemStyle::query()->whereIn('style_id', $styles->pluck('id'))->get(['style_id', 'style_item_id']);
        $items = StyleItem::query()->whereIn('id', $links->pluck('style_item_id'))->get(['id', 'full_name', 'short_name'])->keyBy('id');

        return ApiResponse::ok([
            'company' => Company::query()->whereKey($this->caller()->companyId)->value('name'),
            'categories' => YarnSpec::CATEGORIES,
            // Brickland's style picker names the style only; the header shows its buyer and description.
            'styles' => $styles->map(fn (Style $s): array => [
                'value' => $s->id, 'label' => $s->styleNo(), 'description' => $s->description, 'buyer_id' => $s->buyer_id,
                'buyer' => $s->buyer_id && isset($buyers[$s->buyer_id]) ? ($buyers[$s->buyer_id]->short_name ?: $buyers[$s->buyer_id]->name) : null,
                'items' => $links->where('style_id', $s->id)->map(fn ($l): ?string => isset($items[$l->style_item_id]) ? ($items[$l->style_item_id]->full_name ?: $items[$l->style_item_id]->short_name) : null)->filter()->values()->all(),
            ])->values()->all(),
            'buyers' => Buyer::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($b): array => ['value' => $b->id, 'label' => $b->code ? "{$b->name} ({$b->code})" : $b->name])->values()->all(),
            'fabrics' => FabricType::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($f): array => ['value' => $f->id, 'label' => $f->code ? "{$f->name} ({$f->code})" : $f->name])->values()->all(),
            'parts' => GarmentPart::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($p): array => ['value' => $p->id, 'label' => $p->name])->values()->all(),
            'colours' => Colour::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($c): array => ['value' => $c->id, 'label' => $c->code ? "{$c->name} ({$c->code})" : $c->name])->values()->all(),
        ]);
    }

    /** The style's mappings in one search category. */
    public function index(Request $request): JsonResponse
    {
        $this->allow('merchandise.booking.view');
        $data = $request->validate(['style_id' => ['required', $this->styleRule()], 'category' => ['required', Rule::in(YarnSpec::CATEGORIES)]]);

        return ApiResponse::ok($this->rows(YarnSpec::query()->where('style_id', $data['style_id'])->where('prod_category', $data['category'])->orderBy('created_at')->get()));
    }

    /** Save: one mapping for each production category ticked. */
    public function store(Request $request): JsonResponse
    {
        $this->allow('merchandise.booking.manage');
        $company = $this->caller()->companyId;
        $in = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $company);
        $data = $request->validate([
            'style_id' => ['required', $this->styleRule()],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => [Rule::in(YarnSpec::CATEGORIES)],
            'fabric_type_id' => ['required', $in('fabric_types')],
            'gmt_item' => ['required', 'string', 'max:96'],
            'part_ids' => ['required', 'array', 'min:1', 'max:40'],
            'part_ids.*' => [$in('garment_parts')],
            'all_colours' => ['sometimes', 'boolean'],
            'gmt_colour_ids' => ['array', 'max:60', Rule::requiredIf(! $request->boolean('all_colours'))],
            'gmt_colour_ids.*' => [$in('colours')],
            'fab_colour_ids' => ['required', 'array', 'min:1', 'max:60'],
            'fab_colour_ids.*' => [$in('colours')],
        ], [
            'style_id.required' => __('merchandise.yarn_spec.style_required'),
            'categories.required' => __('merchandise.yarn_spec.needed', ['field' => 'Prod Category']),
            'categories.min' => __('merchandise.yarn_spec.needed', ['field' => 'Prod Category']),
            'fabric_type_id.required' => __('merchandise.yarn_spec.needed', ['field' => 'Fabric']),
            'gmt_item.required' => __('merchandise.yarn_spec.needed', ['field' => 'Gmt Item']),
            'part_ids.required' => __('merchandise.yarn_spec.needed', ['field' => 'Gmt Parts']),
            'part_ids.min' => __('merchandise.yarn_spec.needed', ['field' => 'Gmt Parts']),
            'gmt_colour_ids.required' => __('merchandise.yarn_spec.needed', ['field' => 'Gmt Color']),
            'fab_colour_ids.required' => __('merchandise.yarn_spec.needed', ['field' => 'Fab Color']),
            'fab_colour_ids.min' => __('merchandise.yarn_spec.needed', ['field' => 'Fab Color']),
        ]);
        $all = (bool) ($data['all_colours'] ?? false);
        $created = DB::transaction(fn (): Collection => collect(array_values(array_intersect(YarnSpec::CATEGORIES, $data['categories'])))->map(fn (string $category): YarnSpec => YarnSpec::query()->create([
            'style_id' => $data['style_id'], 'prod_category' => $category, 'fabric_type_id' => $data['fabric_type_id'], 'gmt_item' => trim($data['gmt_item']),
            'part_ids' => array_values(array_unique($data['part_ids'])), 'all_colours' => $all, 'gmt_colour_ids' => $all ? [] : array_values(array_unique($data['gmt_colour_ids'] ?? [])),
            'fab_colour_ids' => array_values(array_unique($data['fab_colour_ids'])), 'status' => 'PENDING', 'created_by' => $this->caller()->auditUserId(),
        ])));

        return ApiResponse::created($this->rows($created));
    }

    public function confirm(string $spec): JsonResponse
    {
        $this->allow('merchandise.booking.manage');
        $row = YarnSpec::query()->whereKey($spec)->firstOrFail();
        if ($row->status !== 'CONFIRMED') {
            $row->update(['status' => 'CONFIRMED', 'confirmed_at' => CarbonImmutable::now(), 'confirmed_by' => $this->caller()->auditUserId()]);
        }

        return ApiResponse::ok($this->rows(collect([$row->refresh()]))[0]);
    }

    public function release(string $spec): JsonResponse
    {
        $this->allow('merchandise.booking.manage');
        $row = YarnSpec::query()->whereKey($spec)->firstOrFail();
        $row->update(['status' => 'PENDING', 'confirmed_at' => null, 'confirmed_by' => null]);

        return ApiResponse::ok($this->rows(collect([$row->refresh()]))[0]);
    }

    public function destroy(string $spec): JsonResponse
    {
        $this->allow('merchandise.booking.manage');
        $row = YarnSpec::query()->whereKey($spec)->firstOrFail();
        if ($row->status === 'CONFIRMED') {
            throw ValidationException::withMessages(['spec' => __('merchandise.yarn_spec.confirmed_locked')]);
        }
        $row->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /**
     * Spec Confirmation: the styles, each Confirmed once it has mappings and every one is confirmed, Pending
     * otherwise — with the buyer, the orders, their quantity and first shipment, and the day it last changed.
     */
    public function confirmation(Request $request): JsonResponse
    {
        $this->allow('merchandise.booking.view');
        $data = $request->validate([
            'state' => ['nullable', Rule::in(['PENDING', 'CONFIRMED'])], 'buyer_id' => ['nullable', 'string'], 'style_id' => ['nullable', 'string'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);
        $styles = Style::query()->when($data['buyer_id'] ?? null, fn ($q, $v) => $q->where('buyer_id', $v))->when($data['style_id'] ?? null, fn ($q, $v) => $q->whereKey($v))->get();
        $specs = YarnSpec::query()->whereIn('style_id', $styles->pluck('id'))->get(['style_id', 'status', 'confirmed_at', 'updated_at'])->groupBy('style_id');
        $orders = Order::query()->whereIn('style_id', $styles->pluck('id'))->where('status', '!=', 'CANCELLED')->orderBy('order_no')->get(['style_id', 'order_no', 'total_qty', 'ship_date'])->groupBy('style_id');
        $buyers = Buyer::query()->whereIn('id', $styles->pluck('buyer_id')->filter())->pluck('name', 'id');

        $rows = $styles->map(function (Style $s) use ($specs, $orders, $buyers): array {
            $mine = $specs[$s->id] ?? collect();
            $own = $orders[$s->id] ?? collect();
            $confirmed = $mine->isNotEmpty() && $mine->every(fn (YarnSpec $y): bool => $y->status === 'CONFIRMED');
            $changed = collect([$s->updated_at, $mine->max('updated_at')])->filter()->max();

            return [
                'style_id' => $s->id, 'status' => $confirmed ? 'CONFIRMED' : 'PENDING', 'buyer' => $s->buyer_id ? ($buyers[$s->buyer_id] ?? null) : null,
                'style' => $s->styleNo(), 'description' => $s->description, 'orders' => $own->pluck('order_no')->implode(', ') ?: null,
                'order_qty' => $own->isNotEmpty() ? (float) $own->sum('total_qty') : null, 'confirm_date' => $confirmed ? $mine->max('confirmed_at')?->toDateString() : null,
                'ship_date' => $own->pluck('ship_date')->filter()->min()?->toDateString(), 'modified_on' => $changed?->toDateString(), 'specs' => $mine->count(),
            ];
        })->filter(fn (array $r): bool => (! ($data['state'] ?? null) || $r['status'] === $data['state'])
            && (! ($data['from'] ?? null) || ($r['modified_on'] && $r['modified_on'] >= $data['from']))
            && (! ($data['to'] ?? null) || ($r['modified_on'] && $r['modified_on'] <= $data['to'])))
            ->sortByDesc('modified_on')->values()->all();

        return ApiResponse::ok($rows);
    }

    private function styleRule(): Exists
    {
        return Rule::exists('merch_styles', 'id')->where('company_id', $this->caller()->companyId);
    }

    /**
     * @param  Collection<int, YarnSpec>  $specs
     * @return list<array<string, mixed>>
     */
    private function rows(Collection $specs): array
    {
        $fabrics = FabricType::query()->whereIn('id', $specs->pluck('fabric_type_id'))->pluck('name', 'id');
        $parts = GarmentPart::query()->whereIn('id', $specs->pluck('part_ids')->flatten()->unique())->pluck('name', 'id');
        $colourIds = $specs->pluck('gmt_colour_ids')->flatten()->merge($specs->pluck('fab_colour_ids')->flatten())->unique();
        $colours = Colour::query()->whereIn('id', $colourIds)->get(['id', 'name', 'code'])->mapWithKeys(fn ($c): array => [$c->id => $c->code ? "{$c->name} ({$c->code})" : $c->name]);
        $users = User::query()->whereIn('id', $specs->pluck('confirmed_by')->filter())->pluck('name', 'id');
        $names = fn (array $ids, $map): array => array_values(array_filter(array_map(fn (string $id): ?string => $map[$id] ?? null, $ids)));

        return $specs->map(fn (YarnSpec $y): array => [
            'id' => $y->id, 'prod_category' => $y->prod_category, 'status' => $y->status, 'fabric_type_id' => $y->fabric_type_id, 'fabric' => $fabrics[$y->fabric_type_id] ?? null,
            'gmt_item' => $y->gmt_item, 'parts' => $names($y->part_ids ?? [], $parts), 'all_colours' => $y->all_colours,
            'gmt_colours' => $names($y->gmt_colour_ids ?? [], $colours), 'fab_colours' => $names($y->fab_colour_ids ?? [], $colours),
            'confirmed_at' => $y->confirmed_at?->toIso8601String(), 'confirmed_by' => $y->confirmed_by ? ($users[$y->confirmed_by] ?? null) : null,
        ])->values()->all();
    }
}

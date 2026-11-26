<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\OrderSize;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleColour;
use App\Modules\Merchandise\Models\StyleItem;
use App\Modules\Merchandise\Models\StyleItemStyle;
use App\Modules\Merchandise\Models\StylePacking;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Item/Combo/Model → Packing Instruction (AnnotechERP 06/05): a style's
 * packing lines, saved together. Each names an SKU, the pack (single piece,
 * or several pieces in a uniform or random ratio, so many to a pack), and
 * the order, garment item and colour (or all colours) it packs. Its quantity
 * is the packs that order needs: the pieces of that colour — from the order's
 * size breakdown, or the whole order without one — over the pieces per pack.
 */
class StylePackingApiController extends ApiController
{
    public function index(string $style): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $row = Style::query()->whereKey($style)->firstOrFail();
        $own = StyleItemStyle::query()->where('style_id', $row->id)->pluck('style_item_id');

        return ApiResponse::ok([
            'rows' => $this->rows($row),
            'options' => [
                'pack_types' => StylePacking::PACK_TYPES,
                'orders' => Order::query()->where('style_id', $row->id)->where('status', '!=', 'CANCELLED')->orderBy('order_no')->get(['id', 'order_no', 'job_no'])
                    ->map(fn (Order $o): array => ['value' => $o->id, 'label' => $o->order_no, 'job' => $o->job_no])->values()->all(),
                'items' => StyleItem::query()->whereIn('id', $own)->orderBy('full_name')->get(['id', 'full_name', 'short_name'])
                    ->map(fn (StyleItem $i): array => ['value' => $i->id, 'label' => $i->full_name ?: $i->short_name])->values()->all(),
                'colours' => $this->colours($row),
            ],
        ]);
    }

    public function save(Request $request, string $style): JsonResponse
    {
        $this->allow('merchandise.style.manage');
        $row = Style::query()->whereKey($style)->firstOrFail();
        $own = StyleItemStyle::query()->where('style_id', $row->id)->pluck('style_item_id')->all();
        $data = $request->validate([
            'rows' => ['present', 'array', 'max:300'],
            'rows.*.sku_ref' => ['required', 'string', 'max:64'],
            'rows.*.pack_type' => ['required', Rule::in(StylePacking::PACK_TYPES)],
            'rows.*.pcs_per_pack' => ['required', 'integer', 'min:1', 'max:1000'],
            'rows.*.order_id' => ['required', Rule::exists('merch_orders', 'id')->where('style_id', $row->id)],
            'rows.*.item_id' => ['required', Rule::in($own)],
            'rows.*.all_colours' => ['sometimes', 'boolean'],
            'rows.*.colour_id' => ['nullable', 'required_unless:rows.*.all_colours,true,1', Rule::exists('merch_style_colours', 'id')->where('style_id', $row->id)],
        ], [
            'rows.*.sku_ref.required' => __('merchandise.style_packing.sku_required'),
            'rows.*.order_id.required' => __('merchandise.style_packing.order_required'),
            'rows.*.item_id.required' => __('merchandise.style_packing.item_required'),
            'rows.*.colour_id.required_unless' => __('merchandise.style_packing.colour_required'),
        ]);

        DB::transaction(function () use ($row, $data): void {
            StylePacking::query()->where('style_id', $row->id)->delete();
            foreach (array_values($data['rows']) as $i => $r) {
                $all = (bool) ($r['all_colours'] ?? false);
                StylePacking::query()->create([
                    'style_id' => $row->id,
                    'sku_ref' => trim($r['sku_ref']),
                    'pack_type' => $r['pack_type'],
                    // A single-piece pack holds one piece.
                    'pcs_per_pack' => $r['pack_type'] === 'SINGLE' ? 1 : (int) $r['pcs_per_pack'],
                    'order_id' => $r['order_id'],
                    'item_id' => $r['item_id'],
                    'all_colours' => $all,
                    'colour_id' => $all ? null : $r['colour_id'],
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
        $lines = StylePacking::query()->where('style_id', $style->id)->orderBy('sort_order')->get();
        $orders = Order::query()->whereIn('id', $lines->pluck('order_id'))->get()->keyBy('id');
        $sizes = OrderSize::query()->whereIn('order_id', $lines->pluck('order_id'))->get(['order_id', 'colour', 'qty'])->groupBy('order_id');
        $items = StyleItem::query()->whereIn('id', $lines->pluck('item_id'))->get(['id', 'full_name', 'short_name'])->keyBy('id');
        $colours = collect($this->colours($style))->pluck('label', 'value');

        return $lines->map(function (StylePacking $p) use ($orders, $sizes, $items, $colours): array {
            $order = $orders[$p->order_id] ?? null;
            $colour = $p->colour_id ? ($colours[$p->colour_id] ?? null) : null;

            return [
                'id' => $p->id, 'sku_ref' => $p->sku_ref, 'pack_type' => $p->pack_type, 'pcs_per_pack' => $p->pcs_per_pack,
                'order_id' => $p->order_id, 'order' => $order?->order_no, 'job' => $order?->job_no,
                'item_id' => $p->item_id, 'item' => ($items[$p->item_id] ?? null)?->full_name ?: ($items[$p->item_id] ?? null)?->short_name,
                'all_colours' => $p->all_colours, 'colour_id' => $p->colour_id, 'colour' => $colour,
                'qty' => $order ? $this->packs($order, $sizes[$order->id] ?? collect(), $p->all_colours ? null : $colour, $p->pcs_per_pack) : 0,
            ];
        })->values()->all();
    }

    /** The packs an order needs for a colour (all colours when null): its pieces over the pieces per pack, rounded up. */
    private function packs(Order $order, $breakdown, ?string $colour, int $perPack): int
    {
        $units = $breakdown->isEmpty()
            ? $order->total_qty
            : (int) $breakdown->filter(fn ($s) => $colour === null || mb_strtoupper(trim($s->colour)) === mb_strtoupper(trim($colour)))->sum('qty');
        $pieces = $units * ($order->unit === 'PCS' ? 1 : max(1, (int) $order->pcs_per_unit));

        return (int) ceil($pieces / max(1, $perPack));
    }
}

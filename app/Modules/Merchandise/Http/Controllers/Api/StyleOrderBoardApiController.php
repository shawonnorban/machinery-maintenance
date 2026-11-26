<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Budget;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\LabdipShade;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleAttachment;
use App\Modules\Merchandise\Models\StyleColour;
use App\Modules\Merchandise\Models\StyleItem;
use App\Modules\Merchandise\Models\StyleItemStyle;
use App\Modules\Merchandise\Resources\OrderResource;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Style & Order → Style & Order Dashboard and Style Progress Board
 * (AnnotechERP 06/07). The dashboard counts styles and orders and lists the
 * newest of each; the board has one row per style with its buyer, season and
 * nature of work and how far its documents have got — its orders and their
 * quantity now, lab dips, samples, bookings and budgets as those modules
 * arrive.
 */
class StyleOrderBoardApiController extends ApiController
{
    public function dashboard(): JsonResponse
    {
        $this->allow('merchandise.order.view');
        $styles = Style::query()->latest('created_at')->limit(10)->get();
        $orders = Order::query()->latest('created_at')->latest('order_no')->limit(10)->get();
        $resource = app(OrderResource::class);

        return ApiResponse::ok([
            'counts' => [
                'styles' => Style::query()->count(),
                'approved' => Style::query()->whereIn('status', ['STYLE_APPROVED', 'RUNNING'])->count(),
                'orders' => Order::query()->where('status', '!=', 'CANCELLED')->count(),
                'in_production' => Order::query()->where('status', 'IN_PRODUCTION')->count(),
            ],
            'styles' => $this->styleRows($styles),
            'orders' => $orders->map(fn (Order $o): array => $resource->present($o))->values()->all(),
        ]);
    }

    public function progress(): JsonResponse
    {
        $this->allow('merchandise.order.view');
        $styles = Style::query()->latest('created_at')->get();
        $orders = Order::query()->where('status', '!=', 'CANCELLED')->whereNotNull('style_id')
            ->selectRaw('style_id, count(*) as n, coalesce(sum(total_qty), 0) as qty')->groupBy('style_id')->get()->keyBy('style_id');
        $labDips = LabdipShade::query()->whereNotNull('style_id')->selectRaw('style_id, count(*) as n')->groupBy('style_id')->pluck('n', 'style_id');
        $budgets = Budget::query()->whereNotNull('style_id')->selectRaw('style_id, count(*) as n')->groupBy('style_id')->pluck('n', 'style_id');

        return ApiResponse::ok(collect($this->styleRows($styles))->map(fn (array $s): array => $s + [
            'orders' => (int) ($orders[$s['id']]->n ?? 0),
            'order_qty' => (int) ($orders[$s['id']]->qty ?? 0),
            // Samples and bookings are counted once their modules keep them.
            'lab_dips' => (int) ($labDips[$s['id']] ?? 0), 'samples' => 0, 'bookings' => 0, 'budgets' => (int) ($budgets[$s['id']] ?? 0),
        ])->values()->all());
    }

    /**
     * Buyer-Wise Style & Order Follow-up: each order against its shipment —
     * its value and, as the production modules keep them, how much is cut,
     * input, sewn, ironed, poly-packed, finished and shipped.
     */
    public function followUp(): JsonResponse
    {
        $this->allow('merchandise.order.view');
        $orders = Order::query()->orderBy('ship_date')->orderBy('order_no')->get();
        $buyers = Buyer::query()->whereIn('id', $orders->pluck('buyer_id'))->get(['id', 'short_name', 'name'])->keyBy('id');
        $styles = Style::query()->whereIn('id', $orders->pluck('style_id')->filter())->pluck('description', 'id');

        return ApiResponse::ok($orders->map(fn (Order $o): array => [
            'id' => $o->id,
            'buyer' => $buyers[$o->buyer_id]->short_name ?? $buyers[$o->buyer_id]->name ?? null,
            'order_no' => $o->order_no,
            'order_style' => "{$o->order_no} ({$o->style_no})",
            'style_no' => $o->style_no,
            'style_desc' => ($o->style_id ? ($styles[$o->style_id] ?? null) : null) ?? $o->gmt_type ?? $o->item,
            'order_qty' => $o->total_qty,
            'order_value' => $o->unit_price === null ? null : round($o->total_qty * $o->unit_price, 2),
            'currency' => $o->currency,
            'ship_date' => $o->ship_date?->format('Y-m-d'),
            // Production keeps these; until it does, nothing is cut, sewn or shipped.
            'cutting' => 0, 'ready_input' => 0, 'inputed' => 0, 'sewing' => 0, 'sew_undone' => 0, 'iron' => 0, 'poly' => 0, 'gmt_fin' => 0, 'shipment' => 0,
            'verified' => false,
            'open' => ! in_array($o->status, ['SHIPPED', 'CLOSED', 'CANCELLED'], true),
            'status' => $o->status,
        ])->values()->all());
    }

    /**
     * Style Catalogue: a visual index of the running styles — each with its
     * picture (the style image, or its first item's key image) and key specs.
     */
    public function gallery(): JsonResponse
    {
        $this->allow('merchandise.style.view');
        $styles = Style::query()->where('status', 'RUNNING')->orderBy('style_ref')->get();
        $images = StyleAttachment::query()->whereIn('style_id', $styles->pluck('id'))->where('kind', 'STYLE_IMAGE')->pluck('file_id', 'style_id');
        $colours = StyleColour::query()->whereIn('style_id', $styles->pluck('id'))->selectRaw('style_id, count(*) as n')->groupBy('style_id')->pluck('n', 'style_id');
        $orders = Order::query()->whereIn('style_id', $styles->pluck('id'))->where('status', '!=', 'CANCELLED')->selectRaw('style_id, count(*) as n')->groupBy('style_id')->pluck('n', 'style_id');

        return ApiResponse::ok(collect($this->styleRows($styles))->map(fn (array $s): array => array_merge($s, [
            'description' => $styles->firstWhere('id', $s['id'])?->description,
            'image' => isset($images[$s['id']]) ? "/api/files/{$images[$s['id']]}/download" : $s['image'],
            'colours' => (int) ($colours[$s['id']] ?? 0),
            'orders' => (int) ($orders[$s['id']] ?? 0),
        ]))->values()->all());
    }

    /** @return list<array<string, mixed>> a style's line on either list: its number, buyer, garment type, season, nature of work and status */
    private function styleRows(Collection $styles): array
    {
        $buyers = Buyer::query()->whereIn('id', $styles->pluck('buyer_id'))->get(['id', 'short_name', 'name'])->keyBy('id');
        $links = StyleItemStyle::query()->whereIn('style_id', $styles->pluck('id'))->get(['style_id', 'style_item_id']);
        $items = StyleItem::query()->whereIn('id', $links->pluck('style_item_id'))->get(['id', 'full_name', 'short_name', 'key_image_id'])->keyBy('id');

        return $styles->map(function (Style $s) use ($buyers, $links, $items): array {
            $item = $items[$links->firstWhere('style_id', $s->id)?->style_item_id] ?? null;

            return [
                'id' => $s->id,
                'style_no' => $s->styleNo(),
                'buyer_id' => $s->buyer_id,
                'buyer' => $buyers[$s->buyer_id]->short_name ?? $buyers[$s->buyer_id]->name ?? null,
                'garment_type' => $item ? ($item->full_name ?: $item->short_name) : null,
                'image' => $item?->key_image_id ? "/api/files/{$item->key_image_id}/download" : null,
                'season' => $s->season,
                'nature_of_work' => $s->nature_of_work,
                'status' => $s->status,
                'created_at' => $s->created_at?->format('Y-m-d'),
            ];
        })->values()->all();
    }
}

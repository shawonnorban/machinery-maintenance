<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Support;

use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\Style;
use Illuminate\Support\Collection;

/**
 * The orders Accessories & Trims (AnnotechERP 06/08) works against, as its
 * screens show them: confirmed (or in production), soonest shipment first,
 * each with its buyer, style, type, quantity, confirmation and lead time.
 */
final class TrimOrders
{
    public const OPEN = ['CONFIRMED', 'IN_PRODUCTION'];

    /** @return Collection<int, Order> */
    public static function open(): Collection
    {
        return Order::query()->whereIn('status', self::OPEN)->orderBy('ship_date')->orderBy('order_no')->get();
    }

    /**
     * Each order as a row: "LPP-2026-000003 / 531" for the lists, the rest for the grids.
     *
     * @param  Collection<int, Order>  $orders
     * @return array<string, array<string, mixed>> keyed by order id
     */
    public static function rows(Collection $orders): array
    {
        $styles = Style::query()->whereIn('id', $orders->pluck('style_id')->filter())->get()->keyBy('id');
        $buyers = Buyer::query()->whereIn('id', $orders->pluck('buyer_id')->merge($styles->pluck('buyer_id'))->filter())->get(['id', 'name', 'short_name'])->keyBy('id');

        return $orders->mapWithKeys(function (Order $o) use ($styles, $buyers): array {
            $s = $o->style_id ? ($styles[$o->style_id] ?? null) : null;
            $b = $buyers[$o->buyer_id ?? $s?->buyer_id] ?? null;
            $style = $s?->styleNo() ?? $o->style_no;

            return [$o->id => [
                'order_id' => $o->id, 'order_no' => $o->order_no, 'title' => "{$o->order_no} / ".($o->work_style_no ?: $style),
                'label' => "{$o->order_no} · {$style} (".($o->work_style_no ?: $style).')', 'buyer_id' => $b?->id, 'buyer' => $b ? ($b->short_name ?: $b->name) : null,
                'style_desc' => $s?->description, 'style_no' => $style, 'work_style' => $o->work_style_no ?: $style, 'job_no' => $o->job_no ?: ($o->work_style_no ?: $style),
                'order_type' => $o->order_type, 'qty' => (int) $o->total_qty, 'conf_date' => $o->conf_date?->toDateString(), 'ship_date' => $o->ship_date?->toDateString(),
                // Lead time: days from confirmation to shipment.
                'lead_time' => $o->conf_date && $o->ship_date ? (int) $o->conf_date->diffInDays($o->ship_date, false) : null,
            ]];
        })->all();
    }
}

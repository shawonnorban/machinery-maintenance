<?php

declare(strict_types=1);

namespace App\Modules\Cutting\Support;

use App\Modules\Cutting\Models\CutBundle;
use App\Modules\DyeHouse\Support\DyeBatches;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A bundle card's rules, the one way every card is made — one at a time on
 * Bundle Entry, or a cutting's whole output at once: its facts from the
 * order, its quantity from its serial run, a serial clash refused, and
 * whether it is cut past the order (Additional / Partial Additional).
 */
final class CutCards
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepare(array $data, ?CutBundle $bundle): array
    {
        $order = DB::table('merch_orders')->where('id', $data['order_id'])->first(['id', 'po_no', 'style_no', 'work_style_no', 'buyer_id', 'total_qty']);
        $colours = DB::table('merch_order_sizes')->where('order_id', $order->id)->get(['colour', 'size', 'qty']);
        $data['colour'] = trim($data['colour']);
        $data['size'] = mb_strtoupper(trim($data['size']));
        if ($colours->isNotEmpty()) {
            $mine = $colours->filter(fn ($r) => mb_strtolower($r->colour) === mb_strtolower($data['colour']));
            if ($mine->isEmpty()) {
                throw ValidationException::withMessages(['colour' => __('cutting.v.colour_not_on_order', ['colour' => $data['colour'], 'po' => $order->po_no])]);
            }
            $data['colour'] = $mine->first()->colour;
            $orderQty = (int) $mine->sum('qty');
        } else {
            $orderQty = (int) $order->total_qty;
        }
        foreach (['parts_name', 'batch_no', 'garment', 'fabric_type', 'gsm', 'dia_width'] as $k) {
            if (isset($data[$k])) {
                $data[$k] = trim((string) $data[$k]) ?: null;
            }
        }
        $data['batch_no'] = mb_strtoupper((string) $data['batch_no']);
        $data['po_no'] = $order->po_no;
        $data['style'] = $order->work_style_no ?: $order->style_no;
        $data['buyer_id'] = $order->buyer_id;
        $data['cut_date'] = $data['cut_date'] ?? ($bundle?->cut_date?->toDateString() ?? CarbonImmutable::now('Asia/Dhaka')->toDateString());
        $data['qty'] = $data['serial_to'] - $data['serial_from'] + 1;

        $clash = CutBundle::query()->where('order_id', $order->id)->where('status', '!=', 'CANCELLED')->where('colour', $data['colour'])->where('size', $data['size'])->where('parts_name', $data['parts_name'])
            ->where('serial_from', '<=', $data['serial_to'])->where('serial_to', '>=', $data['serial_from'])->when($bundle, fn ($q) => $q->whereKeyNot($bundle->id))->first();
        if ($clash) {
            throw ValidationException::withMessages(['serial_from' => __('cutting.v.serial_overlap', ['from' => $clash->serial_from, 'to' => $clash->serial_to, 'size' => $data['size'], 'part' => $data['parts_name'], 'no' => $clash->bundle_no])]);
        }

        // What this part of this colour had been cut before this card decides whether it is additional.
        $before = (int) CutBundle::query()->where('order_id', $order->id)->where('status', '!=', 'CANCELLED')->where('colour', $data['colour'])->where('parts_name', $data['parts_name'])
            ->when($bundle, fn ($q) => $q->whereKeyNot($bundle->id)->where('bundle_no', '<', $bundle->bundle_no))->sum('qty');
        $over = $before + $data['qty'] - $orderQty;
        $data['additional_qty'] = max(0, min($data['qty'], $over));
        $data['cutting_status'] = $over <= 0 ? 'REGULAR' : ($before >= $orderQty ? 'ADDITIONAL' : 'PARTIAL_ADDITIONAL');

        return $data;
    }

    /**
     * Every card of a cutting's sizes at once: per size the pieces, split into
     * cards of the bundle size (the last one takes what is left), each size's
     * serials carrying on from its last card of that part.
     *
     * @param  array<string, mixed>  $base  order_id, colour, cutting_no, parts_name, batch_no, garment…, input_id
     * @param  list<array{size: string, qty: int}>  $sizes
     * @return Collection<int, CutBundle>
     */
    public static function cut(array $base, array $sizes, int $perBundle, ?string $userId): Collection
    {
        return DB::transaction(function () use ($base, $sizes, $perBundle, $userId) {
            $made = collect();
            foreach ($sizes as $row) {
                $size = mb_strtoupper(trim($row['size']));
                $from = 1 + (int) CutBundle::query()->where('order_id', $base['order_id'])->where('status', '!=', 'CANCELLED')->whereRaw('lower(colour) = ?', [mb_strtolower(trim($base['colour']))])
                    ->where('size', $size)->where('parts_name', trim($base['parts_name']))->max('serial_to');
                for ($left = (int) $row['qty']; $left > 0; $left -= $perBundle) {
                    $to = $from + min($left, $perBundle) - 1;
                    $data = self::prepare(['size' => $size, 'serial_from' => $from, 'serial_to' => $to] + $base, null);
                    $made->push(CutBundle::query()->create($data + ['bundle_no' => DyeBatches::nextNumber('BND-', 'cut_bundles', 'bundle_no', $data['cut_date'], 'Ymd-'), 'status' => 'CREATED', 'created_by' => $userId]));
                    $from = $to + 1;
                }
            }

            return $made;
        });
    }
}

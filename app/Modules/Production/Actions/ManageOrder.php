<?php

declare(strict_types=1);

namespace App\Modules\Production\Actions;

use App\Modules\ItemMaster\Services\ItemLineResolver;
use App\Modules\Production\Models\Order;
use App\Modules\Production\Models\Style;
use App\Modules\Production\Models\StyleBomLine;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The minimal order/style/BOM master (Master Data spec §6).
 *
 * One action for the whole little tree, because it is one editing session in
 * practice: a merchandiser types the buyer's order, its styles and what each
 * style consumes in a single sitting. Splitting it across three actions would
 * add ceremony without adding a boundary anybody crosses.
 *
 * The order number is the buyer's, not generated: a factory refers to
 * "H&M 4471-23" and nothing is gained by inventing a second identifier for
 * the same thing.
 */
class ManageOrder
{
    public function __construct(private readonly ItemLineResolver $lines) {}

    /**
     * @param  array<string, mixed>  $data  order_no, buyer, order_qty, order_date?, ship_date?, notes?
     */
    public function create(Factory $factory, array $data, ?string $userId = null): Order
    {
        $this->assertNumberFree((string) ($data['order_no'] ?? ''), null);

        return Order::create([
            'factory_id' => $factory->id,
            'order_no' => trim((string) $data['order_no']),
            'buyer' => trim((string) $data['buyer']),
            'order_qty' => ItemLineResolver::money($data['order_qty'] ?? 0),
            'order_date' => $data['order_date'] ?? CarbonImmutable::today(),
            'ship_date' => $data['ship_date'] ?? null,
            'status' => 'CONFIRMED',
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
        ])->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Order $order, array $data): Order
    {
        $this->assertOpen($order);

        if (array_key_exists('order_no', $data)) {
            $this->assertNumberFree((string) $data['order_no'], $order->id);
        }

        $order->forceFill([
            'order_no' => array_key_exists('order_no', $data) ? trim((string) $data['order_no']) : $order->order_no,
            'buyer' => array_key_exists('buyer', $data) ? trim((string) $data['buyer']) : $order->buyer,
            'order_qty' => array_key_exists('order_qty', $data) ? ItemLineResolver::money($data['order_qty']) : $order->order_qty,
            'order_date' => $data['order_date'] ?? $order->order_date,
            'ship_date' => array_key_exists('ship_date', $data) ? $data['ship_date'] : $order->ship_date,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $order->notes,
        ])->save();

        return $order->fresh();
    }

    public function setStatus(Order $order, string $status): Order
    {
        if (! $order->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => __('procurement.invalid_transition', ['from' => $order->status, 'to' => $status]),
            ])->status(409);
        }

        $order->forceFill(['status' => $status])->save();

        return $order->fresh();
    }

    /**
     * @param  array<string, mixed>  $data  style_no, description?, color?, quantity
     */
    public function addStyle(Order $order, array $data): Style
    {
        $this->assertOpen($order);

        $styleNo = trim((string) ($data['style_no'] ?? ''));

        if ($styleNo === '') {
            throw ValidationException::withMessages(['style_no' => __('validation.required', ['attribute' => 'style_no'])]);
        }

        $taken = Style::where('production_order_id', $order->id)
            ->where('style_no', $styleNo)
            ->when(isset($data['id']), fn ($q) => $q->whereKeyNot($data['id']))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'style_no' => __('production.style_no_taken', ['style' => $styleNo]),
            ]);
        }

        return Style::create([
            'production_order_id' => $order->id,
            'style_no' => $styleNo,
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? null,
            'quantity' => ItemLineResolver::money($data['quantity'] ?? 0),
        ])->fresh();
    }

    /**
     * Replaces a style's bill of materials wholesale.
     *
     * A BOM edited line by line would leave the requirement calculation
     * reading a half-changed bill mid-edit; replacing it in one transaction
     * means every read sees one version or the other.
     *
     * @param  list<array<string, mixed>>  $lines  item_id, consumption_per_unit, uom_id?, wastage_percent?, notes?
     * @return Collection<int, StyleBomLine>
     */
    public function saveBom(Style $style, array $lines): Collection
    {
        $order = Order::findOrFail($style->production_order_id);
        $this->assertOpen($order);

        $resolved = $this->lines->resolve($lines, 'consumption_per_unit');

        $seen = [];

        foreach ($resolved as $index => $line) {
            $itemId = $line['item']->id;

            if (isset($seen[$itemId])) {
                throw ValidationException::withMessages([
                    "lines.$index.item_id" => __('production.item_twice_in_bom', ['item' => $line['item']->item_code]),
                ]);
            }

            $seen[$itemId] = true;
        }

        return DB::transaction(function () use ($style, $resolved): Collection {
            StyleBomLine::where('style_id', $style->id)->delete();

            foreach ($resolved as $line) {
                StyleBomLine::create([
                    'style_id' => $style->id,
                    'item_id' => $line['item']->id,
                    'item_code' => $line['item']->item_code,
                    'item_name' => $line['item']->name,
                    'uom_id' => $line['uom_id'],
                    'consumption_per_unit' => $line['quantity'],
                    'wastage_percent' => number_format((float) ($line['input']['wastage_percent'] ?? 0), 2, '.', ''),
                    'notes' => $line['input']['notes'] ?? null,
                ]);
            }

            return $style->bomLines()->get();
        });
    }

    private function assertOpen(Order $order): void
    {
        if (! $order->isOpen()) {
            throw ValidationException::withMessages([
                'status' => __('production.order_not_open', ['status' => $order->status]),
            ])->status(409);
        }
    }

    /** Unique within the company — `BelongsToTenant` scopes the lookup. */
    private function assertNumberFree(string $orderNo, ?string $ignoreId): void
    {
        $orderNo = trim($orderNo);

        if ($orderNo === '') {
            throw ValidationException::withMessages(['order_no' => __('validation.required', ['attribute' => 'order_no'])]);
        }

        $taken = Order::where('order_no', $orderNo)
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'order_no' => __('production.order_no_taken', ['order' => $orderNo]),
            ]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Services;

use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\Item;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Turns the `lines[]` a document form posts into checked item lines.
 *
 * Every document that picks from the item library — purchase requisition,
 * purchase order, goods receipt, floor requisition — asks the same three
 * questions of a line: is the item one this company can see and still
 * active, is the unit real, is the quantity positive. One place answers
 * them, and one place writes the code/name snapshot every line carries
 * (Master Data spec §2.10).
 */
class ItemLineResolver
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  list<array<string, mixed>>  $lines  each with item_id, quantity (under $quantityKey), optional uom_id
     * @return Collection<int, array{item: Item, uom_id: string, quantity: string, input: array<string, mixed>}>
     */
    public function resolve(array $lines, string $quantityKey = 'quantity', bool $requireActive = true): Collection
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('procurement.needs_lines')]);
        }

        $items = Item::whereIn('id', collect($lines)->pluck('item_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        $uomIds = UnitOfMeasure::availableTo($this->context->companyId())
            ->where('active', true)
            ->pluck('id')
            ->flip();

        return collect($lines)->values()->map(function (array $line, int $index) use ($items, $uomIds, $quantityKey, $requireActive): array {
            $field = "lines.$index";
            $item = $items->get((string) ($line['item_id'] ?? ''));

            if ($item === null) {
                throw ValidationException::withMessages(["$field.item_id" => __('procurement.unknown_item')]);
            }

            if ($requireActive && ! $item->active) {
                // The in-flight rule: a retired item can stay on a document
                // that was already signed, but cannot be put on a new one.
                throw ValidationException::withMessages([
                    "$field.item_id" => __('procurement.item_inactive', ['item' => $item->item_code.' — '.$item->name]),
                ]);
            }

            $uomId = (string) ($line['uom_id'] ?? '') ?: (string) $item->uom_id;

            if (! $uomIds->has($uomId)) {
                throw ValidationException::withMessages(["$field.uom_id" => __('itemmaster.unknown_uom')]);
            }

            $quantity = number_format((float) ($line[$quantityKey] ?? 0), 4, '.', '');

            if (bccomp($quantity, '0', 4) !== 1) {
                throw ValidationException::withMessages(["$field.$quantityKey" => __('inventory.quantity_must_be_positive')]);
            }

            return ['item' => $item, 'uom_id' => $uomId, 'quantity' => $quantity, 'input' => $line];
        });
    }

    /**
     * The §2.10 re-check at submit time: an item retired while the document
     * sat in draft blocks it, naming the line.
     *
     * @param  iterable<int, object{item_id: string, item_code: string}>  $lines
     */
    public function assertAllActive(iterable $lines): void
    {
        $ids = collect($lines)->pluck('item_id')->unique()->all();

        $inactive = Item::whereIn('id', $ids)->where('active', false)->pluck('item_code');

        if ($inactive->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            'lines' => __('procurement.items_inactive_on_submit', ['items' => $inactive->implode(', ')]),
        ])->status(409);
    }

    public static function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 4, '.', '');
    }
}

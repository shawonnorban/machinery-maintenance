<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\SparePartCategory;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * Writes a catalogue entry for a part (SRS 22).
 *
 * The catalogue only: what a part is, what it costs to buy and when to reorder
 * it. Quantity is deliberately absent — stock enters through the ledger as a
 * receipt with a cost and a bin, so every unit on hand has a movement behind
 * it. A form that could set quantity_on_hand directly would produce a balance
 * with no transaction under it, and the first person to replay the ledger
 * against a valuation would find the two do not meet.
 *
 * The screen and the spreadsheet import both come through here, so a rule
 * added for one applies to the other (ADR-066).
 */
class SaveSparePart
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SparePart
    {
        $this->assertCategoryVisible($data['category_id'] ?? null);

        return SparePart::create($this->values($data) + [
            // Defaults, applied only when creating. On an edit the stored
            // values stand.
            'minimum_stock' => '0',
            'reorder_level' => '0',
            'currency' => 'BDT',
            'is_critical_spare' => false,
            'hazardous' => false,
            'active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SparePart $part, array $data): SparePart
    {
        $this->assertCategoryVisible($data['category_id'] ?? null);

        $part->update($this->values($data));

        return $part->fresh();
    }

    /**
     * Retiring a part from the catalogue, which is never a delete.
     *
     * The ledger points at this row, and a part nobody stocks any more is
     * still the part that was fitted to a machine two years ago. Inactive
     * takes it out of every picker and leaves the history readable.
     */
    public function setActive(SparePart $part, bool $active): SparePart
    {
        $part->forceFill(['active' => $active])->save();

        return $part->fresh();
    }

    /**
     * Only the fields the caller actually supplied.
     *
     * The screen and the import send different sets — the form has no purchase
     * price on it, the spreadsheet does — so writing a fixed list would have
     * one caller silently blank what the other filled in.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function values(array $data): array
    {
        $writable = [
            'part_number', 'name', 'brand', 'manufacturer', 'unit',
            'minimum_stock', 'reorder_level', 'lead_time_days', 'shelf_life_days',
            'unit_cost', 'currency', 'notes',
        ];

        $values = [];

        foreach ($writable as $field) {
            if (array_key_exists($field, $data)) {
                $values[$field] = $data[$field];
            }
        }

        if (array_key_exists('category_id', $data)) {
            $values['category_id'] = filled($data['category_id']) ? $data['category_id'] : null;
        }

        foreach (['is_critical_spare', 'hazardous'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $values[$flag] = (bool) $data[$flag];
            }
        }

        return $values;
    }

    /**
     * A category id from outside what this company can see would file the part
     * under another tenant's row.
     */
    private function assertCategoryVisible(?string $categoryId): void
    {
        if (! filled($categoryId)) {
            return;
        }

        $exists = SparePartCategory::availableTo($this->context->companyId())
            ->whereKey($categoryId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'category_id' => __('inventory.unknown_category'),
            ]);
        }
    }
}

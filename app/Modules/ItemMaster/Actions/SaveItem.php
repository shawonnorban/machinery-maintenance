<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Actions;

use App\Modules\Asset\Models\Manufacturer;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemCategory;
use App\Modules\ItemMaster\Models\ItemChemicalAttributes;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\ItemMaster\Models\ItemFabricAttributes;
use App\Modules\ItemMaster\Models\ItemYarnAttributes;
use App\Modules\Procurement\Models\HsCode;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writes one entry in the item library (Master Data spec §2).
 *
 * The screen, the legacy migration and any future spreadsheet import all
 * come through here, so a rule added for one applies to the others
 * (ADR-066): a fabric cannot be saved without GSM, width and composition; a
 * yarn without count and fibre; a sub-category must sit under the chosen
 * category; every reference must be one this company can see.
 */
class SaveItem
{
    /** The extension tables, keyed by the request block that fills each. */
    private const ATTRIBUTE_MODELS = [
        'fabric' => ItemFabricAttributes::class,
        'yarn' => ItemYarnAttributes::class,
        'chemical' => ItemChemicalAttributes::class,
    ];

    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Item
    {
        $this->assertReferencesVisible($data);

        $data = $this->withDerivedItemType($data);
        $this->assertSpecComplete($data['item_type'] ?? 'GENERAL', $data);

        return DB::transaction(function () use ($data): Item {
            $item = Item::create($this->values($data) + [
                // Defaults, applied only when creating. On an edit the stored
                // values stand.
                'item_type' => 'GENERAL',
                'tracking_mode' => 'QUANTITY',
                'minimum_stock' => '0',
                'reorder_level' => '0',
                'is_returnable' => false,
                'hazardous' => false,
                'active' => true,
            ]);

            $this->saveAttributes($item, $data);

            return $item->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Item $item, array $data): Item
    {
        $this->assertReferencesVisible($data);

        $itemType = $data['item_type'] ?? $item->item_type;
        $this->assertSpecComplete($itemType, $data, $item);

        return DB::transaction(function () use ($item, $data): Item {
            $item->update($this->values($data));
            $this->saveAttributes($item, $data);

            return $item->fresh();
        });
    }

    /**
     * Retiring an item from the library, which is never a delete once
     * anything points at it: a purchase order from last year still names it.
     */
    public function setActive(Item $item, bool $active): Item
    {
        $item->forceFill(['active' => $active])->save();

        return $item->fresh();
    }

    /**
     * Only the fields the caller actually supplied, so an import that fills a
     * subset does not blank what the screen filled in.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function values(array $data): array
    {
        $writable = [
            'item_code', 'legacy_item_id', 'name', 'description', 'item_type', 'tracking_mode',
            'model', 'brand', 'origin', 'minimum_stock', 'reorder_level',
        ];

        $values = [];

        foreach ($writable as $field) {
            if (array_key_exists($field, $data)) {
                $values[$field] = $data[$field];
            }
        }

        if (array_key_exists('uom_id', $data)) {
            $values['uom_id'] = $data['uom_id'];
        }

        $references = [
            'item_department_id', 'category_id', 'sub_category_id', 'detail_category_id', 'purchase_uom_id',
            'manufacturer_id', 'default_store_id', 'hs_code_id', 'default_vendor_id',
        ];

        foreach ($references as $field) {
            if (array_key_exists($field, $data)) {
                $values[$field] = filled($data[$field]) ? $data[$field] : null;
            }
        }

        foreach (['is_returnable', 'hazardous'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $values[$flag] = (bool) $data[$flag];
            }
        }

        return $values;
    }

    /**
     * A new item filed under a department takes that department's default
     * kind — a yarn store's items are yarn — unless the caller said
     * otherwise. Derived on create only.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withDerivedItemType(array $data): array
    {
        if (filled($data['item_type'] ?? null) || ! filled($data['item_department_id'] ?? null)) {
            return $data;
        }

        $default = ItemDepartment::query()->whereKey($data['item_department_id'])->value('default_item_type');

        if ($default !== null) {
            $data['item_type'] = $default;
        }

        return $data;
    }

    /**
     * A fabric without GSM, width and composition cannot be inspected against
     * anything; a yarn without count and fibre cannot be issued to the right
     * knitting programme. Blocked at save rather than flagged later.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertSpecComplete(string $itemType, array $data, ?Item $existing = null): void
    {
        [$block, $required] = match ($itemType) {
            'FABRIC' => ['fabric', ItemFabricAttributes::REQUIRED],
            'YARN' => ['yarn', ItemYarnAttributes::REQUIRED],
            default => [null, []],
        };

        if ($block === null) {
            return;
        }

        // An edit that does not touch the spec block keeps the stored one.
        if (! array_key_exists($block, $data) && $existing !== null && $existing->{$block.'Attributes'}()->exists()) {
            return;
        }

        $spec = is_array($data[$block] ?? null) ? $data[$block] : [];
        $errors = [];

        foreach ($required as $field) {
            if (! filled($spec[$field] ?? null)) {
                $errors["{$block}.{$field}"] = __('itemmaster.spec_required', ['field' => __('itemmaster.spec_'.$field)]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * One extension row per item, replaced in place. A block the caller did
     * not send is left as it was, the same rule `values()` applies to the
     * scalar columns.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveAttributes(Item $item, array $data): void
    {
        foreach (self::ATTRIBUTE_MODELS as $block => $model) {
            if (! array_key_exists($block, $data) || ! is_array($data[$block])) {
                continue;
            }

            /** @var Model $instance */
            $instance = new $model;

            // Only the spec's own columns: an unknown key would be a
            // mass-assignment error outside production, not a silent drop.
            $columns = array_diff($instance->getFillable(), ['company_id', 'item_id']);
            $values = array_intersect_key($data[$block], array_flip($columns));

            $model::query()->updateOrCreate(
                ['item_id' => $item->id],
                $values + ['company_id' => $item->company_id],
            );
        }
    }

    /**
     * Every reference must point at a row this company can see: an id from
     * outside that would file the item under another tenant's category,
     * store or supplier.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertReferencesVisible(array $data): void
    {
        $companyId = $this->context->companyId();

        foreach (['category_id', 'sub_category_id', 'detail_category_id'] as $field) {
            $this->assertVisible($field, $data, fn ($id) => ItemCategory::query()->whereKey($id)->exists(), 'itemmaster.unknown_category');
        }

        $this->assertVisible('item_department_id', $data, fn ($id) => ItemDepartment::query()->whereKey($id)->exists(), 'itemmaster.unknown_department');
        $this->assertVisible('uom_id', $data, fn ($id) => UnitOfMeasure::availableTo($companyId)->where('active', true)->whereKey($id)->exists(), 'itemmaster.unknown_uom');
        $this->assertVisible('purchase_uom_id', $data, fn ($id) => UnitOfMeasure::availableTo($companyId)->whereKey($id)->exists(), 'itemmaster.unknown_uom');
        $this->assertVisible('manufacturer_id', $data, fn ($id) => Manufacturer::availableTo($companyId)->whereKey($id)->exists(), 'itemmaster.unknown_manufacturer');
        $this->assertVisible('default_store_id', $data, fn ($id) => Store::query()->whereKey($id)->exists(), 'itemmaster.unknown_store');
        $this->assertVisible('hs_code_id', $data, fn ($id) => HsCode::availableTo($companyId)->whereKey($id)->exists(), 'itemmaster.unknown_hs_code');
        $this->assertVisible('default_vendor_id', $data, fn ($id) => Vendor::query()->whereKey($id)->exists(), 'itemmaster.unknown_vendor');

        $this->assertCategoryChain($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  \Closure(string): bool  $exists
     */
    private function assertVisible(string $field, array $data, \Closure $exists, string $message): void
    {
        $id = $data[$field] ?? null;

        if (! filled($id)) {
            return;
        }

        if (! $exists((string) $id)) {
            throw ValidationException::withMessages([$field => __($message)]);
        }
    }

    /**
     * The three category pickers cascade: a sub-category must sit under the
     * chosen category and a detail category under the chosen sub-category.
     * Only checked when the caller sent the pair.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertCategoryChain(array $data): void
    {
        foreach ([['sub_category_id', 'category_id'], ['detail_category_id', 'sub_category_id']] as [$child, $parent]) {
            if (! filled($data[$child] ?? null) || ! filled($data[$parent] ?? null)) {
                continue;
            }

            $actualParent = ItemCategory::query()->whereKey($data[$child])->value('parent_id');

            if ($actualParent !== $data[$parent]) {
                throw ValidationException::withMessages([
                    $child => __('itemmaster.category_not_under_parent'),
                ]);
            }
        }
    }
}

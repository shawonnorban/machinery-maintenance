<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Models;

use App\Modules\Asset\Models\Manufacturer;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\Procurement\Models\HsCode;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One entry in the item library (Master Data spec §2).
 *
 * What an item is — not how many there are. Quantities live in the item
 * stock ledger (`balances()`), written only by ItemLedger; this row is the
 * thing a requisition or purchase order line points at, and the thing an
 * admin maintains so nobody else has to type it.
 */
class Item extends BaseModel
{
    use BelongsToTenant;

    public const ITEM_TYPES = ['FABRIC', 'YARN', 'TRIMS', 'CHEMICAL', 'GENERAL', 'SPARE'];

    /**
     * How stock of this item is told apart on the shelf. QUANTITY is a pooled
     * balance; LOT groups it by batch; ROLL gives every unit its own identity
     * (fabric); SERIAL is one-of-one.
     */
    public const TRACKING_MODES = ['QUANTITY', 'LOT', 'ROLL', 'SERIAL'];

    protected $table = 'items';

    protected $fillable = [
        'company_id', 'item_department_id', 'category_id', 'sub_category_id', 'detail_category_id',
        'item_code', 'legacy_item_id', 'name', 'description', 'item_type', 'tracking_mode',
        'uom_id', 'purchase_uom_id', 'model', 'brand', 'manufacturer_id', 'origin',
        'default_store_id', 'hs_code_id', 'default_vendor_id',
        'minimum_stock', 'reorder_level', 'is_returnable', 'hazardous', 'active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'minimum_stock' => MoneyCast::class,
            'reorder_level' => MoneyCast::class,
            'is_returnable' => 'boolean',
            'hazardous' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function itemDepartment(): BelongsTo
    {
        return $this->belongsTo(ItemDepartment::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'sub_category_id');
    }

    public function detailCategory(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'detail_category_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function purchaseUom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'purchase_uom_id');
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function defaultStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'default_store_id');
    }

    public function hsCode(): BelongsTo
    {
        return $this->belongsTo(HsCode::class);
    }

    public function defaultVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'default_vendor_id');
    }

    public function fabricAttributes(): HasOne
    {
        return $this->hasOne(ItemFabricAttributes::class);
    }

    public function yarnAttributes(): HasOne
    {
        return $this->hasOne(ItemYarnAttributes::class);
    }

    public function chemicalAttributes(): HasOne
    {
        return $this->hasOne(ItemChemicalAttributes::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(ItemStockBalance::class);
    }

    public function rolls(): HasMany
    {
        return $this->hasMany(FabricRoll::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(ItemReservation::class);
    }

    /**
     * The roll-tracked equivalent of a stock summary: counts by status and
     * the physical totals a fabric store actually cares about, since a
     * ROLL-tracked item never writes to `item_stock_balances` and so has
     * nothing for the quantity `stock` block to show.
     *
     * @return array{by_status: array<string, int>, net_length: string, net_weight: string, roll_count: int}
     */
    public function fabricStockSummary(): array
    {
        $rolls = $this->rolls()->get(['status', 'net_length', 'net_weight']);
        $onHandStatuses = ['AVAILABLE', 'RESERVED', 'ISSUED'];

        $onHand = $rolls->whereIn('status', $onHandStatuses);

        return [
            'roll_count' => $rolls->count(),
            'by_status' => $rolls->countBy('status')->all(),
            'net_length' => number_format((float) $onHand->sum('net_length'), 2, '.', ''),
            'net_weight' => number_format((float) $onHand->sum('net_weight'), 2, '.', ''),
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ItemStockTransaction::class);
    }

    /** Stock of a ROLL-tracked item is per roll (Phase 2); this ledger cannot hold it. */
    public function usesRollLedger(): bool
    {
        return $this->tracking_mode === 'ROLL';
    }

    public function isLotTracked(): bool
    {
        return $this->tracking_mode === 'LOT';
    }

    public function totalOnHand(): string
    {
        return number_format((float) $this->balances()->sum('quantity_on_hand'), 4, '.', '');
    }

    /** On hand minus what is already promised to somebody's requisition. */
    public function totalAvailable(): string
    {
        $onHand = (float) $this->balances()->sum('quantity_on_hand');
        $reserved = (float) $this->balances()->sum('quantity_reserved');

        return number_format($onHand - $reserved, 4, '.', '');
    }

    /**
     * At or below the reorder level is the useful signal, not below zero. By
     * the time stock is out, the lead time has already been lost. A zero
     * level means "nobody set one" and never alerts.
     */
    public function isBelowReorderLevel(): bool
    {
        $reorder = (string) ($this->reorder_level ?? '0');

        return bccomp($reorder, '0', 4) > 0 && bccomp($this->totalOnHand(), $reorder, 4) <= 0;
    }
}

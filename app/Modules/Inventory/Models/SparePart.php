<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Shared\Casts\MoneyCast;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stocked part (SRS 19, ERD Section 13).
 *
 * Carries no quantity. `minimum_stock` and `reorder_level` are policy
 * thresholds; how many there actually are lives in inventory_balances and is
 * derived from the ledger. Storing a quantity here as well would give two
 * answers to one question, and they would eventually disagree.
 */
class SparePart extends BaseModel
{
    use BelongsToTenant;

    public const UNITS = ['PCS', 'SET', 'MTR', 'LTR', 'KG', 'BOX', 'ROLL', 'PAIR'];

    protected $table = 'spare_parts';

    protected $fillable = [
        'company_id', 'category_id', 'part_number', 'name', 'brand', 'manufacturer',
        'unit', 'minimum_stock', 'reorder_level', 'unit_cost', 'currency',
        'lead_time_days', 'default_vendor_id', 'is_critical_spare',
        'shelf_life_days', 'hazardous', 'notes', 'active',
    ];

    protected function casts(): array
    {
        return [
            'minimum_stock' => MoneyCast::class,
            'reorder_level' => MoneyCast::class,
            'unit_cost' => MoneyCast::class,
            'is_critical_spare' => 'boolean',
            'hazardous' => 'boolean',
            'active' => 'boolean',
            'lead_time_days' => 'integer',
            'shelf_life_days' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SparePartCategory::class, 'category_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class)
            ->orderByDesc('transaction_at')
            ->orderByDesc('id');
    }

    public function compatibilities(): HasMany
    {
        return $this->hasMany(SparePartCompatibility::class);
    }

    /** Total on hand across every bin, as a decimal string. */
    public function totalOnHand(): string
    {
        return number_format(
            (float) $this->balances()->sum('quantity_on_hand'),
            4, '.', '',
        );
    }

    /** On hand minus what is already promised to somebody's work order. */
    public function totalAvailable(): string
    {
        $onHand = (float) $this->balances()->sum('quantity_on_hand');
        $reserved = (float) $this->balances()->sum('quantity_reserved');

        return number_format($onHand - $reserved, 4, '.', '');
    }

    /**
     * Below the reorder level is the useful signal, not below zero. By the time
     * stock is out, the lead time has already been lost.
     */
    public function isBelowReorderLevel(): bool
    {
        return bccomp($this->totalOnHand(), (string) ($this->reorder_level ?? '0'), 4) < 0;
    }

    public function isBelowMinimum(): bool
    {
        return bccomp($this->totalOnHand(), (string) ($this->minimum_stock ?? '0'), 4) < 0;
    }
}

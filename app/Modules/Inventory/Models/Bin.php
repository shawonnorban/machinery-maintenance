<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The smallest stock location, and the one balances are held against.
 *
 * Per bin rather than per store, because "we have twelve" is not useful to a
 * technician who then has to open every drawer.
 */
class Bin extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = ['company_id', 'store_id', 'name', 'code', 'is_in_transit', 'active'];

    protected function casts(): array
    {
        return ['is_in_transit' => 'boolean', 'active' => 'boolean'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    /** The factory this bin physically sits in, through store and warehouse. */
    public function factoryId(): ?string
    {
        return $this->store?->warehouse?->factory_id;
    }

    public function fullPath(): string
    {
        $store = $this->store;
        $warehouse = $store?->warehouse;

        return collect([$warehouse?->name, $store?->name, $this->name])
            ->filter()
            ->join(' › ');
    }
}

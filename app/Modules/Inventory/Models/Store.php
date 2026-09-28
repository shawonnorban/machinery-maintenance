<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends BaseModel
{
    use BelongsToTenant;

    /** Bonded stock is what customs watches; a sub-store is a floor-side satellite of a main store. */
    public const STORE_TYPES = ['GENERAL', 'BONDED', 'SUB_STORE'];

    protected $fillable = ['company_id', 'warehouse_id', 'name', 'code', 'store_type', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class);
    }
}

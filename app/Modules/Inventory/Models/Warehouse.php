<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Tenancy\Models\Factory;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = ['company_id', 'factory_id', 'name', 'code', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }
}

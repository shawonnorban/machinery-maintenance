<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetLocation;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Factory extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'factories';

    protected $fillable = [
        'company_id', 'business_unit_id', 'name', 'code',
        'address', 'timezone', 'status',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /** The machines standing in this factory right now. */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'current_factory_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(AssetLocation::class);
    }
}

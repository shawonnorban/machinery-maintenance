<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The tenant root.
 *
 * Company itself is NOT tenant-scoped: the scope is derived from it. Access is
 * controlled by membership through company_users instead (SRS 4).
 */
class Company extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id', 'name', 'code', 'legal_name',
        'base_currency', 'timezone', 'default_locale', 'status',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function factories(): HasMany
    {
        return $this->hasMany(Factory::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_users')
            ->withPivot(['status', 'is_default'])
            ->withTimestamps();
    }
}

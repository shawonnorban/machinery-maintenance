<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Platform-wide permission catalog. Not tenant-owned: the codes are the same
 * for every company (Handbook 2).
 */
class Permission extends BaseModel
{
    protected $fillable = ['code', 'module', 'name', 'description', 'is_elevated'];

    protected function casts(): array
    {
        return ['is_elevated' => 'boolean'];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}

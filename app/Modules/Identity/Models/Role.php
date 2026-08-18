<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A role is either platform-seeded (company_id null, is_system true) or a
 * tenant's own clone. Seeded roles are not editable; a tenant clones one to
 * customize it (SRS 5.3 rule 5).
 *
 * Deliberately NOT using BelongsToTenant: platform roles have a null
 * company_id and must remain visible to every tenant.
 */
class Role extends BaseModel
{
    protected $fillable = [
        'company_id', 'code', 'name', 'description', 'scope', 'is_system',
    ];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * Roles a given company may assign: its own, plus the platform-seeded set.
     */
    public function scopeAvailableTo($query, string $companyId)
    {
        return $query->where(function ($q) use ($companyId): void {
            $q->whereNull('company_id')->orWhere('company_id', $companyId);
        });
    }

    public function isEditable(): bool
    {
        return ! $this->is_system;
    }
}

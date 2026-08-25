<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

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
        'email', 'phone', 'country', 'address', 'logo_path',
        'base_currency', 'timezone', 'default_locale', 'status',
        'suspension_reason', 'suspended_at', 'suspended_by',
    ];

    protected function casts(): array
    {
        return ['suspended_at' => 'datetime'];
    }

    /**
     * Stopped by the platform, as distinct from lapsed by billing.
     *
     * The two are different states with different answers. A lapsed
     * subscription makes a company read-only — every screen opens, nothing can
     * be written, and exports still work, because the data belongs to the
     * customer (ADR-030). A suspension is a deliberate act by platform staff
     * and stops everything, which is why it has to carry a reason the customer
     * can read.
     */
    public function isSuspended(): bool
    {
        return $this->status !== 'ACTIVE';
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

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

    /**
     * A public URL for the logo, or null when none has been uploaded.
     *
     * Stored on the public disk rather than through FileAttachment: a logo is
     * shown in an <img> tag on every page load, never downloaded as evidence,
     * and file_attachments exists for the second thing (SRS 37), not the
     * first.
     */
    public function logoUrl(): ?string
    {
        return $this->logo_path === null
            ? null
            : Storage::disk('public')->url($this->logo_path);
    }
}

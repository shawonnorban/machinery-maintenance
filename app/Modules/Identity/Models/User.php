<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Tenancy\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

/**
 * A user is not tenant-owned. They may belong to several companies through
 * company_users, which is why tenant context is resolved from membership
 * rather than read off this row (SRS 4).
 */
class User extends Authenticatable
{
    use HasUlids;
    use Notifiable;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'status',
        'timezone', 'locale', 'is_platform_admin',
    ];

    protected $hidden = ['password', 'remember_token', 'mfa_secret'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            // Encrypted, not hashed: verifying a one-time code needs the
            // secret itself. That makes it the only credential here a database
            // dump could be used with, so the key lives in the environment
            // rather than beside it (SRS 50.3).
            'mfa_secret' => 'encrypted',
            'mfa_confirmed_at' => 'datetime',
        ];
    }

    /**
     * A second factor is only in force once the person has proved they can
     * produce a code. Scanning the QR and then losing the phone must not lock
     * somebody out of an account that never gained the factor.
     */
    public function hasMfa(): bool
    {
        return $this->mfa_secret !== null && $this->mfa_confirmed_at !== null;
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_users')
            ->withPivot(['status', 'is_default'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    /**
     * Companies this user may actually select. A membership row that is not
     * ACTIVE grants nothing.
     *
     * @return Collection<int, Company>
     */
    public function accessibleCompanies()
    {
        return $this->companies()
            ->wherePivot('status', 'ACTIVE')
            ->whereNull('companies.deleted_at')
            ->get();
    }

    public function belongsToCompany(string $companyId): bool
    {
        return $this->memberships()
            ->where('company_id', $companyId)
            ->where('status', 'ACTIVE')
            ->exists();
    }
}

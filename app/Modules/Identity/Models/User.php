<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Department;
use App\Modules\Tenancy\Models\ProductionLine;
use App\Modules\WorkOrder\Models\Technician;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

/**
 * A user is not tenant-owned. They may belong to several companies through
 * company_users, which is why tenant context is resolved from membership
 * rather than read off this row (SRS 4).
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasUlids;
    use Notifiable;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'status',
        'timezone', 'locale', 'is_platform_admin',
        'department_id', 'production_line_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
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

    /** Set for a role like Line Chief that reports breakdowns without a technician row of its own. */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class);
    }

    /** Not every user is a technician, and not every technician has a login — hence `HasOne`, not a guaranteed relation. */
    public function technician(): HasOne
    {
        return $this->hasOne(Technician::class);
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

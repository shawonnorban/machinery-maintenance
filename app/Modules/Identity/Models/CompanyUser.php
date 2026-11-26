<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Tenancy\Models\Company;
use App\Shared\Models\BaseModel;
use App\Shared\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class CompanyUser extends BaseModel
{
    protected $table = 'company_users';

    protected $fillable = ['company_id', 'user_id', 'status', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /**
     * The company's subscription seats: its active members, less the ones
     * whose every role here is portal-only (a supplier's portal login).
     */
    public static function seatCount(string $companyId): int
    {
        $portal = Role::query()->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))->whereIn('name', Role::PORTAL_ONLY)->pluck('id')->all();

        return self::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $companyId)
            ->where('status', 'ACTIVE')
            ->when($portal !== [], fn ($q) => $q->where(function ($q) use ($portal): void {
                // Kept unless it holds a portal role and nothing besides.
                $q->whereNotExists(fn ($s) => $s->select(DB::raw(1))->from('user_roles')->whereColumn('user_roles.user_id', 'company_users.user_id')
                    ->whereColumn('user_roles.company_id', 'company_users.company_id')->whereIn('user_roles.role_id', $portal))
                    ->orWhereExists(fn ($s) => $s->select(DB::raw(1))->from('user_roles')->whereColumn('user_roles.user_id', 'company_users.user_id')
                        ->whereColumn('user_roles.company_id', 'company_users.company_id')->whereNotIn('user_roles.role_id', $portal));
            }))
            ->distinct()
            ->count('user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * The role/permission catalog lives in Spatie Laravel Permission's own
 * `roles`/`permissions`/`role_has_permissions` tables — this class only
 * adds back the query helpers the previous custom Role model offered, per
 * Spatie's own extension pattern (config('permission.models.role')).
 *
 * `company_id` is Spatie's "team" column here (config('permission.teams')).
 * A role is either platform-seeded (company_id null, is_system true) or a
 * tenant's own clone. Seeded roles are not editable; a tenant clones one to
 * customize it (SRS 5.3 rule 5). Note that Spatie's `name` column carries
 * the machine code (COMPANY_OWNER) and the human-readable label lives in
 * `description` instead — see the migration that introduced these tables.
 *
 * `user_roles` (this application's own assignment table, with its
 * factory_id scoping) still stores the plain foreign key `role_id`
 * pointing at this table's `id` — it does not use Spatie's model_has_roles
 * morph table, so no HasRoles-side wiring is needed here.
 */
class Role extends SpatieRole
{
    /**
     * Roles that only open a portal (Material Procurement's Supplier Portal,
     * AnnotechERP 08/07): an account holding nothing else does no work of
     * the company's own, so it takes no subscription seat.
     */
    public const PORTAL_ONLY = ['SUPPLIER'];

    /** Whether every one of these roles is portal-only (and there is at least one). @param list<int|string> $roleIds */
    public static function onlyPortal(array $roleIds): bool
    {
        if ($roleIds === []) {
            return false;
        }

        return self::query()->whereIn('id', $roleIds)->whereNotIn('name', self::PORTAL_ONLY)->doesntExist();
    }

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_admin' => 'boolean', 'active' => 'boolean', 'rank' => 'integer', 'template_permissions' => 'array'];
    }

    /**
     * Roles a given company may assign: its own, plus the platform-seeded
     * set — but a seeded role the company has its own copy of (same code,
     * RoleProvisioner) is the copy only.
     */
    public function scopeAvailableTo($query, string $companyId)
    {
        return $query->where(function ($q) use ($companyId): void {
            $q->where('roles.company_id', $companyId)->orWhere(fn ($g) => $g->whereNull('roles.company_id')->where('roles.scope', '!=', 'PLATFORM')
                ->whereNotExists(fn ($e) => $e->from('roles as own')->where('own.company_id', $companyId)->whereColumn('own.name', 'roles.name')));
        });
    }

    public function isEditable(): bool
    {
        return ! $this->is_system;
    }
}

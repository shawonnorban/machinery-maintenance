<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * A company's own, editable copy of every seeded role (user, 2026-11-22).
 *
 * The seeded roles (company_id null) stay as templates. Provisioning gives a
 * company a copy of each — same code, name and permissions — and moves its
 * people, approval steps, escalations, item-category grants and column
 * access from the template to the copy, so from then on editing a role
 * changes this company alone. A template that later gains or loses a
 * permission passes that change on to every copy; what the company itself
 * added or took away is kept.
 */
final class RoleProvisioner
{
    /** Templates no company gets a copy of. */
    public const SKIP = ['PLATFORM_SUPER_ADMIN'];

    /** Roles a copy always holds every permission of. */
    public const ALL_PERMISSIONS = ['COMPANY_OWNER'];

    /** Tables whose `role_id`-like column points at a role, by company. */
    private const REFERENCES = [
        ['user_roles', 'role_id'], ['approval_rules', 'role_id'], ['escalation_rules', 'escalation_role_id'], ['role_item_category_permissions', 'role_id'],
    ];

    /** Tables whose `role_access` json is keyed by role id. */
    private const ROLE_ACCESS = ['os_columns', 'ds_columns', 'pb_columns', 'es_columns'];

    /** Bring every company that already has its copies up to date with the templates. */
    public static function syncAll(): void
    {
        if (! Schema::hasColumn('roles', 'template')) {
            return;
        }
        foreach (Role::query()->whereNotNull('company_id')->whereNotNull('template')->distinct()->pluck('company_id') as $companyId) {
            self::provision((string) $companyId);
        }
    }

    /**
     * Copy every template this company lacks, carry template changes onto
     * the copies it has, and point its references at the copies.
     *
     * @return array{created: int, synced: int, moved: int}
     */
    public static function provision(string $companyId): array
    {
        $templates = Role::query()->whereNull('company_id')->where('is_system', true)->whereNotIn('name', self::SKIP)->with('permissions:id,name')->get();
        $own = Role::query()->where('company_id', $companyId)->with('permissions:id,name')->get()->keyBy('name');
        $ids = Permission::query()->pluck('id', 'name');
        $created = $synced = 0;
        $map = [];

        DB::transaction(function () use ($templates, $own, $ids, $companyId, &$created, &$synced, &$map): void {
            foreach ($templates as $t) {
                $now = in_array($t->name, self::ALL_PERMISSIONS, true) ? $ids->keys()->all() : $t->permissions->pluck('name')->all();
                $copy = $own->get($t->name);
                if ($copy === null) {
                    $copy = new Role;
                    $copy->forceFill([
                        'company_id' => $companyId, 'name' => $t->name, 'guard_name' => $t->guard_name, 'description' => $t->description, 'scope' => $t->scope,
                        'name_bn' => $t->name_bn, 'short_name' => $t->short_name, 'rank' => $t->rank, 'active' => true,
                        'is_admin' => in_array($t->name, self::ALL_PERMISSIONS, true), 'is_system' => false,
                        'template' => $t->name, 'template_permissions' => $now,
                    ])->save();
                    $copy->permissions()->sync($ids->only($now)->values()->all());
                    $created++;
                } elseif ($copy->template === $t->name) {
                    $last = (array) ($copy->template_permissions ?? []);
                    $added = array_diff($now, $last);
                    $removed = array_diff($last, $now);
                    if ($added !== [] || $removed !== []) {
                        $held = $copy->permissions->pluck('name')->all();
                        $next = array_values(array_diff(array_unique([...$held, ...$added]), $removed));
                        $copy->permissions()->sync($ids->only($next)->values()->all());
                        $copy->forceFill(['template_permissions' => $now])->save();
                        $synced++;
                    }
                } else {
                    continue; // a role of the company's own that happens to share the code
                }
                $map[(int) $t->id] = (int) $copy->id;
            }
        });

        $moved = self::repoint($companyId, $map);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(\App\Modules\Identity\Services\PermissionResolver::class)->flush();

        return ['created' => $created, 'synced' => $synced, 'moved' => $moved];
    }

    /**
     * This company's role for a code: its own copy, else the template.
     */
    public static function resolve(string $code, ?string $companyId): ?Role
    {
        return Role::query()->where('name', $code)
            ->where(fn ($q) => $q->whereNull('company_id')->when($companyId, fn ($w) => $w->orWhere('company_id', $companyId)))
            ->orderByRaw('company_id is null')->first();
    }

    /** @param  array<int, int>  $map  template id => copy id */
    private static function repoint(string $companyId, array $map): int
    {
        if ($map === []) {
            return 0;
        }
        $moved = 0;
        DB::transaction(function () use ($companyId, $map, &$moved): void {
            foreach (self::REFERENCES as [$table, $column]) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                foreach ($map as $from => $to) {
                    if ($table === 'user_roles') {
                        // One person holding both (rare) keeps a single row.
                        DB::table('user_roles')->where('company_id', $companyId)->where('role_id', $from)
                            ->whereExists(fn ($q) => $q->from('user_roles as u2')->whereColumn('u2.user_id', 'user_roles.user_id')->whereColumn('u2.company_id', 'user_roles.company_id')
                                ->where('u2.role_id', $to)->whereRaw('u2.factory_id is not distinct from user_roles.factory_id'))
                            ->delete();
                    }
                    $moved += DB::table($table)->where('company_id', $companyId)->where($column, $from)->update([$column => $to]);
                }
            }
            foreach (self::ROLE_ACCESS as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                foreach (DB::table($table)->where('company_id', $companyId)->whereNotNull('role_access')->get(['id', 'role_access']) as $row) {
                    $access = json_decode((string) $row->role_access, true);
                    if (! is_array($access) || $access === []) {
                        continue;
                    }
                    $next = [];
                    foreach ($access as $role => $grant) {
                        $next[(string) ($map[(int) $role] ?? $role)] = $grant;
                    }
                    if ($next !== $access) {
                        DB::table($table)->where('id', $row->id)->update(['role_access' => json_encode($next)]);
                        $moved++;
                    }
                }
            }
        });

        return $moved;
    }
}

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copies the legacy role/permission catalog (renamed to legacy_roles /
 * legacy_permissions by the prior migration) into Spatie's tables, then
 * repoints every column that used to reference the old roles table at the
 * new (bigint) ids: user_roles.role_id, approval_rules.role_id, and
 * escalation_rules.escalation_role_id.
 *
 * legacy_permissions.code becomes Spatie's `name` column — Spatie's own
 * uniqueness key — because the code was already unique and namespaced
 * ({module}.{resource}.{action}, SRS 5.2); legacy_roles.code becomes
 * Spatie's `name` the same way. guard_name is 'web' throughout: nothing in
 * this application authenticates through any other guard.
 *
 * Each column changes type (ULID -> bigint) via an add/copy/drop/rename
 * sequence rather than a single ->change(), so it behaves the same on
 * every driver instead of depending on driver-specific column-alter
 * support.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissionIdMap = $this->copyPermissions();
        $roleIdMap = $this->copyRoles();
        $this->copyRolePermissions($roleIdMap, $permissionIdMap);

        // Dropped up front, before role_id changes shape underneath it —
        // found live on a MariaDB/MySQL build that raises "Key column
        // 'role_id' doesn't exist in table" the moment dropColumn() below
        // touches a column still carrying part of a composite unique key,
        // rather than the silent narrowing another MySQL build does with
        // the exact same statement. Dropping the index first removes the
        // ambiguity for every engine instead of depending on which
        // behaviour a given MySQL/MariaDB version picked.
        $this->dropUniqueIfPresent('user_roles', 'user_roles_unique');

        $this->repointRoleColumn('user_roles', 'role_id', $roleIdMap, nullable: false);
        $this->repointRoleColumn('approval_rules', 'role_id', $roleIdMap, nullable: true);
        $this->repointRoleColumn('escalation_rules', 'escalation_role_id', $roleIdMap, nullable: true);

        Schema::table('user_roles', function (Blueprint $blueprint): void {
            $blueprint->unique(['company_id', 'user_id', 'role_id', 'factory_id'], 'user_roles_unique');
        });
    }

    public function down(): void
    {
        $this->dropUniqueIfPresent('user_roles', 'user_roles_unique');

        $this->revertRoleColumn('user_roles', 'role_id', nullable: false);
        $this->revertRoleColumn('approval_rules', 'role_id', nullable: true);
        $this->revertRoleColumn('escalation_rules', 'escalation_role_id', nullable: true);

        Schema::table('user_roles', function (Blueprint $blueprint): void {
            $blueprint->unique(['company_id', 'user_id', 'role_id', 'factory_id'], 'user_roles_unique');
        });
    }

    private function dropUniqueIfPresent(string $table, string $index): void
    {
        $exists = collect(Schema::getIndexes($table))->contains(fn (array $i): bool => $i['name'] === $index);

        if ($exists) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($index));
        }
    }

    /** @return array<string, int> legacy ULID => new Spatie bigint id */
    private function copyPermissions(): array
    {
        $map = [];

        foreach (DB::table('legacy_permissions')->orderBy('code')->get() as $legacy) {
            $map[$legacy->id] = DB::table('permissions')->insertGetId([
                'name' => $legacy->code,
                'guard_name' => 'web',
                'module' => $legacy->module,
                // The old catalog's human-readable label lived in its own
                // `name` column ("View assets"); Spatie's `name` is the
                // code instead, so the label moves to `description`. The
                // old `description` column was never actually populated by
                // PermissionSeeder, so nothing is lost by not copying it.
                'description' => $legacy->name,
                'is_elevated' => $legacy->is_elevated,
                'created_at' => $legacy->created_at,
                'updated_at' => $legacy->updated_at,
            ]);
        }

        return $map;
    }

    /** @return array<string, int> legacy ULID => new Spatie bigint id */
    private function copyRoles(): array
    {
        $map = [];

        foreach (DB::table('legacy_roles')->orderBy('code')->get() as $legacy) {
            $map[$legacy->id] = DB::table('roles')->insertGetId([
                'company_id' => $legacy->company_id,
                'name' => $legacy->code,
                'guard_name' => 'web',
                // Same label/code swap as permissions above: the old
                // human-readable name ("Company Owner") moves to
                // description, since Spatie's `name` now holds the code
                // ("COMPANY_OWNER").
                'description' => $legacy->name,
                'scope' => $legacy->scope,
                'is_system' => $legacy->is_system,
                'created_at' => $legacy->created_at,
                'updated_at' => $legacy->updated_at,
            ]);
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $roleIdMap
     * @param  array<string, int>  $permissionIdMap
     */
    private function copyRolePermissions(array $roleIdMap, array $permissionIdMap): void
    {
        $insert = [];

        foreach (DB::table('role_permissions')->get() as $row) {
            // Guards against an orphaned grant referencing a role or
            // permission that somehow no longer exists — skip rather than
            // fail the whole migration over one bad row.
            if (! isset($roleIdMap[$row->role_id], $permissionIdMap[$row->permission_id])) {
                continue;
            }

            $insert[] = [
                'role_id' => $roleIdMap[$row->role_id],
                'permission_id' => $permissionIdMap[$row->permission_id],
            ];
        }

        foreach (array_chunk($insert, 500) as $chunk) {
            DB::table('role_has_permissions')->insert($chunk);
        }
    }

    /**
     * Repoints one legacy-role-referencing column at Spatie's bigint ids,
     * via add-new-column / copy-mapped-values / drop-old / rename, so it
     * works the same whether or not the driver supports altering a
     * column's type directly.
     *
     * @param  array<string, int>  $roleIdMap
     */
    private function repointRoleColumn(string $table, string $column, array $roleIdMap, bool $nullable): void
    {
        $newColumn = $column.'_spatie';

        Schema::table($table, function (Blueprint $blueprint) use ($newColumn, $column): void {
            $blueprint->unsignedBigInteger($newColumn)->nullable()->after($column);
        });

        foreach ($roleIdMap as $legacyId => $newId) {
            DB::table($table)->where($column, $legacyId)->update([$newColumn => $newId]);
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropForeign([$column]);
            $blueprint->dropColumn($column);
        });

        Schema::table($table, function (Blueprint $blueprint) use ($newColumn, $column): void {
            $blueprint->renameColumn($newColumn, $column);
        });

        Schema::table($table, function (Blueprint $blueprint) use ($column, $nullable): void {
            $nullable
                ? $blueprint->unsignedBigInteger($column)->nullable()->change()
                : $blueprint->unsignedBigInteger($column)->change();
            $blueprint->foreign($column)->references('id')->on('roles')->cascadeOnDelete();
        });
    }

    /**
     * Reverses repointRoleColumn: recovers each row's original ULID by
     * matching Spatie's role name back to legacy_roles.code — the only
     * thread still connecting the two once the column no longer stores the
     * old id directly.
     */
    private function revertRoleColumn(string $table, string $column, bool $nullable): void
    {
        $legacyColumn = $column.'_legacy';

        Schema::table($table, function (Blueprint $blueprint) use ($legacyColumn, $column): void {
            $blueprint->ulid($legacyColumn)->nullable()->after($column);
        });

        DB::table($table)
            ->join('roles', 'roles.id', '=', "{$table}.{$column}")
            ->join('legacy_roles', 'legacy_roles.code', '=', 'roles.name')
            ->update(["{$table}.{$legacyColumn}" => DB::raw('legacy_roles.id')]);

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropForeign([$column]);
            $blueprint->dropColumn($column);
        });

        Schema::table($table, function (Blueprint $blueprint) use ($legacyColumn, $column): void {
            $blueprint->renameColumn($legacyColumn, $column);
        });

        Schema::table($table, function (Blueprint $blueprint) use ($column, $nullable): void {
            $nullable ? $blueprint->ulid($column)->nullable()->change() : $blueprint->ulid($column)->change();
            $blueprint->foreign($column)->references('id')->on('legacy_roles')->cascadeOnDelete();
        });
    }
};

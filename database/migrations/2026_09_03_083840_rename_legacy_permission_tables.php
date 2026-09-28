<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frees the table names `roles` and `permissions` for Spatie Laravel
 * Permission, which owns the role/permission catalog from here on.
 * `user_roles`, with its factory_id scoping, stays a custom table — only
 * the catalog these two fed moves to Spatie.
 *
 * Renamed, not dropped: the next two migrations read these rows to seed
 * Spatie's tables and repoint user_roles.role_id, and a rollback window
 * needs the originals intact until that is verified against real data.
 *
 * The company_id foreign key also gets renamed: MySQL keeps a constraint's
 * own name through a table rename (only the referenced/referencing table
 * changes), so `legacy_roles` still holds a constraint literally named
 * `roles_company_id_foreign` — which collides the moment the next
 * migration tries to create a `roles` table with the same auto-generated
 * constraint name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('roles', 'legacy_roles');
        Schema::rename('permissions', 'legacy_permissions');

        Schema::table('legacy_roles', function (Blueprint $table): void {
            $table->dropForeign('roles_company_id_foreign');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('legacy_roles', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->foreign('company_id', 'roles_company_id_foreign')
                ->references('id')->on('companies')->cascadeOnDelete();
        });

        Schema::rename('legacy_permissions', 'permissions');
        Schema::rename('legacy_roles', 'roles');
    }
};

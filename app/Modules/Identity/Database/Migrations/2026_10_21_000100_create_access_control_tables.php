<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Access Control (AnnotechERP 02/01): Brickland's module → menu tree with
 * per-menu V/C/E/D/A/P/X grants, laid over the permission engine rather than
 * replacing it. A menu flag names permission strings (`menu_permissions`);
 * granting a flag to a role grants those permissions, so every
 * `$this->allow(...)` in the code keeps working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_modules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 48)->unique();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('slug', 64)->unique();
            $table->string('icon', 48)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('menus', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('menu_module_id')->constrained('menu_modules')->restrictOnDelete();
            // Foreign key added below: Postgres cannot reference the table
            // being created from inside its own CREATE.
            $table->ulid('parent_id')->nullable();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->string('security_level', 16)->default('INTERNAL');
            $table->string('icon', 48)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            // A leaf is a screen; anything else is a folder that only groups.
            $table->boolean('is_leaf')->default(true);
            // Whether the leaf is a link in the sidebar, or a screen reached
            // inside its module (a Company Management tab, say) that still
            // needs its own grant.
            $table->boolean('in_sidebar')->default(true);
            $table->boolean('open_new_window')->default(false);
            $table->boolean('is_report')->default(false);
            // Seeded from code (MenuRegistry): renamable, reorderable and
            // hideable, never deletable — the screen behind it exists in code.
            $table->boolean('is_system')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['menu_module_id', 'parent_id', 'sort_order']);
        });

        Schema::table('menus', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('menus')->restrictOnDelete();
        });

        Schema::create('menu_permissions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->char('flag', 1);
            $table->string('permission_name');

            $table->unique(['menu_id', 'flag', 'permission_name']);
            $table->index('permission_name');
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->string('name_bn')->nullable();
            $table->string('short_name', 32)->nullable();
            $table->unsignedInteger('rank')->nullable();
            // Bypasses the menu matrix entirely — grant to as few roles as possible.
            $table->boolean('is_admin')->default(false);
            $table->boolean('active')->default(true);
        });

        Schema::create('lookup_tables', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->string('code', 48);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('lookup_values', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->foreignUlid('lookup_table_id')->constrained('lookup_tables')->cascadeOnDelete();
            $table->string('code', 48);
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['lookup_table_id', 'code']);
        });

        Schema::create('role_item_category_permissions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->restrictOnDelete();
            // Spatie's roles table keys on a bigint, not a ulid.
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignUlid('item_category_id')->constrained('item_categories')->cascadeOnDelete();
            $table->boolean('can_view')->default(true);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_approve')->default(false);
            $table->timestamps();

            $table->unique(['role_id', 'item_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_item_category_permissions');
        Schema::dropIfExists('lookup_values');
        Schema::dropIfExists('lookup_tables');
        Schema::table('roles', fn (Blueprint $table) => $table->dropColumn(['name_bn', 'short_name', 'rank', 'is_admin', 'active']));
        Schema::dropIfExists('menu_permissions');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('menu_modules');
    }
};

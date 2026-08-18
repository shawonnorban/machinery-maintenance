<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users, memberships, roles, permissions, teams: ERD Section 3.
 *
 * Note that `users` carries no company_id. A user may belong to several
 * companies through company_users, which is why tenant context is resolved
 * from membership rather than read off the user row (SRS 4).
 *
 * The default Laravel users table is replaced here so the primary key is a
 * ULID like every other table (ERD rule 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 32)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('status', 32)->default('ACTIVE');
            $table->string('timezone', 64)->default('Asia/Dhaka');
            $table->string('locale', 8)->default('en');
            $table->boolean('is_platform_admin')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('company_users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('ACTIVE');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // {module}.{resource}.{action} per SRS 5.2
            $table->string('code', 128)->unique();
            $table->string('module', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_elevated')->default(false);
            $table->timestamps();

            $table->index('module');
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // null company_id = platform-seeded role, not editable by a tenant
            $table->foreignUlid('company_id')->nullable()
                ->constrained('companies')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('scope', 16)->default('FACTORY'); // COMPANY | FACTORY | PLATFORM
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignUlid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignUlid('permission_id')->constrained('permissions')->cascadeOnDelete();

            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('role_id')->constrained('roles')->cascadeOnDelete();
            // null = company-wide; set = scoped to one factory (ERD Section 3)
            $table->foreignUlid('factory_id')->nullable()
                ->constrained('factories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'role_id', 'factory_id'], 'user_roles_unique');
            $table->index(['company_id', 'user_id']);
        });

        Schema::create('teams', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->nullable()
                ->constrained('factories')->nullOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->string('specialization')->nullable();
            $table->string('status', 32)->default('ACTIVE');
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('login_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('email');
            $table->foreignUlid('user_id')->nullable();
            $table->string('ip_address', 45);
            $table->boolean('successful');
            $table->string('failure_reason', 64)->nullable();
            $table->timestamp('attempted_at');

            $table->index(['email', 'attempted_at']);
            $table->index('attempted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('company_users');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};

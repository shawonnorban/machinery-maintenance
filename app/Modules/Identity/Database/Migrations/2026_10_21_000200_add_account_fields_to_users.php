<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Access Control → User Account Registration and Password Change
 * (AnnotechERP 02/01, step B): Brickland's account columns on the user, and a
 * record of every password change (who changed it, when, whether forced).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Brickland's "User ID": a second sign-in name besides the email.
            $table->string('username', 64)->nullable()->unique();
            $table->string('user_type', 16)->default('INTERNAL');
            $table->string('employee_code', 64)->nullable();
            $table->timestamp('account_expires_at')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->boolean('cannot_change_password')->default(false);
            $table->boolean('password_never_expires')->default(false);
            // Stored now, enforced in a later security step (user decision).
            $table->string('two_factor_method', 16)->default('DISABLED');
            // Hashed; asked for when an administrator resets the password.
            $table->string('memorable_word')->nullable();
            $table->text('justification')->nullable();
        });

        Schema::create('password_changes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            // SELF (own change), ADMIN_SET (an administrator chose it),
            // ADMIN_RESET (one issued and shown once), CREATED.
            $table->string('change_type', 16);
            $table->boolean('forced_change_next_login')->default(false);
            $table->timestamp('changed_at');

            $table->index(['user_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_changes');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'username', 'user_type', 'employee_code', 'account_expires_at', 'must_change_password', 'cannot_change_password',
            'password_never_expires', 'two_factor_method', 'memorable_word', 'justification',
        ]));
    }
};

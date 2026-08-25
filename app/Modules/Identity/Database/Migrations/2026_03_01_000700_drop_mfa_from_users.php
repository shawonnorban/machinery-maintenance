<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-step sign-in removed at the customer's decision.
 *
 * A separate migration rather than an edit to the one that added the columns:
 * that one has been applied to a database, and rewriting applied history means
 * a schema that no longer matches what the migration table says was run.
 *
 * The secrets go with the columns, which is the right way round. A disabled
 * feature that leaves credentials in the database is a feature still holding
 * something worth stealing, and these were the one thing here a database dump
 * could be used with.
 *
 * `down()` restores the shape but not the enrolments. That is honest rather
 * than lazy: a rolled-back drop cannot conjure back a secret it destroyed, and
 * anybody reversing this would have to re-enrol regardless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('mfa_recovery_codes');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['mfa_secret', 'mfa_confirmed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('mfa_secret')->nullable()->after('password');
            $table->timestamp('mfa_confirmed_at')->nullable()->after('mfa_secret');
        });

        Schema::create('mfa_recovery_codes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('code_hash');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'used_at'], 'mfa_recovery_user_index');
        });
    }
};

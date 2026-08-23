<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-factor authentication (SRS 50.3).
 *
 * Two columns and a table, and the shape of each says something.
 *
 * `mfa_secret` is encrypted at rest by the model cast, not hashed, because
 * verifying a TOTP requires the secret itself. That makes it the one credential
 * in this schema that a database dump could be used with — hence encryption,
 * whose key lives in the environment rather than the database.
 *
 * `mfa_confirmed_at` is separate from the secret because enrolment has two
 * steps and the gap between them matters: somebody who scans a QR code and
 * then loses the phone before entering a code must not be locked out of an
 * account that never actually gained a second factor.
 *
 * Recovery codes are hashed and single-use. They are the way back in when the
 * phone is gone, which is exactly why they are as powerful as a password and
 * are stored the same way.
 */
return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        Schema::dropIfExists('mfa_recovery_codes');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['mfa_secret', 'mfa_confirmed_at']);
        });
    }
};

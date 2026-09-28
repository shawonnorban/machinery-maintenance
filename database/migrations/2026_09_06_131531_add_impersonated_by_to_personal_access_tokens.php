<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The API shape of a support-access session (SRS 5.4, Platform API §6).
 *
 * The web flow impersonates through a session — `Auth::login($asUser)` plus
 * a session key `AuditRecorder` reads back onto every row a support session
 * writes. An API bearer token has no session to carry that key, so it is
 * carried on the token itself instead: `POST
 * /platform/support-grants/{grant}/enter` mints an ordinary person-scoped
 * token for the target user, exactly like any other login, except this
 * column is set — and `AuditRecorder` reads it as a second source next to
 * the session key, so a row written under an impersonation token is
 * attributed identically to one written under an impersonated session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->ulid('impersonated_by')->nullable()->after('last_four');
            $table->foreign('impersonated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropForeign(['impersonated_by']);
            $table->dropColumn('impersonated_by');
        });
    }
};

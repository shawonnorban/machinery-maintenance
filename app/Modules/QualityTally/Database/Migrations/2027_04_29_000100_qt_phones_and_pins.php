<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The QC's phone (AnnotechERP 37, phase 1; user 2026-11-25):
 *
 *  - qt_inspectors.pin_hash: null is the DEFAULT PIN (000000) until the QC sets
 *    their own; a reset puts it back to null.
 *  - qt_phones: an installed app, known by its own id and secret. A new phone
 *    waits for approval before a QC may sign in on it; it can be tied to one
 *    line; blocking it ends everything signed in on it.
 *  - qt_sessions: who was on which phone, from when to when. The id is the
 *    phone's own (a QC may sign in while there is no network).
 *  - qt_entries: which phone and session a tap came from.
 *  - personal_access_tokens: a PIN session's token names its session, any token
 *    its phone. No foreign key on the session: a token must never outlive its
 *    marker and turn into an ordinary one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qt_inspectors', function (Blueprint $table): void {
            $table->string('pin_hash')->nullable();
            $table->timestamp('pin_changed_at')->nullable();
        });

        Schema::create('qt_phones', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->uuid('install_id');
            $table->string('secret_hash', 64);
            $table->string('name', 60)->nullable();
            $table->string('section', 12)->nullable();
            $table->ulid('line_id')->nullable();
            $table->string('model', 80)->nullable();
            $table->string('os', 40)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->unsignedInteger('waiting_taps')->default(0);
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignUlid('first_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable(); // a QC asked to sign in on it
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('blocked_at')->nullable();
            $table->foreignUlid('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'install_id']);
        });

        Schema::create('qt_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->uuid('uuid');
            $table->foreignUlid('phone_id')->constrained('qt_phones')->cascadeOnDelete();
            $table->foreignUlid('inspector_id')->nullable()->constrained('qt_inspectors')->nullOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('offline')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 20)->nullable(); // SIGNED_OUT | SWITCHED | PIN_RESET | BLOCKED | REMOVED
            $table->timestamps();
            $table->unique(['phone_id', 'uuid']);
            $table->index(['inspector_id', 'started_at']);
        });

        Schema::table('qt_entries', function (Blueprint $table): void {
            $table->ulid('phone_id')->nullable();
            $table->ulid('session_id')->nullable();
        });

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->ulid('qt_session_id')->nullable()->index();
            $table->ulid('qt_phone_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', fn (Blueprint $table) => $table->dropColumn(['qt_session_id', 'qt_phone_id']));
        Schema::table('qt_entries', fn (Blueprint $table) => $table->dropColumn(['phone_id', 'session_id']));
        Schema::dropIfExists('qt_sessions');
        Schema::dropIfExists('qt_phones');
        Schema::table('qt_inspectors', fn (Blueprint $table) => $table->dropColumn(['pin_hash', 'pin_changed_at']));
    }
};

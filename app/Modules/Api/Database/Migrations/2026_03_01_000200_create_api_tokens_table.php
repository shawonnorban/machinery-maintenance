<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bearer tokens (API 3).
 *
 * One row is one key to one company's data. A user who belongs to three
 * companies holds three tokens rather than one that switches, because a token
 * that can change which company it reads is a token whose blast radius nobody
 * can state.
 *
 * The token itself is never stored. What is stored is a SHA-256 of it, which
 * is enough to recognise a token presented on a request and useless to anybody
 * who steals the table. SHA-256 rather than bcrypt on purpose: this is looked
 * up on every single API request by exact match, and a bcrypt column cannot be
 * indexed. The input is 40 characters of CSPRNG output, not a human password,
 * so there is nothing for a dictionary to guess.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();

            // Exactly one of these is set: a person's token or a machine's.
            // Both are bearer tokens and both are checked the same way; what
            // differs is what they are allowed to do and how they were got.
            $table->foreignUlid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('api_client_id')->nullable()->constrained('api_clients')->cascadeOnDelete();

            $table->string('name');
            $table->char('token_hash', 64)->unique();

            // The last four characters, in the clear, so a person revoking one
            // of six tokens can tell which is which without being shown any of
            // them in full.
            $table->string('last_four', 8);

            // A subset of what the caller could otherwise do, or null for all
            // of it. Null is only ever reachable for a person's own token; a
            // machine client's scopes are always an explicit list.
            $table->json('abilities_json')->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'company_id'], 'api_tokens_user_index');
            $table->index('expires_at', 'api_tokens_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tables that exist only because there is an API: ERD Section 26, SRS 43,
 * API Specification 4.2 and 32.
 *
 * Two unrelated jobs, kept together because both are infrastructure for
 * machine callers rather than for anything a person maintains.
 *
 * `api_clients` are machine-to-machine credentials — an ERP posting costs, a
 * PLC posting meter readings. They are scoped to a company and to an explicit
 * subset of that company's permissions, so a token minted to read meters
 * cannot close a work order even if the account behind it could.
 *
 * `idempotency_keys` is what makes a retry safe. A technician on a factory
 * floor with two bars of signal will press "issue part" again when the first
 * attempt appears to hang, and the second attempt must return the first one's
 * answer rather than issue the part twice. That is not a nicety: stock and
 * money are the two things this system cannot un-move.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();

            $table->string('name');
            $table->string('client_id', 64)->unique();

            // Hashed, never stored in the clear. The secret is shown exactly
            // once at creation; a secret a screen can read back is a secret
            // that leaks through a support session.
            $table->string('secret_hash');
            $table->timestamp('secret_rotated_at')->nullable();

            // The explicit permission subset. Empty is not "everything" — it
            // is a client that can do nothing, which is the safe reading of an
            // administrator who has not decided yet.
            $table->json('scopes_json');

            $table->string('status', 16)->default('ACTIVE'); // ACTIVE|REVOKED
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'api_clients_status_index');
        });

        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();

            // Whichever of the two made the call. Both are nullable because a
            // key belongs to a company first; who used it is context.
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('api_client_id')->nullable()->constrained('api_clients')->nullOnDelete();

            $table->string('key', 128);
            $table->string('endpoint', 255);

            // Method, path and body together. Same key with a different body is
            // a client bug and is refused rather than silently executed.
            $table->string('request_hash', 64);

            $table->string('status', 16); // IN_PROGRESS|COMPLETED|FAILED
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body_json')->nullable();

            $table->string('resource_type', 64)->nullable();
            $table->ulid('resource_id')->nullable();

            $table->timestamp('locked_at');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();

            // The claim itself. Two concurrent requests race to insert this
            // row and exactly one wins; the loser reads the winner's answer
            // rather than executing. A unique index is the whole mechanism.
            $table->unique(['company_id', 'key', 'endpoint'], 'idempotency_keys_claim_unique');
            $table->index('expires_at', 'idempotency_keys_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('api_clients');
    }
};

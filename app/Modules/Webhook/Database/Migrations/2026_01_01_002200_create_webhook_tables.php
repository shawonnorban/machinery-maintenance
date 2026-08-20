<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhooks: ERD Section 22, SRS 43, ADR-035.
 *
 * How this system tells somebody else's system that a machine stopped. The
 * integrations named in SRS 43 — ERP, production, accounting — are somebody
 * else's software on somebody else's schedule, so delivery is attempted,
 * recorded, and retried rather than assumed.
 *
 * Every delivery keeps what was sent, what came back and how long it took.
 * "We sent it" and "they received it" are different claims, and an integration
 * argument is only ever settled by the second one.
 *
 * The secret is stored so outgoing requests can be signed. It is shown to the
 * customer exactly once, when it is created or rotated: a secret that can be
 * read back from a screen is a secret that leaks through a support session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();

            $table->string('url', 2048);
            $table->string('description')->nullable();
            $table->string('secret', 128);
            $table->string('signing_algorithm', 24)->default('HMAC_SHA256');

            // Kept for a window after rotation so a receiver that has not
            // switched yet can still verify. Both signatures are sent.
            $table->string('previous_secret', 128)->nullable();
            $table->timestamp('secret_rotated_at')->nullable();

            $table->string('status', 16)->default('ACTIVE'); // ACTIVE|PAUSED|DISABLED
            // Consecutive, not total: an endpoint that fails once a week and
            // recovers is a flaky network, not a dead endpoint.
            $table->unsignedSmallInteger('consecutive_failure_count')->default(0);
            $table->timestamp('disabled_at')->nullable();
            $table->text('disabled_reason')->nullable();

            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'webhook_endpoints_status_index');
        });

        Schema::create('webhook_subscriptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('webhook_endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();

            $table->string('event_type', 64);
            $table->timestamps();

            $table->unique(['webhook_endpoint_id', 'event_type'], 'webhook_subscriptions_unique');
            $table->index(['company_id', 'event_type'], 'webhook_subscriptions_event_index');
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('webhook_endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();

            $table->string('event_type', 64);
            // Stable across every retry, so a receiver can deduplicate. Without
            // it, a timeout that actually arrived becomes a second order.
            $table->ulid('event_id');

            $table->json('payload_json')->nullable();
            $table->json('request_headers_json')->nullable();
            $table->string('signature', 256)->nullable();

            $table->string('status', 16)->default('PENDING'); // PENDING|DELIVERED|FAILED|EXHAUSTED
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            // An excerpt, not the body: a receiver that answers with a 2 MB
            // stack trace should not fill this table with it.
            $table->text('response_body_excerpt')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('created_at');

            $table->index(['company_id', 'status', 'next_retry_at'], 'webhook_deliveries_retry_index');
            // The same three columns without the company, because the retry
            // sweep has no tenant: it looks for everything that is due across
            // the whole platform, and the tenant-first index cannot serve a
            // query that does not name a company.
            $table->index(['status', 'next_retry_at'], 'webhook_deliveries_due_index');
            $table->index(['webhook_endpoint_id', 'created_at'], 'webhook_deliveries_endpoint_index');
            $table->index('event_id', 'webhook_deliveries_event_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_subscriptions');
        Schema::dropIfExists('webhook_endpoints');
    }
};

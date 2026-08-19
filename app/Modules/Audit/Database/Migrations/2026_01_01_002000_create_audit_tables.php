<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The audit log: ERD Section 18, SRS 34, SRS 49.1.
 *
 * Append-only, and the table is shaped so that staying append-only is cheap.
 * There is no updated_at and no deleted_at, because a row that can be corrected
 * is not evidence — the whole value of this table is that what it says today is
 * what it said the day it was written.
 *
 * The diff is stored beside the before and after snapshots. Reconstructing what
 * changed by comparing two twenty-column JSON blobs is work every reader would
 * otherwise repeat, and a wide update makes it slow enough that people stop
 * looking.
 *
 * company_id is nullable on purpose: a failed login carries an email that may
 * belong to no company, and a platform-level action belongs to no tenant. Those
 * rows must still be recorded rather than dropped for want of a foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('action', 64);
            $table->string('entity_type', 64)->nullable();
            $table->ulid('entity_id')->nullable();
            // Human-readable at the time of writing: an asset code or work order
            // number that stays meaningful even after the record is archived.
            $table->string('entity_label')->nullable();

            $table->json('old_values_json')->nullable();
            $table->json('new_values_json')->nullable();
            $table->json('changed_fields_json')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            // Correlates the request, the jobs it dispatched and every row they
            // wrote, so one support ticket resolves to the full causal chain
            // (ADR-061).
            $table->string('request_id', 64)->nullable();
            $table->string('context', 16)->default('UI'); // API|UI|JOB|CONSOLE|IMPORT|WEBHOOK

            $table->ulid('api_client_id')->nullable();
            // Platform support acting as a tenant user (SRS 5.4). Null for
            // ordinary work; set, it is the first thing an investigation asks.
            $table->foreignUlid('impersonated_by')->nullable()->constrained('users')->nullOnDelete();

            // No updated_at: nothing here is ever updated.
            $table->timestamp('created_at');

            $table->index(['company_id', 'created_at'], 'audit_logs_company_time_index');
            $table->index(['company_id', 'entity_type', 'entity_id'], 'audit_logs_entity_index');
            $table->index(['company_id', 'user_id', 'created_at'], 'audit_logs_actor_index');
            $table->index(['company_id', 'action'], 'audit_logs_action_index');
            $table->index('request_id', 'audit_logs_request_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

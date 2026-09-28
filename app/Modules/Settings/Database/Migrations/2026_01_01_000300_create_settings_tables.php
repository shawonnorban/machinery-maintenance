<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuration as tenant data: ERD Section 24, SRS 53, ADR-054.
 *
 * The SRS calls a dozen behaviours "configurable" without saying where the
 * values live. Environment variables cannot vary per tenant and scattered
 * columns cannot be reasoned about, so configuration is a first-class,
 * tenant-scoped, audited table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The catalog of settable keys. A key absent from here cannot be set,
        // which stops configuration becoming an untyped free-for-all.
        Schema::create('setting_definitions', function (Blueprint $table): void {
            $table->string('key', 128)->primary();
            $table->string('value_type', 16); // BOOL | INT | STRING | DECIMAL | ENUM | LIST
            $table->json('allowed_values')->nullable();
            $table->json('default_value');
            // Which levels may define this key: COMPANY, FACTORY, LINE
            $table->json('scope_levels');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // null company_id = platform default row
            $table->foreignUlid('company_id')->nullable()
                ->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->nullable()
                ->constrained('factories')->cascadeOnDelete();
            $table->foreignUlid('production_line_id')->nullable()
                ->constrained('production_lines')->cascadeOnDelete();
            $table->string('key', 128);
            $table->json('value');
            $table->string('value_type', 16);
            $table->foreignUlid('updated_by')->nullable();
            $table->timestamps();

            // One value per key per exact scope. MySQL treats NULLs as
            // distinct in a unique index, so the application must also check
            // for an existing row before inserting a broader-scope value.
            $table->unique(
                ['company_id', 'factory_id', 'production_line_id', 'key'],
                'settings_scope_key_unique',
            );
            $table->index(['company_id', 'key']);
        });

        // Race-safe document numbering: ERD Section 25, SRS 52, ADR-055.
        Schema::create('number_sequences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->nullable()
                ->constrained('factories')->cascadeOnDelete();
            $table->string('document_type', 32);
            $table->string('format', 128);
            // '2026', '2026-08', or 'ALL' when the sequence never resets
            $table->string('period_key', 16);
            $table->string('reset_policy', 16)->default('YEARLY');
            $table->unsignedBigInteger('current_value')->default(0);
            $table->unsignedTinyInteger('padding')->default(5);
            $table->timestamps();

            $table->unique(
                ['company_id', 'factory_id', 'document_type', 'period_key'],
                'number_sequences_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('setting_definitions');
    }
};

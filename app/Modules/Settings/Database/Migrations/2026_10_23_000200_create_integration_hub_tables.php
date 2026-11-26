<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings → Integration Hub (AnnotechERP 03/02): SMS gateways, bank
 * integrations, and the activity log every outside call writes to. As with
 * mail relays, a secret is never stored — only the name of the environment
 * variable that holds it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_gateways', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('provider', 16);
            $table->string('base_url')->nullable();
            $table->string('username')->nullable();
            $table->string('sender_id', 32)->nullable();
            $table->string('api_key_env', 64);
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_integrations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_name')->nullable();
            $table->string('account_no', 48);
            $table->string('routing_no', 32)->nullable();
            $table->string('swift_code', 16)->nullable();
            $table->string('channel', 8);
            $table->string('file_format', 12)->default('NONE');
            $table->string('endpoint')->nullable();
            $table->string('credential_env', 64)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 12)->nullable();
            $table->timestamps();
        });

        Schema::create('integration_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('channel', 12);
            $table->string('event', 32);
            $table->string('source_name')->nullable();
            $table->string('target')->nullable();
            $table->unsignedInteger('records')->default(1);
            $table->text('details')->nullable();
            $table->string('status', 12);
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['company_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('bank_integrations');
        Schema::dropIfExists('sms_gateways');
    }
};

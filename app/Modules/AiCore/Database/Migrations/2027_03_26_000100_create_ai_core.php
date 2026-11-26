<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * AI services (Settings → Integration Hub): the AI services a company has a
 * key for — Claude, OpenAI, Gemini — and which one each AI feature of the
 * ERP uses. Keys are stored encrypted and never sent back to a screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_services', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('provider', 12); // ANTHROPIC | OPENAI | GEMINI
            $table->string('model', 80);
            $table->text('api_key'); // encrypted
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_ok')->nullable();
            $table->string('last_test_message', 300)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'provider'], 'ai_services_provider_unique');
        });

        Schema::create('ai_features', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('feature', 24); // FILE_READING | ORDER_ANALYSIS
            $table->foreignUlid('service_id')->nullable()->constrained('ai_services')->nullOnDelete();
            $table->boolean('auto_run')->default(true); // FILE_READING: read a file as soon as it is uploaded
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'feature'], 'ai_features_feature_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_features');
        Schema::dropIfExists('ai_services');
    }
};

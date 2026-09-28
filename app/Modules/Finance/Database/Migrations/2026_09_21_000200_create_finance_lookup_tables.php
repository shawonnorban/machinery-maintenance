<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The accounts master data the procurement cycle needs before finance has
 * signed off a chart of accounts (Master Data spec §10–§12, Shared Systems
 * §5.1).
 *
 * Banks and payment types are ordinary shared lookups. GL accounts are
 * tenant-owned and self-referencing: the structure exists now so that GL
 * posting can be written against `gl_accounts.id`, and finance later fills
 * in the real codes as data rather than as a code change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // Null company_id = platform-seeded, visible to every tenant.
            $table->foreignUlid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'banks_code_unique');
        });

        Schema::create('payment_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'payment_types_code_unique');
        });

        Schema::create('gl_accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('account_category', 16);
            $table->ulid('parent_account_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'gl_accounts_code_unique');
            $table->index(['company_id', 'account_category']);
        });

        // Added after the table exists: Postgres wants the primary key in
        // place before a self-referencing constraint can point at it.
        Schema::table('gl_accounts', function (Blueprint $table): void {
            $table->foreign('parent_account_id')->references('id')->on('gl_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_accounts');
        Schema::dropIfExists('payment_types');
        Schema::dropIfExists('banks');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Access Control → Ledger Integration Setup (AnnotechERP 02/03, Brickland's
 * GL Configuration): a GL source names where an amount comes from (its Link
 * Ref, e.g. MACHINERY_PARTS) and its COA lines say which Chart of Account
 * ledgers it debits and credits. Machinery's costs reach Finance & Ledger
 * through these as draft vouchers (the user's decision of 2026-11-15).
 *
 * `acc_ledger_postings` remembers how much of each source record has been put
 * on a voucher, so a changed cost posts only its difference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acc_gl_sources', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('mapping_type', 24)->default('GENERAL'); // GENERAL | BANK_BILL_COLLECTION
            $table->string('link_ref', 64)->nullable();
            $table->boolean('final_bill_collection')->default(false);
            $table->boolean('editable_from_gl')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name'], 'acc_gl_sources_name_unique');
            $table->index(['company_id', 'link_ref']);
        });

        Schema::create('acc_gl_source_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('gl_source_id')->constrained('acc_gl_sources')->cascadeOnDelete();
            $table->string('side', 6); // DEBIT | CREDIT
            $table->foreignUlid('account_id')->constrained('acc_accounts')->restrictOnDelete();
            $table->foreignUlid('cost_center_id')->nullable()->constrained('acc_cost_centers')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('acc_ledger_postings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('source_type', 48);
            $table->ulid('source_id');
            $table->foreignUlid('gl_source_id')->constrained('acc_gl_sources')->restrictOnDelete();
            $table->foreignUlid('voucher_id')->constrained('acc_vouchers')->cascadeOnDelete();
            $table->decimal('amount', 18, 4);
            $table->timestamps();
            $table->index(['company_id', 'source_type', 'source_id'], 'acc_ledger_postings_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acc_ledger_postings');
        Schema::dropIfExists('acc_gl_source_lines');
        Schema::dropIfExists('acc_gl_sources');
    }
};

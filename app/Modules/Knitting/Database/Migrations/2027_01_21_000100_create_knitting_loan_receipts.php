<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knit Production → Loan Rcv from Party (AnnotechERP 15/09): needles or oil
 * borrowed in from a party, received on the store's own MRR (NLB- / OLB-…)
 * — Draft → Waiting for Verification → Verified (posted to the book) → Wait
 * for Accounts Approval → Approved, or Cancelled before it posts. What has
 * gone back is the party's loan-return indents, so a receipt's Returned
 * and Outstanding are read, never typed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knit_needle_loan_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            // NEEDLE, OIL.
            $table->string('book', 8);
            $table->string('mrr_no', 24);
            $table->date('mrr_date');
            $table->string('req_type', 48)->nullable();
            $table->foreignUlid('party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('store_id')->constrained('gs_stores')->restrictOnDelete();
            // ADJUSTMENT, CASH, CREDIT, LC.
            $table->string('pay_method', 12);
            $table->string('source', 80);
            $table->string('challan_no', 60)->nullable();
            $table->date('challan_date')->nullable();
            $table->string('lc_no', 60)->nullable();
            $table->text('remarks')->nullable();
            // DRAFT, WAITING, VERIFIED, WAIT_ACCOUNTS, APPROVED, CANCELLED.
            $table->string('status', 16)->default('DRAFT');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'mrr_no']);
            $table->index(['company_id', 'book', 'party_id']);
        });

        Schema::create('knit_needle_loan_receipt_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->constrained('knit_needle_loan_receipts')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('item_id')->constrained('gs_items')->restrictOnDelete();
            $table->string('uom', 16)->nullable();
            $table->decimal('receive_qty', 14, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knit_needle_loan_receipt_lines');
        Schema::dropIfExists('knit_needle_loan_receipts');
    }
};

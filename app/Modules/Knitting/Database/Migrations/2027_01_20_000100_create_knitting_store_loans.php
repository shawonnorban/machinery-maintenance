<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knit Production → Loan Issue to Party (AnnotechERP 15/08): the needle book
 * becomes the knitting store book with two ledgers — NEEDLE and OIL — on
 * the same General Store items and stores; a loan indent and a store month
 * belong to one of them. And `knit_needle_loan_returns` + lines (NLT- /
 * OLT-…): what a party hands back against a loan issued to it, on its own
 * challan — Draft → Received at store (posted, dated, taken off what the
 * party owes) → Finalized, or Cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knit_needle_moves', function (Blueprint $table): void {
            // NEEDLE, OIL.
            $table->string('book', 8)->default('NEEDLE')->after('company_id');
            $table->index(['company_id', 'book', 'store_id']);
        });
        Schema::table('knit_needle_closings', function (Blueprint $table): void {
            $table->string('book', 8)->default('NEEDLE')->after('company_id');
            $table->dropUnique(['company_id', 'store_id', 'period']);
            $table->unique(['company_id', 'book', 'store_id', 'period']);
        });
        Schema::table('knit_needle_loan_reqs', function (Blueprint $table): void {
            $table->string('book', 8)->default('NEEDLE')->after('company_id');
        });

        Schema::create('knit_needle_loan_returns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('book', 8);
            $table->string('return_no', 24);
            // The party's own challan number and date.
            $table->string('ret_challan_no', 60);
            $table->date('ret_challan_date');
            $table->foreignUlid('req_id')->nullable()->constrained('knit_needle_loan_reqs')->nullOnDelete();
            $table->foreignUlid('party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('store_id')->constrained('gs_stores')->restrictOnDelete();
            $table->foreignUlid('received_by')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->string('gate_pass_no', 40)->nullable();
            $table->string('vehicle_no', 40)->nullable();
            $table->text('remarks')->nullable();
            // DRAFT, RECEIVED, FINALIZED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->date('rcv_date')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'return_no']);
            $table->unique(['company_id', 'book', 'party_id', 'ret_challan_no']);
        });

        Schema::create('knit_needle_loan_return_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('return_id')->constrained('knit_needle_loan_returns')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            // The loan indent line it pays back, when the challan names an indent.
            $table->foreignUlid('req_line_id')->nullable()->constrained('knit_needle_loan_req_lines')->nullOnDelete();
            $table->foreignUlid('item_id')->constrained('gs_items')->restrictOnDelete();
            $table->string('uom', 16)->nullable();
            $table->decimal('return_qty', 14, 4);
            $table->decimal('received_qty', 14, 4)->default(0);
            $table->decimal('rate', 14, 4)->default(0);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knit_needle_loan_return_lines');
        Schema::dropIfExists('knit_needle_loan_returns');
        Schema::table('knit_needle_loan_reqs', fn (Blueprint $t) => $t->dropColumn('book'));
        Schema::table('knit_needle_closings', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'book', 'store_id', 'period']);
            $table->unique(['company_id', 'store_id', 'period']);
            $table->dropColumn('book');
        });
        Schema::table('knit_needle_moves', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'book', 'store_id']);
            $table->dropColumn('book');
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knit Production → Oil Store (AnnotechERP 15/10): the knitting store's OIL
 * book gets its own paper, posted to the same ledger (`knit_needle_moves`,
 * book OIL):
 *
 * - `knit_oil_reqs` + lines (ORQ-…): Knit Machine Oil Indent, one store
 *   asking another — Draft → Submit → Verify → Issue Qty (released from the
 *   store asked, into the store asking) → Partly Issued / Issued, Closed,
 *   Re-open, Cancel.
 * - `knit_oil_openings`: Machine Oil Opening Balance, one line per store,
 *   item and date, priced in its currency; Finalize posts it.
 * - `knit_oil_consumptions` + lines (OCS-…): Daily Machine Oil Consumption,
 *   one sheet per store, date and shift; Finalize takes it out of the store.
 * - `knit_oil_receipts` + lines (OMR-…): Machine Oil Receipt — Draft → QC →
 *   Verification → Accounts → Approved → Complete, desk by desk; Finalize
 *   posts it, Accounting Done marks it.
 * - `knit_oil_returns` + lines: Oil Receipt Return Register, against a
 *   finalized receipt — Issue posts it, Finalize, Re-open, Cancel.
 * - `knit_needle_loan_openings.book`: Oil Loan Opening Balance shares the
 *   needle screen's table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knit_needle_loan_openings', function (Blueprint $table): void {
            $table->string('book', 8)->default('NEEDLE')->after('company_id');
            $table->dropUnique(['company_id', 'party_id', 'item_id']);
            $table->unique(['company_id', 'book', 'party_id', 'item_id']);
        });

        Schema::create('knit_oil_reqs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('req_no', 24);
            $table->date('req_date');
            $table->string('req_type', 48)->nullable();
            $table->foreignUlid('office_id')->nullable()->constrained('factories')->nullOnDelete();
            $table->foreignUlid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            // The store raising the request, and the one asked to release the oil.
            $table->foreignUlid('from_store_id')->constrained('gs_stores')->restrictOnDelete();
            $table->foreignUlid('to_store_id')->constrained('gs_stores')->restrictOnDelete();
            $table->string('reason', 300)->nullable();
            $table->text('remarks')->nullable();
            // DRAFT, WAITING, VERIFIED, PARTLY_ISSUED, ISSUED, CLOSED, CANCELLED.
            $table->string('status', 16)->default('DRAFT');
            $table->timestamp('verified_at')->nullable();
            $table->foreignUlid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'req_no']);
        });

        Schema::create('knit_oil_req_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('req_id')->constrained('knit_oil_reqs')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('item_id')->constrained('gs_items')->restrictOnDelete();
            $table->string('uom', 16)->nullable();
            $table->decimal('required_qty', 14, 4);
            $table->decimal('issue_qty', 14, 4)->default(0);
            $table->decimal('rate', 14, 4)->default(0);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_oil_openings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained('gs_stores')->restrictOnDelete();
            $table->date('ob_date');
            $table->foreignUlid('item_id')->constrained('gs_items')->restrictOnDelete();
            $table->string('uom', 16)->nullable();
            $table->decimal('qty', 14, 4);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->foreignUlid('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->decimal('conv_rate', 14, 4)->default(1);
            // Struck on save: Unit Price × Conv. Rate.
            $table->decimal('cost_price', 14, 4)->default(0);
            $table->string('remarks', 500)->nullable();
            $table->boolean('finalized')->default(false);
            $table->timestamp('finalized_at')->nullable();
            $table->foreignUlid('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'store_id', 'ob_date', 'item_id']);
        });

        Schema::create('knit_oil_consumptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('cons_no', 24);
            $table->date('cons_date');
            $table->foreignUlid('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->foreignUlid('office_id')->nullable()->constrained('factories')->nullOnDelete();
            $table->foreignUlid('store_id')->constrained('gs_stores')->restrictOnDelete();
            $table->foreignUlid('issued_by')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->text('remarks')->nullable();
            // DRAFT (open), FINALIZED.
            $table->string('status', 12)->default('DRAFT');
            $table->timestamp('finalized_at')->nullable();
            $table->foreignUlid('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'cons_no']);
            $table->unique(['company_id', 'store_id', 'cons_date', 'shift_id']);
        });

        Schema::create('knit_oil_consumption_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('consumption_id')->constrained('knit_oil_consumptions')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('machine_id')->constrained('knit_machines')->restrictOnDelete();
            $table->foreignUlid('item_id')->constrained('gs_items')->restrictOnDelete();
            $table->string('uom', 16)->nullable();
            $table->decimal('qty', 14, 4);
            $table->decimal('running_hrs', 8, 2)->default(0);
            $table->decimal('cost_price', 14, 4)->default(0);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_oil_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('mrr_no', 24);
            $table->date('mrr_date');
            $table->foreignUlid('store_id')->constrained('gs_stores')->restrictOnDelete();
            $table->string('req_type', 48)->nullable();
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->string('challan_no', 60)->nullable();
            $table->date('challan_date')->nullable();
            $table->string('pay_method', 12);
            $table->string('source', 80);
            $table->string('lc_no', 60)->nullable();
            $table->text('remarks')->nullable();
            // DRAFT, WAIT_QC, WAITING, WAIT_ACCOUNTS, APPROVED, COMPLETE, CANCELLED.
            $table->string('status', 16)->default('DRAFT');
            $table->timestamp('finalized_at')->nullable();
            $table->foreignUlid('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accounted_at')->nullable();
            $table->foreignUlid('accounted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'mrr_no']);
        });

        Schema::create('knit_oil_receipt_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->constrained('knit_oil_receipts')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('item_id')->constrained('gs_items')->restrictOnDelete();
            $table->string('uom', 16)->nullable();
            $table->decimal('challan_qty', 14, 4)->default(0);
            $table->decimal('received_qty', 14, 4);
            // The part of RCV Qty accepted pending a test result.
            $table->decimal('cond_qty', 14, 4)->default(0);
            $table->decimal('rate', 14, 4)->default(0);
            $table->decimal('amount', 16, 4)->default(0);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_oil_returns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 32);
            $table->foreignUlid('receipt_id')->constrained('knit_oil_receipts')->restrictOnDelete();
            $table->date('challan_date');
            // RETURNABLE, NON_RETURNABLE.
            $table->string('challan_type', 16);
            $table->foreignUlid('store_id')->constrained('gs_stores')->restrictOnDelete();
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->string('gate_pass_no', 40)->nullable();
            $table->string('vehicle_no', 40)->nullable();
            $table->text('reason')->nullable();
            // DRAFT, ISSUED, FINALIZED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'challan_no']);
        });

        Schema::create('knit_oil_return_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('return_id')->constrained('knit_oil_returns')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('receipt_line_id')->constrained('knit_oil_receipt_lines')->restrictOnDelete();
            $table->foreignUlid('item_id')->constrained('gs_items')->restrictOnDelete();
            $table->string('uom', 16)->nullable();
            $table->decimal('qty', 14, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->decimal('amount', 16, 4)->default(0);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['knit_oil_return_lines', 'knit_oil_returns', 'knit_oil_receipt_lines', 'knit_oil_receipts', 'knit_oil_consumption_lines',
            'knit_oil_consumptions', 'knit_oil_openings', 'knit_oil_req_lines', 'knit_oil_reqs'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('knit_needle_loan_openings', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'book', 'party_id', 'item_id']);
            $table->unique(['company_id', 'party_id', 'item_id']);
            $table->dropColumn('book');
        });
    }
};

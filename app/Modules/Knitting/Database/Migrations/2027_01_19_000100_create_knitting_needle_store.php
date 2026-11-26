<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knit Production → Needle Store (AnnotechERP 15/07), the knitting floor's
 * needle and sinker book, its items and stores the General Store's own:
 *
 * - `knit_needle_moves`: the ledger — a store and item's balance is the sum
 *   of its moves (opening, receipt, return, issue out / in, loan out); a
 *   struck month (`knit_needle_closings`) takes no more.
 * - `knit_needle_openings`: Needle Opening Balance, one count per store,
 *   date and item; finalised sheets post and lock.
 * - `knit_needle_loan_openings`: Needle Loan Opening Balance, one net
 *   position per party and item, both directions.
 * - `knit_needle_loan_reqs` + lines (NLR-…): Needle Loan Indent — Draft →
 *   Waiting for Verification → Verified, then Partly Issued / Issued as the
 *   store writes Issue Qty; Closed / Cancelled.
 * - `knit_needle_receipts` + lines (NMR-…): Machine Needle Receipt — Draft →
 *   Waiting for Verification → Verified (posted) → Wait for Accounts
 *   Approval → Approved; Cancelled before it posts.
 * - `knit_needle_returns` + lines (…-R01): Needle Receipt Return — against a
 *   verified receipt; Draft → Issued at gate (posted) → Finalized; Cancelled.
 * - `knit_needle_assignments`: Machine-Wise Needle Assignment, a machine's
 *   standard fit per category.
 * - `knit_needle_brokens` + lines (NBR-…): Machine-Wise Broken Needle —
 *   Draft → Confirmed; Cancelled.
 * - `knit_needle_reqs` + lines (NRQ-…): Machine-Wise Needle Indent, against
 *   a confirmed breakage record; the same standing as a loan indent.
 * - `knit_needle_issues` + lines (NIS-…): Machine-Wise Needle Issue, always
 *   answering a verified indent; posted on save, Withdrawn takes it back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knit_needle_moves', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained('gs_stores')->restrictOnDelete();
            $table->foreignUlid('item_id')->constrained('gs_items')->restrictOnDelete();
            $table->date('move_date');
            // OPENING, RECEIPT, RETURN, ISSUE_OUT, ISSUE_IN, LOAN_OUT.
            $table->string('kind', 12);
            $table->string('doc_no', 40);
            $table->ulid('doc_line_id')->nullable();
            $table->decimal('qty', 14, 4);
            $table->decimal('value', 16, 4)->default(0);
            $table->timestamps();
            $table->index(['company_id', 'store_id', 'item_id', 'move_date']);
            $table->index(['company_id', 'kind', 'doc_no']);
        });

        Schema::create('knit_needle_closings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained('gs_stores')->cascadeOnDelete();
            // YYYY-MM.
            $table->string('period', 7);
            $table->unsignedInteger('lines_struck')->default(0);
            $table->foreignUlid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at');
            $table->timestamps();
            $table->unique(['company_id', 'store_id', 'period']);
        });

        Schema::create('knit_needle_openings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained('gs_stores')->restrictOnDelete();
            $table->date('ob_date');
            $table->foreignUlid('item_id')->constrained('gs_items')->restrictOnDelete();
            $table->decimal('qty', 14, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->boolean('finalized')->default(false);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'store_id', 'ob_date', 'item_id']);
        });

        Schema::create('knit_needle_loan_openings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('ob_date');
            $table->foreignUlid('party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('item_id')->constrained('gs_items')->restrictOnDelete();
            $table->string('uom', 16)->nullable();
            $table->decimal('loan_received', 14, 4)->default(0);
            $table->decimal('loan_issued', 14, 4)->default(0);
            $table->foreignUlid('store_id')->nullable()->constrained('gs_stores')->nullOnDelete();
            $table->decimal('cost_price', 14, 4)->default(0);
            $table->boolean('is_final')->default(false);
            $table->string('remarks', 500)->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'party_id', 'item_id']);
        });

        Schema::create('knit_needle_loan_reqs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('req_no', 24);
            $table->date('req_date');
            // LOAN_ISSUE, LOAN_RETURN.
            $table->string('kind', 12);
            $table->string('req_type', 48)->nullable();
            $table->foreignUlid('office_id')->nullable()->constrained('factories')->nullOnDelete();
            $table->foreignUlid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignUlid('party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('gs_stores')->nullOnDelete();
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

        Schema::create('knit_needle_loan_req_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('req_id')->constrained('knit_needle_loan_reqs')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('item_id')->constrained('gs_items')->restrictOnDelete();
            $table->string('uom', 16)->nullable();
            $table->decimal('required_qty', 14, 4);
            $table->decimal('issue_qty', 14, 4)->default(0);
            $table->decimal('rate', 14, 4)->default(0);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_needle_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('mrr_no', 24);
            $table->date('mrr_date');
            $table->foreignUlid('store_id')->constrained('gs_stores')->restrictOnDelete();
            $table->string('req_type', 48)->nullable();
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->string('challan_no', 60)->nullable();
            $table->date('challan_date')->nullable();
            $table->string('source', 80)->nullable();
            // ADJUSTMENT, CASH, CREDIT, LC.
            $table->string('pay_method', 12)->nullable();
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
        });

        Schema::create('knit_needle_receipt_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->constrained('knit_needle_receipts')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('item_id')->constrained('gs_items')->restrictOnDelete();
            $table->string('uom', 16)->nullable();
            $table->decimal('challan_qty', 14, 4)->default(0);
            $table->decimal('received_qty', 14, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->decimal('amount', 16, 4)->default(0);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_needle_returns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 32);
            $table->foreignUlid('receipt_id')->constrained('knit_needle_receipts')->restrictOnDelete();
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

        Schema::create('knit_needle_return_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('return_id')->constrained('knit_needle_returns')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('receipt_line_id')->constrained('knit_needle_receipt_lines')->restrictOnDelete();
            $table->foreignUlid('item_id')->constrained('gs_items')->restrictOnDelete();
            $table->string('uom', 16)->nullable();
            $table->decimal('qty', 14, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->decimal('amount', 16, 4)->default(0);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_needle_assignments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('office_id')->constrained('factories')->cascadeOnDelete();
            $table->foreignUlid('category_id')->constrained('gs_categories')->restrictOnDelete();
            $table->foreignUlid('machine_id')->constrained('knit_machines')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('gs_items')->restrictOnDelete();
            $table->decimal('qty', 14, 4);
            $table->boolean('finalized')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'machine_id', 'category_id']);
        });

        Schema::create('knit_needle_brokens', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('ref_no', 24);
            $table->date('broken_date');
            $table->foreignUlid('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->foreignUlid('office_id')->nullable()->constrained('factories')->nullOnDelete();
            $table->foreignUlid('machine_type_id')->nullable()->constrained('knit_ref_entries')->nullOnDelete();
            $table->text('remarks')->nullable();
            // DRAFT, CONFIRMED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'ref_no']);
        });

        Schema::create('knit_needle_broken_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('broken_id')->constrained('knit_needle_brokens')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('machine_id')->constrained('knit_machines')->restrictOnDelete();
            $table->foreignUlid('item_id')->constrained('gs_items')->restrictOnDelete();
            $table->string('uom', 16)->nullable();
            $table->decimal('qty', 14, 4);
            $table->decimal('cost_price', 14, 4)->default(0);
            $table->decimal('amount', 16, 4)->default(0);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_needle_reqs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('req_no', 24);
            $table->date('req_date');
            // NEEDLE_BROKEN, SERVICING, SET_CHANGE, NEW_SET, DAMAGED, OTHERS.
            $table->string('reason', 16);
            $table->foreignUlid('broken_id')->nullable()->constrained('knit_needle_brokens')->nullOnDelete();
            $table->foreignUlid('office_id')->nullable()->constrained('factories')->nullOnDelete();
            // The store raising the request, and the store asked to release.
            $table->foreignUlid('from_store_id')->constrained('gs_stores')->restrictOnDelete();
            $table->foreignUlid('to_store_id')->constrained('gs_stores')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->string('status', 16)->default('DRAFT');
            $table->timestamp('verified_at')->nullable();
            $table->foreignUlid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'req_no']);
        });

        Schema::create('knit_needle_req_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('req_id')->constrained('knit_needle_reqs')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('machine_id')->nullable()->constrained('knit_machines')->nullOnDelete();
            $table->foreignUlid('item_id')->constrained('gs_items')->restrictOnDelete();
            $table->string('uom', 16)->nullable();
            $table->decimal('req_qty', 14, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_needle_issues', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('issue_no', 24);
            $table->date('issue_date');
            $table->foreignUlid('req_id')->constrained('knit_needle_reqs')->restrictOnDelete();
            $table->foreignUlid('from_store_id')->constrained('gs_stores')->restrictOnDelete();
            $table->foreignUlid('to_store_id')->constrained('gs_stores')->restrictOnDelete();
            $table->text('remarks')->nullable();
            // ISSUED, WITHDRAWN.
            $table->string('status', 12)->default('ISSUED');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'issue_no']);
        });

        Schema::create('knit_needle_issue_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('issue_id')->constrained('knit_needle_issues')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('req_line_id')->constrained('knit_needle_req_lines')->restrictOnDelete();
            $table->decimal('qty', 14, 4);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['knit_needle_issue_lines', 'knit_needle_issues', 'knit_needle_req_lines', 'knit_needle_reqs', 'knit_needle_broken_lines', 'knit_needle_brokens',
            'knit_needle_assignments', 'knit_needle_return_lines', 'knit_needle_returns', 'knit_needle_receipt_lines', 'knit_needle_receipts',
            'knit_needle_loan_req_lines', 'knit_needle_loan_reqs', 'knit_needle_loan_openings', 'knit_needle_openings', 'knit_needle_closings', 'knit_needle_moves'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

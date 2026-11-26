<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Assembly Floor → Accessories Store (AnnotechERP 20/08): the floor's trims
 * and accessories book, as Brickland keeps it.
 *
 * - `asm_trims_receipts` + lines: Trims & Accessories Receipt (MRR-…) — a
 *   store, the supplier, requisition type, payment method, PR and PO, and
 *   the items received; stock in once Completed.
 * - `asm_trims_issues` + lines: Trims & Accessories Issue (ISS-…) — a store,
 *   the requisition, the order / style and sewing line it goes to, and the
 *   items; submitted, then approved (stock out) or rejected.
 * - `asm_trims_returns` + lines: Trims Receipt Return (RTN-…) — short,
 *   damaged or over-supplied items going back against a completed receipt;
 *   stock out once Completed.
 * - `asm_trims_moves`: every stock movement, signed (+ in, − out) per store
 *   and item; a store's stock is its moves added up.
 */
return new class extends Migration
{
    public function up(): void
    {
        $lines = function (Blueprint $table, string $parent, string $parentTable): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid($parent)->constrained($parentTable)->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('mp_items')->restrictOnDelete();
            $table->string('description', 255)->nullable();
            $table->string('uom', 16)->nullable();
            $table->decimal('qty', 16, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->string('remarks', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        };

        Schema::create('asm_trims_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('mrr_no', 24);
            $table->date('mrr_date');
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('supplier_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->string('req_type', 80)->nullable();
            $table->string('pay_method', 12); // CASH | LC | CHEQUE | TT | CONSIGNMENT
            $table->string('pr_no', 40)->nullable();
            $table->string('po_no', 40)->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 10)->default('DRAFT'); // DRAFT | COMPLETED | CANCELLED
            $table->timestamp('completed_at')->nullable();
            $table->foreignUlid('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'mrr_no']);
            $table->index(['company_id', 'mrr_date']);
        });
        Schema::create('asm_trims_receipt_lines', fn (Blueprint $table) => $lines($table, 'receipt_id', 'asm_trims_receipts'));

        Schema::create('asm_trims_issues', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('issue_no', 24);
            $table->date('issue_date');
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('requisition_no', 40)->nullable();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->foreignUlid('line_id')->nullable()->constrained('planning_lines')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->string('status', 10)->default('DRAFT'); // DRAFT | SUBMITTED | APPROVED | REJECTED
            $table->timestamp('submitted_at')->nullable();
            $table->foreignUlid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reject_reason', 300)->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'issue_no']);
            $table->index(['company_id', 'issue_date']);
        });
        Schema::create('asm_trims_issue_lines', fn (Blueprint $table) => $lines($table, 'issue_id', 'asm_trims_issues'));

        Schema::create('asm_trims_returns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('return_no', 24);
            $table->date('return_date');
            $table->foreignUlid('receipt_id')->constrained('asm_trims_receipts')->restrictOnDelete();
            $table->string('reason', 12); // SHORT | DAMAGED | EXCESS
            $table->text('remarks')->nullable();
            $table->string('status', 10)->default('DRAFT'); // DRAFT | COMPLETED | CANCELLED
            $table->timestamp('completed_at')->nullable();
            $table->foreignUlid('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'return_no']);
            $table->index(['company_id', 'return_date']);
        });
        Schema::create('asm_trims_return_lines', function (Blueprint $table) use ($lines): void {
            $lines($table, 'return_id', 'asm_trims_returns');
            $table->foreignUlid('receipt_line_id')->constrained('asm_trims_receipt_lines')->restrictOnDelete();
        });

        Schema::create('asm_trims_moves', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('item_id')->constrained('mp_items')->restrictOnDelete();
            $table->date('move_date');
            $table->string('kind', 8); // RECEIPT | ISSUE | RETURN
            $table->string('doc_no', 24);
            $table->ulid('doc_line_id')->nullable();
            $table->decimal('qty', 16, 4); // + in, − out
            $table->decimal('value', 18, 4)->default(0);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'store_id', 'item_id']);
            $table->index(['company_id', 'kind', 'doc_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asm_trims_moves');
        Schema::dropIfExists('asm_trims_return_lines');
        Schema::dropIfExists('asm_trims_returns');
        Schema::dropIfExists('asm_trims_issue_lines');
        Schema::dropIfExists('asm_trims_issues');
        Schema::dropIfExists('asm_trims_receipt_lines');
        Schema::dropIfExists('asm_trims_receipts');
    }
};

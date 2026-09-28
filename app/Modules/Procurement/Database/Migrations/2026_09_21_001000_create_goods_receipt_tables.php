<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goods receipt note, header and lines (Procurement spec §9).
 *
 * Schema only in Phase 0: the receiving workflow (complete → post to the
 * ledger → send fabric to inspection) arrives with purchase orders in
 * Phase 1. `po_id`/`po_line_id` are bare ulids for the same reason — the
 * purchase order tables do not exist yet — and a direct receipt without a
 * PO is a supported case regardless.
 *
 * Stock is not posted until the receipt is COMPLETED; before that the lines
 * are what arrived at the gate, not what is on the shelf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->ulid('po_id')->nullable();
            $table->string('grn_no', 32);
            $table->date('grn_date');
            $table->string('gate_pass_no', 64)->nullable();
            $table->string('grn_type', 24)->default('LOCAL');
            $table->string('status', 24)->default('DRAFT');
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'grn_no'], 'goods_receipts_grn_no_unique');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'vendor_id']);
            $table->index(['company_id', 'po_id']);
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->ulid('po_line_id')->nullable();
            $table->decimal('received_qty', 18, 4);
            $table->foreignUlid('uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->string('lot_no', 64)->nullable();
            // The rate at the moment of receipt, kept here so a later price
            // change on the item never rewrites what this delivery cost.
            $table->decimal('rate', 18, 4)->nullable();
            $table->char('currency', 3)->default('BDT');
            $table->string('inspection_status', 16)->default('PENDING');
            $table->timestamps();

            $table->index(['company_id', 'goods_receipt_id']);
            $table->index(['company_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Trims & Accessories (AnnotechERP 06/06): the procurement
 * cost heads (carriage, C&F, LC charges …, each landed into stock value or not
 * and inside the VAT base or not) and the accessories receipt (MRR) — what a
 * supplier delivered to store against a purchase order, its other costs, and
 * the bill: discount, VAT, deductions at source and net payable. A finalised
 * receipt is locked and its goods are on stock at landed cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_proc_cost_heads', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 24);
            $table->string('name', 120);
            $table->boolean('landed')->default(true);
            $table->boolean('vat_applicable')->default(false);
            $table->ulid('expense_ledger_id')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('merch_acc_mrrs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('mrr_no', 32);
            $table->string('mrr_type', 16)->default('PO');
            $table->ulid('unit_company_id');
            $table->ulid('supplier_id');
            $table->ulid('store_id');
            $table->date('mrr_date');
            $table->string('payment_method', 16);
            $table->string('source_type', 8);
            $table->string('challan_no', 60);
            $table->date('challan_date');
            $table->string('currency', 3);
            $table->decimal('conv_rate', 12, 4)->default(1);
            $table->ulid('po_id')->nullable();
            $table->ulid('requisition_id')->nullable();
            $table->string('requisition_type', 80)->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('discount_pct', 7, 3)->default(0);
            $table->decimal('discount_amount', 16, 2)->default(0);
            $table->decimal('vat_pct', 7, 3)->default(0);
            $table->decimal('tds_pct', 7, 3)->default(0);
            $table->decimal('vds_pct', 7, 3)->default(0);
            $table->decimal('goods_value', 16, 2)->default(0);
            $table->decimal('other_cost', 16, 2)->default(0);
            $table->decimal('landed_cost', 16, 2)->default(0);
            $table->decimal('vat_base', 16, 2)->default(0);
            $table->decimal('vat', 16, 2)->default(0);
            $table->decimal('tds', 16, 2)->default(0);
            $table->decimal('vds', 16, 2)->default(0);
            $table->decimal('net_payable', 16, 2)->default(0);
            $table->string('voucher_no', 32)->nullable();
            $table->string('status', 12)->default('DRAFT');
            $table->timestamp('finalized_at')->nullable();
            $table->ulid('finalized_by')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'mrr_no']);
            $table->index(['company_id', 'po_id']);
        });

        Schema::create('merch_acc_mrr_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('mrr_id')->constrained('merch_acc_mrrs')->cascadeOnDelete();
            $table->ulid('item_id');
            $table->ulid('po_line_id')->nullable();
            $table->string('description', 200)->nullable();
            $table->string('batch', 60)->nullable();
            $table->string('uom', 24)->nullable();
            $table->decimal('qty', 14, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->decimal('amount', 16, 2)->default(0);
            $table->decimal('landed_amount', 16, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'item_id']);
            $table->index(['company_id', 'po_line_id']);
        });

        Schema::create('merch_acc_mrr_costs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('mrr_id')->constrained('merch_acc_mrrs')->cascadeOnDelete();
            $table->ulid('cost_head_id');
            $table->boolean('landed')->default(true);
            $table->boolean('vat_applicable')->default(false);
            $table->decimal('amount', 16, 2);
            $table->string('remarks', 200)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_acc_mrr_costs');
        Schema::dropIfExists('merch_acc_mrr_lines');
        Schema::dropIfExists('merch_acc_mrrs');
        Schema::dropIfExists('merch_proc_cost_heads');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Trims & Accessories (AnnotechERP 06/06): the purchased
 * fabric receipt (MRR) — what arrived against a fabric purchase order, batch
 * by batch: received, rejected and accepted; the other costs the delivery
 * carried and the bill. Finalised, it is locked, its accepted fabric goes on
 * the purchased-fabric stock ledger at landed cost and its order moves to
 * Partial or Received.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_pfab_mrrs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('mrr_no', 32);
            $table->ulid('unit_company_id');
            $table->ulid('supplier_id');
            $table->ulid('store_id');
            $table->ulid('po_id')->nullable();
            $table->date('mrr_date');
            $table->string('payment_method', 16);
            $table->string('source_type', 8);
            $table->string('challan_no', 60);
            $table->date('challan_date');
            $table->string('currency', 3);
            $table->decimal('conv_rate', 12, 4)->default(1);
            $table->string('lc_no', 60)->nullable();
            $table->date('lc_date')->nullable();
            $table->string('bill_entry_no', 60)->nullable();
            $table->date('bill_entry_date')->nullable();
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
            $table->decimal('net_amount', 16, 2)->default(0);
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

        Schema::create('merch_pfab_mrr_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('mrr_id')->constrained('merch_pfab_mrrs')->cascadeOnDelete();
            $table->ulid('po_line_id');
            $table->ulid('fabric_type_id')->nullable();
            $table->string('description', 200)->nullable();
            $table->ulid('composition_id')->nullable();
            $table->ulid('colour_id')->nullable();
            $table->string('gsm', 40)->nullable();
            $table->string('dia', 40)->nullable();
            $table->string('batch_no', 60)->nullable();
            $table->unsignedInteger('rolls')->nullable();
            $table->string('uom', 8);
            $table->decimal('receive_qty', 14, 4);
            $table->decimal('reject_qty', 14, 4)->default(0);
            $table->decimal('accept_qty', 14, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->decimal('amount', 16, 2)->default(0);
            $table->decimal('landed_amount', 16, 2)->default(0);
            $table->string('reject_reason', 200)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'po_line_id']);
        });

        Schema::create('merch_pfab_mrr_costs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('mrr_id')->constrained('merch_pfab_mrrs')->cascadeOnDelete();
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
        Schema::dropIfExists('merch_pfab_mrr_costs');
        Schema::dropIfExists('merch_pfab_mrr_lines');
        Schema::dropIfExists('merch_pfab_mrrs');
    }
};

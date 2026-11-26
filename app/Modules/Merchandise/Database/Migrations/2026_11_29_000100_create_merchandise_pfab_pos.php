<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Trims & Accessories (AnnotechERP 06/06): the purchased
 * fabric purchase order — placed against approved fabric indents, each line
 * ordering an indent line, with the other costs agreed on the order and its
 * amount: PO value less discount, plus other cost and VAT, less TDS and VDS,
 * plus or minus an adjustment. Revised on each change until received against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_pfab_pos', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('po_no', 32);
            $table->date('po_date');
            $table->ulid('supplier_id');
            $table->string('source', 8)->default('LOCAL');
            $table->ulid('indent_id')->nullable();
            $table->ulid('beneficiary_company_id')->nullable();
            $table->ulid('billed_to_company_id')->nullable();
            $table->ulid('delivery_store_id')->nullable();
            $table->boolean('is_online')->default(false);
            $table->boolean('same_as_company')->default(false);
            $table->string('po_group', 60)->nullable();
            $table->string('inco_term', 8)->nullable();
            $table->string('place_of_delivery', 160)->nullable();
            $table->string('payment_method', 16);
            $table->string('currency', 3);
            $table->decimal('conv_rate', 12, 4)->default(1);
            $table->string('payment_term', 16);
            $table->string('mode_of_shipment', 12);
            $table->date('delivery_date');
            $table->string('cash_supplier', 120)->nullable();
            $table->text('other_term')->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('discount_pct', 7, 3)->default(0);
            $table->decimal('discount_amount', 16, 2)->default(0);
            $table->decimal('vat_pct', 7, 3)->default(0);
            $table->decimal('tds_pct', 7, 3)->default(0);
            $table->decimal('vds_pct', 7, 3)->default(0);
            $table->decimal('adjustment', 16, 2)->default(0);
            $table->string('adjustment_note', 200)->nullable();
            $table->decimal('po_value', 16, 2)->default(0);
            $table->decimal('other_cost', 16, 2)->default(0);
            $table->decimal('vat', 16, 2)->default(0);
            $table->decimal('tds', 16, 2)->default(0);
            $table->decimal('vds', 16, 2)->default(0);
            $table->decimal('net_amount', 16, 2)->default(0);
            $table->unsignedSmallInteger('revision')->default(0);
            $table->string('receive_status', 12)->default('PENDING');
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'po_no']);
            $table->index(['company_id', 'supplier_id']);
        });

        Schema::create('merch_pfab_po_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('po_id')->constrained('merch_pfab_pos')->cascadeOnDelete();
            $table->ulid('indent_id');
            $table->ulid('indent_line_id');
            $table->ulid('fabric_type_id')->nullable();
            $table->string('description', 200)->nullable();
            $table->string('construction', 120)->nullable();
            $table->ulid('composition_id')->nullable();
            $table->ulid('colour_id')->nullable();
            $table->string('gsm', 40)->nullable();
            $table->string('dia', 40)->nullable();
            $table->string('uom', 8);
            $table->decimal('qty', 14, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->decimal('amount', 16, 2)->default(0);
            $table->string('remarks', 200)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'indent_line_id']);
        });

        Schema::create('merch_pfab_po_costs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('po_id')->constrained('merch_pfab_pos')->cascadeOnDelete();
            $table->ulid('cost_head_id');
            $table->boolean('vat_applicable')->default(false);
            $table->decimal('amount', 16, 2);
            $table->string('remarks', 200)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_pfab_po_costs');
        Schema::dropIfExists('merch_pfab_po_lines');
        Schema::dropIfExists('merch_pfab_pos');
    }
};

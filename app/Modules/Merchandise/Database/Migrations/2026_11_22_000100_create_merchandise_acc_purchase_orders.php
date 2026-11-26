<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Trims & Accessories (AnnotechERP 06/06): the accessories
 * purchase order — a supplier's order against approved requisitions, with its
 * master data, terms & condition and priced lines; revised on each change
 * until it is received against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_acc_pos', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('po_no', 32);
            $table->date('po_date');
            $table->ulid('supplier_id');
            $table->string('source', 8)->default('LOCAL');
            $table->ulid('requisition_id')->nullable();
            $table->ulid('beneficiary_company_id')->nullable();
            $table->ulid('billed_to_company_id')->nullable();
            $table->ulid('delivery_store_id')->nullable();
            $table->boolean('is_online')->default(false);
            $table->boolean('same_as_company')->default(false);
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
            $table->unsignedSmallInteger('revision')->default(0);
            $table->string('receive_status', 12)->default('PENDING');
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'po_no']);
            $table->index(['company_id', 'supplier_id']);
        });

        Schema::create('merch_acc_po_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('po_id')->constrained('merch_acc_pos')->cascadeOnDelete();
            $table->ulid('item_id');
            $table->ulid('requisition_id')->nullable();
            $table->ulid('requisition_line_id')->nullable();
            $table->string('specification', 200)->nullable();
            $table->string('uom', 24)->nullable();
            $table->decimal('qty', 14, 4);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->string('note', 200)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'requisition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_acc_po_lines');
        Schema::dropIfExists('merch_acc_pos');
    }
};

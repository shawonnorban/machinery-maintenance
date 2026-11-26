<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Trims & Accessories (AnnotechERP 06/06): the accessories
 * purchase indent — a requisition against an order, filled from the order's
 * approved BOM (required, in stock and short per item), saved with its lines
 * and taken Draft → Submitted → Approved or Rejected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_acc_requisitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('req_no', 32);
            $table->date('req_date');
            $table->ulid('buyer_office_id')->nullable();
            $table->foreignUlid('order_id')->constrained('merch_orders')->restrictOnDelete();
            $table->ulid('supplier_id')->nullable();
            $table->string('addition_for', 120)->nullable();
            $table->ulid('bom_version_id')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 12)->default('DRAFT');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->ulid('decided_by')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'req_no']);
            $table->index(['company_id', 'order_id']);
        });

        Schema::create('merch_acc_requisition_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('requisition_id')->constrained('merch_acc_requisitions')->cascadeOnDelete();
            $table->ulid('bom_line_id')->nullable();
            $table->ulid('item_id')->nullable();
            $table->string('item_code', 64)->nullable();
            $table->string('description', 200);
            $table->string('uom', 16)->nullable();
            $table->decimal('required_qty', 14, 3);
            $table->decimal('stock_qty', 14, 3)->default(0);
            $table->decimal('short_qty', 14, 3)->default(0);
            $table->date('required_date')->nullable();
            $table->ulid('supplier_id')->nullable();
            $table->string('remarks', 200)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_acc_requisition_lines');
        Schema::dropIfExists('merch_acc_requisitions');
    }
};

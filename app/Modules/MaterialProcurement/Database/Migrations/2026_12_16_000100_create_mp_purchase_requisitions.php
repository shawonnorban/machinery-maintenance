<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Material Procurement → Purchase Requisition (AnnotechERP 08/03): a material
 * requisition raised for a confirmed order or as a direct requisition
 * (PR-2026-00001), and its fabric and accessories lines. No supplier is
 * chosen here: once submitted, every line is open for every active
 * supplier to quote on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mp_requisitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('pr_no', 24);
            $table->string('req_type', 10);
            $table->date('req_date');
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->restrictOnDelete();
            $table->string('status', 12)->default('DRAFT');
            $table->text('remarks')->nullable();
            // Who raised it, and the department they sat in when they did.
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'pr_no']);
        });

        Schema::create('mp_requisition_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('requisition_id')->constrained('mp_requisitions')->cascadeOnDelete();
            $table->string('kind', 8);
            $table->foreignUlid('item_id')->constrained('mp_items')->restrictOnDelete();
            $table->decimal('req_qty', 14, 3);
            $table->string('uom', 16);
            $table->date('req_date');
            $table->string('specification', 500)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->string('status', 24)->default('OPEN_FOR_QUOTATION');
            $table->foreignUlid('approved_supplier_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['requisition_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_requisition_lines');
        Schema::dropIfExists('mp_requisitions');
    }
};

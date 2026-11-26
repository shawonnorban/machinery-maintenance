<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory & Stores → Store & Material (AnnotechERP 09): the material store
 * that Material Procurement's approved requisitions are received into.
 *
 * - `sm_stocks`: one bucket per material (a Fabric / Accessories master item)
 *   and whom it is held for — buyer, PO, style and order — or general stock.
 * - `sm_ledger`: every movement of a bucket — GRN, Material Out, Material
 *   Return, Adjustment and Reversal — the running balance read from it.
 * - `sm_grns` / `sm_grn_lines`: In-House / GRN, against an approved PR or a
 *   direct receive; confirmed at once, reversed (never deleted) by the
 *   Store Manager.
 * - `sm_material_outs`, `sm_material_returns`: issue to a floor, section and
 *   line for a purpose, handed to an employee; unused material back against
 *   its Material Out.
 * - `sm_adjustments`: a physical count against system stock, reaching the
 *   books only when the Store Manager approves; `sm_min_levels`: the
 *   minimum stock a material is kept at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_stocks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('mp_items')->restrictOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            // Whom it is held for; all blank is general stock.
            $table->string('buyer', 120)->default('');
            $table->string('po_number', 120)->default('');
            $table->string('style', 120)->default('');
            $table->string('order_no', 60)->default('');
            $table->string('uom', 16);
            $table->timestamps();
            $table->unique(['company_id', 'item_id', 'buyer', 'po_number', 'style', 'order_no']);
        });

        Schema::create('sm_grns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('grn_no', 24);
            $table->date('grn_date');
            // PR (against an approved requisition) or DIRECT.
            $table->string('kind', 8);
            $table->foreignUlid('requisition_id')->nullable()->constrained('mp_requisitions')->restrictOnDelete();
            $table->foreignUlid('supplier_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->string('supplier_name')->nullable();
            $table->string('buyer', 120)->default('');
            $table->string('po_number', 120)->default('');
            $table->string('style', 120)->default('');
            $table->string('order_no', 60)->default('');
            $table->string('currency', 3)->nullable();
            $table->foreignUlid('received_by')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->text('remarks')->nullable();
            // CONFIRMED or REVERSED.
            $table->string('status', 10)->default('CONFIRMED');
            $table->foreignUlid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reverse_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'grn_no']);
        });

        Schema::create('sm_grn_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('grn_id')->constrained('sm_grns')->cascadeOnDelete();
            $table->foreignUlid('requisition_line_id')->nullable()->constrained('mp_requisition_lines')->restrictOnDelete();
            $table->foreignUlid('stock_id')->constrained('sm_stocks')->restrictOnDelete();
            $table->foreignUlid('item_id')->constrained('mp_items')->restrictOnDelete();
            // FABRIC or TRIMS — and so the Fabric or the Trims & Accessories warehouse.
            $table->string('kind', 8);
            $table->string('uom', 16);
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('sm_material_outs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('mo_no', 24);
            $table->date('mo_date');
            $table->foreignUlid('stock_id')->constrained('sm_stocks')->restrictOnDelete();
            $table->string('purpose', 20);
            $table->decimal('qty', 14, 3);
            $table->foreignUlid('floor_id')->constrained('floors')->restrictOnDelete();
            $table->foreignUlid('section_id')->constrained('sections')->restrictOnDelete();
            $table->foreignUlid('line_id')->constrained('production_lines')->restrictOnDelete();
            $table->foreignUlid('receiver_id')->constrained('hr_employees')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'mo_no']);
        });

        Schema::create('sm_material_returns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('mr_no', 24);
            $table->date('mr_date');
            $table->foreignUlid('material_out_id')->constrained('sm_material_outs')->restrictOnDelete();
            $table->decimal('qty', 14, 3);
            $table->string('reason', 24);
            $table->string('line_name', 120);
            $table->foreignUlid('returned_by')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignUlid('received_by')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'mr_no']);
        });

        Schema::create('sm_adjustments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('sa_no', 24);
            $table->date('sa_date');
            $table->foreignUlid('stock_id')->constrained('sm_stocks')->restrictOnDelete();
            $table->decimal('system_qty', 14, 3);
            $table->decimal('physical_qty', 14, 3);
            $table->string('reason', 24);
            $table->text('remarks')->nullable();
            // PENDING, APPROVED or REJECTED.
            $table->string('status', 10)->default('PENDING');
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'sa_no']);
        });

        Schema::create('sm_min_levels', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('mp_items')->cascadeOnDelete();
            $table->decimal('min_qty', 14, 3);
            $table->timestamps();
            $table->unique(['company_id', 'item_id']);
        });

        Schema::create('sm_ledger', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('stock_id')->constrained('sm_stocks')->restrictOnDelete();
            $table->date('entry_date');
            // GRN, MATERIAL_OUT, MATERIAL_RETURN, ADJUSTMENT or REVERSAL.
            $table->string('type', 16);
            $table->string('reference', 24);
            $table->ulid('source_id');
            $table->decimal('in_qty', 14, 3)->default(0);
            $table->decimal('out_qty', 14, 3)->default(0);
            $table->decimal('return_qty', 14, 3)->default(0);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['company_id', 'stock_id', 'entry_date']);
            $table->index(['company_id', 'reference']);
        });
    }

    public function down(): void
    {
        foreach (['sm_ledger', 'sm_min_levels', 'sm_adjustments', 'sm_material_returns', 'sm_material_outs', 'sm_grn_lines', 'sm_grns', 'sm_stocks'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

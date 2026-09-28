<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase orders, lines and revision history (Procurement spec §7).
 *
 * Phase 1 carries the local flow only: `purchase_type` accepts IMPORT so the
 * column does not need widening later, but the L/C, bond and customs fields
 * arrive with Phase 4. A revision is a full snapshot of the order before the
 * change, so "what did the supplier originally agree to" is a read, not a
 * reconstruction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->foreignUlid('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->string('po_no', 32);
            $table->date('po_date');
            // LOCAL | IMPORT
            $table->string('purchase_type', 16)->default('LOCAL');
            $table->char('currency', 3)->default('BDT');
            $table->foreignUlid('payment_type_id')->nullable()->constrained('payment_types')->nullOnDelete();
            $table->string('payment_terms')->nullable();
            $table->date('delivery_date')->nullable();
            $table->foreignUlid('delivery_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('status', 24)->default('DRAFT');
            $table->string('approval_status', 16)->default('NOT_REQUIRED');
            $table->ulid('approval_request_id')->nullable();
            $table->unsignedInteger('revision_no')->default(0);
            $table->decimal('subtotal', 18, 4)->default(0);
            // The subtotal the last approval was granted against. A revision
            // that grows past it goes back for signature.
            $table->decimal('approved_subtotal', 18, 4)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'po_no'], 'purchase_orders_po_no_unique');
            $table->index(['company_id', 'factory_id', 'status'], 'purchase_orders_status_idx');
            $table->index(['company_id', 'vendor_id'], 'purchase_orders_vendor_idx');
        });

        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignUlid('pr_line_id')->nullable()->constrained('purchase_requisition_lines')->nullOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->string('item_code', 64);
            $table->string('item_name');
            $table->foreignUlid('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('ordered_qty', 18, 4);
            $table->decimal('rate', 18, 4)->default(0);
            $table->decimal('amount', 18, 4)->default(0);
            $table->decimal('received_qty', 18, 4)->default(0);
            $table->date('required_date')->nullable();
            $table->json('technical_spec_json')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'purchase_order_id'], 'purchase_order_lines_po_idx');
            $table->index(['company_id', 'item_id'], 'purchase_order_lines_item_idx');
        });

        Schema::create('purchase_order_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->unsignedInteger('revision_no');
            $table->text('reason');
            // Header and lines as they stood before this revision.
            $table->json('snapshot_json');
            $table->foreignUlid('revised_by')->nullable();
            $table->timestamp('revised_at');
            $table->timestamps();

            $table->unique(['purchase_order_id', 'revision_no'], 'purchase_order_revisions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_revisions');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};

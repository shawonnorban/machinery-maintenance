<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase requisitions, header and lines (Procurement spec §5).
 *
 * A requisition is a request to buy, not a commitment to: it goes through
 * approval and is then converted into one or more purchase orders. Lines
 * point at the item library and also snapshot the item's code and name, so
 * a requisition approved last year still reads the way it did when it was
 * signed even if the item has since been renamed or retired (§2.10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requisitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('pr_no', 32);
            $table->date('pr_date');
            // MANUAL | AUTO | EMERGENCY | FROM_FLOOR_REQUISITION
            $table->string('pr_type', 24)->default('MANUAL');
            // The floor requisition this was raised from, when it was.
            $table->ulid('source_reference')->nullable();
            $table->string('priority', 16)->default('NORMAL');
            $table->string('status', 24)->default('DRAFT');
            // The approval engine's verdict, separate from the document's own
            // workflow status: NOT_REQUIRED | PENDING | APPROVED | REJECTED.
            $table->string('approval_status', 16)->default('NOT_REQUIRED');
            $table->ulid('approval_request_id')->nullable();
            $table->foreignUlid('requested_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            // Sum of line estimates, frozen at submit: what the approval
            // threshold was measured against.
            $table->decimal('estimated_total', 18, 4)->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'pr_no'], 'purchase_requisitions_pr_no_unique');
            $table->index(['company_id', 'factory_id', 'status'], 'purchase_requisitions_status_idx');
            $table->index(['company_id', 'requested_by'], 'purchase_requisitions_requester_idx');
        });

        Schema::create('purchase_requisition_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('purchase_requisition_id')->constrained('purchase_requisitions')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            // Snapshot at the time the line was written (spec §2.10).
            $table->string('item_code', 64);
            $table->string('item_name');
            $table->foreignUlid('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('requested_qty', 18, 4);
            // How much of this line has been placed on purchase orders.
            $table->decimal('ordered_qty', 18, 4)->default(0);
            $table->date('required_date')->nullable();
            $table->decimal('estimated_rate', 18, 4)->nullable();
            // What the requester saw when they asked: on hand and already
            // on order. Kept so "why did they order more" is answerable.
            $table->decimal('current_stock_qty', 18, 4)->nullable();
            $table->decimal('already_ordered_qty', 18, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'purchase_requisition_id'], 'purchase_requisition_lines_pr_idx');
            $table->index(['company_id', 'item_id'], 'purchase_requisition_lines_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requisition_lines');
        Schema::dropIfExists('purchase_requisitions');
    }
};

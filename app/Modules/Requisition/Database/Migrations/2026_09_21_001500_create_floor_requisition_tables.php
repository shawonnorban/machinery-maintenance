<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Floor requisitions, header and lines (Floor/Store Requisition spec §3).
 *
 * A production floor asking a store for items. The form is Phase 1; the
 * store's review, issue and fulfilment states come with Phase 3, which is
 * why the status vocabulary here stops at SUBMITTED.
 *
 * Two department axes, deliberately: `requester_department_id` is the
 * organisation department the requester belongs to (for reporting), while
 * `target_item_department_id` is the item library's department the items
 * are picked from — and that dropdown always shows every item department,
 * never only the requester's own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('floor_requisitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->string('fr_no', 32);
            $table->date('fr_date');
            $table->foreignUlid('requested_by')->nullable();
            $table->foreignUlid('requester_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignUlid('target_item_department_id')->constrained('item_departments')->restrictOnDelete();
            $table->string('priority', 16)->default('NORMAL');
            $table->string('status', 24)->default('DRAFT');
            $table->text('purpose')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'fr_no'], 'floor_requisitions_fr_no_unique');
            $table->index(['company_id', 'factory_id', 'status'], 'floor_requisitions_status_idx');
            $table->index(['company_id', 'requested_by'], 'floor_requisitions_requester_idx');
        });

        Schema::create('floor_requisition_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('floor_requisition_id')->constrained('floor_requisitions')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->string('item_code', 64);
            $table->string('item_name');
            $table->foreignUlid('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('requested_qty', 18, 4);
            // Set by the store at review time (Phase 3).
            $table->decimal('approved_qty', 18, 4)->nullable();
            $table->text('purpose')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'floor_requisition_id'], 'floor_requisition_lines_fr_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floor_requisition_lines');
        Schema::dropIfExists('floor_requisitions');
    }
};

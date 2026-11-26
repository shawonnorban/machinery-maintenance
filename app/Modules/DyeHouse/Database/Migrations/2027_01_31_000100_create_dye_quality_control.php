<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dye House → Dyeing Q.C (AnnotechERP 17/08).
 *
 * - `dye_check_roll_inspections`: Check Roll Quality Inspection — one roll of
 *   a dyed batch received from dyeing (CRI-YYYYMM####), the faults ticked on
 *   it and the department answerable; nothing ticked passes it, anything
 *   ticked holds it. One inspection per roll of a batch.
 * - `dye_batch_completions`: Batch Programme Completion — a batch programme
 *   requisition (17/05) marked finished once every QC gate has passed.
 * - `dye_shade_approvals`: Rib & Collar Shade Approval — rib and collar shade
 *   checked against the body, per batch and position in the lot (SL#), grey
 *   and finished in weight and rolls.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dye_check_roll_inspections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('inspection_no', 24);
            $table->date('inspection_date');
            $table->foreignUlid('batch_id')->constrained('dye_batches')->restrictOnDelete();
            $table->string('roll_no', 40);
            // The faults ticked, by code (CREASE_MARK, SHADE_NOT_OK…).
            $table->json('defects');
            $table->foreignUlid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            // PASSED (nothing ticked) or HELD.
            $table->string('result', 8);
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'inspection_no']);
            $table->unique(['batch_id', 'roll_no']);
            $table->index(['company_id', 'inspection_date']);
        });
        Schema::create('dye_batch_completions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('requisition_id')->constrained('dye_chem_requisitions')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->foreignUlid('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('requisition_id');
        });
        Schema::create('dye_shade_approvals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('batch_id')->constrained('dye_batches')->restrictOnDelete();
            $table->string('lot_no', 40)->nullable();
            $table->unsignedSmallInteger('sl_no');
            $table->decimal('b_qty', 14, 3)->nullable();
            $table->decimal('rp_qty', 14, 3)->nullable();
            $table->date('rcv_date')->nullable();
            $table->date('aprv_date')->nullable();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->decimal('g_qty', 14, 3)->nullable();
            $table->unsignedInteger('g_roll')->nullable();
            $table->decimal('fin_qty', 14, 3)->nullable();
            $table->unsignedInteger('fin_roll')->nullable();
            // PENDING, PASSED, HOLD_DYEING, REJECTED.
            $table->string('status', 12);
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['batch_id', 'sl_no']);
            $table->index(['company_id', 'rcv_date']);
        });
    }

    public function down(): void
    {
        foreach (['dye_shade_approvals', 'dye_batch_completions', 'dye_check_roll_inspections'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

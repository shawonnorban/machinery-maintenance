<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Work Study → Floor Operations (AnnotechERP 14/07), as Brickland keeps it:
 *
 * - `ws_output_adjustments`: Sewing Output Adjustment — a line's sewing output
 *   for one hour of a day corrected, the recorded figure kept beside the
 *   adjusted one so the correction stays auditable.
 * - `ws_wash_requisitions`: Garment Wash Requisition — garments sent out for
 *   wash against a style, numbered on save and moved a step at a time from
 *   draft to complete (or cancelled).
 * - `ws_man_minute_adjustments`: Standard Minute Adjustment — the operators
 *   and ASOs a line carried but did not sew its order with, by reason, in the
 *   general shift and each overtime hour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ws_output_adjustments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('adjust_date');
            $table->foreignUlid('floor_id')->constrained('floors')->restrictOnDelete();
            $table->foreignUlid('planning_line_id')->nullable()->constrained('planning_lines')->restrictOnDelete();
            // The hour of the working day (1 … 11), as the sheet picks it.
            $table->unsignedSmallInteger('hour');
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('order_no', 64)->nullable();
            $table->string('item_name', 120)->nullable();
            $table->unsignedInteger('recorded_qty')->default(0);
            $table->unsignedInteger('adjusted_qty');
            $table->text('reason')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'floor_id', 'adjust_date', 'hour']);
            $table->unique(['planning_line_id', 'adjust_date', 'hour']);
        });

        Schema::create('ws_wash_requisitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('requisition_no', 32);
            $table->date('requisition_date');
            $table->foreignUlid('requested_by')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignUlid('attn_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('floor_id')->constrained('floors')->restrictOnDelete();
            $table->foreignUlid('planning_line_id')->nullable()->constrained('planning_lines')->nullOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('style_no', 64);
            $table->string('order_no', 64);
            $table->date('shipment_date')->nullable();
            $table->unsignedInteger('order_qty')->nullable();
            $table->unsignedInteger('wash_qty');
            $table->string('df_process', 120)->nullable();
            $table->string('gmt_category', 120)->nullable();
            $table->unsignedInteger('hourly_production')->nullable();
            $table->unsignedInteger('daily_production');
            $table->date('start_date')->nullable();
            $table->date('close_date')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 16)->default('DRAFT_CREATED');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'requisition_no']);
            $table->index(['company_id', 'status', 'requisition_date']);
        });

        Schema::create('ws_man_minute_adjustments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('adjust_date');
            $table->foreignUlid('floor_id')->constrained('floors')->restrictOnDelete();
            $table->foreignUlid('planning_line_id')->constrained('planning_lines')->restrictOnDelete();
            $table->string('reason', 64);
            $table->unsignedSmallInteger('used_op')->default(0);
            $table->unsignedSmallInteger('used_aso')->default(0);
            // The overtime hours, keyed as the OT Plan keys them (9 … 20): {"9": {"op": 2, "aso": 0}}.
            $table->json('ot_slots')->nullable();
            $table->unsignedSmallInteger('ot_op')->default(0);
            $table->unsignedSmallInteger('ot_aso')->default(0);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['planning_line_id', 'adjust_date', 'reason']);
            $table->index(['company_id', 'floor_id', 'adjust_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ws_man_minute_adjustments');
        Schema::dropIfExists('ws_wash_requisitions');
        Schema::dropIfExists('ws_output_adjustments');
    }
};

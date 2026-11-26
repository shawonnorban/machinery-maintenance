<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Work Study → Target & Feeding (AnnotechERP 14/03), as Brickland keeps it:
 *
 * - `ws_yarn_targets`: Excess Yarn Allowance — the yarn overrun (qty/kg) each
 *   department is allowed in a month.
 * - `ws_changeovers`: Style Feeding & Change-over — a line moving from one
 *   style to the next, the minutes lost and the feeding time, finalised for the
 *   efficiency report.
 * - `ws_sewing_targets`: Garment Target Setting's target sheet — every order a
 *   line runs on a day, the manpower, hours and efficiency its target is worked
 *   out from, and what it made.
 * - `ws_line_allocations`: Production Entry / Production Plans — the orders
 *   allocated to a line, when each is planned to run, how many pieces against
 *   the allocation, whether it is on the day's target and its status.
 * - `ws_npt_entries`: NPT Entry — the non-productive time booked against a
 *   line on a day: the cause, the hours it hit, the manpower and minutes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ws_yarn_targets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->char('period', 7);
            $table->foreignUlid('department_id')->constrained('departments')->restrictOnDelete();
            $table->decimal('target_qty', 12, 2);
            $table->timestamps();
            $table->unique(['company_id', 'period', 'department_id']);
        });

        Schema::create('ws_changeovers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('floor_id')->constrained('floors')->restrictOnDelete();
            $table->foreignUlid('planning_line_id')->constrained('planning_lines')->restrictOnDelete();
            $table->date('changeover_date');
            $table->foreignUlid('new_buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('new_order', 64);
            $table->string('new_gmt_item', 120)->nullable();
            $table->foreignUlid('prev_buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('prev_order', 64)->nullable();
            $table->string('prev_gmt_item', 120)->nullable();
            $table->date('prev_output_on')->nullable();
            $table->unsignedSmallInteger('sco_minutes');
            $table->decimal('ft_hours', 6, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('finalized')->default(false);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'floor_id', 'changeover_date']);
        });

        Schema::create('ws_sewing_targets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('target_date');
            $table->foreignUlid('floor_id')->constrained('floors')->restrictOnDelete();
            $table->foreignUlid('planning_line_id')->constrained('planning_lines')->restrictOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('order_style', 64)->nullable();
            $table->string('gmt_item', 120)->nullable();
            $table->date('ship_date')->nullable();
            $table->decimal('fob', 12, 4)->nullable();
            $table->decimal('smv', 8, 2);
            $table->boolean('is_car')->default(false);
            $table->unsignedSmallInteger('day_run')->nullable();
            $table->decimal('lo_effi', 6, 2)->nullable();
            $table->decimal('t_effi', 6, 2);
            $table->decimal('wh', 5, 2);
            $table->decimal('wh_from', 5, 2)->nullable();
            $table->decimal('wh_to', 5, 2)->nullable();
            $table->unsignedSmallInteger('used_op');
            $table->unsignedSmallInteger('used_aso');
            $table->unsignedSmallInteger('layout_op')->nullable();
            $table->unsignedSmallInteger('layout_aso')->nullable();
            $table->unsignedInteger('wip_rfi')->nullable();
            $table->unsignedInteger('plan')->nullable();
            // Worked out from Used OP + ASO, WH, T.Effi. and SMV when the sheet is saved — never typed.
            $table->unsignedInteger('target_gen');
            $table->unsignedInteger('target_ot')->nullable();
            $table->unsignedInteger('prod_gen')->nullable();
            $table->unsignedInteger('prod_ot')->nullable();
            $table->unsignedInteger('input_min')->nullable();
            $table->unsignedInteger('prod_min')->nullable();
            $table->decimal('ttl_value', 14, 2)->nullable();
            $table->boolean('is_merged')->default(false);
            $table->boolean('is_single')->default(false);
            $table->boolean('is_off')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'floor_id', 'target_date']);
        });

        Schema::create('ws_line_allocations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('floor_id')->constrained('floors')->restrictOnDelete();
            $table->foreignUlid('planning_line_id')->constrained('planning_lines')->restrictOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('order_style', 64);
            $table->string('style', 64)->nullable();
            $table->string('item', 120)->nullable();
            $table->decimal('smv', 8, 2)->nullable();
            $table->date('input_date')->nullable();
            $table->date('plan_start')->nullable();
            $table->date('plan_end')->nullable();
            $table->unsignedInteger('allocation')->default(0);
            $table->unsignedInteger('production')->default(0);
            $table->boolean('in_target')->default(false);
            $table->string('status', 16)->default('OPEN');
            $table->timestamps();
            $table->index(['company_id', 'planning_line_id']);
        });

        Schema::create('ws_npt_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('npt_date');
            $table->foreignUlid('floor_id')->constrained('floors')->restrictOnDelete();
            $table->foreignUlid('planning_line_id')->constrained('planning_lines')->restrictOnDelete();
            $table->string('order_style', 64)->nullable();
            $table->string('reason_type', 40);
            $table->string('reason_details', 255)->nullable();
            $table->string('responsible_dept', 40)->nullable();
            $table->json('hour_slots');
            $table->unsignedSmallInteger('manpower');
            $table->unsignedSmallInteger('minutes');
            // Manpower × -Min, worked out when the sheet is saved.
            $table->unsignedInteger('npt');
            $table->timestamps();
            $table->index(['company_id', 'planning_line_id', 'npt_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ws_npt_entries');
        Schema::dropIfExists('ws_line_allocations');
        Schema::dropIfExists('ws_sewing_targets');
        Schema::dropIfExists('ws_changeovers');
        Schema::dropIfExists('ws_yarn_targets');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Capacity Planning → Production Plan (AnnotechERP 12/02).
 *
 * - `planning_master_plans`: one version of an order's capacity plan
 *   (MP-2026110004 V1) — its SMV, how its sewing was laid out (backward from
 *   shipment or forward from a date), its totals and sewing window, drafted,
 *   then confirmed and locked; an approved revision opens the next version as
 *   a draft and, once that is locked, the earlier one is Revised.
 * - `planning_master_plan_lines`: each line the plan uses, with the resources
 *   it was planned on, its capacity, allocated quantity and sewing window.
 * - `planning_master_plan_days`: the daily target per line and day — the line
 *   days the plan holds.
 * - `planning_master_plan_activities`: booking, in-house, cutting, sewing,
 *   finishing, packing and shipment, with the dates they run.
 * - `planning_revision_requests`: a request to change a locked plan and the
 *   planning manager's decision.
 * - `planning_plan_events`: each plan's audit trail.
 * - `planning_daily_plans`: a day's resources and target per line under a
 *   locked plan, and the output it made.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_master_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('plan_no', 24);
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignUlid('order_id')->constrained('merch_orders')->restrictOnDelete();
            // DRAFT, LOCKED or REVISED (a later version was locked over it).
            $table->string('status', 10)->default('DRAFT');
            $table->decimal('smv', 8, 2);
            $table->string('smv_source', 16);
            $table->decimal('sam', 8, 2)->nullable();
            $table->string('sam_source', 16)->nullable();
            // BACKWARD (from shipment) or FORWARD (from forward_from).
            $table->string('direction', 8)->default('BACKWARD');
            $table->date('forward_from')->nullable();
            $table->string('scenario', 32)->default('Scenario A');
            $table->unsignedInteger('order_qty');
            $table->unsignedInteger('allocated_qty')->default(0);
            $table->unsignedInteger('daily_capacity')->default(0);
            $table->decimal('required_days', 8, 2)->default(0);
            $table->date('sewing_start')->nullable();
            $table->date('sewing_end')->nullable();
            $table->date('ship_date');
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'plan_no', 'version']);
            $table->index(['company_id', 'order_id']);
        });

        Schema::create('planning_master_plan_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('master_plan_id')->constrained('planning_master_plans')->cascadeOnDelete();
            $table->foreignUlid('planning_line_id')->constrained('planning_lines')->restrictOnDelete();
            $table->unsignedInteger('manpower');
            $table->unsignedInteger('machines');
            $table->decimal('hours', 5, 2);
            $table->decimal('efficiency', 5, 2);
            $table->unsignedInteger('hourly_capacity');
            $table->unsignedInteger('daily_capacity');
            $table->unsignedInteger('allocated_qty');
            $table->date('sewing_start')->nullable();
            $table->date('sewing_end')->nullable();
            $table->unsignedSmallInteger('days')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('planning_master_plan_days', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('master_plan_id')->constrained('planning_master_plans')->cascadeOnDelete();
            $table->foreignUlid('planning_line_id')->constrained('planning_lines')->restrictOnDelete();
            $table->date('day');
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('target');
            $table->timestamps();
            $table->index(['planning_line_id', 'day']);
        });

        Schema::create('planning_master_plan_activities', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('master_plan_id')->constrained('planning_master_plans')->cascadeOnDelete();
            $table->string('activity', 24);
            $table->unsignedSmallInteger('duration');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('based_on', 24);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('planning_revision_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('master_plan_id')->constrained('planning_master_plans')->cascadeOnDelete();
            $table->text('reason');
            // PENDING, APPROVED or REJECTED.
            $table->string('status', 10)->default('PENDING');
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignUlid('revised_plan_id')->nullable()->constrained('planning_master_plans')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('planning_plan_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('plan_no', 24);
            $table->unsignedSmallInteger('version');
            $table->string('action', 16);
            $table->string('summary', 1000);
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['company_id', 'plan_no']);
        });

        Schema::create('planning_daily_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('plan_date');
            $table->foreignUlid('order_id')->constrained('merch_orders')->restrictOnDelete();
            $table->foreignUlid('master_plan_id')->constrained('planning_master_plans')->restrictOnDelete();
            $table->foreignUlid('planning_line_id')->constrained('planning_lines')->restrictOnDelete();
            // The HR shift the line runs that day; its working hours set the hours.
            $table->foreignUlid('shift_id')->nullable()->constrained('hr_shifts')->nullOnDelete();
            $table->unsignedInteger('manpower');
            $table->unsignedInteger('machines');
            $table->decimal('hours', 5, 2);
            $table->decimal('efficiency', 5, 2);
            $table->decimal('smv', 8, 2);
            $table->unsignedInteger('daily_capacity');
            $table->unsignedInteger('master_target');
            $table->unsignedInteger('daily_target');
            // The output typed for the day; null reads the sewing scans.
            $table->unsignedInteger('typed_output')->nullable();
            // PLANNED or CONFIRMED.
            $table->string('status', 10)->default('PLANNED');
            $table->string('remarks', 500)->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['plan_date', 'order_id', 'planning_line_id']);
            $table->index(['company_id', 'plan_date']);
        });
    }

    public function down(): void
    {
        foreach (['planning_daily_plans', 'planning_plan_events', 'planning_revision_requests', 'planning_master_plan_activities', 'planning_master_plan_days', 'planning_master_plan_lines', 'planning_master_plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Work orders: ERD Section 9, SRS 13.
 *
 * The v1.0 draft had an undefined state called "Pending". It is ON_HOLD here,
 * it requires a reason code, and the time spent in it is excluded from repair
 * time, so waiting for a spare part does not masquerade as slow repair work
 * (ADR-051).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->foreignUlid('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignUlid('asset_location_id')->nullable()
                ->constrained('asset_locations')->nullOnDelete();

            $table->foreignUlid('maintenance_schedule_id')->nullable()
                ->constrained('maintenance_schedules')->nullOnDelete();
            // One breakdown may raise many work orders; a work order belongs to
            // at most one. The v1.0 pivot is not implemented (ERD Section 10).
            $table->ulid('breakdown_id')->nullable();
            $table->foreignUlid('maintenance_type_id')->constrained('maintenance_types')->restrictOnDelete();
            $table->foreignUlid('template_version_id')->nullable()
                ->constrained('maintenance_template_versions')->nullOnDelete();

            $table->string('work_order_number', 48);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('DRAFT');
            $table->string('priority', 16)->default('MEDIUM');
            $table->string('source', 24)->default('MANUAL');

            $table->string('approval_status', 24)->default('NOT_REQUIRED');
            $table->ulid('approval_request_id')->nullable();
            // Resolved once at creation from asset criticality and settings,
            // so changing the setting later cannot retroactively reopen closed
            // work.
            $table->boolean('requires_verification')->default(false);
            $table->boolean('requires_shutdown')->default(false);
            $table->string('downtime_class', 24)->default('NONE');

            $table->foreignUlid('assigned_team_id')->nullable()->constrained('teams')->nullOnDelete();

            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('scheduled_end')->nullable();
            $table->timestamp('actual_start')->nullable();
            $table->timestamp('actual_end')->nullable();

            // Accumulated across every hold. Excluded from MTTR (SRS 31.1).
            $table->unsignedInteger('hold_minutes')->default(0);
            $table->timestamp('on_hold_since')->nullable();
            $table->string('hold_reason_code', 32)->nullable();

            $table->decimal('estimated_labor_cost', 18, 4)->nullable();
            $table->decimal('estimated_parts_cost', 18, 4)->nullable();
            // Derived from labour entries and part lines, never accepted from a
            // client (ADR-064).
            $table->decimal('actual_labor_cost', 18, 4)->default(0);
            $table->decimal('actual_parts_cost', 18, 4)->default(0);
            $table->decimal('actual_other_cost', 18, 4)->default(0);
            $table->decimal('actual_cost', 18, 4)->default(0);
            $table->char('currency', 3)->default('BDT');

            $table->foreignUlid('created_by')->nullable();
            $table->foreignUlid('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUlid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignUlid('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignUlid('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->unsignedInteger('reopened_count')->default(0);

            $table->boolean('is_imported')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['company_id', 'work_order_number'], 'work_orders_number_unique');
            $table->index(['company_id', 'factory_id', 'status', 'scheduled_start'], 'work_orders_queue_idx');
            $table->index(['company_id', 'asset_id'], 'work_orders_asset_idx');
            $table->index(['company_id', 'approval_status'], 'work_orders_approval_idx');
        });

        Schema::create('work_order_assignments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignUlid('technician_id')->constrained('technicians')->cascadeOnDelete();
            $table->foreignUlid('assigned_by')->nullable();
            $table->timestamp('assigned_at');
            // Soft-ended rather than deleted, so "who was on this job" stays
            // answerable after a reassignment.
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'technician_id', 'unassigned_at'], 'wo_assignments_tech_idx');
            $table->index(['company_id', 'work_order_id'], 'wo_assignments_wo_idx');
        });

        Schema::create('work_order_labor_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignUlid('technician_id')->nullable()->constrained('technicians')->nullOnDelete();
            // REGULAR | OVERTIME | EXTERNAL
            $table->string('labor_category', 16)->default('REGULAR');
            $table->foreignUlid('labor_grade_id')->nullable()
                ->constrained('labor_rate_grades')->nullOnDelete();
            $table->ulid('vendor_id')->nullable();

            $table->dateTime('started_at', 3);
            $table->dateTime('ended_at', 3);
            $table->unsignedInteger('minutes');
            // Copied from the grade effective at the time, so historical cost
            // stays reproducible when rates change (ADR-065).
            $table->decimal('hourly_rate', 18, 4);
            $table->char('currency', 3)->default('BDT');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->decimal('amount', 18, 4);
            $table->decimal('base_amount', 18, 4);

            $table->text('notes')->nullable();
            $table->foreignUlid('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'technician_id', 'started_at'], 'wo_labor_tech_idx');
            $table->index(['company_id', 'work_order_id'], 'wo_labor_wo_idx');
        });

        Schema::create('work_order_holds', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            // AWAITING_PARTS is the one that matters: it makes a spare-part
            // shortage visible as its own cause instead of inflating MTTR.
            $table->string('reason_code', 32);
            $table->text('notes')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('minutes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'work_order_id'], 'wo_holds_wo_idx');
            $table->index(['company_id', 'reason_code'], 'wo_holds_reason_idx');
        });

        Schema::create('work_order_status_histories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignUlid('changed_by')->nullable();
            $table->timestamp('changed_at');
            $table->string('reason')->nullable();

            $table->index(['company_id', 'work_order_id', 'changed_at'], 'wo_status_hist_idx');
        });

        Schema::create('work_order_checklist_results', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            // Points at the item on the frozen template version the work order
            // snapshotted, so history always reproduces the exact list.
            $table->foreignUlid('checklist_item_id')->constrained('checklist_items')->restrictOnDelete();

            $table->string('result', 16);
            $table->decimal('numeric_value', 18, 4)->nullable();
            $table->text('text_value')->nullable();
            $table->text('observation')->nullable();
            $table->ulid('file_id')->nullable();
            // Derived for NUMERIC items: a reading outside tolerance is a fail
            // whatever the technician tapped.
            $table->boolean('is_within_tolerance')->nullable();
            $table->foreignUlid('followup_work_order_id')->nullable();

            $table->foreignUlid('completed_by')->nullable();
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['work_order_id', 'checklist_item_id'], 'wo_checklist_result_unique');
            $table->index(['company_id', 'work_order_id'], 'wo_checklist_wo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_checklist_results');
        Schema::dropIfExists('work_order_status_histories');
        Schema::dropIfExists('work_order_holds');
        Schema::dropIfExists('work_order_labor_entries');
        Schema::dropIfExists('work_order_assignments');
        Schema::dropIfExists('work_orders');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance plans and schedules: ERD Section 7, SRS 10.
 *
 * A plan describes when maintenance is due. A schedule is one concrete
 * occurrence of it. The scheduler generates schedules forward; work orders
 * are raised from them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();

            // A plan targets one asset, or every asset of a type. Exactly one.
            $table->foreignUlid('asset_id')->nullable()->constrained('assets')->cascadeOnDelete();
            $table->foreignUlid('asset_type_id')->nullable()->constrained('asset_types')->cascadeOnDelete();

            $table->foreignUlid('maintenance_type_id')->constrained('maintenance_types')->restrictOnDelete();
            // Binds to a VERSION, never a template: the checklist a plan
            // raises must not change underneath it (SRS 12).
            $table->foreignUlid('template_version_id')->nullable()
                ->constrained('maintenance_template_versions')->nullOnDelete();

            $table->string('name');
            // TIME | METER | USAGE | CONDITION | COMBINED
            $table->string('trigger_type', 16);
            // ROLLING recomputes from completion; FIXED follows the calendar
            // anchor regardless of when the last one was done (SRS 10).
            $table->string('schedule_mode', 16)->default('ROLLING');
            // OR | AND, explicit. There is no implicit default (ADR-012).
            $table->string('rule_logic', 4)->nullable();

            $table->string('priority', 16)->default('MEDIUM');
            $table->unsignedInteger('grace_period_minutes')->default(0);
            $table->unsignedInteger('lead_time_days')->default(14);
            $table->string('non_working_day_policy', 32)->default('NEXT_WORKING_DAY');
            $table->boolean('requires_shutdown')->default(false);

            $table->foreignUlid('assigned_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignUlid('default_technician_id')->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();

            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('active')->default(false);
            $table->string('timezone', 64)->nullable();

            $table->timestamp('last_generated_at')->nullable();
            $table->timestamp('last_completed_at')->nullable();
            // Denormalised for dashboard queries; maintained by the scheduler.
            $table->timestamp('next_due_at')->nullable();

            $table->foreignUlid('created_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'active', 'next_due_at'], 'plans_active_due_idx');
            $table->index(['company_id', 'asset_id'], 'plans_asset_idx');
            $table->index(['company_id', 'asset_type_id'], 'plans_asset_type_idx');
        });

        Schema::create('maintenance_plan_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('maintenance_plan_id')->constrained('maintenance_plans')->cascadeOnDelete();

            // TIME | METER | USAGE | CONDITION
            $table->string('rule_type', 16);
            $table->string('operator', 16)->default('EVERY');
            $table->decimal('value', 18, 4);
            // DAY/WEEK/MONTH for TIME; the meter's own unit for METER
            $table->string('unit', 32);
            $table->foreignUlid('meter_type_id')->nullable()
                ->constrained('meter_types')->restrictOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'maintenance_plan_id'], 'plan_rules_plan_idx');
        });

        Schema::create('maintenance_schedules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('maintenance_plan_id')->constrained('maintenance_plans')->cascadeOnDelete();
            $table->foreignUlid('asset_id')->constrained('assets')->cascadeOnDelete();

            $table->timestamp('due_at');
            // Populated alongside due_at for a combined rule, so whichever
            // threshold is reached first can trigger the occurrence.
            $table->decimal('due_meter', 18, 4)->nullable();
            $table->foreignUlid('due_meter_type_id')->nullable();

            $table->string('status', 32)->default('PLANNED');
            $table->timestamp('grace_until')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUlid('work_order_id')->nullable();

            $table->foreignUlid('generated_from_schedule_id')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('rescheduled_from_due_at')->nullable();
            $table->string('rescheduled_reason')->nullable();
            $table->string('skipped_reason')->nullable();
            $table->foreignUlid('skipped_by')->nullable();
            // Which rule fired: TIME or METER. Without it, a combined plan's
            // history cannot explain why an occurrence appeared early.
            $table->string('triggered_by', 16)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->timestamps();

            // Idempotent generation enforced by the database, rather than by
            // trusting the job never to run twice (ERD Section 7).
            $table->unique(
                ['maintenance_plan_id', 'asset_id', 'due_at'],
                'schedules_occurrence_unique',
            );
            $table->index(['company_id', 'status', 'due_at'], 'schedules_due_idx');
            $table->index(['company_id', 'asset_id', 'status'], 'schedules_asset_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('maintenance_plan_rules');
        Schema::dropIfExists('maintenance_plans');
    }
};

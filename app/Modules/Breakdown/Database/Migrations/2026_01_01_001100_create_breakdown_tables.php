<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Breakdowns, the failure taxonomy and downtime: ERD Sections 10-12, SRS 15-17.
 *
 * The timestamp chain is the substance here. A breakdown is not one moment, it
 * is seven: when it failed, when someone said so, when maintenance acknowledged
 * it, when a technician reached the machine, when repair started and finished,
 * and when production actually resumed. Collapsing those into "start and end"
 * makes response time and repair time indistinguishable, and a factory cannot
 * then tell a slow maintenance team from a slow reporting culture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failure_categories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // Null company_id = platform-seeded, visible to every tenant.
            $table->foreignUlid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('code', 48);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'failure_categories_code_unique');
        });

        Schema::create('failure_codes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('failure_category_id')->constrained('failure_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('code', 48);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'failure_codes_code_unique');
            $table->index(['company_id', 'failure_category_id'], 'failure_codes_category_idx');
        });

        Schema::create('root_causes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('code', 48);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'root_causes_code_unique');
        });

        Schema::create('downtime_reason_codes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('code', 48);
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('downtime_class', 24)->default('UNPLANNED');
            // AWAITING_SPARE counts. That is the point of the column: it makes
            // the cost of an understocked store visible as downtime instead of
            // hiding it inside repair time (Seed Catalog 5).
            $table->boolean('counts_against_availability')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'downtime_reasons_code_unique');
        });

        Schema::create('breakdowns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->foreignUlid('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignUlid('asset_location_id')->nullable()
                ->constrained('asset_locations')->nullOnDelete();
            $table->foreignUlid('production_line_id')->nullable()
                ->constrained('production_lines')->nullOnDelete();

            $table->string('breakdown_number', 48);
            $table->foreignUlid('reported_by')->nullable();

            // Second precision is not enough: two events in the same second sort
            // arbitrarily and the chain then reads out of order.
            $table->dateTime('failure_at', 3);
            $table->dateTime('reported_at', 3);
            $table->dateTime('acknowledged_at', 3)->nullable();
            $table->dateTime('technician_arrival_at', 3)->nullable();
            $table->dateTime('repair_started_at', 3)->nullable();
            $table->dateTime('repair_completed_at', 3)->nullable();
            $table->dateTime('production_resumed_at', 3)->nullable();

            $table->string('status', 32)->default('REPORTED');
            $table->string('priority', 16)->default('HIGH');
            $table->string('severity', 16)->default('MAJOR');
            $table->text('problem_description');

            $table->foreignUlid('failure_category_id')->nullable()
                ->constrained('failure_categories')->nullOnDelete();
            $table->foreignUlid('failure_code_id')->nullable()
                ->constrained('failure_codes')->nullOnDelete();
            $table->foreignUlid('root_cause_id')->nullable()
                ->constrained('root_causes')->nullOnDelete();
            $table->text('corrective_action')->nullable();
            $table->text('preventive_action')->nullable();
            $table->string('production_order_reference')->nullable();

            $table->foreignUlid('assigned_technician_id')->nullable()
                ->constrained('technicians')->nullOnDelete();
            $table->foreignUlid('assigned_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->dateTime('assigned_at', 3)->nullable();
            $table->foreignUlid('acknowledged_by')->nullable();

            $table->string('downtime_class', 24)->default('UNPLANNED');
            $table->foreignUlid('downtime_reason_code_id')->nullable()
                ->constrained('downtime_reason_codes')->nullOnDelete();

            // A second report against a machine already down is the same event,
            // not an independent failure. Counting it twice halves MTBF for a
            // machine that broke once (ERD Section 10 rule 4).
            $table->foreignUlid('is_recurrence_of_breakdown_id')->nullable();

            // Accumulated across holds and excluded from repair time, exactly as
            // on a work order (ADR-051).
            $table->unsignedInteger('hold_minutes')->default(0);
            $table->dateTime('on_hold_since', 3)->nullable();
            $table->string('hold_reason_code', 32)->nullable();

            $table->foreignUlid('closed_by')->nullable();
            $table->dateTime('closed_at', 3)->nullable();
            $table->text('closure_notes')->nullable();
            $table->foreignUlid('cancelled_by')->nullable();
            $table->dateTime('cancelled_at', 3)->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['company_id', 'breakdown_number'], 'breakdowns_number_unique');
            $table->index(['company_id', 'factory_id', 'status', 'reported_at'], 'breakdowns_queue_idx');
            $table->index(['company_id', 'asset_id', 'failure_at'], 'breakdowns_asset_idx');
            $table->index(['company_id', 'failure_code_id'], 'breakdowns_failure_code_idx');
        });

        Schema::create('breakdown_status_histories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('breakdown_id')->constrained('breakdowns')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignUlid('changed_by')->nullable();
            $table->dateTime('changed_at', 3);
            $table->string('reason')->nullable();

            $table->index(['company_id', 'breakdown_id', 'changed_at'], 'breakdown_status_hist_idx');
        });

        Schema::create('production_impacts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('breakdown_id')->constrained('breakdowns')->cascadeOnDelete();
            $table->foreignUlid('production_line_id')->nullable()
                ->constrained('production_lines')->nullOnDelete();
            $table->string('production_order_reference')->nullable();
            // Pieces, not money. Converting to currency needs a costing rate
            // this system does not own, and a fabricated figure would be quoted
            // as fact.
            $table->decimal('estimated_loss', 18, 4)->nullable();
            $table->decimal('actual_loss', 18, 4)->nullable();
            $table->string('unit', 32)->default('PIECES');
            $table->text('notes')->nullable();
            $table->foreignUlid('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'breakdown_id'], 'production_impacts_breakdown_idx');
        });

        Schema::create('downtime_records', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->foreignUlid('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignUlid('breakdown_id')->nullable()->constrained('breakdowns')->cascadeOnDelete();
            $table->foreignUlid('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignUlid('production_line_id')->nullable()
                ->constrained('production_lines')->nullOnDelete();

            $table->dateTime('failure_at', 3);
            $table->dateTime('reported_at', 3)->nullable();
            $table->dateTime('acknowledged_at', 3)->nullable();
            $table->dateTime('technician_arrival_at', 3)->nullable();
            $table->dateTime('repair_started_at', 3)->nullable();
            $table->dateTime('repair_completed_at', 3)->nullable();
            $table->dateTime('production_resumed_at', 3)->nullable();

            $table->unsignedInteger('response_minutes')->nullable();
            $table->unsignedInteger('repair_minutes')->nullable();
            $table->unsignedInteger('total_downtime_minutes')->nullable();
            $table->unsignedInteger('hold_minutes')->default(0);

            $table->string('downtime_class', 24)->default('UNPLANNED');
            $table->foreignUlid('downtime_reason_code_id')->nullable()
                ->constrained('downtime_reason_codes')->nullOnDelete();
            $table->boolean('counts_against_availability')->default(true);
            // An unclassified row is flagged, never silently dropped from the
            // denominator (ERD Section 12 rule 1).
            $table->boolean('needs_review')->default(false);

            // Which basis produced these numbers. A report that silently changes
            // basis is worse than one that says which it used (SRS 47.2).
            $table->boolean('calendar_aware')->default(false);
            $table->string('calculation_basis', 32)->nullable();
            $table->unsignedInteger('scheduled_operating_minutes_in_window')->nullable();
            // Changing a downtime rule must never silently rewrite a closed
            // period's KPIs. A recalculation writes a new version (SRS 17.3).
            $table->unsignedInteger('calculation_version')->default(1);
            $table->dateTime('calculated_at', 3);
            $table->timestamps();

            $table->unique(['breakdown_id', 'calculation_version'], 'downtime_breakdown_version_unique');
            $table->index(['company_id', 'asset_id', 'failure_at'], 'downtime_asset_idx');
            $table->index(['company_id', 'factory_id', 'downtime_class'], 'downtime_class_idx');
            $table->index(['company_id', 'needs_review'], 'downtime_review_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downtime_records');
        Schema::dropIfExists('production_impacts');
        Schema::dropIfExists('breakdown_status_histories');
        Schema::dropIfExists('breakdowns');
        Schema::dropIfExists('downtime_reason_codes');
        Schema::dropIfExists('root_causes');
        Schema::dropIfExists('failure_codes');
        Schema::dropIfExists('failure_categories');
    }
};

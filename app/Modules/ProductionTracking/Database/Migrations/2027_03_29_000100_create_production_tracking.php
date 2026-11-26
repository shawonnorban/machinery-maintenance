<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production Tracking (AnnotechERP 30, Brickland's /m/production-tracking):
 * the module's settings, each line's standard capacity, the daily production
 * plans (a line's target for a day, with its revisions and their history),
 * the Production Master Data entries (a day's cutting, sewing and finishing
 * figures) and the factory-floor TV screens with their display links.
 *
 * A line is a sewing line (Capacity Planning's planning line, the one Assembly
 * Floor scans bundles onto) or a finishing line (Company Management's
 * production line of type FINISHING); `section` says which.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pt_settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->unsignedSmallInteger('refresh_seconds')->default(30);
            $table->unsignedSmallInteger('stale_seconds')->default(180);
            $table->unsignedTinyInteger('on_track_pct')->default(90);
            $table->unsignedTinyInteger('below_pct')->default(75);
            $table->boolean('approval_workflow')->default(true);
            $table->boolean('smv_method')->default(false);
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pt_line_capacities', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('section', 12); // SEWING | FINISHING
            $table->ulid('line_id');
            $table->string('capacity_type', 10)->default('PER_HOUR'); // PER_HOUR | PER_DAY
            $table->decimal('standard_capacity', 10, 2);
            $table->decimal('default_efficiency', 5, 2)->nullable();
            $table->boolean('includes_efficiency')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('active')->default(true);
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'section', 'line_id', 'effective_from'], 'pt_capacities_line_idx');
        });

        Schema::create('pt_daily_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('plan_no', 32);
            $table->unsignedSmallInteger('revision')->default(1);
            $table->date('plan_date');
            $table->string('section', 12);
            $table->ulid('line_id');
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('po_no', 64)->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('style', 120)->nullable();
            $table->string('method', 1); // A | B | C | D
            $table->decimal('planned_hours', 5, 2);
            $table->json('capacity');
            $table->unsignedInteger('calculated_target');
            $table->unsignedInteger('proposed_target');
            $table->unsignedInteger('approved_target')->nullable();
            $table->text('override_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('DRAFT'); // DRAFT | PENDING_APPROVAL | APPROVED | REJECTED | CANCELLED
            $table->boolean('current')->default(true);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'plan_no', 'revision'], 'pt_plans_no_unique');
            $table->index(['company_id', 'plan_date', 'section', 'line_id'], 'pt_plans_day_idx');
        });

        Schema::create('pt_daily_plan_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('plan_id')->constrained('pt_daily_plans')->cascadeOnDelete();
            $table->string('action', 20);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('pt_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('section', 12); // CUTTING | SEWING | FINISHING
            $table->date('entry_date');
            $table->ulid('line_id')->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('style', 120)->nullable();
            $table->string('po_no', 64)->nullable();
            $table->string('item', 120)->nullable();
            $table->unsignedInteger('order_qty')->nullable();
            $table->unsignedInteger('target')->nullable();
            $table->unsignedInteger('production')->nullable(); // sewing/finishing output, or cut pcs ready
            $table->unsignedInteger('bundles_ready')->nullable();
            $table->unsignedInteger('bundles_issued')->nullable();
            $table->unsignedInteger('reject')->nullable();
            $table->unsignedInteger('rework')->nullable();
            $table->decimal('smv', 8, 3)->nullable();
            $table->unsignedInteger('manpower')->nullable();
            $table->decimal('available_hours', 8, 2)->nullable();
            $table->decimal('plan_hours', 6, 2)->nullable();
            $table->unsignedInteger('run_day')->nullable();
            $table->unsignedInteger('total_produced')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'section', 'entry_date'], 'pt_entries_day_idx');
        });

        Schema::create('pt_tv_screens', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('section', 12);
            $table->json('line_ids');
            $table->string('layout', 8)->default('AUTO'); // AUTO | 1 | 2 | 3 | 4 | 6
            $table->unsignedSmallInteger('refresh_seconds')->default(30);
            $table->boolean('show_defects')->default(true);
            $table->boolean('active')->default(true);
            $table->string('token_hash', 64)->nullable()->unique();
            $table->string('token_hint', 8)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['pt_tv_screens', 'pt_entries', 'pt_daily_plan_events', 'pt_daily_plans', 'pt_line_capacities', 'pt_settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Capacity Planning → Master Data (AnnotechERP 12/01): each factory's working
 * calendar, the planning lines with their standard manpower, machines, hours
 * and efficiency (and the machines, roles and shifts under each), efficiency
 * standards, style SMV / SAM, and the lead times a master plan starts from.
 * Factories, floors and floor lines are Company Management's; buyers are
 * Merchandise Desk's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_weekly_holidays', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 Sunday … 6 Saturday
            $table->timestamps();
            $table->unique(['factory_id', 'weekday'], 'planning_weekly_holidays_unique');
        });

        // A day the factory does not run as the weekly pattern says: a holiday,
        // a special working day, or a day with its own hours.
        Schema::create('planning_calendar_days', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->cascadeOnDelete();
            $table->date('day');
            $table->string('status', 20);
            $table->string('reason')->nullable();
            $table->decimal('working_hours', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['factory_id', 'day'], 'planning_calendar_days_unique');
        });

        Schema::create('planning_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('floor_id')->constrained('floors')->restrictOnDelete();
            // The Company Management line whose scanned output Plan vs Actual reads.
            $table->foreignUlid('floor_line_id')->nullable()->constrained('production_lines')->nullOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->unsignedInteger('std_manpower')->default(0);
            $table->unsignedInteger('std_machines')->default(0);
            $table->decimal('std_hours', 5, 2)->default(10);
            $table->decimal('std_efficiency', 5, 2)->default(65);
            $table->string('best_fit')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('under_maintenance')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'floor_id', 'code'], 'planning_lines_code_unique');
        });

        Schema::create('planning_line_machines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('planning_line_id')->constrained('planning_lines')->cascadeOnDelete();
            $table->string('machine_type');
            $table->string('name_code')->nullable();
            $table->string('category', 16); // SEWING | SPECIAL | FINISHING | OTHER
            $table->unsignedInteger('installed')->default(0);
            $table->unsignedInteger('working')->default(0);
            $table->unsignedInteger('standby')->default(0);
            $table->decimal('capacity_per_hour', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('planning_line_manpower', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('planning_line_id')->constrained('planning_lines')->cascadeOnDelete();
            $table->string('role', 16); // OPERATOR | HELPER | IRONMAN | QC | SUPERVISOR | OTHER
            $table->unsignedInteger('required');
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->unique(['planning_line_id', 'role'], 'planning_line_manpower_unique');
        });

        Schema::create('planning_line_shifts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('planning_line_id')->constrained('planning_lines')->cascadeOnDelete();
            $table->string('shift_name', 64);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('manpower')->nullable();
            $table->unsignedInteger('target')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['planning_line_id', 'shift_name'], 'planning_line_shifts_unique');
        });

        Schema::create('planning_efficiency_standards', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('applies_to', 8); // STYLE | BUYER | LINE | UNIT
            $table->foreignUlid('factory_id')->nullable()->constrained('factories')->cascadeOnDelete();
            $table->foreignUlid('planning_line_id')->nullable()->constrained('planning_lines')->cascadeOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->cascadeOnDelete();
            $table->string('style_no', 64)->nullable();
            $table->decimal('efficiency', 5, 2);
            $table->date('effective_from');
            $table->string('remarks')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'applies_to', 'effective_from'], 'planning_efficiency_lookup');
        });

        Schema::create('planning_style_smvs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('style_no', 64);
            $table->decimal('smv', 8, 2)->nullable();
            $table->decimal('sam', 8, 2)->nullable();
            $table->string('remarks')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'style_no'], 'planning_style_smvs_unique');
        });

        Schema::create('planning_lead_times', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('activity', 32);
            $table->unsignedInteger('lead_days');
            $table->string('counted_from', 24); // BEFORE_CUTTING | BEFORE_FIRST_SEWING | AFTER_LAST_SEWING
            $table->string('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('changed')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'activity'], 'planning_lead_times_unique');
        });
    }

    public function down(): void
    {
        foreach (['planning_lead_times', 'planning_style_smvs', 'planning_efficiency_standards', 'planning_line_shifts', 'planning_line_manpower', 'planning_line_machines', 'planning_lines', 'planning_calendar_days', 'planning_weekly_holidays'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Working calendar: ERD Section 23, SRS 47, ADR-048.
 *
 * Absent from v1.0, which left downtime, availability, response-time SLAs and
 * escalation timers with no definition of working time. A breakdown reported
 * at 21:50 in a factory whose shift ends at 22:00 must accrue 10 minutes of
 * downtime, not 8 hours and 10 minutes.
 *
 * Everything here is effective-dated, so editing a shift never rewrites a
 * closed period (SRS 47.2 rule 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factory_calendars', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->cascadeOnDelete();
            $table->string('operating_mode', 16)->default('SHIFT_BASED');
            // ISO-8601 day numbers, 1 = Monday .. 7 = Sunday. Friday is the
            // usual weekly off in Bangladesh, so this is data, not a constant.
            $table->json('weekly_off_days');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'factory_id', 'effective_from']);
        });

        Schema::create('shifts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->time('start_time');
            $table->time('end_time');
            // A shift ending before it starts crosses midnight and is
            // attributed to its start date (SRS 47.2 rule 2).
            $table->boolean('crosses_midnight')->default(false);
            $table->json('days_of_week');
            $table->boolean('is_overtime')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 32)->default('ACTIVE');
            $table->timestamps();

            $table->unique(['company_id', 'factory_id', 'code', 'effective_from'], 'shifts_unique');
            $table->index(['company_id', 'factory_id', 'effective_from']);
        });

        Schema::create('shift_breaks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('counts_as_operating_time')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'shift_id']);
        });

        Schema::create('factory_holidays', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->cascadeOnDelete();
            $table->date('date');
            $table->string('name');
            // Allows an override that turns a normal off-day into a working
            // day, which happens during peak shipment weeks.
            $table->boolean('is_working_day')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'factory_id', 'date'], 'factory_holidays_unique');
            $table->index(['company_id', 'factory_id', 'date']);
        });

        Schema::create('production_line_shift_overrides', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('production_line_id')->constrained('production_lines')->cascadeOnDelete();
            $table->foreignUlid('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'production_line_id', 'effective_from'], 'line_shift_overrides_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_line_shift_overrides');
        Schema::dropIfExists('factory_holidays');
        Schema::dropIfExists('shift_breaks');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('factory_calendars');
    }
};

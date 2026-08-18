<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metering: ERD Section 8, SRS 11.
 *
 * Brought forward from build order step 14 because the scheduling engine's
 * headline case is a combined rule - "every 30 days OR every 500 running
 * hours, whichever occurs first" (SRS 10) - which cannot work without meters.
 *
 * Readings are append-only. A wrong reading is corrected by a compensating
 * reading, never by an update, because maintenance due dates were already
 * computed from the original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // null = platform-seeded, shared by every tenant
            $table->foreignUlid('company_id')->nullable()
                ->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 48);
            $table->string('unit', 32);
            // A meter that only ever counts upwards (running hours, stitches)
            // versus one that can legitimately be reset (a replaced counter).
            $table->boolean('is_cumulative')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('asset_meters', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignUlid('meter_type_id')->constrained('meter_types')->restrictOnDelete();
            // Denormalised from the latest reading so due-date evaluation does
            // not scan the reading history on every scheduler tick.
            $table->decimal('current_value', 18, 4)->default(0);
            $table->timestamp('last_reading_at')->nullable();
            $table->string('status', 32)->default('ACTIVE');
            $table->timestamps();

            $table->unique(['company_id', 'asset_id', 'meter_type_id'], 'asset_meters_unique');
            $table->index(['company_id', 'asset_id']);
        });

        Schema::create('meter_readings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignUlid('meter_id')->constrained('asset_meters')->cascadeOnDelete();
            $table->decimal('value', 18, 4);
            $table->decimal('previous_value', 18, 4)->nullable();
            $table->decimal('delta', 18, 4)->nullable();
            // Millisecond precision (ERD rule 15). At second precision the
            // uniqueness guard below would reject two legitimate readings
            // posted in the same second, which an API import can do.
            $table->dateTime('reading_at', 3);
            // MANUAL | IMPORT | API | IOT
            $table->string('source', 16)->default('MANUAL');
            $table->string('source_reference')->nullable();
            $table->boolean('is_reset_baseline')->default(false);
            $table->text('notes')->nullable();
            $table->foreignUlid('recorded_by')->nullable();
            // Append-only: no updated_at, no deleted_at (ERD rule 19).
            $table->dateTime('created_at', 3)->nullable();

            // Rejects a duplicate submission of the same reading on retry.
            $table->unique(['company_id', 'meter_id', 'reading_at', 'source'], 'meter_readings_unique');
            $table->index(['company_id', 'meter_id', 'reading_at']);
        });

        Schema::create('meter_reset_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('meter_id')->constrained('asset_meters')->cascadeOnDelete();
            $table->decimal('old_value', 18, 4);
            $table->decimal('new_value', 18, 4);
            $table->string('reason');
            $table->timestamp('reset_at');
            $table->foreignUlid('reset_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['company_id', 'meter_id', 'reset_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_reset_events');
        Schema::dropIfExists('meter_readings');
        Schema::dropIfExists('asset_meters');
        Schema::dropIfExists('meter_types');
    }
};

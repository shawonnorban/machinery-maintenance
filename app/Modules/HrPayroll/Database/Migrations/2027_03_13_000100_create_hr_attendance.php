<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * HR & Payroll → Attendance & Shift (AnnotechERP 24/04):
 *
 * - `hr_shift_rosters`: an employee on a shift from a date (to a date, or
 *   open-ended) with the weekly off days — assigned one by one, several at
 *   once, a whole line, or a weekly rotation (one row per week). The newest
 *   assignment covering a day decides that day's shift; the assignments made
 *   together share a batch.
 * - `hr_attendance_days`: one row per employee and day — the shift, the first
 *   IN and last OUT punch, worked minutes, late, early out and OT minutes,
 *   and the status (Present, Absent, Leave, Missing Punch, Holiday, Weekly
 *   Off) with the late and early-out flags. Worked out from the terminals'
 *   punches; a corrected day is kept as corrected and is not worked out again.
 * - `hr_attendance_corrections`: a missing punch put right — the correct IN
 *   and OUT with the reason, requested, then approved or rejected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_shift_rosters', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUlid('shift_id')->constrained('hr_shifts')->restrictOnDelete();
            $table->date('from_date');
            $table->date('to_date')->nullable();
            $table->json('weekly_off');
            $table->string('mode', 10); // INDIVIDUAL | MULTIPLE | LINE | ROTATION
            $table->string('batch', 16);
            $table->string('remarks', 300)->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'employee_id', 'from_date']);
        });

        Schema::create('hr_attendance_days', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('date');
            $table->foreignUlid('shift_id')->nullable()->constrained('hr_shifts')->nullOnDelete();
            $table->timestamp('in_at')->nullable();
            $table->timestamp('out_at')->nullable();
            $table->unsignedInteger('worked_minutes')->nullable();
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_out_minutes')->default(0);
            $table->unsignedInteger('ot_minutes')->nullable();
            $table->string('status', 14); // PRESENT | ABSENT | LEAVE | MISSING_PUNCH | HOLIDAY | WEEKLY_OFF
            $table->boolean('is_late')->default(false);
            $table->boolean('is_early_out')->default(false);
            $table->boolean('corrected')->default(false);
            $table->string('remarks', 300)->nullable();
            $table->foreignUlid('corrected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
            $table->index(['company_id', 'date', 'status']);
        });

        Schema::create('hr_attendance_corrections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('date');
            $table->string('old_in', 5)->nullable();
            $table->string('old_out', 5)->nullable();
            $table->string('correct_in', 5)->nullable();
            $table->string('correct_out', 5)->nullable();
            $table->string('reason', 500);
            $table->string('status', 10)->default('PENDING'); // PENDING | APPROVED | REJECTED
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_corrections');
        Schema::dropIfExists('hr_attendance_days');
        Schema::dropIfExists('hr_shift_rosters');
    }
};

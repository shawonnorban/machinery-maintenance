<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * HR & Payroll → Leave & Overtime (AnnotechERP 24/05):
 *
 * - `hr_leave_applications`: a leave keyed by HR — daily (from / to) or an
 *   early departure (a date, a time and hours) — against a leave type and a
 *   fiscal year, saved pending, approved, rejected or cancelled. An approved
 *   daily leave makes its days Leave on the attendance; an approved early
 *   departure excuses that day's early out.
 * - `hr_ot_limits`: the company's OT limits — when OT starts after the shift
 *   end, the grace before the next rounding step, the rounding (general and
 *   worker OT), the OT break and when it applies, the minimum and the daily cap.
 * - `hr_grade_ot`: whether a grade earns General OT (on unless switched off).
 * - `hr_employee_ot`: Worker OT switched on for a staff member, with a note.
 * - `hr_ot_audit`: every OT limit, grade and employee switch, with the reason.
 * - `hr_ot_requests` / `hr_ot_request_employees`: OT asked for a line on a
 *   day with its window and reason — approved directly or sent to an
 *   approver who approves (unticking anyone), rejects or returns it.
 * - `hr_ot_corrections` / `hr_ot_correction_history`: a day's OT hours or
 *   rate put right with a reason, and every save or reset.
 * - `hr_attendance_days` gains the leave type of a leave day and the OT type
 *   (General / Worker) of the day's OT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_leave_applications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('ref_no', 16);
            $table->foreignUlid('fiscal_year_id')->constrained('acc_fiscal_years')->restrictOnDelete();
            $table->string('mode', 16); // DAILY | EARLY_DEPARTURE
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUlid('leave_type_id')->constrained('hr_leave_types')->restrictOnDelete();
            $table->string('purpose', 120)->nullable();
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('days', 6, 1)->default(0);
            $table->string('early_time', 5)->nullable();
            $table->decimal('hours', 5, 2)->nullable();
            $table->date('apply_date');
            $table->date('approve_date')->nullable();
            $table->foreignUlid('duty_by_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->date('joining_date')->nullable();
            $table->string('status', 10); // PENDING | APPROVED | REJECTED | CANCELLED
            $table->boolean('confirmed')->default(false);
            $table->text('reason')->nullable();
            $table->text('reason_bn')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'ref_no'], 'hr_leave_applications_unique');
            $table->index(['company_id', 'employee_id', 'from_date']);
        });

        Schema::create('hr_ot_limits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->unsignedSmallInteger('start_after_min')->default(0);
            $table->unsignedSmallInteger('grace_min')->default(0);
            $table->unsignedSmallInteger('general_rounding_min')->default(60);
            $table->unsignedSmallInteger('worker_rounding_min')->default(60);
            $table->unsignedSmallInteger('break_min')->default(0);
            $table->decimal('break_after_hours', 4, 1)->default(0);
            $table->unsignedSmallInteger('min_minutes')->default(0);
            $table->decimal('max_hours_day', 4, 1)->default(0);
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hr_grade_ot', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->boolean('general_ot')->default(true);
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'grade_id'], 'hr_grade_ot_unique');
        });

        Schema::create('hr_employee_ot', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->unique()->constrained('hr_employees')->cascadeOnDelete();
            $table->boolean('worker_ot')->default(false);
            $table->string('note', 200)->nullable();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hr_ot_audit', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('subject', 10); // LIMITS | GRADE | EMPLOYEE
            $table->string('subject_id', 26)->nullable();
            $table->string('change', 300);
            $table->string('reason', 300)->nullable();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hr_ot_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('request_no', 16);
            $table->date('date');
            $table->foreignUlid('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignUlid('section_id')->constrained('sections')->restrictOnDelete();
            $table->foreignUlid('line_id')->constrained('production_lines')->restrictOnDelete();
            $table->foreignUlid('shift_id')->nullable()->constrained('hr_shifts')->nullOnDelete();
            $table->string('ot_start', 5);
            $table->string('ot_end', 5);
            $table->unsignedSmallInteger('minutes');
            $table->text('reason');
            $table->string('approval', 8); // DIRECT | ROUTED
            $table->foreignUlid('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10); // PENDING | APPROVED | REJECTED | RETURNED
            $table->text('decision_note')->nullable();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'request_no'], 'hr_ot_requests_unique');
            $table->index(['company_id', 'status', 'date']);
        });

        Schema::create('hr_ot_request_employees', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('request_id')->constrained('hr_ot_requests')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->boolean('removed')->default(false);
            $table->unique(['request_id', 'employee_id'], 'hr_ot_request_employees_unique');
        });

        Schema::create('hr_ot_corrections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('hours', 5, 2);
            $table->decimal('rate', 10, 2);
            $table->string('reason', 300);
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'date'], 'hr_ot_corrections_unique');
        });

        Schema::create('hr_ot_correction_history', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('date');
            $table->string('action', 6); // SAVE | RESET
            $table->decimal('hours', 5, 2)->nullable();
            $table->decimal('rate', 10, 2)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('reason', 300)->nullable();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'date']);
        });

        Schema::table('hr_attendance_days', function (Blueprint $table): void {
            $table->foreignUlid('leave_type_id')->nullable()->after('status')->constrained('hr_leave_types')->nullOnDelete();
            $table->string('ot_type', 8)->nullable()->after('ot_minutes'); // GENERAL | WORKER
        });
    }

    public function down(): void
    {
        Schema::table('hr_attendance_days', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('leave_type_id');
            $table->dropColumn('ot_type');
        });
        foreach (['hr_ot_correction_history', 'hr_ot_corrections', 'hr_ot_request_employees', 'hr_ot_requests', 'hr_ot_audit', 'hr_employee_ot', 'hr_grade_ot', 'hr_ot_limits', 'hr_leave_applications'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

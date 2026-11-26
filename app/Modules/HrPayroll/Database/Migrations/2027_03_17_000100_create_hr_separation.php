<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * HR & Payroll → Separation (AnnotechERP 24/08):
 *
 * - `hr_separations`: a resignation, release or termination — the
 *   application and last working dates, notice days and reason — sent for
 *   approval; approved, the employee leaves the active roster after the last
 *   working day.
 * - `hr_lefty_cases`: an employee who left without notice — reported with
 *   the last attendance, then investigated, confirmed or rejected; a
 *   confirmed lefty is reinstated or closed. Every move is kept in the
 *   case's history.
 * - `hr_settlements`: the final settlement of a leaving employee — salary,
 *   OT and bonus due, leave encashment, deduction, loan recovery and other
 *   adjustment, and the final payable — worked out from the records, kept
 *   as a draft, approved and paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_separations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('ref_no', 20);
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('type', 12); // RESIGNATION | RELEASE | TERMINATION
            $table->date('application_date');
            $table->date('last_working_date');
            $table->unsignedSmallInteger('notice_days')->nullable();
            $table->text('reason');
            $table->string('status', 10); // PENDING | APPROVED | REJECTED
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'ref_no'], 'hr_separations_unique');
            $table->index(['company_id', 'status', 'last_working_date']);
        });

        Schema::create('hr_lefty_cases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('ref_no', 20);
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('last_attendance_date')->nullable();
            $table->date('last_working_date');
            $table->date('reported_date');
            $table->text('reason');
            $table->string('document_ref', 300)->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20); // PENDING_REVIEW | UNDER_INVESTIGATION | CONFIRMED | REJECTED | REINSTATED | CLOSED
            $table->foreignUlid('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->json('history');
            $table->timestamps();
            $table->unique(['company_id', 'ref_no'], 'hr_lefty_cases_unique');
        });

        Schema::create('hr_settlements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('ref_no', 16);
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUlid('separation_id')->nullable()->constrained('hr_separations')->nullOnDelete();
            $table->foreignUlid('lefty_id')->nullable()->constrained('hr_lefty_cases')->nullOnDelete();
            $table->string('settlement_type', 32); // RESIGN_FROM_JOB | DISABLE_FOR_LONG_ABSENTEEISM | TERMINATION | RETIREMENT | DEATH
            $table->date('action_date');
            foreach (['salary_due', 'ot_due', 'bonus_due', 'leave_encashment', 'deduction', 'loan_recovery', 'other_adjustment', 'payable'] as $money) {
                $table->decimal($money, 12, 2)->default(0);
            }
            $table->text('remarks')->nullable();
            $table->text('notes')->nullable(); // how the figures were worked out
            $table->string('status', 10); // DRAFT | APPROVED | PAID
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'ref_no'], 'hr_settlements_unique');
        });
    }

    public function down(): void
    {
        foreach (['hr_settlements', 'hr_lefty_cases', 'hr_separations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

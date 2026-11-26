<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * HR & Payroll → Salary Processing (AnnotechERP 24/06):
 *
 * - `hr_daily_salaries`: one employee's pay for one day — the day's share of
 *   Gross A, allowance and OT less deductions — calculated, reviewed and
 *   approved (an approved day is not recalculated until it is un-approved).
 * - `hr_salary_runs`: a pay period's monthly payroll and where it stands —
 *   calculated, reviewed, approved, finalized (locked) and paid.
 * - `hr_salary_slips`: an employee's slip on the run: days, Gross A earned,
 *   allowances, OT, festival bonus, late and other deductions, loan/advance,
 *   adjustment and net, with the posting it was paid under.
 * - `hr_bonus_runs` / `hr_bonus_lines`: a festival's bonus sheet — every
 *   active employee, eligible with the rule and amount or not with why —
 *   calculated, reviewed, approved, finalized and paid; the payment month's
 *   slips carry the finalized bonus.
 * - `hr_payroll_adjustments`: a finalized slip is never rewritten; the
 *   difference (revised OT, or an amount to pay or recover) is raised,
 *   approved or rejected, and added once to the next monthly salary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_daily_salaries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->date('date');
            $table->string('attendance_status', 14); // the attendance status, or NO_RECORD
            $table->string('in_time', 5)->nullable();
            $table->string('out_time', 5)->nullable();
            $table->unsignedInteger('late_minutes')->default(0);
            $table->decimal('gross_a', 12, 2);
            $table->decimal('daily_salary', 12, 2);
            $table->decimal('allowance', 12, 2)->default(0);
            $table->decimal('ot_hours', 6, 2)->default(0);
            $table->decimal('ot_rate', 10, 2)->default(0);
            $table->decimal('ot_amount', 12, 2)->default(0);
            $table->decimal('late_deduction', 12, 2)->default(0);
            $table->decimal('other_deduction', 12, 2)->default(0);
            $table->decimal('net', 12, 2);
            $table->string('status', 12); // CALCULATED | REVIEWED | APPROVED
            $table->json('flags')->nullable();
            $table->foreignUlid('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
            $table->index(['company_id', 'date']);
        });

        Schema::create('hr_salary_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('pay_period_id')->unique()->constrained('hr_pay_periods')->restrictOnDelete();
            $table->string('status', 12); // CALCULATED | REVIEWED | APPROVED | FINALIZED | PAID
            $table->timestamp('run_at');
            $table->foreignUlid('run_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignUlid('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hr_salary_slips', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('run_id')->constrained('hr_salary_runs')->cascadeOnDelete();
            $table->foreignUlid('pay_period_id')->constrained('hr_pay_periods')->restrictOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUlid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignUlid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignUlid('line_id')->nullable()->constrained('production_lines')->nullOnDelete();
            $table->foreignUlid('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignUlid('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->decimal('gross_a', 12, 2);
            $table->decimal('gross_b', 12, 2)->default(0);
            $table->decimal('basic', 12, 2)->default(0);
            $table->unsignedSmallInteger('period_days');
            $table->unsignedSmallInteger('present_days')->default(0);
            $table->unsignedSmallInteger('absent_days')->default(0);
            $table->unsignedSmallInteger('unpaid_days')->default(0);
            $table->unsignedSmallInteger('payable_days');
            $table->decimal('leave_deduction', 12, 2)->default(0);
            $table->decimal('earned_gross', 12, 2);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->decimal('ot_hours', 8, 2)->default(0);
            $table->decimal('ot_amount', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->unsignedSmallInteger('late_days')->default(0);
            $table->decimal('late_deduction', 12, 2)->default(0);
            $table->decimal('stamp_charge', 12, 2)->default(0);
            $table->decimal('other_deduction', 12, 2)->default(0);
            $table->decimal('loan_advance', 12, 2)->default(0);
            $table->decimal('adjustment', 12, 2)->default(0);
            $table->decimal('net', 12, 2);
            $table->json('lines')->nullable(); // the allowance and deduction heads, for the payslip
            $table->string('status', 10)->default('DRAFT'); // DRAFT | PROCESSED | PAID
            $table->string('payment_method', 6)->default('CASH'); // BANK | CASH
            $table->boolean('held')->default(false);
            $table->string('structure')->nullable();
            $table->decimal('minimum_wage', 12, 2)->nullable();
            $table->string('minimum_basis', 12)->nullable(); // GRADE | FIXED
            $table->boolean('below_minimum')->default(false);
            $table->timestamps();
            $table->unique(['pay_period_id', 'employee_id']);
        });

        Schema::create('hr_bonus_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('festival_id')->unique()->constrained('hr_festivals')->restrictOnDelete();
            $table->foreignUlid('pay_period_id')->constrained('hr_pay_periods')->restrictOnDelete();
            $table->string('status', 10); // DRAFT | REVIEWED | APPROVED | FINALIZED | PAID
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamp('calculated_at');
            $table->foreignUlid('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignUlid('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hr_bonus_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('run_id')->constrained('hr_bonus_runs')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->boolean('eligible');
            $table->unsignedInteger('service_days')->nullable();
            $table->decimal('gross_a', 12, 2)->default(0);
            $table->string('basis', 5)->nullable(); // BASIC | GROSS
            $table->decimal('basis_amount', 12, 2)->default(0);
            $table->decimal('pct', 6, 2)->nullable();
            $table->decimal('fixed', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('rule')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->unique(['run_id', 'employee_id']);
        });

        Schema::create('hr_payroll_adjustments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('ref_no', 16);
            $table->foreignUlid('slip_id')->constrained('hr_salary_slips')->restrictOnDelete();
            $table->foreignUlid('pay_period_id')->constrained('hr_pay_periods')->restrictOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('kind', 6); // OT | OTHER
            $table->decimal('hours', 8, 2)->nullable();
            $table->decimal('rate', 10, 2)->nullable();
            $table->decimal('original', 12, 2);
            $table->decimal('revised', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            $table->string('status', 10); // PENDING | APPROVED | REJECTED | APPLIED
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignUlid('paid_in_period_id')->nullable()->constrained('hr_pay_periods')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'ref_no'], 'hr_payroll_adjustments_unique');
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['hr_payroll_adjustments', 'hr_bonus_lines', 'hr_bonus_runs', 'hr_salary_slips', 'hr_salary_runs', 'hr_daily_salaries'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

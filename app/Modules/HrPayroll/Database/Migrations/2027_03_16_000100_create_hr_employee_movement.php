<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * HR & Payroll → Employee Movement (AnnotechERP 24/07):
 *
 * - `hr_transfers`: an employee's move to another department, section or
 *   line (the floor and building follow the line) — the old and new posting,
 *   the effective date and reason, kept as a draft or finalized; finalizing
 *   moves the employee and closes their posting in the history.
 * - `hr_promotions`: a new designation, grade and (optionally) Gross A, sent
 *   for approval; approved and dated today or earlier it takes effect at
 *   once, dated ahead on its day.
 * - `hr_increments`: a new Gross A (given as the new salary, an amount or a
 *   percent), sent for approval and applied on its effective date. Gross B
 *   is never changed.
 * - `hr_rejoins`: a former employee back on their own record, with the pay
 *   period, the date, the re-join letter and remarks; what they left as is
 *   kept so a removed re-join can put it back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_transfers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('ref_no', 16);
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUlid('old_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignUlid('old_section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignUlid('old_line_id')->nullable()->constrained('production_lines')->nullOnDelete();
            $table->foreignUlid('old_floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->foreignUlid('new_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignUlid('new_section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignUlid('new_line_id')->nullable()->constrained('production_lines')->nullOnDelete();
            $table->foreignUlid('new_floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->date('effective_date');
            $table->text('reason')->nullable();
            $table->string('status', 10); // DRAFT | FINALIZED
            $table->foreignUlid('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'ref_no'], 'hr_transfers_unique');
        });

        Schema::create('hr_promotions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('ref_no', 16);
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUlid('old_designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignUlid('new_designation_id')->constrained('designations')->restrictOnDelete();
            $table->foreignUlid('old_grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->foreignUlid('new_grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->decimal('old_gross', 12, 2);
            $table->decimal('new_gross', 12, 2)->nullable();
            $table->date('effective_date');
            $table->text('reason');
            $table->string('status', 10); // PENDING | APPROVED | REJECTED
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'ref_no'], 'hr_promotions_unique');
            $table->index(['company_id', 'status', 'effective_date']);
        });

        Schema::create('hr_increments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('ref_no', 16);
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('mode', 8); // NEW | AMOUNT | PERCENT
            $table->decimal('value', 12, 2);
            $table->decimal('old_gross', 12, 2);
            $table->decimal('new_gross', 12, 2);
            $table->date('effective_date');
            $table->text('reason');
            $table->string('status', 10); // PENDING | APPROVED | REJECTED
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->foreignUlid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'ref_no'], 'hr_increments_unique');
            $table->index(['company_id', 'status', 'effective_date']);
        });

        Schema::create('hr_rejoins', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('ref_no', 16);
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUlid('pay_period_id')->nullable()->constrained('hr_pay_periods')->nullOnDelete();
            $table->date('rejoin_date');
            $table->string('doc_path', 500)->nullable();
            $table->ulid('doc_file_id')->nullable();
            $table->text('remarks')->nullable();
            $table->text('remarks_bn')->nullable();
            $table->string('previous_status', 12);
            $table->date('previous_separation_date')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'ref_no'], 'hr_rejoins_unique');
        });
    }

    public function down(): void
    {
        foreach (['hr_rejoins', 'hr_increments', 'hr_promotions', 'hr_transfers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

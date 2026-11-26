<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR & Payroll → Employee (AnnotechERP 24/03): the employee master.
 *
 * - `hr_employees`: the auto-numbered code (EMP-000001), the card / proxy
 *   number the gate, the floor and the store know them by, name, NID and the
 *   basic identity; the salary structure (a gazette's lines for an employee
 *   category) with Gross A (factory payroll), Gross B (reporting) and the
 *   actual salary; the current posting. The personal columns are filled
 *   from the Employee Profile.
 * - `hr_employee_assignments`: every posting — factory, building, floor,
 *   department, section, line, designation, grade and shift — the current
 *   one open, each earlier one closed the day it was transferred, with why.
 * - `hr_employee_salaries`: the salary as it was set, from its effective date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employees', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('employee_code', 24);
            $table->string('card_no', 40);
            $table->string('name');
            $table->string('nid', 20);
            $table->date('date_of_birth')->nullable();
            $table->date('joining_date')->nullable();
            $table->string('mobile', 20)->nullable();
            // ACTIVE, INACTIVE, SUSPENDED or SEPARATED.
            $table->string('status', 12)->default('ACTIVE');
            $table->foreignUlid('gazette_id')->constrained('hr_gazettes')->restrictOnDelete();
            $table->foreignUlid('employment_type_id')->constrained('hr_employment_types')->restrictOnDelete();
            $table->decimal('gross_a', 12, 2);
            $table->decimal('gross_b', 12, 2);
            $table->decimal('actual_salary', 12, 2)->nullable();
            // From the Employee Profile.
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('email')->nullable();
            $table->string('gender', 8)->nullable();
            $table->string('religion', 16)->nullable();
            $table->string('blood_group', 8)->nullable();
            $table->string('marital_status', 12)->nullable();
            $table->string('country', 64)->nullable();
            $table->string('rocket_no', 20)->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();
            // The current posting (its history is in hr_employee_assignments).
            $table->foreignUlid('factory_id')->nullable()->constrained('factories')->nullOnDelete();
            $table->foreignUlid('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->foreignUlid('floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->foreignUlid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignUlid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignUlid('line_id')->nullable()->constrained('production_lines')->nullOnDelete();
            $table->foreignUlid('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignUlid('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->foreignUlid('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'employee_code']);
            $table->unique(['company_id', 'card_no']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('hr_employee_assignments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->foreignUlid('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->foreignUlid('floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->foreignUlid('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignUlid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignUlid('line_id')->nullable()->constrained('production_lines')->nullOnDelete();
            $table->foreignUlid('designation_id')->constrained('designations')->restrictOnDelete();
            $table->foreignUlid('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->foreignUlid('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'effective_from']);
        });

        Schema::create('hr_employee_salaries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUlid('gazette_id')->constrained('hr_gazettes')->restrictOnDelete();
            $table->foreignUlid('employment_type_id')->constrained('hr_employment_types')->restrictOnDelete();
            $table->decimal('gross_a', 12, 2);
            $table->decimal('gross_b', 12, 2);
            $table->decimal('actual_salary', 12, 2)->nullable();
            $table->date('effective_from');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_salaries');
        Schema::dropIfExists('hr_employee_assignments');
        Schema::dropIfExists('hr_employees');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Technicians and labour rate grades: ERD Section 16 and 16.1, ADR-065.
 *
 * The system stores no salary. A technician carries a GRADE, and the grade
 * carries a standard rate. Two technicians on the same grade cost the same,
 * by design: this is a maintenance system, not a payroll system, and nobody
 * reading a work order's cost breakdown should be able to derive a
 * colleague's pay (SRS 3.3, 25.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labor_rate_grades', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->nullable()->constrained('factories')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->decimal('standard_hourly_rate', 18, 4)->default(0);
            // Twice the ordinary rate is the common Bangladesh Labour Act
            // treatment. Seeded as a default because it is a legal and
            // contractual matter, not a product decision.
            $table->decimal('overtime_multiplier', 9, 4)->default(2);
            $table->char('currency', 3)->default('BDT');
            // Effective-dated: changing a rate never rewrites the cost of work
            // already recorded.
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code', 'effective_from'], 'labor_grades_unique');
            $table->index(['company_id', 'active'], 'labor_grades_active_idx');
        });

        Schema::create('technicians', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            // Nullable: a technician may exist without a login account, and a
            // supervisor records their time for them (ERD Section 16).
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('labor_grade_id')->nullable()
                ->constrained('labor_rate_grades')->nullOnDelete();
            $table->foreignUlid('department_id')->nullable()->constrained('departments')->nullOnDelete();

            $table->string('employee_id', 64);
            $table->string('name');
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('specialization')->nullable();
            $table->date('joining_date')->nullable();
            $table->unsignedInteger('max_concurrent_work_orders')->nullable();
            $table->string('status', 32)->default('ACTIVE');
            $table->timestamps();

            // No salary, wage, bonus or payroll identifier: see the class note.
            $table->unique(['company_id', 'employee_id'], 'technicians_employee_unique');
            $table->index(['company_id', 'factory_id', 'status'], 'technicians_factory_idx');
        });

        Schema::create('technician_skills', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('technician_id')->constrained('technicians')->cascadeOnDelete();
            $table->string('skill_name');
            $table->string('proficiency', 16)->default('COMPETENT');
            $table->timestamps();

            $table->unique(['technician_id', 'skill_name'], 'technician_skills_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_skills');
        Schema::dropIfExists('technicians');
        Schema::dropIfExists('labor_rate_grades');
    }
};

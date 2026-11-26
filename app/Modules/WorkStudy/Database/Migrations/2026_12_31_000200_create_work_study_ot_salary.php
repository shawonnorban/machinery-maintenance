<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Work Study → OT & Salary (AnnotechERP 14/06), as Brickland keeps it:
 *
 * - `ws_salary_rates`: Monthly Salary & OT Rates — a month's average operator
 *   and ASO salary and overtime rate, which the line costing values a day and
 *   an overtime hour at.
 * - `ws_ot_plans`: Garment Overtime Plan — a manpower group on a floor (its
 *   line, department and designation) for one day, the general-hours
 *   headcount and hours, and how many operators and ASOs stay to each hour
 *   between 6PM and 5AM (`slots`, keyed by the hour of the working day).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ws_salary_rates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->char('period', 7);
            $table->decimal('op_salary', 12, 2);
            $table->decimal('aso_salary', 12, 2);
            $table->decimal('op_ot_rate', 10, 2);
            $table->decimal('aso_ot_rate', 10, 2);
            $table->timestamps();
            $table->unique(['company_id', 'period']);
        });

        Schema::create('ws_ot_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('plan_date');
            $table->foreignUlid('floor_id')->constrained('floors')->restrictOnDelete();
            $table->foreignUlid('planning_line_id')->nullable()->constrained('planning_lines')->nullOnDelete();
            $table->foreignUlid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignUlid('designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->unsignedSmallInteger('mp_gen');
            $table->decimal('wh_gen', 5, 2)->nullable();
            $table->decimal('prev_wh', 5, 2)->nullable();
            // {"9": {"op": 30, "aso": 6}, …}: the 9th hour of the working day is 6PM.
            $table->json('slots');
            $table->string('remarks', 255)->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'floor_id', 'plan_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ws_ot_plans');
        Schema::dropIfExists('ws_salary_rates');
    }
};

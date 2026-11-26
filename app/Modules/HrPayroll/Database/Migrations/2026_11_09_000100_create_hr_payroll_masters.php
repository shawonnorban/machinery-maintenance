<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * HR & Payroll → Master Data (AnnotechERP 24/01): employment types and
 * employee categories, pay elements, allowances and deductions per pay
 * period, shifts, leave types, OT and bonus rules, wage gazettes with their
 * policies, structure lines and grade wages, festivals and holidays, payroll
 * settings and pay periods. Pay grades are Company Management's `grades`
 * (Grade# [B] added here); fiscal years Finance & Ledger's register; holidays
 * are put on the platform's factory calendar. Until the employee master
 * (24/03) arrives, an allowance or deduction names a company user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table): void {
            $table->string('code_bn', 32)->nullable()->after('code');
        });

        Schema::create('hr_employment_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('name_bn');
            $table->string('short_name', 16);
            $table->ulid('parent_id')->nullable();
            $table->unsignedSmallInteger('level')->default(1);
            $table->boolean('is_leaf')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name'], 'hr_employment_types_unique');
        });
        Schema::table('hr_employment_types', fn (Blueprint $t) => $t->foreign('parent_id')->references('id')->on('hr_employment_types')->restrictOnDelete());

        Schema::create('hr_designation_groups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('name_bn');
            $table->string('short_name', 32);
            $table->ulid('parent_id')->nullable();
            $table->unsignedSmallInteger('level')->default(1);
            $table->unsignedInteger('sort_order');
            $table->string('manpower_category', 16)->nullable(); // OPERATOR | QUALITY | HELPER | SUPERVISOR | STAFF
            $table->boolean('is_leaf')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name'], 'hr_designation_groups_unique');
        });
        Schema::table('hr_designation_groups', fn (Blueprint $t) => $t->foreign('parent_id')->references('id')->on('hr_designation_groups')->restrictOnDelete());

        Schema::create('hr_pay_elements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'hr_pay_elements_unique');
        });

        Schema::create('hr_pay_periods', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('period_no', 40);
            $table->string('period_type', 8); // MONTHLY | DAYS
            $table->foreignUlid('fiscal_year_id')->constrained('acc_fiscal_years')->restrictOnDelete();
            $table->unsignedTinyInteger('month');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('pay_date');
            $table->string('status', 12)->default('INITIATED'); // INITIATED | SUBMITTED | ACTIVATED | CLOSED
            $table->foreignUlid('pay_element_id')->nullable()->constrained('hr_pay_elements')->nullOnDelete();
            $table->string('dept_type', 16)->nullable(); // PRODUCTION | NON_PRODUCTION
            $table->text('remarks')->nullable();
            $table->boolean('show_for_report')->default(true);
            $table->boolean('closed')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'period_no'], 'hr_pay_periods_unique');
        });

        // An allowance or deduction for one employee in one pay period.
        foreach (['hr_allowances', 'hr_deductions'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->ulid('id')->primary();
                $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignUlid('pay_period_id')->constrained('hr_pay_periods')->cascadeOnDelete();
                $table->foreignUlid('pay_element_id')->constrained('hr_pay_elements')->restrictOnDelete();
                $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->timestamps();
                $table->unique(['pay_period_id', 'pay_element_id', 'user_id'], "{$name}_unique");
            });
        }

        Schema::create('hr_shifts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('name');
            $table->string('start_time', 5);
            $table->string('end_time', 5);
            $table->unsignedSmallInteger('break_min')->nullable();
            $table->decimal('working_hours', 4, 2)->nullable();
            $table->unsignedSmallInteger('grace_min')->nullable();
            $table->boolean('cross_day')->default(false);
            $table->boolean('ot_allowed')->default(false);
            $table->boolean('ot_approval')->default(false);
            $table->date('effective_date')->nullable();
            $table->boolean('active')->default(true);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'hr_shifts_unique');
        });

        Schema::create('hr_leave_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 8);
            $table->string('color', 7)->nullable();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->boolean('paid')->default(true);
            $table->foreignUlid('pay_element_id')->nullable()->constrained('hr_pay_elements')->nullOnDelete();
            $table->boolean('day_forward')->default(false);
            $table->unsignedSmallInteger('forward_days')->nullable();
            $table->boolean('accrual')->default(false);
            $table->boolean('accrual_auto')->default(false);
            $table->boolean('offday_included')->default(false);
            $table->boolean('holiday_included')->default(false);
            $table->boolean('balance_check')->default(false);
            $table->string('leave_group', 8); // LEAVE | PRESENT | ABSENT
            $table->unsignedSmallInteger('min_employment')->nullable();
            $table->string('min_unit', 6)->nullable(); // DAY | MONTH | YEAR
            $table->unsignedSmallInteger('notify_days')->nullable();
            $table->unsignedSmallInteger('max_days')->nullable();
            $table->unsignedSmallInteger('min_work_days')->nullable();
            $table->string('applicable_for', 8); // MALE | FEMALE | COMMON
            $table->boolean('encashable')->default(false);
            $table->boolean('active')->default(true);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'hr_leave_types_unique');
        });

        Schema::create('hr_ot_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('name');
            $table->string('wage_basis', 5); // BASIC | GROSS
            $table->decimal('divisor', 8, 2);
            $table->decimal('multiplier', 5, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignUlid('employment_type_id')->nullable()->constrained('hr_employment_types')->nullOnDelete();
            $table->foreignUlid('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->string('legal_ref')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'hr_ot_rules_unique');
        });

        foreach (['hr_bonus_rule_sets', 'hr_shift_teams'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->ulid('id')->primary();
                $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('name');
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->unique(['company_id', 'name'], "{$name}_unique");
            });
        }

        Schema::create('hr_bonus_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('rule_set_id')->constrained('hr_bonus_rule_sets')->restrictOnDelete();
            $table->unsignedInteger('service_low');
            $table->unsignedInteger('service_high');
            $table->decimal('salary_low', 12, 2);
            $table->decimal('salary_high', 12, 2);
            $table->string('basis', 5); // BASIC | GROSS
            $table->decimal('pct', 6, 2)->nullable();
            $table->decimal('fixed', 12, 2)->nullable();
            $table->foreignUlid('employment_type_id')->constrained('hr_employment_types')->restrictOnDelete();
            $table->foreignUlid('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignUlid('designation_group_id')->nullable()->constrained('hr_designation_groups')->nullOnDelete();
            $table->foreignUlid('shift_team_id')->nullable()->constrained('hr_shift_teams')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('hr_gazettes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('number')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('industry')->nullable();
            $table->date('effective_date');
            $table->boolean('active')->default(true);
            $table->string('reference')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'name'], 'hr_gazettes_unique');
        });

        Schema::create('hr_gazette_policies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('gazette_id')->constrained('hr_gazettes')->cascadeOnDelete();
            $table->string('code', 24);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['gazette_id', 'code'], 'hr_gazette_policies_unique');
        });

        // Wage/Gazette Rule: what a gazette sets per employee category and pay element.
        Schema::create('hr_gazette_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('gazette_id')->constrained('hr_gazettes')->cascadeOnDelete();
            $table->foreignUlid('employment_type_id')->constrained('hr_employment_types')->restrictOnDelete();
            $table->foreignUlid('pay_element_id')->constrained('hr_pay_elements')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['gazette_id', 'employment_type_id', 'pay_element_id'], 'hr_gazette_lines_unique');
        });

        Schema::create('hr_gazette_grade_wages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('gazette_id')->constrained('hr_gazettes')->cascadeOnDelete();
            $table->foreignUlid('employment_type_id')->constrained('hr_employment_types')->restrictOnDelete();
            $table->foreignUlid('grade_id')->constrained('grades')->restrictOnDelete();
            $table->decimal('basic', 12, 2);
            $table->decimal('house_rent_pct', 5, 2);
            $table->decimal('medical', 12, 2);
            $table->decimal('conveyance', 12, 2);
            $table->decimal('food', 12, 2);
            $table->decimal('other', 12, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['gazette_id', 'employment_type_id', 'grade_id'], 'hr_gazette_grade_wages_unique');
        });

        Schema::create('hr_holidays', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('fiscal_year_id')->constrained('acc_fiscal_years')->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('type', 12); // COMPANY | GOVT | WEEKLY_OFF
            $table->date('start_date')->nullable();
            $table->unsignedSmallInteger('days')->default(1);
            $table->boolean('recurring')->default(false);
            $table->unsignedInteger('calendar_days')->default(0);
            $table->text('remarks')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'hr_holidays_unique');
        });

        Schema::create('hr_festivals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->date('festival_date');
            $table->unsignedSmallInteger('year');
            $table->string('payment_month', 7); // YYYY-MM
            $table->foreignUlid('bonus_rule_set_id')->nullable()->constrained('hr_bonus_rule_sets')->nullOnDelete();
            $table->string('bonus_type', 16)->nullable(); // ATTENDANCE | EID_ADHA | EID_FITR | PRODUCTION
            $table->unsignedSmallInteger('min_service')->nullable();
            $table->boolean('active')->default(true);
            $table->text('eligibility')->nullable();
            $table->text('reference')->nullable();
            $table->foreignUlid('holiday_id')->nullable()->constrained('hr_holidays')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'name', 'year'], 'hr_festivals_unique');
        });

        Schema::create('hr_payroll_settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('key', 32);
            $table->string('value', 32);
            $table->timestamps();
            $table->unique(['company_id', 'key'], 'hr_payroll_settings_unique');
        });
    }

    public function down(): void
    {
        foreach (['hr_payroll_settings', 'hr_festivals', 'hr_holidays', 'hr_gazette_grade_wages', 'hr_gazette_lines', 'hr_gazette_policies', 'hr_gazettes', 'hr_bonus_rules', 'hr_shift_teams', 'hr_bonus_rule_sets', 'hr_ot_rules', 'hr_leave_types', 'hr_shifts', 'hr_deductions', 'hr_allowances', 'hr_pay_periods', 'hr_pay_elements', 'hr_designation_groups', 'hr_employment_types'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('grades', fn (Blueprint $table) => $table->dropColumn('code_bn'));
    }
};

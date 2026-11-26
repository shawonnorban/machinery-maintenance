<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * HR & Payroll → Employee → Employee Profile (AnnotechERP 24/03), as Brickland
 * keeps an employee's full record:
 *
 * - `hr_employees` gains the profile's columns: the rest of Personal
 *   Information, the separation date and Active, the Contact Detail (post
 *   codes, the emergency contact), the Document tab (passport, driving
 *   licence, the photograph), and Optional (Bangla name, education, spouse,
 *   family and festival-OT flags). NID becomes optional: Quick Registration
 *   still asks for it, the profile does not.
 * - `hr_employee_pay_lines`: the allowances and deductions that make up an
 *   employee's gross — one line per pay element.
 * - `hr_employee_background_checks`: verification runs against an employee,
 *   with who cleared each and when it was filed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->string('nid', 20)->nullable()->change();
            $table->string('first_name', 80)->nullable();
            $table->string('last_name', 80)->nullable();
            $table->string('nick_name', 80)->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_relation', 12)->nullable();
            $table->string('birth_certificate_no', 40)->nullable();
            $table->string('nationality', 64)->nullable();
            $table->unsignedTinyInteger('child_count')->nullable();
            $table->string('home_phone', 30)->nullable();
            $table->string('home_phone_ext', 10)->nullable();
            $table->string('tin', 30)->nullable();
            $table->date('separation_date')->nullable();
            $table->boolean('active')->default(true);
            $table->string('present_post_code', 12)->nullable();
            $table->string('permanent_post_code', 12)->nullable();
            $table->string('emergency_name')->nullable();
            $table->string('emergency_relation', 12)->nullable();
            $table->string('emergency_mobile', 20)->nullable();
            $table->text('emergency_address')->nullable();
            $table->string('passport_no', 30)->nullable();
            $table->string('passport_issue_place', 80)->nullable();
            $table->date('passport_issue_date')->nullable();
            $table->date('passport_expire_date')->nullable();
            $table->string('driving_license_no', 30)->nullable();
            $table->date('driving_license_expire')->nullable();
            $table->ulid('photo_file_id')->nullable();
            $table->string('name_bn')->nullable();
            $table->string('higher_education', 120)->nullable();
            $table->string('spouse_name')->nullable();
            $table->string('spouse_relation', 12)->nullable();
            $table->string('spouse_employee_code', 24)->nullable();
            $table->boolean('stay_with_family')->default(false);
            $table->boolean('festival_ot_eligible')->default(false);
        });

        Schema::create('hr_employee_pay_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUlid('pay_element_id')->constrained('hr_pay_elements')->restrictOnDelete();
            $table->string('type', 10); // ALLOWANCE | DEDUCTION
            $table->boolean('core_salary')->default(false);
            $table->decimal('amount', 12, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('effect_salary')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['employee_id', 'pay_element_id'], 'hr_employee_pay_lines_unique');
        });

        Schema::create('hr_employee_background_checks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('check_type', 120);
            $table->string('verified_by', 160)->nullable();
            $table->date('verified_on');
            $table->boolean('cleared')->default(false);
            $table->string('remarks', 400)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_background_checks');
        Schema::dropIfExists('hr_employee_pay_lines');
        Schema::table('hr_employees', function (Blueprint $table): void {
            $table->dropColumn(['first_name', 'last_name', 'nick_name', 'guardian_name', 'guardian_relation', 'birth_certificate_no', 'nationality', 'child_count', 'home_phone', 'home_phone_ext', 'tin', 'separation_date', 'active',
                'present_post_code', 'permanent_post_code', 'emergency_name', 'emergency_relation', 'emergency_mobile', 'emergency_address', 'passport_no', 'passport_issue_place', 'passport_issue_date', 'passport_expire_date',
                'driving_license_no', 'driving_license_expire', 'photo_file_id', 'name_bn', 'higher_education', 'spouse_name', 'spouse_relation', 'spouse_employee_code', 'stay_with_family', 'festival_ot_eligible']);
        });
    }
};

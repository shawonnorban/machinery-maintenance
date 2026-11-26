<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A technician is picked from the HR & Payroll employee list (the user's
 * decision, Open Question on Technician ↔ HR employee): the roster row
 * points at the employee, and its name, code, mobile, email and joining date
 * come from there rather than being typed a second time.
 *
 * Nullable: a technician entered before the link existed stays valid until
 * someone opens it and picks the employee. One employee, one technician.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technicians', function (Blueprint $table): void {
            $table->foreignUlid('hr_employee_id')->nullable()->after('user_id')
                ->constrained('hr_employees')->nullOnDelete();
            $table->unique(['company_id', 'hr_employee_id'], 'technicians_hr_employee_unique');
        });
    }

    public function down(): void
    {
        Schema::table('technicians', function (Blueprint $table): void {
            $table->dropUnique('technicians_hr_employee_unique');
            $table->dropConstrainedForeignId('hr_employee_id');
        });
    }
};

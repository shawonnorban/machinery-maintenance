<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quality Tally, phase A (user 2026-11-24, the rest of annotech-rmg):
 *
 *  - a job also has a size and a due date; its identity is job no + PO +
 *    colour + size;
 *  - a tap names the HR & Payroll shift it was made in. A night shift that
 *    crosses midnight files its 02:00 taps on the day the shift started, and
 *    the production date is fixed at write time — re-timing a shift next year
 *    must not move last year's taps;
 *  - line attendance: how many people a line had on a day (and shift) and its
 *    overtime minutes — what efficiency divides by. One row per line, day and
 *    shift ('' = the company runs no HR shifts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qt_jobs', function (Blueprint $table): void {
            $table->string('size', 64)->default('')->after('color');
            $table->date('due_date')->nullable()->after('smv');
            $table->dropUnique('qt_jobs_identity_unique');
        });
        Schema::table('qt_jobs', function (Blueprint $table): void {
            $table->unique(['company_id', 'job_no', 'po_no', 'color', 'size'], 'qt_jobs_identity_unique');
            $table->index(['company_id', 'due_date']);
        });

        Schema::table('qt_entries', function (Blueprint $table): void {
            $table->foreignUlid('shift_id')->nullable()->after('user_id')->constrained('hr_shifts')->nullOnDelete();
        });

        Schema::create('qt_line_attendances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('att_date');
            $table->string('section', 12);
            $table->ulid('line_id');
            $table->string('shift_id', 26)->default(''); // '' = no HR shift
            $table->unsignedSmallInteger('attendance');
            $table->unsignedSmallInteger('overtime_minutes')->default(0);
            $table->foreignUlid('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'att_date', 'section', 'line_id', 'shift_id'], 'qt_attendance_unique');
        });

        DB::statement('create index qt_entries_shift_idx on qt_entries (company_id, production_date, shift_id)');
    }

    public function down(): void
    {
        DB::statement('drop index if exists qt_entries_shift_idx');
        Schema::dropIfExists('qt_line_attendances');
        Schema::table('qt_entries', fn (Blueprint $table) => $table->dropConstrainedForeignId('shift_id'));
        Schema::table('qt_jobs', function (Blueprint $table): void {
            $table->dropUnique('qt_jobs_identity_unique');
            $table->dropIndex(['company_id', 'due_date']);
            $table->dropColumn(['size', 'due_date']);
        });
        Schema::table('qt_jobs', fn (Blueprint $table) => $table->unique(['company_id', 'job_no', 'po_no', 'color'], 'qt_jobs_identity_unique'));
    }
};

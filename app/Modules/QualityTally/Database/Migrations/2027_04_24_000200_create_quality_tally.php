<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quality Tally (AnnotechERP 36, from annotech-rmg's QC tally; user
 * 2026-11-24): a module of its own — its own jobs and line targets in, its
 * own tally out — sold separately (LicensedModules QUALITY_TALLY).
 *
 *  - qt_defect_types: each section's defect list; Cutting, Sewing and Quality
 *    check for different faults.
 *  - qt_jobs: the jobs tallied (buyer, style, PO, colour, order quantity, SMV).
 *  - qt_line_targets: a line's target for a job on a day, with its hours and
 *    manpower (the hourly target is target ÷ hours).
 *  - qt_inspectors / qt_inspector_lines: a QC is a user (who signs in and taps)
 *    tied to an HR employee, and taps only on the lines given to them.
 *  - qt_entries: one row per tap on one garment. `action` is one of four
 *    INDEPENDENT counters — RFT, DEFECT, RECTIFY, REJECT. `occurred_at` is when
 *    the tap happened (a tap queued offline at 09:15 and synced at 14:00
 *    belongs to the 09:00 hour), and `client_uuid` makes an offline replay
 *    idempotent: the unique index turns a repeat into a no-op.
 *  - qt_entry_defects: the defect tags of a DEFECT tap. One garment may carry
 *    several; THIS row count is the DHU numerator, not the number of taps.
 *
 * Lines are not copied: sewing lines are Capacity Planning's planning lines,
 * cutting and quality lines Company Management's production lines of that
 * type (QtBook::lines) — the ids are kept without a foreign key, as
 * Production Tracking does, since they live in two tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qt_defect_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('section', 12); // CUTTING | SEWING | QUALITY
            $table->string('code', 20);
            $table->string('name', 120);
            $table->string('name_bn', 120)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'section', 'code']);
        });

        Schema::create('qt_jobs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('job_no', 40);
            $table->string('buyer_name', 120);
            $table->string('style', 120);
            $table->string('po_no', 64)->default('');
            $table->string('color', 64)->default('');
            $table->unsignedInteger('order_qty')->default(0);
            $table->decimal('smv', 8, 3)->nullable();
            $table->string('status', 10)->default('OPEN'); // OPEN | CLOSED
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'job_no', 'po_no', 'color'], 'qt_jobs_identity_unique');
            $table->index(['company_id', 'status']);
        });

        Schema::create('qt_line_targets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('target_date');
            $table->string('section', 12);
            $table->ulid('line_id');
            $table->foreignUlid('job_id')->constrained('qt_jobs')->restrictOnDelete();
            $table->decimal('working_hours', 4, 2);
            $table->unsignedSmallInteger('manpower')->nullable();
            $table->unsignedInteger('target_qty');
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'target_date', 'section', 'line_id', 'job_id'], 'qt_targets_unique');
        });

        Schema::create('qt_inspectors', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('hr_employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
            $table->unique(['company_id', 'hr_employee_id']);
        });

        Schema::create('qt_inspector_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('inspector_id')->constrained('qt_inspectors')->cascadeOnDelete();
            $table->string('section', 12);
            $table->ulid('line_id');
            $table->timestamps();
            $table->unique(['inspector_id', 'section', 'line_id']);
        });

        Schema::create('qt_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->uuid('client_uuid')->nullable();
            $table->string('section', 12);
            $table->ulid('line_id');
            $table->foreignUlid('job_id')->constrained('qt_jobs')->restrictOnDelete();
            $table->foreignUlid('inspector_id')->nullable()->constrained('qt_inspectors')->nullOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 10); // RFT | DEFECT | RECTIFY | REJECT
            $table->timestamp('occurred_at');
            $table->date('production_date');
            $table->unsignedTinyInteger('hour');
            $table->boolean('synced_late')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'client_uuid']);
            $table->index(['company_id', 'production_date', 'section', 'line_id', 'job_id'], 'qt_entries_day_idx');
            $table->index(['company_id', 'user_id', 'created_at']);
        });

        Schema::create('qt_entry_defects', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('entry_id')->constrained('qt_entries')->cascadeOnDelete();
            $table->foreignUlid('defect_type_id')->constrained('qt_defect_types')->restrictOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'defect_type_id']);
            $table->index('entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qt_entry_defects');
        Schema::dropIfExists('qt_entries');
        Schema::dropIfExists('qt_inspector_lines');
        Schema::dropIfExists('qt_inspectors');
        Schema::dropIfExists('qt_line_targets');
        Schema::dropIfExists('qt_jobs');
        Schema::dropIfExists('qt_defect_types');
    }
};

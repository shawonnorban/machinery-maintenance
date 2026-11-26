<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cutting is checked PANEL by panel, not garment by garment (user,
 * 2026-11-25, Norban Comtex's "100% Cut Panel Inspection Report",
 * NCL_CTG QA_006):
 *
 *  - qt_panels: the panels of each garment type (T-Shirt: Back, Front,
 *    Sleeve; Hoody: … Hood Top, Hood Inner …);
 *  - qt_panel_inspections: one report — a cutting table, a job, the cutting
 *    and batch numbers and the cut quantity, on a day;
 *  - qt_panel_inspection_rows: one bundle (no, serial range, quantity, size)
 *    and the panel checked, with how many were checked and rejected;
 *  - qt_panel_inspection_defects: how many of that row's panels carried each
 *    cutting defect (B Bias & Bowing … Y1 Yarn contamination). A panel can
 *    carry two faults, so the reject count is stored, not summed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qt_panels', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('garment_type', 40);
            $table->string('code', 12)->default('');
            $table->string('name', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'garment_type', 'name']);
        });

        Schema::create('qt_panel_inspections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('inspection_no', 32);
            $table->date('inspection_date');
            $table->ulid('line_id'); // a cutting table (production line of type CUTTING)
            $table->foreignUlid('job_id')->constrained('qt_jobs')->restrictOnDelete();
            $table->string('garment_type', 40);
            $table->string('cutting_no', 40)->default('');
            $table->string('batch_no', 40)->default('');
            $table->unsignedInteger('cut_qty')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUlid('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'inspection_no']);
            $table->index(['company_id', 'inspection_date']);
            $table->index(['company_id', 'job_id']);
        });

        Schema::create('qt_panel_inspection_rows', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('inspection_id')->constrained('qt_panel_inspections')->cascadeOnDelete();
            $table->unsignedSmallInteger('sl');
            $table->string('bundle_no', 20)->default('');
            $table->string('bundle_serial', 30)->default('');
            $table->unsignedInteger('bundle_qty')->default(0);
            $table->string('size', 20)->default('');
            $table->foreignUlid('panel_id')->constrained('qt_panels')->restrictOnDelete();
            $table->unsignedInteger('checked_qty');
            $table->unsignedInteger('reject_qty')->default(0);
            $table->timestamps();
            $table->index('inspection_id');
        });

        Schema::create('qt_panel_inspection_defects', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('row_id')->constrained('qt_panel_inspection_rows')->cascadeOnDelete();
            $table->foreignUlid('defect_type_id')->constrained('qt_defect_types')->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->timestamps();
            $table->index('row_id');
            $table->index(['company_id', 'defect_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qt_panel_inspection_defects');
        Schema::dropIfExists('qt_panel_inspection_rows');
        Schema::dropIfExists('qt_panel_inspections');
        Schema::dropIfExists('qt_panels');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The cut panel inspection is the QC's TALLY, not a form typed in afterwards
 * (user, 2026-11-25: "এমপ্লয়ি টালি দিয়ে প্যানেল ভিত্তিক চেক করবে, সেই রেকর্ড
 * গুলোই ইন্সপেকশন এ থাকবে"). A cutting tap is one panel checked: OK (RFT) or
 * rejected (DEFECT, with its codes), and it carries the lot (garment, cutting
 * no, batch no, cut qty) and the bundle (no, serial, qty, size) it belongs
 * to. The 100% Cut Panel Inspection Report is built from those taps.
 *
 * The typed-in report tables of the morning go: they held one sample report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qt_entries', function (Blueprint $table): void {
            $table->foreignUlid('panel_id')->nullable()->after('shift_id')->constrained('qt_panels')->restrictOnDelete();
            $table->string('garment_type', 40)->nullable()->after('panel_id');
            $table->string('cutting_no', 40)->nullable()->after('garment_type');
            $table->string('batch_no', 40)->nullable()->after('cutting_no');
            $table->unsignedInteger('cut_qty')->nullable()->after('batch_no');
            $table->string('bundle_no', 20)->nullable()->after('cut_qty');
            $table->string('bundle_serial', 30)->nullable()->after('bundle_no');
            $table->unsignedInteger('bundle_qty')->nullable()->after('bundle_serial');
            $table->string('size', 20)->nullable()->after('bundle_qty');
        });
        DB::statement('create index qt_entries_cutting_lot_idx on qt_entries (company_id, production_date, line_id, job_id, cutting_no) where panel_id is not null');

        Schema::dropIfExists('qt_panel_inspection_defects');
        Schema::dropIfExists('qt_panel_inspection_rows');
        Schema::dropIfExists('qt_panel_inspections');
    }

    public function down(): void
    {
        DB::statement('drop index if exists qt_entries_cutting_lot_idx');
        Schema::table('qt_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('panel_id');
            $table->dropColumn(['garment_type', 'cutting_no', 'batch_no', 'cut_qty', 'bundle_no', 'bundle_serial', 'bundle_qty', 'size']);
        });
    }
};

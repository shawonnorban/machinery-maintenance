<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each section's manager gives the input; the QC only checks (user,
 * 2026-11-25). A job starts at cutting and ends at quality:
 *
 *  - qt_cut_bundles: the cutting manager's input — the bundle cards the
 *    cutting department made after cutting (scanned from Cutting Floor's
 *    cut_bundles, or typed in), each a part (panel) of a size and serial run,
 *    given to a cutting QC table. The QC scans the card and taps its panels.
 *  - qt_line_inputs: pieces given to a sewing or quality line by that
 *    section's manager — never more than the section before has passed
 *    (sewing: complete pieces out of cutting; quality: sewing's pass).
 *  - qt_entries.cut_bundle_id: the bundle a cutting tap was made on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qt_cut_bundles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('barcode', 40);
            $table->string('bundle_no', 40);
            $table->foreignUlid('job_id')->constrained('qt_jobs')->restrictOnDelete();
            $table->ulid('line_id'); // the cutting QC table it is checked on
            $table->string('garment_type', 40);
            $table->foreignUlid('panel_id')->nullable()->constrained('qt_panels')->restrictOnDelete();
            $table->string('size', 32)->default('');
            $table->unsignedInteger('serial_from')->nullable();
            $table->unsignedInteger('serial_to')->nullable();
            $table->unsignedInteger('qty');
            $table->string('cutting_no', 40)->default('');
            $table->string('batch_no', 40)->default('');
            $table->date('input_date');
            $table->string('source', 10)->default('MANUAL'); // SCAN | MANUAL
            $table->ulid('source_id')->nullable(); // cut_bundles.id when scanned
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'barcode']);
            $table->index(['company_id', 'input_date', 'line_id']);
            $table->index(['company_id', 'job_id']);
        });

        Schema::create('qt_line_inputs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('input_date');
            $table->string('section', 12); // SEWING | QUALITY
            $table->ulid('line_id');
            $table->foreignUlid('job_id')->constrained('qt_jobs')->restrictOnDelete();
            $table->string('size', 32)->default('');
            $table->unsignedInteger('qty');
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'section', 'job_id']);
            $table->index(['company_id', 'input_date', 'section', 'line_id']);
        });

        Schema::table('qt_entries', function (Blueprint $table): void {
            $table->foreignUlid('cut_bundle_id')->nullable()->after('panel_id')->constrained('qt_cut_bundles')->restrictOnDelete();
        });
        DB::statement('create index qt_entries_cut_bundle_idx on qt_entries (company_id, cut_bundle_id, panel_id) where cut_bundle_id is not null');
    }

    public function down(): void
    {
        DB::statement('drop index if exists qt_entries_cut_bundle_idx');
        Schema::table('qt_entries', fn (Blueprint $table) => $table->dropConstrainedForeignId('cut_bundle_id'));
        Schema::dropIfExists('qt_line_inputs');
        Schema::dropIfExists('qt_cut_bundles');
    }
};

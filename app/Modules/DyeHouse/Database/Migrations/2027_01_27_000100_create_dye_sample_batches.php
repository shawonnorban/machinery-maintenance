<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Dye House → Batch (Sample) (AnnotechERP 17/04), on 17/03's batch cards
 * (a sample card is a `dye_batches` row of scope SAMPLE, S-YYMM####):
 *
 * - `dye_grey_indents.party_id`: the company a sample requisition is raised for.
 * - `dye_productions` / `dye_production_loads`: a batch's output on a day — the
 *   weight that went on, machine, model/combo, lab dip, liquor ratio — and one
 *   row per load (machine, load and unload time, type, status). Bulk
 *   production (17/07) writes the same rows.
 * - `dye_finishing_qcs`: the shade, hand-feel and GSM verdict on a finished batch,
 *   one per batch per day.
 * - `dye_sample_transfers`: a sample drawn out of a bulk batch onto a card of its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dye_grey_indents', function (Blueprint $table): void {
            $table->foreignUlid('party_id')->nullable()->after('store_id')->constrained('companies')->nullOnDelete();
        });

        Schema::create('dye_productions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('batch_id')->constrained('dye_batches')->cascadeOnDelete();
            $table->date('production_date');
            $table->foreignUlid('requisition_id')->nullable()->constrained('dye_grey_indents')->nullOnDelete();
            $table->decimal('weight_kg', 14, 3)->nullable();
            $table->foreignUlid('dye_machine_id')->nullable()->constrained('dye_machines')->nullOnDelete();
            $table->string('model_combo', 120)->nullable();
            $table->string('lab_dip_ref', 40)->nullable();
            $table->string('liquor_ratio', 16);
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['batch_id', 'production_date'], 'dye_productions_day_unique');
        });

        Schema::create('dye_production_loads', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('production_id')->constrained('dye_productions')->cascadeOnDelete();
            $table->foreignUlid('dye_machine_id')->nullable()->constrained('dye_machines')->nullOnDelete();
            $table->timestamp('load_time')->nullable();
            $table->timestamp('unload_time')->nullable();
            $table->string('load_type', 24)->default('Dyeing');
            $table->string('status', 10)->nullable(); // LOADED | RUNNING | UNLOADED | ABORTED
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('dye_finishing_qcs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('scope', 8)->default('SAMPLE'); // SAMPLE | BULK
            $table->foreignUlid('batch_id')->constrained('dye_batches')->cascadeOnDelete();
            $table->date('qc_date');
            $table->string('result', 8); // PENDING | PASSED | FAILED | REWORK
            $table->string('model_combo', 120)->nullable();
            $table->decimal('gsm', 8, 2)->nullable();
            $table->decimal('width_inch', 8, 2)->nullable();
            $table->foreignUlid('checked_by')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->string('shade_note')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['batch_id', 'qc_date'], 'dye_finishing_qcs_day_unique');
        });

        Schema::create('dye_sample_transfers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('transfer_no', 24);
            $table->date('transfer_date');
            $table->foreignUlid('batch_id')->constrained('dye_batches');
            $table->foreignUlid('new_batch_id')->nullable()->constrained('dye_batches')->nullOnDelete();
            $table->string('new_batch_no', 24);
            $table->string('lot_no', 40)->nullable();
            $table->date('batch_date');
            $table->decimal('batch_qty', 14, 2);
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'transfer_no'], 'dye_sample_transfers_no_unique');
        });
    }

    public function down(): void
    {
        foreach (['dye_sample_transfers', 'dye_finishing_qcs', 'dye_production_loads', 'dye_productions'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('dye_grey_indents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('party_id');
        });
    }
};

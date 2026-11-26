<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Finishing Line → QC Process (AnnotechERP 18/04): the roll-wise readings
 * taken as a batch comes off a machine; the batch's shade, GSM and width
 * decision against the buyer's standard; the four-point inspection of its
 * rolls, whose posted verdict moves the batch on the floor board; and the
 * re-process the floor is advised to run on what a QC check rejected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_qc_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('entry_date');
            $table->foreignUlid('receipt_id')->constrained('fin_batch_receipts')->restrictOnDelete();
            $table->foreignUlid('machine_id')->nullable()->constrained('fin_machines')->nullOnDelete();
            $table->foreignUlid('process_id')->nullable()->constrained('fin_process_types')->nullOnDelete();
            $table->string('shift', 8);
            $table->string('roll_no', 40);
            $table->decimal('weight_kg', 12, 3);
            $table->decimal('gsm', 8, 2)->nullable();
            $table->decimal('dia', 8, 2)->nullable();
            $table->string('shade', 8); // OK | OFF
            $table->string('result', 8); // OK | FAULT
            $table->string('defect')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['receipt_id', 'roll_no'], 'fin_qc_entries_roll_unique');
        });

        Schema::create('fin_shade_decisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->constrained('fin_batch_receipts')->restrictOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('style', 120)->nullable();
            $table->string('colour', 120)->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->decimal('batch_qty', 14, 3)->nullable();
            $table->string('model_continuity', 120)->nullable();
            $table->string('status', 10)->default('PENDING'); // PENDING | APPROVED | REJECTED
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('receipt_id');
        });

        Schema::create('fin_inspections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('ref_no', 40);
            $table->foreignUlid('receipt_id')->constrained('fin_batch_receipts')->restrictOnDelete();
            $table->date('insp_date');
            $table->string('shift', 8);
            $table->decimal('actual_dia', 8, 2)->nullable();
            $table->decimal('shrinkage_l', 6, 2)->nullable();
            $table->decimal('shrinkage_w', 6, 2)->nullable();
            $table->decimal('spirality', 6, 2)->nullable();
            $table->foreignUlid('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('shift_incharge_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('qc_incharge_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('verdict', 8); // PASS | HOLD | REJECT
            $table->text('shade_note')->nullable();
            $table->text('qc_comment')->nullable();
            $table->unsignedInteger('roll_count')->default(0);
            $table->decimal('total_kg', 14, 3)->default(0);
            $table->unsignedInteger('total_points')->default(0);
            $table->string('status', 8)->default('DRAFT'); // DRAFT | POSTED
            $table->timestamp('posted_at')->nullable();
            $table->foreignUlid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'ref_no'], 'fin_inspections_ref_unique');
        });

        Schema::create('fin_inspection_rolls', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('inspection_id')->constrained('fin_inspections')->cascadeOnDelete();
            $table->string('roll_no', 40);
            $table->decimal('weight_kg', 12, 3);
            $table->unsignedInteger('points')->default(0);
            $table->string('grade', 8)->nullable();
            $table->string('result', 8); // PASS | HOLD | REJECT
            $table->string('defect')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['inspection_id', 'roll_no'], 'fin_inspection_rolls_unique');
        });

        Schema::create('fin_reprocess_advices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('advice_no', 24);
            $table->date('advice_date');
            $table->foreignUlid('inspection_id')->constrained('fin_inspections')->restrictOnDelete();
            $table->foreignUlid('receipt_id')->constrained('fin_batch_receipts')->restrictOnDelete();
            $table->foreignUlid('process_id')->constrained('fin_process_types')->restrictOnDelete();
            $table->foreignUlid('machine_id')->nullable()->constrained('fin_machines')->nullOnDelete();
            $table->unsignedInteger('rejected_rolls')->default(0);
            $table->decimal('rejected_kg', 14, 3)->default(0);
            $table->string('reason', 10); // SHADE | GSM | WIDTH | DEFECT | OTHER
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'advice_no'], 'fin_reprocess_advices_no_unique');
            $table->unique('inspection_id');
        });
    }

    public function down(): void
    {
        foreach (['fin_reprocess_advices', 'fin_inspection_rolls', 'fin_inspections', 'fin_shade_decisions', 'fin_qc_entries'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

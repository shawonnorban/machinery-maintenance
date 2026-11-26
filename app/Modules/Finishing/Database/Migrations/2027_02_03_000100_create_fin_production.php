<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Finishing Line → DF Production (AnnotechERP 18/02): a dyed batch received
 * on the floor from the Dye House (its finishing card, which carries the
 * batch's WIP stage); the shift's machine-wise production sheets, one per
 * line, whose posting moves each batch to the stage its machine and process
 * are mapped to; every move of a batch from one stage to the next; a batch
 * sent back to the Dye House for re-processing; and the corrections made to
 * a posted production line, with what it read before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_batch_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('receipt_no', 24);
            $table->date('receive_date');
            $table->foreignUlid('batch_id')->constrained('dye_batches')->restrictOnDelete();
            $table->string('dia_type', 8); // OPEN | TUBE | BOTH
            $table->string('booking_type', 12); // IN_HOUSE | SUB_CONTRACT | SAMPLE
            $table->string('challan_no', 40)->nullable();
            $table->decimal('challan_qty_kg', 14, 3)->nullable();
            $table->unsignedInteger('challan_rolls')->nullable();
            $table->decimal('recv_qty_kg', 14, 3);
            $table->unsignedInteger('recv_rolls')->default(0);
            $table->foreignUlid('floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->string('stage', 24)->default('AWAITING_LOAD');
            $table->timestamp('stage_at')->nullable();
            $table->timestamp('last_load_at')->nullable();
            $table->foreignUlid('last_machine_id')->nullable()->constrained('fin_machines')->nullOnDelete();
            $table->foreignUlid('last_process_id')->nullable()->constrained('fin_process_types')->nullOnDelete();
            $table->string('status', 10)->default('ON_FLOOR'); // ON_FLOOR | RETURNED
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'receipt_no'], 'fin_batch_receipts_no_unique');
            $table->index(['company_id', 'batch_id']);
        });

        Schema::create('fin_production_sheets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('sheet_no', 24);
            $table->string('line', 8); // OPEN | TUBE
            $table->date('production_date');
            $table->foreignUlid('floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->string('shift', 8);
            $table->text('remarks')->nullable();
            $table->string('status', 8)->default('DRAFT'); // DRAFT | POSTED
            $table->timestamp('posted_at')->nullable();
            $table->foreignUlid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'sheet_no'], 'fin_production_sheets_no_unique');
        });

        Schema::create('fin_production_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('sheet_id')->constrained('fin_production_sheets')->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->constrained('fin_batch_receipts')->restrictOnDelete();
            $table->foreignUlid('machine_id')->constrained('fin_machines')->restrictOnDelete();
            $table->foreignUlid('process_id')->constrained('fin_process_types')->restrictOnDelete();
            $table->decimal('input_kg', 14, 3);
            $table->decimal('output_kg', 14, 3);
            $table->unsignedInteger('rolls')->nullable();
            $table->decimal('fin_gsm', 8, 2)->nullable();
            $table->decimal('fin_dia', 8, 2)->nullable();
            $table->timestamp('load_time')->nullable();
            $table->timestamp('unload_time')->nullable();
            $table->string('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fin_process_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->constrained('fin_batch_receipts')->cascadeOnDelete();
            $table->string('from_stage', 24)->nullable();
            $table->string('to_stage', 24);
            $table->timestamp('delivered_at');
            $table->string('source', 8); // RECEIPT | SHEET | BOARD
            $table->foreignUlid('sheet_id')->nullable()->constrained('fin_production_sheets')->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['receipt_id', 'delivered_at']);
        });

        Schema::create('fin_reprocess_returns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('return_no', 24);
            $table->date('return_date');
            $table->foreignUlid('receipt_id')->constrained('fin_batch_receipts')->restrictOnDelete();
            $table->string('reason', 10); // SHADE | GSM | WIDTH | OTHER
            $table->decimal('qty_kg', 14, 3);
            $table->unsignedInteger('rolls')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'return_no'], 'fin_reprocess_returns_no_unique');
            $table->unique('receipt_id');
        });

        Schema::create('fin_production_amendments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('amend_no', 24);
            $table->date('amend_date');
            $table->foreignUlid('line_id')->constrained('fin_production_lines')->restrictOnDelete();
            $table->decimal('old_input_kg', 14, 3);
            $table->decimal('new_input_kg', 14, 3);
            $table->decimal('old_output_kg', 14, 3);
            $table->decimal('new_output_kg', 14, 3);
            $table->foreignUlid('old_machine_id')->nullable()->constrained('fin_machines')->nullOnDelete();
            $table->foreignUlid('new_machine_id')->nullable()->constrained('fin_machines')->nullOnDelete();
            $table->foreignUlid('old_process_id')->nullable()->constrained('fin_process_types')->nullOnDelete();
            $table->foreignUlid('new_process_id')->nullable()->constrained('fin_process_types')->nullOnDelete();
            $table->text('reason');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'amend_no'], 'fin_production_amendments_no_unique');
        });
    }

    public function down(): void
    {
        foreach (['fin_production_amendments', 'fin_reprocess_returns', 'fin_process_deliveries', 'fin_production_lines', 'fin_production_sheets', 'fin_batch_receipts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

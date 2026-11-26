<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Dye House → Requisition (AnnotechERP 17/05):
 *
 * - `dye_chem_requisitions` / `_lines`: dyes and chemicals asked of a chemical
 *   sub-store (IR-DC-YYYYMM####), raised for a batch programme, a machine
 *   wash, a finishing process or a re-process — what it is for (batch,
 *   machine, shade, buyer, order) and one line per item. A re-process waits on
 *   the approval desk before the store may issue against it.
 * - `dye_check_roll_returns`: a check roll QC failed, returned for re-processing
 *   (CRR-YYYYMM####), with the defect and how long it waited.
 * - `dye_machine_maintenance`: a dyeing machine's downtime — reported, stopped,
 *   running again — and who reported and who serviced it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dye_chem_requisitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('req_no', 24);
            $table->date('req_date');
            $table->string('req_for', 16); // BATCH_PROGRAM | MC_WASH | FINISHING | REPROCESS
            $table->string('req_type', 120);
            $table->string('process_type', 12)->nullable(); // RE_MATCHING | RE_WASH | RE_DYEING | STRIPPING | SOFTENER
            $table->foreignUlid('party_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignUlid('store_id')->constrained('stores');
            $table->foreignUlid('batch_id')->nullable()->constrained('dye_batches')->nullOnDelete();
            $table->foreignUlid('dye_machine_id')->nullable()->constrained('dye_machines')->nullOnDelete();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->string('approval', 12)->default('NOT_REQUIRED'); // NOT_REQUIRED | PENDING | APPROVED | REJECTED
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('status', 10)->default('WAITING'); // WAITING | PARTIAL | ISSUED | CANCELLED
            $table->foreignUlid('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'req_no'], 'dye_chem_requisitions_no_unique');
            $table->index(['company_id', 'req_for', 'status']);
        });

        Schema::create('dye_chem_requisition_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('requisition_id')->constrained('dye_chem_requisitions')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items');
            $table->string('uom', 16)->nullable();
            $table->decimal('required_qty', 14, 3);
            $table->decimal('issued_qty', 14, 3)->default(0);
            $table->string('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('dye_check_roll_returns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('return_no', 24);
            $table->date('req_date');
            $table->date('not_ok_date')->nullable();
            $table->string('reprocess_type', 12); // RE_MATCHING | RE_WASH | RE_DYEING | STRIPPING | SOFTENER
            $table->foreignUlid('party_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignUlid('batch_id')->nullable()->constrained('dye_batches')->nullOnDelete();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->foreignUlid('dye_machine_id')->nullable()->constrained('dye_machines')->nullOnDelete();
            $table->decimal('qty_kg', 14, 3);
            $table->string('defect')->nullable();
            $table->text('remarks')->nullable();
            // "Back" from the register sends the roll back; it is not edited after.
            $table->timestamp('sent_back_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'return_no'], 'dye_check_roll_returns_no_unique');
        });

        Schema::create('dye_machine_maintenance', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('dye_machine_id')->constrained('dye_machines');
            $table->date('log_date');
            $table->timestamp('report_time');
            $table->timestamp('problem_time')->nullable();
            $table->timestamp('resolved_time')->nullable();
            $table->string('defect_type', 120);
            $table->foreignUlid('report_by')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignUlid('service_by')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->string('status', 12); // REPORTED | IN_PROGRESS | RESOLVED | CANCELLED
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'dye_machine_id', 'log_date']);
        });
    }

    public function down(): void
    {
        foreach (['dye_machine_maintenance', 'dye_check_roll_returns', 'dye_chem_requisition_lines', 'dye_chem_requisitions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Assembly Floor → Packing (AnnotechERP 20/04) and its Packing Process
 * (20/05), as Brickland keeps them: a Quality-passed bundle scanned into
 * packing (Section > Floor > Packing Line / Location) joins the open packing
 * batch of its PO and colour — the first scan opens one — until Folding
 * starts; the batch then goes through the seven processes in order (Folding,
 * Poly Packing, Barcode & Label, Assortment, Carton Packing, Carton Marking,
 * Carton Sealing) with poly labels per size, cartons with their size
 * break-up, marking and sealing; Packing Out confirms the final packed
 * quantity and moves it to Ready for Shipment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asm_packing_batches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('packing_no', 20); // PKG-20261107-0001
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('po_no', 60)->nullable();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('style', 80)->nullable();
            $table->string('colour', 60);
            $table->foreignUlid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignUlid('floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->foreignUlid('line_id')->constrained('production_lines')->restrictOnDelete();
            $table->string('status', 12)->default('PACKING_IN'); // PACKING_IN | IN_PROGRESS | PACKED_OUT | CANCELLED
            $table->string('assort_ratio', 200)->nullable(); // S:1, M:2, L:2 — blank = solid-size packing
            $table->string('destination', 120)->nullable();
            $table->string('country', 80)->nullable();
            $table->string('shipping_mark', 200)->nullable();
            $table->string('special_instruction', 300)->nullable();
            $table->unsignedInteger('marking_print_count')->default(0);
            $table->unsignedInteger('final_packed_qty')->nullable();
            $table->date('out_date')->nullable();
            $table->timestamp('packed_out_at')->nullable();
            $table->foreignUlid('packed_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'packing_no']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'order_id', 'po_no', 'colour']);
        });

        Schema::create('asm_packing_ins', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('batch_id')->constrained('asm_packing_batches')->restrictOnDelete();
            $table->foreignUlid('bundle_id')->constrained('cut_bundles')->restrictOnDelete();
            $table->foreignUlid('quality_out_id')->constrained('asm_quality_outs')->restrictOnDelete();
            $table->date('in_date');
            $table->string('size', 20);
            $table->unsignedInteger('qty');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'in_date']);
            $table->index('bundle_id');
        });

        Schema::create('asm_packing_steps', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('batch_id')->constrained('asm_packing_batches')->cascadeOnDelete();
            $table->string('step', 12); // FOLDING | POLY | LABEL | ASSORTMENT | CARTON | MARKING | SEALING
            $table->unsignedTinyInteger('seq');
            $table->string('status', 12)->default('PENDING'); // PENDING | IN_PROGRESS | COMPLETED
            $table->unsignedInteger('qty')->nullable();
            $table->string('remarks', 300)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->foreignUlid('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUlid('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reopen_reason', 300)->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignUlid('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['batch_id', 'step']);
            $table->index(['company_id', 'step', 'status']);
        });

        Schema::create('asm_packing_labels', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('batch_id')->constrained('asm_packing_batches')->cascadeOnDelete();
            $table->string('size', 20);
            $table->unsignedInteger('qty');
            $table->string('barcode', 30);
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignUlid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'barcode']);
            $table->unique(['batch_id', 'size']);
        });

        Schema::create('asm_packing_cartons', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('batch_id')->constrained('asm_packing_batches')->cascadeOnDelete();
            $table->unsignedSmallInteger('serial');
            $table->string('carton_no', 20); // CTN-20261107-0001; its barcode is the number without dashes
            $table->string('carton_type', 40)->nullable();
            $table->string('carton_size', 40)->nullable();
            $table->decimal('gross_weight', 8, 2)->nullable();
            $table->decimal('net_weight', 8, 2)->nullable();
            $table->unsignedInteger('qty');
            $table->string('status', 8)->default('OPEN'); // OPEN | MARKED | SEALED
            $table->timestamp('sealed_at')->nullable();
            $table->foreignUlid('sealed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('unseal_reason', 300)->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'carton_no']);
            $table->unique(['batch_id', 'serial']);
        });

        Schema::create('asm_packing_carton_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('carton_id')->constrained('asm_packing_cartons')->cascadeOnDelete();
            $table->string('size', 20);
            $table->unsignedInteger('qty');
            $table->timestamps();
            $table->unique(['carton_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asm_packing_carton_lines');
        Schema::dropIfExists('asm_packing_cartons');
        Schema::dropIfExists('asm_packing_labels');
        Schema::dropIfExists('asm_packing_steps');
        Schema::dropIfExists('asm_packing_ins');
        Schema::dropIfExists('asm_packing_batches');
    }
};

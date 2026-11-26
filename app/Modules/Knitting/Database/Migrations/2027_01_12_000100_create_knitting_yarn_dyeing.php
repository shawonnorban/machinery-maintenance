<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knit Production → yarn dyeing, as Brickland works it across Yarn Store
 * (AnnotechERP 15/03) and Production (15/04):
 *
 * - Y/D Program (YDP-…): greige yarn placed on a dye house for a buyer's
 *   order, one line per shade to come back (colour, yarn, kg). Raised on the
 *   Yarn Dyeing Programme Register; each revision is numbered in order and
 *   says why. Its Issue is what the store sent against it (requisitions of
 *   type YARN_DYE naming it, and re-process documents), its Delivery the
 *   confirmed dyed receipts, and approved process-loss memos write the
 *   shrinkage off.
 * - Dyed Yarn Receive (YDR-…): shades back from the dye house. A draft moves
 *   nothing; confirming it takes the cones onto the store.
 * - Process Loss memo (YDL-…): Issued − Received per shade, a draft until
 *   approved.
 * - YD Re-process (YRP-…): dyed yarn going back to a dye house to be run
 *   again — off the mill's own shelf (in-house, issuing it takes it out of
 *   stock) or recovered from a sub-contract knitter.
 * - S/C Yarn Receive Challan (in-house): yarn a sub-contract party sends in
 *   for the mill to knit, against its programme receipt; accepting it takes
 *   the cones onto the store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knit_yd_programs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('program_no', 24);
            $table->date('program_date');
            // IN_HOUSE, SUB_CONTRACT.
            $table->string('yd_type', 16);
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->date('delivery_date')->nullable();
            $table->unsignedSmallInteger('rev_no')->default(0);
            $table->text('remarks')->nullable();
            // RUNNING, COMPLETED, CLOSED, CANCELLED.
            $table->string('status', 12)->default('RUNNING');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'program_no']);
            $table->index(['company_id', 'status', 'program_date']);
        });

        Schema::create('knit_yd_program_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('program_id')->constrained('knit_yd_programs')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('colour_name', 80);
            $table->string('colour_group', 40)->nullable();
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('lot_no', 40)->nullable();
            $table->decimal('qty', 14, 4);
            $table->string('uom', 16)->default('kg');
            $table->decimal('rate', 14, 4)->nullable();
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_yd_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('program_id')->constrained('knit_yd_programs')->cascadeOnDelete();
            $table->unsignedSmallInteger('rev_no');
            $table->date('revised_on');
            $table->string('reason', 500);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['program_id', 'rev_no']);
        });

        Schema::create('knit_yd_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('receive_no', 24);
            $table->date('receive_date');
            $table->foreignUlid('program_id')->constrained('knit_yd_programs')->restrictOnDelete();
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->string('working_ref', 40)->nullable();
            $table->string('challan_no', 40)->nullable();
            $table->date('challan_date')->nullable();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->text('remarks')->nullable();
            // DRAFT, CONFIRMED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'receive_no']);
            $table->index(['company_id', 'status', 'receive_date']);
        });

        Schema::create('knit_yd_receipt_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->constrained('knit_yd_receipts')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('program_line_id')->constrained('knit_yd_program_lines')->restrictOnDelete();
            // The dyed lot as printed on the cone label.
            $table->string('lot_no', 40);
            $table->decimal('qty', 14, 4);
            $table->string('uom', 16)->default('kg');
            $table->unsignedInteger('cones')->nullable();
            // The stock line the cones went onto, once confirmed.
            $table->foreignUlid('lot_id')->nullable()->constrained('knit_yarn_lots')->nullOnDelete();
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_yd_loss_memos', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('memo_no', 24);
            $table->date('memo_date');
            $table->foreignUlid('program_id')->constrained('knit_yd_programs')->restrictOnDelete();
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->text('remarks')->nullable();
            // DRAFT, APPROVED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'memo_no']);
            $table->index(['company_id', 'status', 'memo_date']);
        });

        Schema::create('knit_yd_loss_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('memo_id')->constrained('knit_yd_loss_memos')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('program_line_id')->constrained('knit_yd_program_lines')->restrictOnDelete();
            $table->string('colour_group', 40)->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->decimal('issued_qty', 14, 4);
            $table->decimal('received_qty', 14, 4);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_yd_reprocesses', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('ref_no', 24);
            // IN_HOUSE (off the mill's shelf), SUB_CONTRACT (recovered from a knitter).
            $table->string('kind', 16);
            $table->date('ref_date');
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('party_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->string('bill_no', 40)->nullable();
            $table->string('challan_no', 40)->nullable();
            $table->date('challan_date')->nullable();
            $table->foreignUlid('program_id')->nullable()->constrained('knit_yd_programs')->nullOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->text('remarks')->nullable();
            // DRAFT, ISSUED, ACCEPTED, REJECTED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'ref_no']);
            $table->index(['company_id', 'kind', 'status', 'ref_date']);
        });

        Schema::create('knit_yd_reprocess_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('reprocess_id')->constrained('knit_yd_reprocesses')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            // In-house: the stock line the cones leave.
            $table->foreignUlid('lot_id')->nullable()->constrained('knit_yarn_lots')->restrictOnDelete();
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('colour_group', 40)->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->decimal('qty', 14, 4);
            $table->string('uom', 16)->default('kg');
            $table->string('reason', 120);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_sc_yarn_challans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 40);
            $table->date('challan_date');
            $table->foreignUlid('receipt_id')->constrained('knit_sc_receipts')->restrictOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->text('remarks')->nullable();
            // DRAFT, ISSUED, ACCEPTED, REJECTED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'challan_no']);
            $table->index(['company_id', 'status', 'challan_date']);
        });

        Schema::create('knit_sc_yarn_challan_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('challan_id')->constrained('knit_sc_yarn_challans')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('colour_group', 40)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('lot_no', 40);
            $table->decimal('qty', 14, 4);
            $table->string('uom', 16)->default('kg');
            $table->unsignedInteger('cones')->nullable();
            // The stock line the cones went onto, once accepted.
            $table->foreignUlid('lot_id')->nullable()->constrained('knit_yarn_lots')->nullOnDelete();
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['knit_sc_yarn_challan_lines', 'knit_sc_yarn_challans', 'knit_yd_reprocess_lines', 'knit_yd_reprocesses', 'knit_yd_loss_lines', 'knit_yd_loss_memos',
            'knit_yd_receipt_lines', 'knit_yd_receipts', 'knit_yd_revisions', 'knit_yd_program_lines', 'knit_yd_programs'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

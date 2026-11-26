<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knit Production → Fabric Store (AnnotechERP 15/05), the yarn going out
 * and coming back around sub-contract and yarn-dyed work, as Brickland
 * works it (every paper Draft → Issued → Finalized, or Cancelled):
 *
 * - Out-House Yarn Transfer (OYT-…): an approved sub-contract programme,
 *   with the yarn the knitter holds against it, handed from one outside
 *   knitter to another. Finalizing moves the programme onto the receiving
 *   party; the yarn was already off the store, so no ledger moves.
 * - Grey Yarn Transfer for Dyeing (GYT-…): greige going to a dye house,
 *   against a Y/D programme's shades (or none, when raised at the gate).
 *   Issue takes it off the yarn store; it counts in the programme's Issue.
 * - Dyed Yarn Receipt Challan (DYR-…): dyed yarn back against the grey
 *   challan it answers (its weight is the G.Yarn Qty). Finalizing is the
 *   store signing for the count — the cones go onto the yarn store.
 * - Out-House Dyed Yarn Transfer Challan (DYT-…): finalised dyed yarn
 *   forwarded to an outside knitter, each line naming the receive line it
 *   came off, so no delivery is forwarded twice. Issue takes it off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knit_yarn_prog_transfers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 24);
            $table->date('challan_date');
            $table->foreignUlid('sc_program_id')->constrained('knit_sc_programs')->restrictOnDelete();
            $table->foreignUlid('from_party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('to_party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('gate_pass_no', 40)->nullable();
            $table->string('vehicle_no', 40)->nullable();
            $table->text('note')->nullable();
            // DRAFT, ISSUED, FINALIZED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'challan_no']);
        });

        Schema::create('knit_yarn_prog_transfer_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('transfer_id')->constrained('knit_yarn_prog_transfers')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('colour_group', 40)->nullable();
            $table->string('shade', 40)->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->unsignedInteger('bags')->nullable();
            $table->decimal('qty', 14, 4);
            $table->string('uom', 16);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_yd_grey_transfers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 24);
            $table->date('challan_date');
            $table->string('gate_pass_no', 40)->nullable();
            $table->string('vehicle_no', 40)->nullable();
            $table->foreignUlid('party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('program_id')->nullable()->constrained('knit_yd_programs')->nullOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->string('status', 12)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'challan_no']);
        });

        Schema::create('knit_yd_grey_transfer_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('transfer_id')->constrained('knit_yd_grey_transfers')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('program_line_id')->nullable()->constrained('knit_yd_program_lines')->nullOnDelete();
            // The yarn store's stock line the greige leaves.
            $table->foreignUlid('lot_id')->constrained('knit_yarn_lots')->restrictOnDelete();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('colour_name', 80)->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->unsignedInteger('pack_qty')->nullable();
            $table->decimal('qty', 14, 4);
            $table->string('uom', 16);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_yd_dyed_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 24);
            $table->date('challan_date');
            $table->string('gate_pass_no', 40)->nullable();
            $table->foreignUlid('party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('grey_transfer_id')->nullable()->constrained('knit_yd_grey_transfers')->nullOnDelete();
            $table->foreignUlid('program_id')->nullable()->constrained('knit_yd_programs')->nullOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->string('status', 12)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'challan_no']);
        });

        Schema::create('knit_yd_dyed_receipt_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->constrained('knit_yd_dyed_receipts')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('knit_prog_id')->nullable()->constrained('knit_sc_programs')->nullOnDelete();
            $table->foreignUlid('collar_prog_id')->nullable()->constrained('knit_sc_programs')->nullOnDelete();
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('colour_name', 80)->nullable();
            $table->string('lot_no', 40);
            $table->unsignedInteger('pack_qty')->nullable();
            $table->decimal('qty', 14, 4);
            $table->string('uom', 16);
            $table->string('remarks', 300)->nullable();
            // The yarn store's stock line the cones went onto, once finalised.
            $table->foreignUlid('lot_id')->nullable()->constrained('knit_yarn_lots')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('knit_yd_dyed_transfers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 24);
            $table->date('challan_date');
            $table->string('gate_pass_no', 40)->nullable();
            $table->string('vehicle_no', 40)->nullable();
            $table->foreignUlid('party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->string('status', 12)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'challan_no']);
        });

        Schema::create('knit_yd_dyed_transfer_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('transfer_id')->constrained('knit_yd_dyed_transfers')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('receipt_line_id')->constrained('knit_yd_dyed_receipt_lines')->restrictOnDelete();
            $table->foreignUlid('knit_prog_id')->nullable()->constrained('knit_sc_programs')->nullOnDelete();
            $table->foreignUlid('collar_prog_id')->nullable()->constrained('knit_sc_programs')->nullOnDelete();
            $table->unsignedInteger('pack_qty')->nullable();
            $table->decimal('qty', 14, 4);
            $table->string('uom', 16);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['knit_yd_dyed_transfer_lines', 'knit_yd_dyed_transfers', 'knit_yd_dyed_receipt_lines', 'knit_yd_dyed_receipts', 'knit_yd_grey_transfer_lines',
            'knit_yd_grey_transfers', 'knit_yarn_prog_transfer_lines', 'knit_yarn_prog_transfers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

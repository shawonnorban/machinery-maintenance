<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knit Production → Fabric Store (AnnotechERP 15/05), the sub-contract
 * challans on the grey ledger, as Brickland works them:
 *
 * - Out-House Grey Receipt Challan (OGR-…): grey fabric, collar and cuff in
 *   from an outside knitter against the party's own challan. Received, it is
 *   booked into the store and goes on the QC worklist (Out-House Collar &
 *   Cuff QC Receipt passes, holds or rejects the collar / cuff challans).
 * - Grey Return Challan (OGT-… out-house, IGT-… in-house): grey going back
 *   to a party. A line that names a roll or stock line leaves the store when
 *   the challan is issued; a line without one is a quantity that moves
 *   nothing. The party's acknowledgement is Received Back.
 * - In-House Grey Delivery Challan (IGD-…): one paper for one lorry — the
 *   fabric rolls, the collar / cuff and the party's unused yarn going back.
 *   Issued, the rolls leave the grey ledger and the yarn the yarn ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knit_sc_grey_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('rcv_no', 24);
            $table->date('rcv_date');
            $table->foreignUlid('party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->restrictOnDelete();
            $table->string('challan_no', 40);
            $table->date('challan_date');
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('gate_pass_no', 40)->nullable();
            $table->string('vehicle_no', 40)->nullable();
            $table->text('note')->nullable();
            // DRAFT, RECEIVED, QC_PASSED, QC_HOLD, QC_REJECTED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'rcv_no']);
            $table->index(['company_id', 'status', 'rcv_date']);
        });

        Schema::create('knit_sc_grey_receipt_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->constrained('knit_sc_grey_receipts')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            // FABRIC, COLLAR, CUFF.
            $table->string('type', 8);
            $table->foreignUlid('fabric_type_id')->nullable()->constrained('fabric_types')->nullOnDelete();
            $table->string('description', 160);
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('colour_name', 80)->nullable();
            $table->string('shade', 40)->nullable();
            $table->string('roll_no', 40)->nullable();
            $table->string('finish_dia', 24)->nullable();
            $table->decimal('gsm', 8, 2)->nullable();
            $table->unsignedInteger('r_qty')->nullable();
            $table->decimal('qty', 14, 4);
            $table->string('uom', 16);
            $table->string('remarks', 300)->nullable();
            // The stock line it was booked onto, once received.
            $table->foreignUlid('lot_id')->nullable()->constrained('knit_grey_lots')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('knit_sc_grey_returns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 24);
            // OUT_HOUSE, IN_HOUSE.
            $table->string('kind', 10);
            $table->date('challan_date');
            $table->foreignUlid('party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            // Out-house: the receive challan it answers; in-house: the party's programme receipt.
            $table->foreignUlid('receipt_id')->nullable()->constrained('knit_sc_grey_receipts')->nullOnDelete();
            $table->foreignUlid('sc_receipt_id')->nullable()->constrained('knit_sc_receipts')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->string('gate_pass_no', 40)->nullable();
            $table->string('vehicle_no', 40)->nullable();
            $table->string('driver', 80)->nullable();
            $table->text('note')->nullable();
            // DRAFT, ISSUED, RECEIVED_BACK, CANCELLED.
            $table->string('status', 14)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'challan_no']);
            $table->index(['company_id', 'kind', 'status']);
        });

        Schema::create('knit_sc_grey_return_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('return_id')->constrained('knit_sc_grey_returns')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->string('type', 8);
            // The roll or stock line leaving the store; without one the line moves nothing.
            $table->foreignUlid('lot_id')->nullable()->constrained('knit_grey_lots')->restrictOnDelete();
            $table->string('description', 160);
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('colour_name', 80)->nullable();
            $table->string('shade', 40)->nullable();
            $table->string('roll_no', 40)->nullable();
            $table->unsignedInteger('r_qty')->nullable();
            $table->decimal('qty', 14, 4);
            $table->string('uom', 16);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_sc_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 24);
            $table->date('challan_date');
            $table->foreignUlid('party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('gate_pass_no', 40)->nullable();
            $table->string('vehicle_no', 40)->nullable();
            $table->string('driver', 80)->nullable();
            $table->string('receiver', 80)->nullable();
            $table->string('receiver_mobile', 24)->nullable();
            $table->text('note')->nullable();
            // DRAFT, ISSUED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'challan_no']);
            $table->index(['company_id', 'status', 'challan_date']);
        });

        Schema::create('knit_sc_delivery_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('delivery_id')->constrained('knit_sc_deliveries')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            // FABRIC, COLLAR_CUFF, YARN.
            $table->string('section', 12);
            // COLLAR, CUFF — collar / cuff lines' part.
            $table->string('part', 8)->nullable();
            $table->foreignUlid('lot_id')->nullable()->constrained('knit_grey_lots')->restrictOnDelete();
            $table->foreignUlid('yarn_lot_id')->nullable()->constrained('knit_yarn_lots')->restrictOnDelete();
            $table->string('description', 160)->nullable();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('colour_name', 80)->nullable();
            $table->string('shade', 40)->nullable();
            $table->string('roll_no', 40)->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->string('finish_dia', 24)->nullable();
            $table->string('mc_gauge', 24)->nullable();
            $table->unsignedInteger('r_qty')->nullable();
            $table->unsignedInteger('bags')->nullable();
            $table->decimal('qty', 14, 4);
            $table->string('uom', 16);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['knit_sc_delivery_lines', 'knit_sc_deliveries', 'knit_sc_grey_return_lines', 'knit_sc_grey_returns', 'knit_sc_grey_receipt_lines', 'knit_sc_grey_receipts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

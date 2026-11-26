<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knit Production → Fabric Store (AnnotechERP 15/05), the grey fabric store,
 * as Brickland works it:
 *
 * - The grey ledger: a stock line per store, opening kind, order, party,
 *   shade, fabric, dia, GSM and roll (a weighed roll is its own line;
 *   balances without a roll are not), and the signed moves every document
 *   posts into it, replacing what it posted before. A struck month takes no
 *   more movement.
 * - Grey Fabric Store Receipt (GFR-…): rolls scanned off the floor into a
 *   store and rack, one receive note per store and day; each scan posts at
 *   once and can be withdrawn until the day is confirmed.
 * - Grey Fabric Opening Stock (GOB-…): opening lines by tab (In-house,
 *   Collar-Cuff, S/C In-house), open until finalised into the ledger.
 * - Store-to-Store Grey Transfer (GFT-…): rolls or balances moved between
 *   stores, the order unchanged; Submit takes them out of the first store,
 *   Receive puts them into the second.
 * - Grey Fabric Stock Correction (GFA-…): physical-count corrections, posted
 *   when verified and then approved.
 * - Grey Fabric Stock Close: a finished month struck.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knit_grey_lots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('lot_key', 600);
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            // IN_HOUSE, COLLAR_CUFF, SC_IN_HOUSE.
            $table->string('kind', 12)->default('IN_HOUSE');
            // BULK, YD, SAMPLE, FLAT_BED_RIB — the production order's kind, when the fabric came off one.
            $table->string('prod_type', 16)->nullable();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->foreignUlid('party_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->foreignUlid('roll_id')->nullable()->constrained('knit_rolls')->nullOnDelete();
            $table->string('roll_no', 40)->nullable();
            $table->foreignUlid('fabric_type_id')->nullable()->constrained('fabric_types')->nullOnDelete();
            $table->string('fabric', 160);
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('colour_name', 80)->nullable();
            $table->string('finish_dia', 24)->nullable();
            $table->decimal('gsm', 8, 2)->nullable();
            $table->string('part', 40)->nullable();
            // The rack the store put it on.
            $table->foreignUlid('bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->decimal('rate', 14, 4)->default(0);
            $table->date('first_date');
            $table->timestamps();
            $table->unique(['company_id', 'lot_key']);
            $table->index(['company_id', 'store_id']);
        });

        Schema::create('knit_grey_moves', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('lot_id')->constrained('knit_grey_lots')->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->date('move_date');
            $table->string('kind', 16);
            $table->string('doc_no', 40);
            $table->ulid('doc_line_id')->nullable();
            $table->decimal('qty', 14, 4);
            $table->integer('rolls')->default(0);
            $table->decimal('value', 16, 4)->default(0);
            $table->timestamps();
            $table->index(['company_id', 'lot_id']);
            $table->index(['company_id', 'kind', 'doc_no']);
            $table->index(['company_id', 'move_date']);
        });

        Schema::create('knit_grey_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('note_no', 24);
            $table->date('receive_date');
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            // DRAFT, CONFIRMED.
            $table->string('status', 12)->default('DRAFT');
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignUlid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'note_no']);
            $table->unique(['company_id', 'store_id', 'receive_date']);
        });

        Schema::create('knit_grey_receipt_rolls', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->constrained('knit_grey_receipts')->cascadeOnDelete();
            $table->foreignUlid('roll_id')->constrained('knit_rolls')->restrictOnDelete();
            $table->foreignUlid('bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->foreignUlid('lot_id')->constrained('knit_grey_lots')->restrictOnDelete();
            $table->decimal('weight', 12, 3);
            $table->timestamp('scanned_at');
            $table->foreignUlid('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // A roll is received into a store once.
            $table->unique(['company_id', 'roll_id']);
        });

        Schema::create('knit_grey_openings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('opening_no', 24);
            $table->string('kind', 12);
            $table->date('opening_date');
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->foreignUlid('party_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->string('fabric', 160);
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('colour_name', 80)->nullable();
            $table->string('finish_dia', 24)->nullable();
            $table->decimal('gsm', 8, 2)->nullable();
            $table->decimal('qty', 14, 4);
            $table->decimal('rate', 14, 4)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->boolean('finalized')->default(false);
            $table->foreignUlid('lot_id')->nullable()->constrained('knit_grey_lots')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'opening_no']);
            $table->index(['company_id', 'kind', 'finalized']);
        });

        Schema::create('knit_grey_transfers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('transfer_no', 24);
            $table->date('transfer_date');
            // DEV_TO_DEV, BULK_TO_BULK, SAMPLE_TO_SAMPLE, PROJECTION_TO_BULK.
            $table->string('trans_type', 20);
            $table->string('trans_ref', 40)->nullable();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->foreignUlid('from_store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('to_store_id')->constrained('stores')->restrictOnDelete();
            $table->text('remarks')->nullable();
            // DRAFT, SUBMITTED, RECEIVED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'transfer_no']);
            $table->index(['company_id', 'status', 'transfer_date']);
        });

        Schema::create('knit_grey_transfer_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('transfer_id')->constrained('knit_grey_transfers')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            // The stock line moved out of the first store.
            $table->foreignUlid('lot_id')->constrained('knit_grey_lots')->restrictOnDelete();
            $table->string('roll_no', 40)->nullable();
            $table->unsignedInteger('rolls')->default(0);
            $table->decimal('qty', 14, 4);
            // The stock line it landed on in the second, once received.
            $table->foreignUlid('to_lot_id')->nullable()->constrained('knit_grey_lots')->nullOnDelete();
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_grey_adjustments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('adj_no', 24);
            $table->date('adj_date');
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('reason', 255)->nullable();
            $table->text('remarks')->nullable();
            // DRAFT, VERIFIED, APPROVED.
            $table->string('status', 12)->default('DRAFT');
            // ACTIVE or CANCELLED (Brickland's Activity Status).
            $table->string('activity', 10)->default('ACTIVE');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'adj_no']);
            $table->index(['company_id', 'status', 'adj_date']);
        });

        Schema::create('knit_grey_adjustment_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('adjustment_id')->constrained('knit_grey_adjustments')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('colour_name', 80)->nullable();
            $table->string('fabric', 160);
            $table->string('roll_no', 40)->nullable();
            $table->string('part', 40)->nullable();
            // Signed: positive puts fabric on the shelf, negative takes it off.
            $table->decimal('qty', 14, 4);
            $table->string('uom', 16)->default('kg');
            $table->decimal('rate', 14, 4)->nullable();
            $table->string('reason', 255)->nullable();
            // The stock line it was struck on, once approved.
            $table->foreignUlid('lot_id')->nullable()->constrained('knit_grey_lots')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('knit_grey_month_closes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->char('period', 7);
            $table->string('remarks', 255)->nullable();
            $table->timestamp('closed_at');
            $table->foreignUlid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'period']);
        });
    }

    public function down(): void
    {
        foreach (['knit_grey_month_closes', 'knit_grey_adjustment_lines', 'knit_grey_adjustments', 'knit_grey_transfer_lines', 'knit_grey_transfers', 'knit_grey_openings',
            'knit_grey_receipt_rolls', 'knit_grey_receipts', 'knit_grey_moves', 'knit_grey_lots'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

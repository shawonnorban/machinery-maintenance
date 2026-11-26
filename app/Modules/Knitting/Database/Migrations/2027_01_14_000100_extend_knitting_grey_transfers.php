<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knit Production → Production (AnnotechERP 15/04): the grey movements that
 * ride on the Fabric Store's grey ledger, all one paper told apart by kind —
 * STORE (store to store, 15/05), COLLAR_CUFF (flat-knit rolls off the beds
 * into a store), TO_BATCH (grey rolls gathered onto a dye batch), ORDER
 * (from one order's account to another's), REUSE (left-over pulled back
 * into a live order), TO_LEFTOVER and BATCH_TO_LEFTOVER (unconsumed grey
 * into the left-over store).
 *
 * A stock line held on a dye batch carries the batch's number; a collar /
 * cuff line names the roll it was knitted as rather than a stock line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knit_grey_lots', function (Blueprint $table): void {
            $table->string('batch_no', 40)->nullable()->after('part');
        });

        Schema::table('knit_grey_transfers', function (Blueprint $table): void {
            $table->string('kind', 20)->default('STORE')->after('transfer_no');
            // COLLAR, CUFF, COLLAR_CUFF — the flat-knit part.
            $table->string('cc_type', 12)->nullable()->after('trans_type');
            $table->foreignUlid('to_order_id')->nullable()->after('order_id')->constrained('merch_orders')->nullOnDelete();
            $table->string('batch_no', 40)->nullable()->after('to_store_id');
            $table->index(['company_id', 'kind', 'status']);
        });
        Schema::table('knit_grey_transfers', function (Blueprint $table): void {
            $table->ulid('from_store_id')->nullable()->change();
            $table->ulid('to_store_id')->nullable()->change();
        });

        Schema::table('knit_grey_transfer_lines', function (Blueprint $table): void {
            $table->ulid('lot_id')->nullable()->change();
            $table->foreignUlid('roll_id')->nullable()->after('lot_id')->constrained('knit_rolls')->restrictOnDelete();
            $table->string('fabric', 160)->nullable()->after('roll_no');
            $table->foreignUlid('colour_id')->nullable()->after('fabric')->constrained('colours')->nullOnDelete();
            $table->string('colour_name', 80)->nullable()->after('colour_id');
        });
    }

    public function down(): void
    {
        Schema::table('knit_grey_transfer_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('roll_id');
            $table->dropConstrainedForeignId('colour_id');
            $table->dropColumn(['fabric', 'colour_name']);
        });
        Schema::table('knit_grey_transfers', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'kind', 'status']);
            $table->dropConstrainedForeignId('to_order_id');
            $table->dropColumn(['kind', 'cc_type', 'batch_no']);
        });
        Schema::table('knit_grey_lots', function (Blueprint $table): void {
            $table->dropColumn('batch_no');
        });
    }
};

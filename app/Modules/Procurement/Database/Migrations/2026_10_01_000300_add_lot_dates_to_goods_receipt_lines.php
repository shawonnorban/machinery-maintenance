<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The dates printed on the drum, captured where they can be: at the gate
 * (Inventory spec §12).
 *
 * They live on the receipt line rather than only on `item_lots` because
 * the receipt is the document that saw them — the lot record is derived
 * from it, and a GRN that was corrected should be able to correct the lot
 * it created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table): void {
            $table->date('lot_manufactured_date')->nullable()->after('lot_no');
            $table->date('lot_expiry_date')->nullable()->after('lot_manufactured_date');
            $table->string('supplier_lot_no', 64)->nullable()->after('lot_expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table): void {
            $table->dropColumn(['lot_manufactured_date', 'lot_expiry_date', 'supplier_lot_no']);
        });
    }
};

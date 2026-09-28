<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a lot number means (Inventory spec §12: yarn lot-wise, chemical
 * expiry and FIFO).
 *
 * The stock ledger already keeps a balance per (item, bin, lot), but the
 * lot number itself is only a string there — it cannot say when the dye
 * goes off or when the yarn was spun, so nothing can suggest which drum to
 * open first. This is the small record that can: one row per (item, lot),
 * written at the gate from what is printed on the drum.
 *
 * Deliberately not a balance. Quantities stay in `item_stock_balances`
 * where the ledger owns them; a quantity here would be the same number
 * with two homes and a way to disagree.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_lots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('lot_no', 64);
            $table->date('manufactured_date')->nullable();
            $table->date('expiry_date')->nullable();
            // When this lot first arrived — plain FIFO reads this.
            $table->date('first_received_date')->nullable();
            // The mill's own batch reference, which rarely matches ours.
            $table->string('supplier_lot_no', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'item_id', 'lot_no'], 'item_lots_lot_unique');
            $table->index(['company_id', 'expiry_date'], 'item_lots_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_lots');
    }
};

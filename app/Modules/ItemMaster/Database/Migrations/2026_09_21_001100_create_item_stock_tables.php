<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The item library's own stock ledger (Inventory spec §16, decided with
 * Phase 1): a balance per item, bin and lot, and an append-only movement log
 * whose rows each carry the balance and average cost they left behind.
 *
 * Deliberately a mirror of `inventory_balances` / `inventory_transactions`
 * rather than a widening of them. The spare-parts ledger keys on
 * `spare_part_id` and knows nothing about lots; making one table serve both
 * would mean every existing query gained a "which catalogue" condition.
 * Bins are shared: an item and a spare can sit on the same shelf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_stock_balances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignUlid('bin_id')->constrained('bins')->restrictOnDelete();
            // The lot dimension. QUANTITY-tracked items pool into one row with
            // an empty string here — empty rather than NULL because a unique
            // index treats every NULL as distinct and would allow two pools.
            $table->string('lot_no', 64)->default('');

            $table->decimal('quantity_on_hand', 18, 4)->default(0);
            // Encumbered, not moved: reservations never write to the ledger.
            $table->decimal('quantity_reserved', 18, 4)->default(0);
            $table->decimal('weighted_average_cost', 18, 4)->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['item_id', 'bin_id', 'lot_no'], 'item_stock_balances_unique');
            $table->index(['company_id', 'item_id'], 'item_stock_balances_item_idx');
            $table->index(['company_id', 'bin_id'], 'item_stock_balances_bin_idx');
        });

        Schema::create('item_stock_transactions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignUlid('bin_id')->constrained('bins')->restrictOnDelete();
            $table->string('lot_no', 64)->default('');

            $table->string('transaction_type', 24);
            // Always positive. Direction comes from the type, so a sign error
            // cannot silently reverse a movement.
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('total_cost', 18, 4);
            $table->char('currency', 3)->default('BDT');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->decimal('base_total_cost', 18, 4);

            // Written with the row, so replaying the ledger must reproduce the
            // current balance. A mismatch is then findable.
            $table->decimal('balance_after', 18, 4);
            $table->decimal('wac_after', 18, 4);

            // The document that caused the movement (goods_receipt, floor
            // requisition...). Bare rather than a foreign key because the set
            // of documents grows a phase at a time.
            $table->string('reference_type', 48)->nullable();
            $table->ulid('reference_id')->nullable();
            $table->ulid('reverses_transaction_id')->nullable();

            $table->foreignUlid('performed_by')->nullable();
            $table->dateTime('transaction_at', 3);
            $table->text('notes')->nullable();
            // A retried request must not post the movement twice (ADR-056).
            $table->string('idempotency_key', 64)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'idempotency_key'], 'item_stock_tx_idempotency_unique');
            $table->index(['company_id', 'item_id', 'transaction_at'], 'item_stock_tx_item_idx');
            $table->index(['company_id', 'bin_id', 'transaction_at'], 'item_stock_tx_bin_idx');
            $table->index(['company_id', 'reference_type', 'reference_id'], 'item_stock_tx_ref_idx');
            $table->index(['company_id', 'transaction_type'], 'item_stock_tx_type_idx');
        });

        // Self-reference added after the table exists (Postgres rejects it
        // inside the create).
        Schema::table('item_stock_transactions', function (Blueprint $table): void {
            $table->foreign('reverses_transaction_id', 'item_stock_tx_reverses_fk')
                ->references('id')->on('item_stock_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_stock_transactions');
        Schema::dropIfExists('item_stock_balances');
    }
};

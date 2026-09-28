<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The roll ledger for ROLL-tracked items (Inventory spec §3): where a
 * QUANTITY/LOT item pools into `item_stock_balances`, a fabric roll is its
 * own row — every unit has its own identity, measurements and inspection
 * history. This table *is* the stock for these items; they never write to
 * `item_stock_balances` (`ItemLedger::assertLedgerable()` refuses them).
 *
 * Created at goods-receipt completion in status UNDER_INSPECTION, carrying
 * a Provisional Unit Cost off the GRN line's rate (§10). Most of the later
 * status values (RESERVED, ISSUED, SPLIT, MERGED, SENT_FOR_SUBCONTRACT) and
 * `parent_roll_id`/`reserved_order_id` are part of the roll's full field set
 * per spec but are not reachable by any action until Phase 3 (reservation)
 * and Phase 4 (split/merge/subcontract) — the columns exist now so those
 * phases are additive, not another migration touching this table's shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fabric_rolls', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignUlid('goods_receipt_id')->constrained('goods_receipts')->restrictOnDelete();
            $table->foreignUlid('goods_receipt_line_id')->constrained('goods_receipt_lines')->restrictOnDelete();
            $table->foreignUlid('bin_id')->constrained('bins')->restrictOnDelete();

            $table->string('roll_no', 64);
            $table->string('lot_no', 64)->nullable();
            $table->string('shade', 64)->nullable();
            $table->string('shade_band', 32)->nullable();
            $table->decimal('nominal_width', 10, 2)->nullable();
            // Set at inspection, not at receiving.
            $table->decimal('actual_width', 10, 2)->nullable();
            $table->decimal('gross_length', 12, 2)->nullable();
            $table->decimal('net_length', 12, 2)->nullable();
            $table->decimal('gross_weight', 12, 2)->nullable();
            $table->decimal('net_weight', 12, 2)->nullable();

            $table->string('status', 24)->default('UNDER_INSPECTION');
            // Split/Merge (Phase 4) — the column exists now, unused until then.
            $table->ulid('parent_roll_id')->nullable();
            // Hard allocation to an order/style (Phase 3) — unused until then.
            $table->ulid('reserved_order_id')->nullable();

            // Provisional at receiving (§10); Landed Cost post (Phase 5)
            // recalculates it, cascading to any child rolls that exist by then.
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->char('currency', 3)->default('BDT');

            // Inspection data (§4.3).
            $table->string('inspection_grade', 8)->nullable();
            $table->decimal('inspection_points', 6, 2)->nullable();
            $table->boolean('shade_match')->nullable();
            $table->text('inspection_notes')->nullable();
            $table->foreignUlid('inspected_by')->nullable();
            $table->timestamp('inspected_at')->nullable();

            $table->date('received_date');
            $table->timestamps();

            $table->unique(['company_id', 'roll_no'], 'fabric_rolls_roll_no_unique');
            $table->index(['company_id', 'item_id'], 'fabric_rolls_item_idx');
            $table->index(['company_id', 'status'], 'fabric_rolls_status_idx');
            $table->index(['company_id', 'goods_receipt_id'], 'fabric_rolls_grn_idx');
            $table->index(['company_id', 'goods_receipt_line_id'], 'fabric_rolls_grn_line_idx');
        });

        // Self-reference added after the table exists (Postgres rejects it
        // inside the create).
        Schema::table('fabric_rolls', function (Blueprint $table): void {
            $table->foreign('parent_roll_id', 'fabric_rolls_parent_fk')
                ->references('id')->on('fabric_rolls')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabric_rolls');
    }
};

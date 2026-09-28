<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock promised to a requisition but not yet moved (Inventory spec §7).
 *
 * `item_stock_balances.quantity_reserved` has been read since Phase 1 —
 * by ItemLedger's issue check, by Item::totalAvailable(), by the item API —
 * but nothing wrote it. This table is what writes it, and what says who is
 * holding the stock and why, so "why can I not issue what I can see on the
 * shelf" has an answer.
 *
 * Three shapes, all in one table because the lifecycle is identical:
 *
 *  - soft reservation — a quantity block against one (item, bin, lot)
 *    balance. Raises quantity_reserved, moves nothing.
 *  - hard allocation — `fabric_roll_id` set: one named roll locked to this
 *    claim. ROLL items keep no balance row, so the roll's own status carries
 *    the encumbrance instead.
 *  - earmark — `bin_id` null, status PENDING_ARRIVAL: a claim on stock that
 *    has not arrived yet (spec §5, the floor-requisition shortage case). It
 *    encumbers nothing, because there is nothing there to encumber; it turns
 *    into a real reservation when the goods receipt lands.
 *
 * Rows are closed, never deleted: a released reservation is the record of
 * what was held and given back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_reservations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            // Null while the stock has not arrived (an earmark).
            $table->foreignUlid('bin_id')->nullable()->constrained('bins')->restrictOnDelete();
            $table->string('lot_no', 64)->default('');
            // Set = hard allocation of that one roll.
            $table->foreignUlid('fabric_roll_id')->nullable()->constrained('fabric_rolls')->nullOnDelete();

            // BOOKING and ORDER join this list with the production-planning
            // phase; FLOOR_REQUISITION and MANUAL are what exists now.
            $table->string('reservation_type', 24)->default('MANUAL');
            $table->string('reference_type', 64)->nullable();
            $table->ulid('reference_id')->nullable();

            $table->decimal('quantity', 18, 4);
            $table->decimal('quantity_issued', 18, 4)->default(0);
            $table->decimal('quantity_released', 18, 4)->default(0);

            $table->string('status', 24)->default('ACTIVE');

            $table->foreignUlid('reserved_by')->nullable();
            $table->timestamp('reserved_at')->nullable();
            // Stock held for a job nobody started is stock the rest of the
            // factory cannot use; the sweeper reads this.
            $table->timestamp('expires_at')->nullable();
            $table->foreignUlid('released_by')->nullable();
            $table->timestamp('released_at')->nullable();
            // Why a store manager took stock somebody else was holding (§5).
            $table->text('override_reason')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'item_id', 'status'], 'item_reservations_item_idx');
            $table->index(['company_id', 'reference_type', 'reference_id'], 'item_reservations_reference_idx');
            $table->index(['company_id', 'status', 'expires_at'], 'item_reservations_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_reservations');
    }
};

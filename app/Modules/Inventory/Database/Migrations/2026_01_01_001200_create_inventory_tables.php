<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spare parts and the inventory ledger: ERD Section 13, SRS 19-21.
 *
 * The ledger is the important half. Stock quantities are never edited; they are
 * derived from an append-only sequence of transactions, each carrying the
 * balance and weighted average cost that resulted from it. That makes the
 * ledger self-auditing: replaying it must reproduce the current balance
 * exactly, and a disagreement is a bug you can find rather than a number
 * everyone stops trusting.
 *
 * A store handing out a part it does not have, or a work order costed at a
 * price nobody paid, is how a maintenance system quietly loses the confidence
 * of the people who have to sign off its figures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spare_part_categories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // Null company_id = platform-seeded, visible to every tenant.
            $table->foreignUlid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('code', 48);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'spare_part_categories_code_unique');
        });

        Schema::create('spare_parts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('category_id')->nullable()
                ->constrained('spare_part_categories')->nullOnDelete();

            $table->string('part_number', 64);
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('unit', 16)->default('PCS');

            // Policy thresholds, not quantities. Actual stock lives in
            // inventory_balances and is derived from the ledger; storing a
            // quantity here would give two answers to one question.
            $table->decimal('minimum_stock', 18, 4)->default(0);
            $table->decimal('reorder_level', 18, 4)->default(0);
            // Last purchase price. Informational only — never used to cost an
            // issue, which always uses the ledger's weighted average.
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->char('currency', 3)->default('BDT');

            $table->unsignedInteger('lead_time_days')->nullable();
            $table->ulid('default_vendor_id')->nullable();
            // A critical spare is one whose absence stops a critical machine.
            // It drives the low-stock report's ordering, not its arithmetic.
            $table->boolean('is_critical_spare')->default(false);
            $table->unsignedInteger('shelf_life_days')->nullable();
            $table->boolean('hazardous')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'part_number'], 'spare_parts_number_unique');
            $table->index(['company_id', 'category_id'], 'spare_parts_category_idx');
            $table->index(['company_id', 'is_critical_spare'], 'spare_parts_critical_idx');
        });

        Schema::create('spare_part_compatibilities', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('spare_part_id')->constrained('spare_parts')->cascadeOnDelete();
            $table->foreignUlid('asset_model_id')->nullable()->constrained('asset_models')->cascadeOnDelete();
            $table->foreignUlid('asset_id')->nullable()->constrained('assets')->cascadeOnDelete();
            // FITS | SUBSTITUTE
            $table->string('compatibility_type', 24)->default('FITS');
            $table->foreignUlid('substitute_for_part_id')->nullable()
                ->constrained('spare_parts')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'spare_part_id'], 'spare_part_compat_part_idx');
            $table->index(['company_id', 'asset_model_id'], 'spare_part_compat_model_idx');
        });

        /*
         * Factory -> Warehouse -> Store -> Bin (SRS 19).
         *
         * Balances are held per bin rather than per store, because "we have
         * twelve" is not useful if nobody can say which shelf.
         */
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'warehouses_code_unique');
        });

        Schema::create('stores', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'stores_code_unique');
        });

        Schema::create('bins', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32);
            // A system bin holds stock that has left one factory and not yet
            // arrived at the other. Without it, dispatched stock is either in
            // two places or in none.
            $table->boolean('is_in_transit')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'bins_code_unique');
            $table->index(['company_id', 'store_id'], 'bins_store_idx');
        });

        Schema::create('inventory_balances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('spare_part_id')->constrained('spare_parts')->cascadeOnDelete();
            $table->foreignUlid('bin_id')->constrained('bins')->cascadeOnDelete();

            $table->decimal('quantity_on_hand', 18, 4)->default(0);
            // Encumbered, not moved. A reservation never writes to the ledger;
            // it only makes stock unavailable to somebody else.
            $table->decimal('quantity_reserved', 18, 4)->default(0);
            $table->decimal('weighted_average_cost', 18, 4)->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            // One balance row per part per bin. Two would make "how many do we
            // have" unanswerable.
            $table->unique(['spare_part_id', 'bin_id'], 'inventory_balances_unique');
            $table->index(['company_id', 'spare_part_id'], 'inventory_balances_part_idx');
            $table->index(['company_id', 'bin_id'], 'inventory_balances_bin_idx');
        });

        Schema::create('inventory_transactions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('spare_part_id')->constrained('spare_parts')->restrictOnDelete();
            $table->foreignUlid('bin_id')->constrained('bins')->restrictOnDelete();

            $table->string('transaction_type', 24);
            // Always positive. Direction comes from the type, so a sign error
            // cannot silently reverse a movement (ERD Section 13).
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

            $table->string('reference_type', 48)->nullable();
            $table->ulid('reference_id')->nullable();
            $table->foreignUlid('reservation_id')->nullable();
            $table->foreignUlid('inventory_transfer_id')->nullable();
            $table->foreignUlid('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            // A correction is a new row pointing at the one it undoes. The
            // original is never edited or deleted.
            $table->foreignUlid('reverses_transaction_id')->nullable();

            $table->foreignUlid('performed_by')->nullable();
            $table->dateTime('transaction_at', 3);
            $table->text('notes')->nullable();
            // A retried request must not post the movement twice (ADR-056).
            $table->string('idempotency_key', 64)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'idempotency_key'], 'inventory_tx_idempotency_unique');
            $table->index(['company_id', 'spare_part_id', 'transaction_at'], 'inventory_tx_part_idx');
            $table->index(['company_id', 'bin_id', 'transaction_at'], 'inventory_tx_bin_idx');
            $table->index(['company_id', 'work_order_id'], 'inventory_tx_wo_idx');
            $table->index(['company_id', 'transaction_type'], 'inventory_tx_type_idx');
        });

        Schema::create('spare_part_reservations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('spare_part_id')->constrained('spare_parts')->cascadeOnDelete();
            $table->foreignUlid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            // Missing in v1.0. Because balances are per bin, a reservation with
            // no bin cannot be enforced against any balance at all.
            $table->foreignUlid('bin_id')->constrained('bins')->restrictOnDelete();
            $table->ulid('work_order_part_id')->nullable();

            $table->decimal('quantity', 18, 4);
            $table->decimal('quantity_released', 18, 4)->default(0);
            $table->decimal('quantity_issued', 18, 4)->default(0);
            $table->string('status', 24)->default('ACTIVE');

            $table->foreignUlid('reserved_by')->nullable();
            $table->dateTime('reserved_at', 3);
            // Stock held indefinitely for a job nobody started is stock the
            // rest of the factory cannot use.
            $table->dateTime('expires_at', 3)->nullable();
            $table->foreignUlid('released_by')->nullable();
            $table->dateTime('released_at', 3)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'work_order_id'], 'reservations_wo_idx');
            $table->index(['company_id', 'spare_part_id', 'status'], 'reservations_part_idx');
            $table->index(['company_id', 'status', 'expires_at'], 'reservations_expiry_idx');
        });

        Schema::create('work_order_parts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignUlid('spare_part_id')->constrained('spare_parts')->restrictOnDelete();
            // Recorded, because fitting a different part than the one specified
            // is exactly what a failure analysis needs to know later (SRS 20).
            $table->foreignUlid('substitute_for_spare_part_id')->nullable()
                ->constrained('spare_parts')->nullOnDelete();
            $table->foreignUlid('bin_id')->nullable()->constrained('bins')->nullOnDelete();

            $table->decimal('quantity_requested', 18, 4)->default(0);
            $table->decimal('quantity_reserved', 18, 4)->default(0);
            $table->decimal('quantity_issued', 18, 4)->default(0);
            $table->decimal('quantity_consumed', 18, 4)->default(0);
            $table->decimal('quantity_returned', 18, 4)->default(0);

            // Captured at issue time and frozen. A later purchase at a different
            // price must not rewrite what this repair cost.
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->char('currency', 3)->default('BDT');
            $table->decimal('total_cost', 18, 4)->default(0);
            $table->decimal('base_total_cost', 18, 4)->default(0);

            $table->foreignUlid('reservation_id')->nullable();
            $table->string('status', 24)->default('REQUESTED');
            $table->timestamps();

            $table->index(['company_id', 'work_order_id'], 'work_order_parts_wo_idx');
            $table->index(['company_id', 'spare_part_id'], 'work_order_parts_part_idx');
        });

        Schema::create('inventory_transfers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('transfer_number', 48);
            $table->foreignUlid('from_factory_id')->constrained('factories')->restrictOnDelete();
            $table->foreignUlid('to_factory_id')->constrained('factories')->restrictOnDelete();
            $table->foreignUlid('in_transit_bin_id')->nullable()->constrained('bins')->nullOnDelete();

            $table->string('status', 24)->default('REQUESTED');
            $table->foreignUlid('requested_by')->nullable();
            $table->foreignUlid('approved_by')->nullable();
            $table->dateTime('approved_at', 3)->nullable();
            $table->foreignUlid('dispatched_by')->nullable();
            $table->dateTime('dispatched_at', 3)->nullable();
            $table->foreignUlid('received_by')->nullable();
            $table->dateTime('received_at', 3)->nullable();
            $table->foreignUlid('rejected_by')->nullable();
            $table->dateTime('rejected_at', 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'transfer_number'], 'inventory_transfers_number_unique');
            $table->index(['company_id', 'status'], 'inventory_transfers_status_idx');
        });

        Schema::create('inventory_transfer_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('inventory_transfer_id')->constrained('inventory_transfers')->cascadeOnDelete();
            $table->foreignUlid('spare_part_id')->constrained('spare_parts')->restrictOnDelete();
            $table->foreignUlid('from_bin_id')->constrained('bins')->restrictOnDelete();
            $table->foreignUlid('to_bin_id')->nullable()->constrained('bins')->nullOnDelete();

            $table->decimal('quantity_requested', 18, 4);
            $table->decimal('quantity_dispatched', 18, 4)->default(0);
            $table->decimal('quantity_received', 18, 4)->default(0);
            // Derived at receipt. A non-zero variance is what drives a
            // discrepancy investigation rather than a silent write-off.
            $table->decimal('quantity_variance', 18, 4)->default(0);
            $table->decimal('unit_cost_at_dispatch', 18, 4)->nullable();
            $table->char('currency', 3)->default('BDT');
            $table->timestamps();

            $table->index(['company_id', 'inventory_transfer_id'], 'transfer_items_transfer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfer_items');
        Schema::dropIfExists('inventory_transfers');
        Schema::dropIfExists('work_order_parts');
        Schema::dropIfExists('spare_part_reservations');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('bins');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('spare_part_compatibilities');
        Schema::dropIfExists('spare_parts');
        Schema::dropIfExists('spare_part_categories');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Knit Production → Yarn Store (AnnotechERP 15/03), the stock side Brickland
 * keeps:
 *
 * - `knit_yarn_lots`: a stock line — one store's yarn of one item, count,
 *   colour, brand and lot, from one supplier, for one buyer (or buffer).
 * - `knit_yarn_moves`: the yarn ledger — every quantity into or out of a lot,
 *   signed, with the document that moved it. A lot's balance is its sum.
 * - `knit_yarn_obs` + lines: Yarn Store Opening Balance, posted on Finalize.
 * - `knit_yarn_receipts` + lines: Daily Yarn Receipt (MRR) — direct or
 *   against a purchase order; Store Receive → Store Verify → Audit Verify →
 *   Accounts Approve.
 * - `knit_yarn_returns` + lines: Returns & Exchanges — yarn refused back to
 *   the supplier off an MRR, posted when issued.
 * - `knit_yarn_adjustments` + lines: Yarn Stock Adjustment.
 * - `knit_yarn_racks`: Rack Assignment — where received yarn is put away.
 * - `knit_yarn_period_lines`: Yarn Opening Entry — a store's month per lot,
 *   its adjustment and reason, finalised.
 * - `knit_yarn_month_closes`: Yarn Store Month Closing — the months struck.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knit_yarn_lots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('lot_key', 255);
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('colour', 40)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->foreignUlid('supplier_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->boolean('is_buffer')->default(false);
            // LOCAL, IMPORT, SC_PARTY, INHOUSE_DYED, YARN_DYED.
            $table->string('source', 16)->nullable();
            $table->string('uom', 16)->default('kg');
            $table->decimal('rate', 14, 4)->default(0);
            $table->date('first_date')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'lot_key']);
            $table->index(['company_id', 'store_id']);
        });

        Schema::create('knit_yarn_moves', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('lot_id')->constrained('knit_yarn_lots')->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->date('move_date');
            // OB, RECEIPT, RETURN, ADJUSTMENT, PERIOD_ADJ (and the issue side as it is built).
            $table->string('kind', 16);
            $table->string('doc_no', 32);
            $table->ulid('doc_line_id')->nullable();
            $table->decimal('qty', 14, 4);
            $table->decimal('value', 16, 4)->default(0);
            $table->timestamps();
            $table->index(['company_id', 'lot_id', 'move_date']);
            $table->index(['company_id', 'doc_no']);
        });

        Schema::create('knit_yarn_obs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('ob_no', 24);
            $table->date('ob_date');
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->boolean('is_buffer')->default(false);
            $table->text('remarks')->nullable();
            $table->boolean('finalized')->default(false);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'ob_no']);
        });

        Schema::create('knit_yarn_ob_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('ob_id')->constrained('knit_yarn_obs')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->string('source', 16);
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('colour', 40)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->foreignUlid('supplier_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->decimal('pack_qty', 14, 4)->nullable();
            $table->decimal('qty_per_pack', 14, 4)->nullable();
            $table->string('pack_uom', 16)->nullable();
            $table->decimal('total_qty', 14, 4);
            $table->decimal('cost_price', 14, 4)->default(0);
            $table->unsignedInteger('cones')->nullable();
            $table->date('rcv_date')->nullable();
            $table->timestamps();
        });

        Schema::create('knit_yarn_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('mrr_no', 24);
            $table->date('mrr_date');
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            // DIRECT, PO, PI.
            $table->string('recv_opt', 8);
            $table->foreignUlid('po_id')->nullable()->constrained('knit_yarn_pos')->nullOnDelete();
            $table->string('recv_ref', 64)->nullable();
            $table->string('challan_no', 64)->nullable();
            $table->foreignUlid('supplier_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->text('remarks')->nullable();
            // STORE_RECEIVE, STORE_VERIFY, AUDIT_VERIFY, ACCOUNTS_APPROVE, CANCELLED.
            $table->string('status', 20)->default('STORE_RECEIVE');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'mrr_no']);
            $table->index(['company_id', 'status', 'mrr_date']);
        });

        Schema::create('knit_yarn_receipt_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->constrained('knit_yarn_receipts')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('po_line_id')->nullable()->constrained('knit_yarn_po_lines')->nullOnDelete();
            $table->foreignUlid('lot_id')->nullable()->constrained('knit_yarn_lots')->nullOnDelete();
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('colour', 40)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->decimal('pack_qty', 14, 4)->nullable();
            $table->decimal('qty_per_pack', 14, 4)->nullable();
            $table->string('pack_uom', 16)->nullable();
            $table->decimal('recv_qty', 14, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->decimal('challan_qty', 14, 4)->nullable();
            $table->unsignedInteger('cones')->nullable();
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_yarn_returns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('return_no', 24);
            $table->foreignUlid('receipt_id')->constrained('knit_yarn_receipts')->restrictOnDelete();
            $table->foreignUlid('supplier_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->date('return_date');
            // RETURN or EXCHANGE.
            $table->string('type', 10);
            // RETURNABLE or NON_RETURNABLE.
            $table->string('challan_type', 16)->nullable();
            $table->boolean('finalized')->default(false);
            $table->text('reason')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'return_no']);
        });

        Schema::create('knit_yarn_return_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('return_id')->constrained('knit_yarn_returns')->cascadeOnDelete();
            $table->foreignUlid('receipt_line_id')->constrained('knit_yarn_receipt_lines')->cascadeOnDelete();
            $table->decimal('qty', 14, 4);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_yarn_adjustments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('adj_no', 24);
            $table->date('adj_date');
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('reason', 255);
            // ACTIVE or CANCELLED (Brickland's Activity Status).
            $table->string('status', 10)->default('ACTIVE');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'adj_no']);
        });

        Schema::create('knit_yarn_adjustment_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('adjustment_id')->constrained('knit_yarn_adjustments')->cascadeOnDelete();
            $table->foreignUlid('lot_id')->constrained('knit_yarn_lots')->restrictOnDelete();
            $table->decimal('qty', 14, 4);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_yarn_racks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('lot_id')->constrained('knit_yarn_lots')->cascadeOnDelete();
            $table->foreignUlid('receipt_line_id')->nullable()->constrained('knit_yarn_receipt_lines')->cascadeOnDelete();
            $table->string('rack_no', 40);
            $table->decimal('qty', 14, 4);
            $table->date('rack_date');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'lot_id']);
        });

        Schema::create('knit_yarn_period_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->char('period', 7);
            $table->foreignUlid('lot_id')->constrained('knit_yarn_lots')->cascadeOnDelete();
            $table->decimal('adjustment', 14, 4)->default(0);
            $table->string('reason', 255)->nullable();
            $table->boolean('finalized')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'store_id', 'period', 'lot_id']);
        });

        Schema::create('knit_yarn_month_closes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->char('period', 7);
            $table->timestamp('closed_at');
            $table->foreignUlid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'period']);
        });
    }

    public function down(): void
    {
        foreach (['knit_yarn_month_closes', 'knit_yarn_period_lines', 'knit_yarn_racks', 'knit_yarn_adjustment_lines', 'knit_yarn_adjustments', 'knit_yarn_return_lines', 'knit_yarn_returns',
            'knit_yarn_receipt_lines', 'knit_yarn_receipts', 'knit_yarn_ob_lines', 'knit_yarn_obs', 'knit_yarn_moves', 'knit_yarn_lots'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

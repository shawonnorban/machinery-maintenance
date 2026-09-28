<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Landed cost (Procurement spec §11).
 *
 * A purchase order's rate is only part of what the goods cost. Freight,
 * duty, C&F, insurance and bank charges arrive weeks later on invoices of
 * their own, and until they are spread across the consignment the stock is
 * carried at a number everybody knows is too low.
 *
 * Four tables rather than one because each answers a different question:
 * the voucher is the event, the charges are what was billed and by whom,
 * the receipts are which goods it paid to bring in (one freight bill does
 * not respect GRN boundaries), and the allocations are the arithmetic that
 * was actually used — kept rather than recomputed, because the quantities
 * and rates it depended on will move.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landed_cost_vouchers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->string('voucher_no', 32);
            $table->date('voucher_date');
            // QTY | VALUE — how the charges are spread (§11).
            $table->string('basis', 8)->default('VALUE');
            $table->char('currency', 3)->default('BDT');
            $table->decimal('total_charges', 18, 4)->default(0);
            $table->string('status', 24)->default('DRAFT');
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->foreignUlid('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'voucher_no'], 'landed_cost_vouchers_no_unique');
            $table->index(['company_id', 'factory_id', 'status'], 'landed_cost_vouchers_status_idx');
        });

        Schema::create('landed_cost_charges', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('landed_cost_voucher_id')->constrained('landed_cost_vouchers')->cascadeOnDelete();
            // FREIGHT | DUTY | CNF | INSURANCE | BANK_CHARGE | OTHER
            $table->string('charge_type', 24);
            $table->string('label', 160)->nullable();
            // Who billed it — the forwarder, the C&F agent, the bank.
            $table->foreignUlid('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3)->default('BDT');
            $table->string('reference_no', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'landed_cost_voucher_id'], 'landed_cost_charges_voucher_idx');
        });

        Schema::create('landed_cost_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('landed_cost_voucher_id')->constrained('landed_cost_vouchers')->cascadeOnDelete();
            $table->foreignUlid('goods_receipt_id')->constrained('goods_receipts')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['landed_cost_voucher_id', 'goods_receipt_id'], 'landed_cost_receipts_unique');
        });

        Schema::create('landed_cost_allocations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('landed_cost_voucher_id')->constrained('landed_cost_vouchers')->cascadeOnDelete();
            $table->foreignUlid('goods_receipt_id')->constrained('goods_receipts')->restrictOnDelete();
            $table->foreignUlid('goods_receipt_line_id')->constrained('goods_receipt_lines')->restrictOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();

            // The weight this line carried, and the numbers it came from.
            $table->decimal('basis_value', 18, 4)->default(0);
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('share_amount', 18, 4)->default(0);
            $table->decimal('unit_uplift', 18, 4)->default(0);
            $table->decimal('previous_unit_cost', 18, 4)->default(0);
            $table->decimal('new_unit_cost', 18, 4)->default(0);
            $table->timestamps();

            $table->index(['company_id', 'landed_cost_voucher_id'], 'landed_cost_allocations_voucher_idx');
            $table->index(['company_id', 'goods_receipt_line_id'], 'landed_cost_allocations_line_idx');
        });

        Schema::table('goods_receipts', function (Blueprint $table): void {
            // Rate lock (§11): once landed cost is posted the rates on this
            // receipt are part of a valuation somebody has reported.
            $table->timestamp('landed_cost_posted_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropColumn('landed_cost_posted_at');
        });

        Schema::dropIfExists('landed_cost_allocations');
        Schema::dropIfExists('landed_cost_receipts');
        Schema::dropIfExists('landed_cost_charges');
        Schema::dropIfExists('landed_cost_vouchers');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claims against a supplier (Procurement spec §12) and money paid to one
 * before the goods arrive (§13).
 *
 * A claim is the counterpart of a return: when the cloth has been cut, dyed
 * or sewn there is nothing to put on a lorry, but the loss is just as real,
 * so what is claimed is money. Nothing here touches stock — which is why it
 * is a separate table from `purchase_returns` rather than a flag on it.
 *
 * An advance is the opposite direction: money out before goods in. The
 * adjustments are rows rather than a running balance, because "how much is
 * left" and "which bill consumed it" are different questions and only the
 * second can be argued with. A reversed adjustment keeps its row with a
 * timestamp for the same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_claims', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->string('claim_no', 32);
            $table->date('claim_date');
            $table->foreignUlid('vendor_id')->constrained('vendors')->restrictOnDelete();

            // What it is about — any of these may be absent, because a claim
            // can be raised on a whole consignment rather than one roll.
            $table->foreignUlid('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
            $table->foreignUlid('purchase_return_id')->nullable()->constrained('purchase_returns')->nullOnDelete();
            $table->foreignUlid('fabric_roll_id')->nullable()->constrained('fabric_rolls')->nullOnDelete();
            $table->foreignUlid('item_id')->nullable()->constrained('items')->nullOnDelete();

            // QUALITY | SHORT_SHIPMENT | DAMAGE_IN_TRANSIT | PRICE_DIFFERENCE | OTHER
            $table->string('reason', 24)->default('OTHER');
            $table->string('status', 24)->default('RAISED');
            $table->decimal('claim_amount', 18, 4);
            // Settled in instalments: a mill pays part and argues the rest.
            $table->decimal('settled_amount', 18, 4)->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->text('description')->nullable();
            $table->string('settlement_reference', 64)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'claim_no'], 'supplier_claims_no_unique');
            $table->index(['company_id', 'vendor_id', 'status'], 'supplier_claims_vendor_idx');
            $table->index(['company_id', 'factory_id', 'status'], 'supplier_claims_status_idx');
        });

        Schema::create('supplier_advances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->string('advance_no', 32);
            $table->date('advance_date');
            $table->foreignUlid('vendor_id')->constrained('vendors')->restrictOnDelete();
            // Usually against an order, but a supplier can be paid on account.
            $table->foreignUlid('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignUlid('payment_type_id')->nullable();
            $table->string('reference_no', 64)->nullable();

            $table->decimal('amount', 18, 4);
            $table->decimal('adjusted_amount', 18, 4)->default(0);
            $table->decimal('refunded_amount', 18, 4)->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->string('status', 24)->default('OPEN');
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'advance_no'], 'supplier_advances_no_unique');
            // The outstanding-advance report reads exactly this.
            $table->index(['company_id', 'vendor_id', 'status'], 'supplier_advances_vendor_idx');
        });

        Schema::create('supplier_advance_adjustments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('supplier_advance_id')->constrained('supplier_advances')->cascadeOnDelete();
            // The bill it went against, when it went against one.
            $table->foreignUlid('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
            // ADJUSTMENT | REFUND
            $table->string('adjustment_type', 16)->default('ADJUSTMENT');
            $table->decimal('amount', 18, 4);
            $table->string('reference_no', 64)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('adjusted_by')->nullable();
            $table->timestamp('adjusted_at')->nullable();
            // Kept rather than deleted — see the class note.
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'supplier_advance_id'], 'advance_adjustments_advance_idx');
            $table->index(['company_id', 'goods_receipt_id'], 'advance_adjustments_grn_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_advance_adjustments');
        Schema::dropIfExists('supplier_advances');
        Schema::dropIfExists('supplier_claims');
    }
};

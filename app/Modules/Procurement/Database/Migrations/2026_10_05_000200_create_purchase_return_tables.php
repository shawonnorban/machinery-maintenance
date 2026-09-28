<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goods going back to the supplier (Procurement spec §12, Inventory §11).
 *
 * The flow the spec sets out — approval, gate pass, stock down, debit note
 * — is why this is its own document rather than a negative goods receipt.
 * A negative receipt would need an approval state, a gate pass number and a
 * debit note number bolted onto a table that means something else, and
 * every report that counts receipts would have to learn to subtract some of
 * them.
 *
 * `debit_note_no` is nullable because it is allocated at dispatch, when the
 * goods actually leave: a cancelled return must not leave a hole in the
 * series the supplier's accounts department reconciles against.
 *
 * A line names either a bin (a pooled or lot-tracked item) or a roll — for
 * a roll-tracked item the roll is the stock, and "20 metres of navy" is not
 * something that can be handed to a driver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->string('return_no', 32);
            $table->date('return_date');
            $table->foreignUlid('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignUlid('goods_receipt_id')->constrained('goods_receipts')->restrictOnDelete();
            // QUALITY_FAIL | EXCESS | WRONG_ITEM | DAMAGE | OTHER
            $table->string('reason', 24)->default('OTHER');
            $table->string('status', 24)->default('DRAFT');
            // The approval engine's own columns.
            $table->string('approval_status', 16)->default('NOT_REQUIRED');
            $table->ulid('approval_request_id')->nullable();

            $table->string('gate_pass_no', 64)->nullable();
            // Allocated at dispatch — see the class note.
            $table->string('debit_note_no', 32)->nullable();
            $table->decimal('debit_note_amount', 18, 4)->default(0);
            $table->char('currency', 3)->default('BDT');

            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->foreignUlid('dispatched_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'return_no'], 'purchase_returns_no_unique');
            $table->index(['company_id', 'factory_id', 'status'], 'purchase_returns_status_idx');
            $table->index(['company_id', 'vendor_id'], 'purchase_returns_vendor_idx');
            $table->index(['company_id', 'goods_receipt_id'], 'purchase_returns_grn_idx');
        });

        Schema::create('purchase_return_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignUlid('goods_receipt_line_id')->constrained('goods_receipt_lines')->restrictOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            // Snapshot, as every item line carries (Master Data spec §2.10).
            $table->string('item_code', 64);
            $table->string('item_name');
            $table->foreignUlid('uom_id')->constrained('units_of_measure')->restrictOnDelete();

            $table->foreignUlid('bin_id')->nullable()->constrained('bins')->restrictOnDelete();
            $table->string('lot_no', 64)->nullable();
            $table->foreignUlid('fabric_roll_id')->nullable()->constrained('fabric_rolls')->nullOnDelete();

            $table->decimal('quantity', 18, 4);
            // The rate it came in at — what the supplier is being debited.
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4)->default(0);
            // Null until dispatch: the stock has not moved before then.
            $table->ulid('item_stock_transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'purchase_return_id'], 'purchase_return_lines_return_idx');
            $table->index(['company_id', 'goods_receipt_line_id'], 'purchase_return_lines_grn_line_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_lines');
        Schema::dropIfExists('purchase_returns');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Physical inventory (Inventory spec §12): cycle counts, full stock taking
 * and the adjustment they produce.
 *
 * The one thing a stock take has to get right is **when the system
 * quantity was read**. `system_qty` is frozen onto the line the moment the
 * sheet is generated, never re-read at posting time: if it were, a
 * movement that happened legitimately while people were walking the aisles
 * would turn into a "variance" nobody caused, and the count would start
 * manufacturing the discrepancies it exists to find.
 *
 * `counted_qty` is nullable on purpose — null means "nobody has been to
 * that shelf yet", which is a different thing from a counted zero, and a
 * count that cannot tell those apart is not a count.
 *
 * A line also carries `item_stock_transaction_id`: the adjustment it
 * posted. That back-link is what lets somebody looking at a movement six
 * months later find the count sheet that caused it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('count_no', 32);
            // CYCLE (a slice of the store, often) | FULL (everything, rarely)
            $table->string('count_type', 16)->default('CYCLE');
            $table->string('status', 24)->default('COUNTING');
            /*
             * A blind count hides the system quantity from the person
             * holding the sheet. Showing it is how a count becomes a
             * formality: the easiest thing in the world is to write down
             * the number already printed next to the box.
             */
            $table->boolean('is_blind')->default(true);
            $table->date('count_date');
            // What the sheet was built from, kept so the scope of a cycle
            // count can be read back a year later.
            $table->json('scope_json')->nullable();

            $table->decimal('variance_value', 18, 4)->default(0);
            $table->string('approval_status', 24)->nullable();
            $table->ulid('approval_request_id')->nullable();

            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignUlid('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'count_no'], 'stock_counts_no_unique');
            $table->index(['company_id', 'store_id', 'status'], 'stock_counts_store_idx');
            $table->index(['company_id', 'count_date'], 'stock_counts_date_idx');
        });

        Schema::create('stock_count_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            // Snapshots: an item renamed after the count must not rewrite
            // what the sheet said on the day.
            $table->string('item_code', 64);
            $table->string('item_name', 255);
            $table->foreignUlid('uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->foreignUlid('bin_id')->constrained('bins')->restrictOnDelete();
            $table->string('lot_no', 64)->default('');

            // Frozen when the sheet was generated — see the class note.
            $table->decimal('system_qty', 18, 4)->default(0);
            // Null until somebody has actually been to the shelf.
            $table->decimal('counted_qty', 18, 4)->nullable();
            $table->decimal('variance_qty', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('variance_value', 18, 4)->default(0);

            // PENDING | MATCHED | VARIANCE | RECOUNT | ADJUSTED
            $table->string('status', 16)->default('PENDING');
            // Stock on a shelf the system had no row for at all.
            $table->boolean('is_unexpected')->default(false);
            $table->foreignUlid('counted_by')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->string('notes', 500)->nullable();
            // The adjustment this line posted, so a movement can be traced
            // back to the sheet that caused it.
            $table->foreignUlid('item_stock_transaction_id')->nullable();
            $table->timestamps();

            $table->unique(
                ['stock_count_id', 'item_id', 'bin_id', 'lot_no'],
                'stock_count_lines_place_unique',
            );
            $table->index(['company_id', 'item_id'], 'stock_count_lines_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
    }
};

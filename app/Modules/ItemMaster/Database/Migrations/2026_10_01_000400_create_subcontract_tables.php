<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fabric out for dyeing, printing or embroidery (Inventory spec §8).
 *
 * The difficulty this table exists for: the cloth leaves the building and
 * stays ours. It is not an issue (nothing consumed), not a sale (nothing
 * sold) and not a transfer (no store of ours holds it) — so it gets its own
 * document, and the roll gets the status `SENT_FOR_SUBCONTRACT`, which
 * keeps it off the available shelf without pretending it has gone.
 *
 * Nothing here posts to the stock ledger, deliberately. Replaying the
 * ledger must still reproduce what is on the shelf, and this cloth is not
 * on a shelf at all.
 *
 * On the line, `sent_qty = returned_qty + wastage_qty` is what settles it.
 * A dyer who returns 94 of 100 metres has either lost six to shrinkage or
 * owes them; recording only the 94 lets the difference vanish, and process
 * loss nobody can see is process loss nobody fixes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcontract_challans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->string('challan_no', 32);
            $table->date('challan_date');
            $table->foreignUlid('vendor_id')->constrained('vendors')->restrictOnDelete();
            // DYEING | PRINTING | EMBROIDERY | WASHING | OTHER
            $table->string('purpose', 24)->default('OTHER');
            $table->date('expected_return_date')->nullable();
            $table->string('status', 24)->default('DRAFT');
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'challan_no'], 'subcontract_challans_no_unique');
            $table->index(['company_id', 'factory_id', 'status'], 'subcontract_challans_status_idx');
            $table->index(['company_id', 'vendor_id'], 'subcontract_challans_vendor_idx');
            $table->index(['company_id', 'expected_return_date'], 'subcontract_challans_due_idx');
        });

        Schema::create('subcontract_challan_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('subcontract_challan_id')->constrained('subcontract_challans')->cascadeOnDelete();
            $table->foreignUlid('fabric_roll_id')->constrained('fabric_rolls')->restrictOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            // Snapshot, as every line that names a record carries.
            $table->string('roll_no', 64);

            $table->decimal('sent_qty', 12, 2)->default(0);
            $table->decimal('returned_qty', 12, 2)->default(0);
            // Shrinkage in dyeing, trimmed selvedge in printing — accounted
            // for, not quietly lost.
            $table->decimal('wastage_qty', 12, 2)->default(0);

            $table->string('status', 24)->default('OUT');
            // Set if the subcontractor returns the cloth as a different roll.
            $table->ulid('returned_roll_id')->nullable();
            $table->string('quality_status', 64)->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'subcontract_challan_id'], 'subcontract_lines_challan_idx');
            $table->index(['company_id', 'fabric_roll_id'], 'subcontract_lines_roll_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcontract_challan_lines');
        Schema::dropIfExists('subcontract_challans');
    }
};

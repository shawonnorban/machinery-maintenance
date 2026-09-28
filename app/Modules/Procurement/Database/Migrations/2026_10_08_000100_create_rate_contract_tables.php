<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rate contracts / framework agreements (Procurement spec §14).
 *
 * A long-term price fixed with a supplier, so a buyer raising an order
 * does not have to remember what was agreed in March — the order picks the
 * rate up by itself.
 *
 * Two shapes worth explaining:
 *
 *  - A contract is **company-wide by default** (`factory_id` nullable). A
 *    price negotiated with a mill is normally a group price, and forcing
 *    one row per factory would mean amending five rows every time it moved
 *    — with four of them eventually out of step.
 *  - A line carries `min_quantity`, so a contract can hold a price break
 *    ("200 taka up to 500 metres, 190 above"). The resolver takes the best
 *    band the order actually qualifies for; without the column the only
 *    way to express a break is a second contract, and then two contracts
 *    cover the same item on the same day and nobody can say which applies.
 *
 * `purchase_order_lines.rate_contract_id` (added below) records **which**
 * contract a rate came from. Six months later "why was this 190?" has an
 * answer, which a copied number never does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_contracts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            // Null means every factory — see the class note.
            $table->foreignUlid('factory_id')->nullable()->constrained('factories')->nullOnDelete();
            $table->foreignUlid('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->string('contract_no', 32);
            $table->string('title', 160)->nullable();
            // The supplier's own reference for the same agreement.
            $table->string('supplier_reference', 64)->nullable();
            $table->date('valid_from');
            $table->date('valid_to');
            $table->char('currency', 3)->default('BDT');
            // DRAFT | ACTIVE | CANCELLED. Expiry is not a status: it is
            // what the dates already say, and a stored one would need a
            // sweep to stay true and would be wrong in between.
            $table->string('status', 24)->default('DRAFT');
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'contract_no'], 'rate_contracts_no_unique');
            // The resolver's own lookup: this vendor, live on this date.
            $table->index(['company_id', 'vendor_id', 'status', 'valid_from', 'valid_to'], 'rate_contracts_live_idx');
        });

        Schema::create('rate_contract_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('rate_contract_id')->constrained('rate_contracts')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->string('item_code', 64);
            $table->string('item_name', 255);
            $table->foreignUlid('uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('rate', 18, 4);
            // The quantity this band starts at; 0 is the base price.
            $table->decimal('min_quantity', 18, 4)->default(0);
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            // One price per item per band inside a contract.
            $table->unique(['rate_contract_id', 'item_id', 'min_quantity'], 'rate_contract_lines_band_unique');
            $table->index(['company_id', 'item_id'], 'rate_contract_lines_item_idx');
        });

        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            // Where this rate came from, when it came from a contract.
            $table->foreignUlid('rate_contract_id')->nullable()->after('rate');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->dropColumn('rate_contract_id');
        });

        Schema::dropIfExists('rate_contract_lines');
        Schema::dropIfExists('rate_contracts');
    }
};

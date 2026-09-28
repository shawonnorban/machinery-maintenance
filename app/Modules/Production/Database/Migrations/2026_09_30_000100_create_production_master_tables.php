<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The minimal order / style / BOM / cutting-programme master (Master Data
 * spec §6).
 *
 * The spec says plainly that this belongs to a Merchandising module where
 * one exists, and that this scope builds a minimal version where it does
 * not. It does not, so this is that version — and it is deliberately thin.
 * It exists because three things downstream cannot be built without it:
 * fabric booking against an order, the material requirement calculation,
 * and the BOM consumption variance measured when material is issued to a
 * cut. Anything a real merchandising system would also hold — costing,
 * sampling, shipment, packing — is not here and should not be added; if
 * such a system arrives, these tables become a reference to it.
 *
 * Table names carry a `production_` prefix rather than the bare `orders`,
 * `styles`: `orders` in particular is a word half the application could
 * claim, and a table called that would be read as a purchase order by
 * whoever comes next.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            // The buyer's own number — not generated. A factory says
            // "H&M 4471-23"; inventing a second identifier helps nobody.
            $table->string('order_no', 64);
            $table->string('buyer', 160);
            $table->decimal('order_qty', 18, 4)->default(0);
            $table->date('order_date')->nullable();
            $table->date('ship_date')->nullable();
            $table->string('status', 24)->default('CONFIRMED');
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'order_no'], 'production_orders_no_unique');
            $table->index(['company_id', 'factory_id', 'status'], 'production_orders_status_idx');
            $table->index(['company_id', 'ship_date'], 'production_orders_ship_idx');
        });

        Schema::create('production_styles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->string('style_no', 64);
            $table->string('description', 500)->nullable();
            $table->string('color', 64)->nullable();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'production_order_id', 'style_no'], 'production_styles_no_unique');
        });

        Schema::create('production_style_bom_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('style_id')->constrained('production_styles')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            // Snapshot, as every item line carries (Master Data spec §2.10).
            $table->string('item_code', 64);
            $table->string('item_name');
            $table->foreignUlid('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            // Per garment, in the item's own unit.
            $table->decimal('consumption_per_unit', 18, 4);
            // Planned allowance, added on top by the requirement formula —
            // not the variance measured after cutting, which lives on the
            // issue and is a different number.
            $table->decimal('wastage_percent', 6, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'style_id', 'item_id'], 'production_bom_item_unique');
        });

        Schema::create('production_cutting_programs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->foreignUlid('style_id')->constrained('production_styles')->restrictOnDelete();
            $table->string('program_no', 32);
            $table->decimal('program_qty', 18, 4);
            $table->date('cutting_date')->nullable();
            $table->string('status', 24)->default('CONFIRMED');
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'program_no'], 'production_programs_no_unique');
            $table->index(['company_id', 'style_id', 'status'], 'production_programs_style_idx');
            $table->index(['company_id', 'cutting_date'], 'production_programs_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_cutting_programs');
        Schema::dropIfExists('production_style_bom_lines');
        Schema::dropIfExists('production_styles');
        Schema::dropIfExists('production_orders');
    }
};

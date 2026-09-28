<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fabric bookings, header and lines (Procurement spec §3).
 *
 * The step between a confirmed buyer order and a purchase order: the mill is
 * asked to hold yardage while price and terms are settled. Nothing is owed
 * and nothing arrives on its own, so this is not a purchase — but a
 * confirmed booking is planned against, which is why it stakes a claim on
 * the incoming stock (`fabric_booking_lines.item_reservation_id`) and why
 * the material requirement subtracts it from what still needs buying.
 *
 * `production_order_id` and `style_id` are nullable on purpose: early in a
 * season a merchandiser books against a forecast before the style exists.
 * Without them the over-booking check has nothing to measure against and
 * simply says nothing, which is the right behaviour rather than a refusal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fabric_bookings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->string('booking_no', 32);
            $table->date('booking_date');
            $table->foreignUlid('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignUlid('production_order_id')->nullable()->constrained('production_orders')->nullOnDelete();
            $table->foreignUlid('style_id')->nullable()->constrained('production_styles')->nullOnDelete();
            $table->date('required_date')->nullable();
            $table->string('status', 24)->default('DRAFT');
            $table->char('currency', 3)->default('BDT');
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            // The purchase requisition this booking turned into.
            $table->ulid('converted_pr_id')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'booking_no'], 'fabric_bookings_no_unique');
            $table->index(['company_id', 'factory_id', 'status'], 'fabric_bookings_status_idx');
            $table->index(['company_id', 'style_id'], 'fabric_bookings_style_idx');
            $table->index(['company_id', 'vendor_id'], 'fabric_bookings_vendor_idx');
        });

        Schema::create('fabric_booking_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('fabric_booking_id')->constrained('fabric_bookings')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            // Snapshot, as every item line carries (Master Data spec §2.10).
            $table->string('item_code', 64);
            $table->string('item_name');
            $table->foreignUlid('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('booked_qty', 18, 4);
            // Tentative: the price is what the booking exists to settle.
            $table->decimal('tentative_rate', 18, 4)->nullable();
            $table->date('required_date')->nullable();
            // The claim staked when the booking was confirmed.
            $table->ulid('item_reservation_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'fabric_booking_id'], 'fabric_booking_lines_booking_idx');
            $table->index(['company_id', 'item_id'], 'fabric_booking_lines_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabric_booking_lines');
        Schema::dropIfExists('fabric_bookings');
    }
};
